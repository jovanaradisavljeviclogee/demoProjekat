# ADR-14 — Pristup `provider_accounts` ide kroz repozitorijum klasu

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** [`data.md`](../../data.md) opisuje `provider_accounts` kao tabelu koja nije vlasništvo aplikacije: puni je seeder, aplikacija je samo čita, i „u fazi 2 ova tabela izlazi iz aplikacije i postaje deo zasebnog provider servisa". Dokument izričito traži da „sav pristup treba da ide kroz jednu tačku (repozitorijum ili servis klasu), ne kroz upite razasute po kodu" — i imenuje razlog: to je ono što fazu 2 čini malom promenom umesto prepisivanjem.

Tabela je namerno odvojena od `connections`. Kredencijali koje prodavac unese žive u `connections`; `provider_accounts` drži ono što provajder smatra validnim. Veze između njih nema — poređenje je provera vrednosti, ne strani ključ.

**Opcije:** (a) posvećena klasa `App\Repositories\ProviderAccountRepository`; (b) statičke metode na `App\Models\ProviderAccount`; (c) Eloquent query scope-ovi na modelu; (d) zaseban PSR-4 koren, po uzoru na `Domain\` → `ExamplePSR/`.

**Odluka:** (a).

**Obrazloženje:** sve četiri opcije daju mesto na koje se može pokazati. Razlika je šta se dešava u fazi 2.

(b) i (c) vezuju tačku pristupa za Eloquent model, a model je vezan za tabelu. U fazi 2 tabela nestaje iz ove baze. Ostaje model bez tabele, sa metodama koje sad zovu HTTP klijenta — klasa koja nasleđuje `Eloquent\Model` a ništa ne radi sa bazom. To nije proširenje nego ostatak. (c) je uz to i slabiji: scope ne sprečava nikoga da napiše `ProviderAccount::where(...)` mimo njega, pa invarijanta 3 iz `data.md` prestaje da bude proverljiva `grep`-om.

(d) bi bio ispravan da je reč o zaokruženom domenu sa više klasa. Ovde je jedna klasa koja čita jednu tabelu. Nov PSR-4 koren znači izmenu `composer.json`-a i `composer dump-autoload`, uz ceo lanac načina na koje registracija korena ume da zakaže (vidi skill `composer-autoload`) — trošak koji jedna klasa ne opravdava.

(a) ostavlja `ProviderAccount` kao običan Eloquent model, dok `ProviderAccountRepository` menja telo i zadržava potpis. Nijedan pozivalac se ne dira.

Precizno, jer se ove dve rečenice prividno sudaraju: u fazi 2 nestaje **tabela**, ne nužno i klasa. `findActive()` vraća `?ProviderAccount`, pa tip ostaje deo potpisa. Klasa tada prestaje da bude Eloquent model i postaje običan objekat koji repozitorijum popunjava iz odgovora provider servisa. Pozivaoci i dalje ne vide razliku — što je cela poenta odluke.

**Posledice:** klasa živi u `app/Repositories/ProviderAccountRepository.php`, namespace `App\Repositories`, pod postojećim korenom `App\` → `app/`. **`composer.json` se ne menja** i nov `composer dump-autoload` za koren nije potreban.

Ovo je prva klasa u `app/Repositories/`. Time nastaje direktorijum koji Laravel ne propisuje i koji se lako pogrešno čita kao poziv da svaki model dobije repozitorijum. Ne dobija: `Connection`, `Setting` i `PaymentMethod` se čitaju direktno kroz Eloquent. Repozitorijum ovde postoji zbog jedne konkretne buduće promene, ne kao sloj.

Invarijanta je merljiva, ali se mora meriti tačno ono što je bitno: **broj fajlova u `app/` koji postavljaju upit nad `provider_accounts` mora ostati 1** — sam repozitorijum. Fajlova koji tabelu ili model *pominju* je dva: `app/Models/ProviderAccount.php` i `app/Repositories/ProviderAccountRepository.php`. Model mora postojati da bi repozitorijum imao šta da vrati; on sam ne postavlja nijedan upit.

Formulacija „tačno jedan fajl pominje" bila bi pogrešna i oborila bi `grep` proveru na prvom PR-u koji uvodi repozitorijum. To je NFR N11 u [`docs/domenProblema/design.md`](../domenProblema/design.md) i proverava se `grep`-om na svakom PR-u, kroz `structure-reviewer`.
