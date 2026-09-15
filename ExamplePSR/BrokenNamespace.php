<?php

/**
 * KVAR 2 od 3 — POGRESAN NAMESPACE
 *
 * Fajl je na tacnoj putanji koju PSR-4 ocekuje: mapiranje "Domain\\" => "ExamplePSR/"
 * znaci da se klasa Domain\BrokenNamespace trazi bas u ExamplePSR/BrokenNamespace.php.
 * Ime fajla se poklapa sa imenom klase. Sve izgleda ispravno.
 *
 * Greska je u deklarisanom namespace-u: ovde stoji Domain\Wrong umesto Domain.
 * Composer ce fajl PRONACI i UCITATI, ali ce u njemu zateci klasu pod imenom
 * Domain\Wrong\BrokenNamespace. Trazena Domain\BrokenNamespace nije definisana
 * nigde, pa PHP prijavljuje da klasa ne postoji.
 *
 * Kako se ovaj kvar razlikuje od druga dva: fajl JESTE ucitan. To se dokazuje tako
 * sto poziv stvarno deklarisanog imena (Domain\Wrong\BrokenNamespace) radi odmah
 * posle neuspelog poziva — klasa je vec u memoriji. Kod kvara 1 i kvara 3 fajl se
 * nikada ne otvori, pa taj trik ne prolazi.
 *
 * Ovaj fajl je namerno pokvaren i nijedan deo aplikacije ga ne koristi.
 */

namespace Domain\Wrong;

class BrokenNamespace
{
    public function message(): string
    {
        return 'Ova klasa postoji, ali pod imenom Domain\Wrong\BrokenNamespace';
    }
}
