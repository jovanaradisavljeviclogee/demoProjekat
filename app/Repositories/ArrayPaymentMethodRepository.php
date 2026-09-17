<?php

namespace App\Repositories;

use Illuminate\Contracts\Session\Session;

/**
 * Jedina implementacija ugovora, i jedino mesto u aplikaciji koje zna gde su podaci.
 *
 * Izvor je statički niz `SEED` napisan u kodu (PM-FR-01). Pošto PHP ne pamti ništa
 * između zahteva, `SEED` služi kao početno stanje, a radna kopija stoji u sesiji —
 * inače bi create, update, toggle i delete nestajali odmah po odgovoru, pa poruka
 * o uspešnom brisanju iz PM-FR-67 ne bi bila istinita. Obrazloženje i odbačene
 * alternative su u ADR-16.
 *
 * Zamena ovog niza bazom dira samo ovu klasu (PM-FR-06).
 */
class ArrayPaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    private const SESSION_KEY = 'payment_methods';

    /** Poslednji dodeljen `id`. Odvojen od niza jer mora da preživi brisanje. */
    private const LAST_ID_KEY = 'payment_methods_last_id';

    /** Početno stanje. Odobreno uz PM-Q-01. */
    private const SEED = [
        ['id' => 1, 'name' => 'Bancontact', 'code' => 'BCMC', 'type' => 'redirect',
            'currencies' => ['EUR'], 'countries' => ['BE'], 'sortOrder' => 1, 'active' => true],
        ['id' => 2, 'name' => 'iDEAL', 'code' => 'IDEAL', 'type' => 'redirect',
            'currencies' => ['EUR'], 'countries' => ['NL'], 'sortOrder' => 2, 'active' => true],
        ['id' => 3, 'name' => 'Credit Card', 'code' => 'CARD', 'type' => 'iframe',
            'currencies' => ['EUR', 'USD', 'GBP'], 'countries' => ['BE', 'NL', 'DE', 'GB', 'US'], 'sortOrder' => 3, 'active' => true],
        ['id' => 4, 'name' => 'PayPal', 'code' => 'PAYPAL', 'type' => 'redirect',
            'currencies' => ['EUR', 'USD'], 'countries' => ['BE', 'NL', 'DE', 'US'], 'sortOrder' => 4, 'active' => false],
        ['id' => 5, 'name' => 'SOFORT', 'code' => 'SOFORT', 'type' => 'redirect',
            'currencies' => ['EUR'], 'countries' => ['DE', 'AT'], 'sortOrder' => 5, 'active' => true],
    ];

    /** ISO 4217. Jedine valute koje forma nudi i koje validacija prihvata. */
    private const ALLOWED_CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'RSD'];

    /** ISO 3166-1 alpha-2. Jedine zemlje koje forma nudi i koje validacija prihvata. */
    private const ALLOWED_COUNTRIES = ['BE', 'NL', 'DE', 'AT', 'GB', 'US', 'RS'];

    public function __construct(private readonly Session $session)
    {
    }

    public function all(): array
    {
        return $this->session->get(self::SESSION_KEY, self::SEED);
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $method) {
            if ($method['id'] === $id) {
                return $method;
            }
        }

        return null;
    }

    public function create(array $attributes): array
    {
        $methods = $this->all();

        $method = ['id' => $this->nextId($methods)] + $this->fill($attributes);
        $methods[] = $method;

        $this->persist($methods);

        return $method;
    }

    public function update(int $id, array $attributes): ?array
    {
        $methods = $this->all();

        foreach ($methods as $index => $method) {
            if ($method['id'] !== $id) {
                continue;
            }

            $methods[$index] = ['id' => $id] + $this->fill($attributes);
            $this->persist($methods);

            return $methods[$index];
        }

        return null;
    }

    public function setActive(int $id, bool $active): ?array
    {
        $methods = $this->all();

        foreach ($methods as $index => $method) {
            if ($method['id'] !== $id) {
                continue;
            }

            $methods[$index]['active'] = $active;
            $this->persist($methods);

            return $methods[$index];
        }

        return null;
    }

    public function delete(int $id): bool
    {
        $methods = $this->all();

        foreach ($methods as $index => $method) {
            if ($method['id'] !== $id) {
                continue;
            }

            // Valute i zemlje su polja unutar samog zapisa, pa odlaze sa njim.
            unset($methods[$index]);
            $this->persist(array_values($methods));

            return true;
        }

        return false;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        foreach ($this->all() as $method) {
            if ($method['id'] !== $exceptId && strcasecmp($method['code'], $code) === 0) {
                return true;
            }
        }

        return false;
    }

    public function allowedCurrencies(): array
    {
        return self::ALLOWED_CURRENCIES;
    }

    public function allowedCountries(): array
    {
        return self::ALLOWED_COUNTRIES;
    }

    /**
     * Prihvata samo poznata polja. Bez ovoga bi svaki dodatni ključ iz zahteva
     * završio u zapisu, pa bi oblik odgovora zavisio od toga šta je klijent poslao.
     */
    private function fill(array $attributes): array
    {
        return [
            'name' => (string) $attributes['name'],
            'code' => (string) $attributes['code'],
            'type' => (string) $attributes['type'],
            'currencies' => array_values($attributes['currencies']),
            'countries' => array_values($attributes['countries']),
            'sortOrder' => (int) $attributes['sortOrder'],
            'active' => (bool) ($attributes['active'] ?? false),
        ];
    }

    /**
     * Brojač je monoton i ne osvrće se na trenutni sadržaj niza.
     *
     * `max(id) + 1` bi posle brisanja poslednje metode dodelio upravo njen `id`, pa bi
     * otvorena kartica sa starim linkom `/admin/payment-methods/{id}/edit` neprimetno
     * počela da menja drugu metodu. Brisanje ne sme da reciklira identitet.
     */
    private function nextId(array $methods): int
    {
        $lastId = max(
            (int) $this->session->get(self::LAST_ID_KEY, 0),
            $methods === [] ? 0 : max(array_column($methods, 'id')),
        );

        $this->session->put(self::LAST_ID_KEY, $lastId + 1);

        return $lastId + 1;
    }

    private function persist(array $methods): void
    {
        $this->session->put(self::SESSION_KEY, $methods);
    }
}
