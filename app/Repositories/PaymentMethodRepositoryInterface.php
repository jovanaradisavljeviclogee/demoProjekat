<?php

namespace App\Repositories;

/**
 * Ugovor za čitanje i pisanje payment metoda (PM-FR-04).
 *
 * Nijedan sloj iznad ovog interfejsa ne sme znati odakle podaci dolaze. Zbog toga
 * je ovde i `codeExists`: jedinstvenost koda je svojstvo celog skupa, a skup zna
 * samo implementacija. Da provera stoji u servisu, servis bi morao da povuče sve
 * zapise — a to je upravo ono znanje o izvoru koje PM-FR-04 zabranjuje.
 *
 * Jedna metoda je asocijativan niz sa ključevima:
 * id, name, code, type, currencies, countries, sortOrder, active.
 */
interface PaymentMethodRepositoryInterface
{
    /** Sve metode, nepoređane. Sortiranje za prikaz je posao sloja iznad. */
    public function all(): array;

    public function find(int $id): ?array;

    /** Dodeljuje sledeći slobodan `id` i vraća upisanu metodu. */
    public function create(array $attributes): array;

    public function update(int $id, array $attributes): ?array;

    /**
     * Menja isključivo `active`. Odvojeno od `delete` i nikada ga ne poziva —
     * metoda koja treba samo da prestane da se nudi isključuje se, ne briše
     * (PM-FR-69, PM-C-04).
     */
    public function setActive(int $id, bool $active): ?array;

    /** Uklanja metodu zajedno sa njenim vezama ka valutama i zemljama. */
    public function delete(int $id): bool;

    /** `$exceptId` izuzima metodu koja se upravo menja (PM-FR-52). */
    public function codeExists(string $code, ?int $exceptId = null): bool;

    /** Kodovi valuta koje forma nudi i koje validacija prihvata (PM-FR-03). */
    public function allowedCurrencies(): array;

    /** Kodovi zemalja koje forma nudi i koje validacija prihvata (PM-FR-03). */
    public function allowedCountries(): array;
}
