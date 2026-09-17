# ADR-15 — Model se zove `Log`, uprkos sudaru sa Laravel `Log` fasadom

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** [`data.md`](../../data.md) u sekciji *Implementacija (Laravel / Eloquent)* imenuje model `Log`, sa `const UPDATED_AT = null`, `context` cast-om u `array` i `user()` relacijom koja sme da vrati `null`. Laravel isporučuje `Illuminate\Support\Facades\Log`, aliasovan globalno kao `\Log`. Dva simbola dele isto kratko ime.

Sudar je stvaran ali uzak: javlja se samo u fajlu koji uvozi oba. PHP rešava ime po `use` izjavama tog fajla, pa `use App\Models\Log;` čini da `Log::info(...)` u istom fajlu više ne pogađa fasadu — tiho, bez greške pri učitavanju, sa fatalnom greškom tek pri pozivu.

**Opcije:** (a) zadržati `Log`, kako `data.md` propisuje; (b) preimenovati model u `LogEntry`, tabela ostaje `logs`; (c) zadržati `Log` i ukloniti globalni alias fasade iz `config/app.php`.

**Odluka:** (a).

**Obrazloženje:** (b) je ono što bi većina projekata izabrala i nije pogrešno samo po sebi. Cena je da se uvodi treće ime za istu stvar: tabela `logs`, dokument kaže `Log`, klasa kaže `LogEntry`. `data.md` je ugovor između dokumenta i koda, i prvo odstupanje od njega je ono koje svako sledeće čini jeftinijim. Odstupanje bi uz to moralo da se zapiše i u `data.md`, čime bi izvor istine počeo da opisuje kod umesto da ga vodi.

(c) uklanja sudar u korenu, ali menja globalnu konfiguraciju celog Laravel-a zbog jedne klase, i razbija svaki primer iz dokumentacije i svaki paket koji računa na `\Log`. Cena je nesrazmerno veća od problema.

Presudilo je što je (a) jedini izbor čija je cena ograničena na fajlove koje sami pišemo, i to samo na one koji zaista koriste oba simbola. Takvih fajlova u ovoj fazi nema nijedan — nema koda koji piše logove.

**Posledice:** fajl kome trebaju oba simbola piše `use App\Models\Log;` i fasadu poziva kao `\Log::info(...)` ili, bolje, kroz `logger()` helper koji nema sudar. `App\Models\Log` nosi docblock koji imenuje sudar, da ga čitalac ne otkriva iz fatalne greške.

Ovo je klasa grešaka koju bi statička analiza (PHPStan, Psalm) uhvatila; nijedan takav alat nije u projektu i uvođenje nije u opsegu ovog posla. Zabeleženo kao RK2 u [`docs/domenProblema/design.md`](../domenProblema/design.md).
