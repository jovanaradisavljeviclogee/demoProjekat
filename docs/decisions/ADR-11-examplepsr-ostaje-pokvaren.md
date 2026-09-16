# ADR-11 — `ExamplePSR/` ostaje namerno pokvaren

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-16 · **Status:** prihvaćeno

**Kontekst:** `composer.json:77` ima `"optimize-autoloader": true`, pa je **svaki** dump optimizovan — ne samo onaj u kome se `-o` otkuca ručno. Posledica je da `BrokenNamespace.php` i `WrongFileName.php` proizvode dve `does not comply ... Skipping.` linije u svakom `composer install`, svakom `composer dump-autoload` i svakom Docker build-u (`Dockerfile:47`). Exit kod ostaje `0` i build prolazi, ali su to dva trajna upozorenja u svakom build logu. Nalaz je otkrio `structure-reviewer` — [zapis](../agent-outputs/2026-09-16-structure-reviewer-examplepsr.md).

**Opcije:** (a) zadržati fajlove i upozoriti na mestima gde se buka zaista vidi; (b) „popraviti" ih, tj. ispraviti namespace i preimenovati fajl; (c) izuzeti `ExamplePSR/` iz build konteksta preko `.dockerignore`.

**Odluka:** (a).

**Obrazloženje:** (b) uklanja dva od tri kvara koje primer postoji da bi pokazao — ostaje samo ispravna kontrola, a to više nije primer nego jedna klasa. (c) ućutkuje build, ali fajlovi ostaju u repozitorijumu i `demo-breaks.sh` ih i dalje reprodukuje, pa se problem samo pomera iz build loga u lokalno pokretanje; uz to bi sledeći čitalac video pokvarene fajlove bez ijednog traga da je to namerno.

Stvarni rizik nije buka nego tiho brisanje: tekst upozorenja imenuje fajl i pravilo, ali ne pokazuje nazad na objašnjenje. Prvi ko bude čistio log „popravio" bi oba fajla u dobroj nameri.

**Posledice:** dva upozorenja ostaju u svakom build logu, trajno. Zaštita je postavljena na tri mesta gde se log čita — komentar u `Dockerfile`-u iznad `RUN composer dump-autoload`, sekcija 10 u `README.md`, i pasus u `ExamplePSR/AUTOLOADING-BREAKS.md`. `composer.json` nije diran jer strogi JSON ne podnosi komentar. CI koji upozorenja tretira kao greške mora ta dva izuzeti **poimence**, nikada uklanjanjem primera.
