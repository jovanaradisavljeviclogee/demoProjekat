<?php

namespace App\Http\Requests;

use App\Repositories\PaymentMethodRepositoryInterface;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Zajednička pravila iz PM-FR-51…57 za kreiranje i izmenu.
 *
 * Jedina razlika između dva naslednika je `id` koji se izuzima iz provere jedinstvenosti
 * koda. Zato dve klase umesto grananja po `$this->route()` unutar pravila — izostavljeno
 * izuzeće je tiha greška koju niko ne primeti dok metoda ne odbije sopstveni kod (PM-R-05).
 *
 * Dozvoljene valute i zemlje se čitaju iz repozitorijuma, ne prepisuju: literal iznad
 * repozitorijuma je zabranjen (PM-C-05).
 */
abstract class PaymentMethodRequest extends FormRequest
{
    /** Autorizacija administratora je van opsega ovog tiketa (spec 2.2). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `rules()` se razrešava kroz kontejner, pa repozitorijum stiže metodnom injekcijom —
     * konstruktor `FormRequest`-a pripada Symfony zahtevu i ne sme se menjati.
     */
    public function rules(PaymentMethodRepositoryInterface $repository): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', $this->uniqueCode($repository)],
            'type' => ['required', 'in:redirect,iframe'],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => [Rule::in($repository->allowedCurrencies())],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => [Rule::in($repository->allowedCountries())],
            'sortOrder' => ['required', 'integer'],
            // Obavezno, iako PM-FR-51…57 ne traže izričito. Kao neobavezno polje sa
            // podrazumevanim `false`, izmena koja ga izostavi tiho gasi metodu — a
            // gašenje i izmena su po PM-FR-69 dve različite radnje. Bolje je odbiti
            // nepotpun zahtev nego izvesti radnju koju pošiljalac nije tražio.
            'active' => ['required', 'boolean'],
        ];
    }

    /** `null` pri kreiranju, `{id}` iz rute pri izmeni (PM-FR-52). */
    abstract protected function ignoredId(): ?int;

    /**
     * Jedinstvenost je svojstvo celog skupa, a skup zna samo repozitorijum — zato provera
     * ide kroz `codeExists`, a ne kroz povlačenje niza u ovaj sloj (PM-FR-04).
     */
    private function uniqueCode(PaymentMethodRepositoryInterface $repository): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($repository): void {
            if (is_string($value) && $repository->codeExists($value, $this->ignoredId())) {
                $fail('The code has already been taken.');
            }
        };
    }
}
