<?php

namespace App\Repositories;

use App\Models\ProviderAccount;

/**
 * Jedina tacka pristupa tabeli `provider_accounts`.
 *
 * Tabela je read-only za aplikaciju (invarijanta 3 iz data.md): puni je seeder,
 * a ovde postoje samo metode koje citaju. Nijedna metoda ne sme da zove save(),
 * update(), insert(), create() ni delete() - putanja upisa iz `app/` ne postoji.
 *
 * Klasa postoji zbog faze 2 (ADR-14): tada `provider_accounts` izlazi iz ove
 * baze u zaseban provider servis, telo ovih metoda pocinje da zove HTTP klijent,
 * a potpisi i svi pozivaoci ostaju neizmenjeni.
 *
 * Zato repozitorijum ne zna za `connections`. Ne cita tu tabelu i ne zna odakle
 * su uneti kredencijali stigli - dobija ih kao obicne string argumente, a
 * proveru pokrece pozivalac, koji u ovoj iteraciji jos ne postoji. Poredjenje
 * se izvodi ovde, uz sam podatak, da bi tajne ostale unutar jedine tacke
 * pristupa: kad u fazi 2 nalozi odu u provider servis, ovde se menja telo
 * metode, a da nijedan pozivalac nije video `api_secret`.
 *
 * Vrednosti `api_key` i `api_secret` se nigde ne loguju, ne bacaju u poruci
 * izuzetka i ne vracaju pozivaocu - metoda provere vraca samo bool.
 */
class ProviderAccountRepository
{
    /**
     * Aktivan nalog za dati PSPID i okruzenje, ili null kada ga nema.
     *
     * Upit je uvek ogranicen na jedan red - tabela se nikad ne ucitava cela.
     */
    public function findActive(string $pspid, string $environment): ?ProviderAccount
    {
        return ProviderAccount::query()
            ->where('pspid', $pspid)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Da li trojac kredencijala odgovara aktivnom nalogu.
     *
     * `$environment` nije cetvrti kredencijal nego opseg pretrage: isti PSPID
     * moze imati i `test` i `live` nalog, pa bi provera bez njega dozvolila da
     * test kredencijali prodju kao ispravni na produkciji.
     *
     * Poredjenje ide kroz hash_equals() umesto `===`: trajanje poredjenja ne
     * sme da zavisi od toga koliko se pocetnih znakova poklopilo, jer bi se
     * inace tajna pogadjala znak po znak. Iz istog razloga se oba poredjenja
     * izracunaju pre nego sto se rezultati spoje - kratki spoj na prvom bi odao
     * da li je `api_key` sam po sebi tacan.
     *
     * Sto ova metoda namerno ne krije: postojanje naloga. Kada aktivnog naloga
     * nema, vraca se odmah, bez poredjenja, pa se po trajanju odgovora moze
     * razlikovati nepoznat PSPID od pogresnog kljuca. PSPID nije tajna - tajne
     * su `api_key` i `api_secret` i njih stiti hash_equals().
     */
    public function activeCredentialsMatch(
        string $pspid,
        string $apiKey,
        string $apiSecret,
        string $environment,
    ): bool {
        $account = $this->findActive($pspid, $environment);

        if ($account === null) {
            return false;
        }

        $knownKey = (string) $account->api_key;
        $knownSecret = (string) $account->api_secret;

        // Prazna poznata vrednost bi kroz hash_equals('', '') propustila prazan
        // unos kao ispravan. Kolone su danas NOT NULL, ali select bez tih
        // kolona ili kasnije popustanje sheme bi bez ove provere postali tiha
        // rupa u autentikaciji.
        if ($knownKey === '' || $knownSecret === '') {
            return false;
        }

        $keyMatches = hash_equals($knownKey, $apiKey);
        $secretMatches = hash_equals($knownSecret, $apiSecret);

        return $keyMatches && $secretMatches;
    }
}
