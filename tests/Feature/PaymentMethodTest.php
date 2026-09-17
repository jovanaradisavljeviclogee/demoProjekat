<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Pokriva automatski proverljive kriterijume iz docs/features/payment-methods/spec.md.
 *
 * Baza se ne koristi — podaci su u nizu iza repozitorijuma, a radna kopija u sesiji
 * (ADR-16). U testovima je SESSION_DRIVER=array, pa svaki test kreće od seed niza.
 */
class PaymentMethodTest extends TestCase
{
    private const VALID = [
        'name' => 'Klarna',
        'code' => 'KLARNA',
        'type' => 'iframe',
        'currencies' => ['EUR', 'GBP'],
        'countries' => ['DE', 'NL'],
        'sortOrder' => 6,
        'active' => true,
    ];

    /** PM-AC-01 */
    public function test_list_is_sorted_by_sort_order(): void
    {
        $response = $this->getJson('/admin/api/payment-methods');

        $response->assertOk();

        $order = array_column($response->json('data'), 'sortOrder');
        $sorted = $order;
        sort($sorted);

        $this->assertSame($sorted, $order);
        $this->assertNotEmpty($order);
    }

    public function test_list_carries_the_allowed_codes_the_form_offers(): void
    {
        $response = $this->getJson('/admin/api/payment-methods');

        $response->assertOk()
            ->assertJsonStructure(['data', 'currencies', 'countries']);

        $this->assertContains('EUR', $response->json('currencies'));
        $this->assertContains('BE', $response->json('countries'));
    }

    /** PM-AC-09 */
    public function test_duplicate_code_is_rejected_on_the_code_field(): void
    {
        $this->postJson('/admin/api/payment-methods', ['code' => 'BCMC'] + self::VALID)
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /** PM-AC-10 — jedinstvenost izuzima metodu koja se menja. */
    public function test_a_method_may_be_saved_with_its_own_code(): void
    {
        $existing = $this->getJson('/admin/api/payment-methods')->json('data.0');

        $this->putJson("/admin/api/payment-methods/{$existing['id']}", [
            'name' => $existing['name'],
            'code' => $existing['code'],
            'type' => $existing['type'],
            'currencies' => $existing['currencies'],
            'countries' => $existing['countries'],
            'sortOrder' => $existing['sortOrder'],
            'active' => $existing['active'],
        ])->assertOk();
    }

    /** PM-AC-11 — metoda koja ne važi nigde ne može biti sačuvana. */
    public function test_at_least_one_currency_and_one_country_are_required(): void
    {
        $this->postJson('/admin/api/payment-methods', ['currencies' => [], 'countries' => []] + self::VALID)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currencies', 'countries']);
    }

    /** PM-AC-12 */
    public function test_codes_outside_the_allowed_lists_are_rejected(): void
    {
        $this->postJson('/admin/api/payment-methods', ['currencies' => ['XXX'], 'countries' => ['ZZ']] + self::VALID)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currencies.0', 'countries.0']);
    }

    /** PM-AC-13 */
    public function test_type_outside_redirect_and_iframe_is_rejected(): void
    {
        $this->postJson('/admin/api/payment-methods', ['type' => 'popup'] + self::VALID)
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_name_code_type_and_sort_order_are_required(): void
    {
        $this->postJson('/admin/api/payment-methods', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'type', 'sortOrder']);
    }

    public function test_sort_order_must_be_a_whole_number(): void
    {
        $this->postJson('/admin/api/payment-methods', ['sortOrder' => 'prvi'] + self::VALID)
            ->assertStatus(422)
            ->assertJsonValidationErrors('sortOrder');
    }

    /** PM-AC-14 — ništa se ne upisuje dok sva pravila ne prođu. */
    public function test_a_rejected_request_changes_nothing(): void
    {
        $before = $this->getJson('/admin/api/payment-methods')->json('data');

        $this->postJson('/admin/api/payment-methods', ['code' => 'BCMC'] + self::VALID)
            ->assertStatus(422);

        $this->assertSame($before, $this->getJson('/admin/api/payment-methods')->json('data'));
    }

    public function test_a_valid_method_is_created_and_appears_in_the_list(): void
    {
        $before = count($this->getJson('/admin/api/payment-methods')->json('data'));

        $created = $this->postJson('/admin/api/payment-methods', self::VALID)
            ->assertCreated()
            ->json('data');

        $this->assertSame('KLARNA', $created['code']);
        $this->assertCount($before + 1, $this->getJson('/admin/api/payment-methods')->json('data'));
    }

    /** PM-AC-04 — isključivanje nikada ne uklanja metodu iz liste. */
    public function test_switching_a_method_off_keeps_it_in_the_list(): void
    {
        $id = $this->getJson('/admin/api/payment-methods')->json('data.0.id');
        $before = count($this->getJson('/admin/api/payment-methods')->json('data'));

        $this->patchJson("/admin/api/payment-methods/{$id}/active", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $data = $this->getJson('/admin/api/payment-methods')->json('data');

        $this->assertCount($before, $data);
        $this->assertContains($id, array_column($data, 'id'));
    }

    public function test_a_method_can_be_switched_back_on(): void
    {
        $id = $this->getJson('/admin/api/payment-methods')->json('data.0.id');

        $this->patchJson("/admin/api/payment-methods/{$id}/active", ['active' => false])->assertOk();
        $this->patchJson("/admin/api/payment-methods/{$id}/active", ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.active', true);
    }

    public function test_deleting_removes_the_method_and_returns_a_message(): void
    {
        $created = $this->postJson('/admin/api/payment-methods', self::VALID)->json('data');

        $this->deleteJson("/admin/api/payment-methods/{$created['id']}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $ids = array_column($this->getJson('/admin/api/payment-methods')->json('data'), 'id');

        $this->assertNotContains($created['id'], $ids);
    }

    /** PM-AC-23 */
    public function test_a_successful_deletion_writes_a_log_entry(): void
    {
        $created = $this->postJson('/admin/api/payment-methods', self::VALID)->json('data');

        Log::shouldReceive('info')
            ->once()
            ->with('Payment method deleted', [
                'id' => $created['id'],
                'name' => $created['name'],
                'code' => $created['code'],
            ]);

        $this->deleteJson("/admin/api/payment-methods/{$created['id']}")->assertOk();
    }

    /** PM-AC-24 — deaktivacija i brisanje su odvojene putanje. */
    public function test_switching_a_method_off_writes_no_log_entry(): void
    {
        $id = $this->getJson('/admin/api/payment-methods')->json('data.0.id');

        Log::shouldReceive('info')->never();

        $this->patchJson("/admin/api/payment-methods/{$id}/active", ['active' => false])->assertOk();
    }

    public function test_unknown_ids_are_not_found(): void
    {
        $this->getJson('/admin/api/payment-methods/9999')->assertNotFound();
        $this->putJson('/admin/api/payment-methods/9999', self::VALID)->assertNotFound();
        $this->patchJson('/admin/api/payment-methods/9999/active', ['active' => false])->assertNotFound();
        $this->deleteJson('/admin/api/payment-methods/9999')->assertNotFound();
    }

    /**
     * Brisanje ne sme da oslobodi `id` za ponovnu upotrebu — stari link na
     * `/admin/payment-methods/{id}/edit` bi inače počeo da menja drugu metodu.
     */
    public function test_ids_are_not_recycled_after_deletion(): void
    {
        $first = $this->postJson('/admin/api/payment-methods', self::VALID)->json('data');

        $this->deleteJson("/admin/api/payment-methods/{$first['id']}")->assertOk();

        $second = $this->postJson('/admin/api/payment-methods', ['code' => 'OTHER'] + self::VALID)->json('data');

        $this->assertGreaterThan($first['id'], $second['id']);
    }

    /** Prekidač na prazno ili besmisleno telo mora da odbije, ne da ugasi metodu. */
    public function test_toggling_requires_an_explicit_boolean(): void
    {
        $id = $this->getJson('/admin/api/payment-methods')->json('data.0.id');

        $this->patchJson("/admin/api/payment-methods/{$id}/active", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('active');

        $this->patchJson("/admin/api/payment-methods/{$id}/active", ['active' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('active');

        $this->assertTrue($this->getJson('/admin/api/payment-methods')->json('data.0.active'));
    }

    /** Izmena bez `active` bi tiho ugasila metodu — gašenje je druga radnja (PM-FR-69). */
    public function test_saving_without_active_is_rejected(): void
    {
        $existing = $this->getJson('/admin/api/payment-methods')->json('data.0');
        $payload = self::VALID;
        unset($payload['active']);

        $this->putJson("/admin/api/payment-methods/{$existing['id']}", ['code' => $existing['code']] + $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('active');

        $this->assertTrue($this->getJson('/admin/api/payment-methods')->json('data.0.active'));
    }

    /** PM-AC-26 — deep link mora da vrati SPA shell, ne JSON i ne 404. */
    public function test_every_spa_route_serves_the_shell(): void
    {
        foreach (['', '/create', '/3/edit'] as $path) {
            $this->get("/admin/payment-methods{$path}")
                ->assertOk()
                ->assertSee('id="app"', false);
        }
    }

    /** PM-R-01 — shell ruta ne sme progutati API putanje. */
    public function test_the_shell_route_does_not_swallow_the_api(): void
    {
        $this->getJson('/admin/api/payment-methods')
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }
}
