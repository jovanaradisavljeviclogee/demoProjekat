<?php

/**
 * KVAR 3 od 3 — POGRESNO IME FAJLA
 *
 * Namespace je tacan (Domain), mapiranje u composer.json je tacno
 * ("Domain\\" => "ExamplePSR/"), klasa je sintaksno ispravna. Jedina greska je
 * sto se ime fajla ne poklapa sa imenom klase.
 *
 * PSR-4 trazi fajl ISKLJUCIVO po imenu klase. Za Domain\MismatchedClass Composer
 * izracuna putanju ExamplePSR/MismatchedClass.php i pogleda samo tu. Taj fajl ne
 * postoji — klasa zivi u WrongFileName.php, gde PSR-4 nikada ne gleda.
 *
 * Kako se ovaj kvar razlikuje od druga dva: fajl POSTOJI na disku i sadrzaj mu je
 * potpuno ispravan. Problem je iskljucivo u imenu. Zato `ls ExamplePSR/` pokazuje
 * fajl, a autoloader ga i dalje ne vidi — kombinacija koja zbunjuje dok se ne
 * shvati da je ime fajla deo ugovora, a ne stvar ukusa.
 *
 * Napomena za Linux: fajlsistem razlikuje velika i mala slova, pa bi i hello.php
 * umesto Hello.php proizveo isti kvar. Na Windows-u i macOS-u to cesto prodje
 * neprimeceno i pukne tek na serveru.
 *
 * Ovaj fajl je namerno pokvaren i nijedan deo aplikacije ga ne koristi.
 */

namespace Domain;

class MismatchedClass
{
    public function message(): string
    {
        return 'Ova klasa je ispravna, ali zivi u fajlu pogresnog imena';
    }
}
