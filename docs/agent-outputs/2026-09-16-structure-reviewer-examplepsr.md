# structure-reviewer — `ExamplePSR/` — 2026-09-16

- **Agent:** `structure-reviewer` (`demo-project@ai-toolkit` 0.1.0)
- **Meta:** grana `feature/laravel_and_docker`, folder `ExamplePSR/`
- **Povod:** prvi test agenta na stvarnom slučaju. Folder je izabran zato što ima **unapred poznat tačan odgovor** — `Hello.php` je ispravan i služi kao kontrola, `BrokenNamespace.php` i `WrongFileName.php` su namerno pokvareni, svaki na drugi način.
- **Ishod:** prošao sva četiri kriterijuma i uz to prijavio posledicu koju dokumentacija samog primera nije pominjala.

---

## Izveštaj (doslovno)

> `Hello.php` je čist, a dva namerno pokvarena fajla su tačne ilustracije kvarova koje `composer-autoload` opisuje. Ali je izašla jedna posledica koju njihovi header-i ne pominju:
>
> **Upozorenja nisu opciona.** `composer.json:77` ima `"optimize-autoloader": true`, što znači da je svaki dump optimizovan — ne samo kad neko otkuca `-o`. Te dve `does not comply ... Skipping.` linije, koje skill opisuje kao dijagnostiku koju pokreneš namerno, zapravo izlaze iz:
>
> - svakog `composer install` u kontejneru,
> - svakog običnog `composer dump-autoload`,
> - svakog Docker build-a, na `Dockerfile:47`.
>
> Exit kod ostaje 0 i build prolazi, ali su to dva trajna upozorenja u svakom build logu.
>
> **Rizik iz toga:** tekst upozorenja imenuje fajl i pravilo, ali ne pokazuje nazad na `AUTOLOADING-BREAKS.md`. Ko god bude čistio bučan build log „popraviće" namespace na `BrokenNamespace.php:23` i preimenovati `WrongFileName.php` — i time tiho uništiti dve trećine primera. CI konfiguracije u repou nema, pa trenutno ništa ne pada, ali svaki pipeline koji grepuje build izlaz ili tretira upozorenja kao greške zapinje na repou koji se ponaša tačno kako je projektovan.
>
> **Tri manja nalaza:**
>
> - `.dockerignore` protivreči sopstvenom obrazloženju. Izuzima `README.md` i `docs/*.md` jer se markdown često menja i nepotrebno invalidira `COPY` sloj — ali `ExamplePSR/AUTOLOADING-BREAKS.md` i `demo-breaks.sh` nisu izuzeti, pa ulaze u build kontekst i na `Dockerfile:42` obaraju keš tog sloja i `dump-autoload` ispod njega. U runtime image ne stižu (`Dockerfile:166-167` kopira samo `vendor` i `public/build`), pa je uticaj mali.
> - `demo-breaks.sh:69-74` upisuje u `composer.json` na hostu preko `python3`, a `trap` na liniji 24 vraća stanje. Zaštita je dobro napisana, ali ne radi pod `SIGKILL` ili padom mašine — tada u praćenom fajlu ostaje `Domain\ => PogresanFolder/`. Uz to, `python3` je nedeklarisan preduslov koji header na linijama 5-6 ne navodi.
> - **Gde pripada `AUTOLOADING-BREAKS.md`** — nijedan skill ne zabranjuje `.md` i `.sh` u PSR-4 root-u, i tehnički su bezopasni. Ali `laravel-structure` u svojoj tabeli ne pominje ni `docs/` ni root `README.md`, pa ne postoji pravilo koje kaže da li prozni opis ide pored koda koji objašnjava ili u `docs/`. To je stvarna rupa u skill-u, ne u ovom repou.
>
> **Čisto:** `ExamplePSR/` kao namespace root je ispravno smešten i registrovan u `autoload`, a ne `autoload-dev` — što je tačno, jer ga `routes/web.php:4` koristi u runtime-u. Nema `env()` poziva, nema HTTP ili persistence logike u domenskim klasama, ništa privatno nije završilo u `public/`.

---

## Provera tvrdnji

Izveštaj agenta je tvrdnja dok se ne proveri. Svaka referenca na broj linije proverena je protiv repozitorijuma:

| Tvrdnja | Ishod |
|---|---|
| `composer.json:77` — `"optimize-autoloader": true` | tačno |
| `.dockerignore:88-91` — izuzeti `README.md` i `docs/*.md`, `ExamplePSR/*` nije | tačno |
| `Dockerfile:42` — `COPY . .` | tačno |
| `Dockerfile:47` — `RUN composer dump-autoload` | tačno |
| `Dockerfile:166-167` — kopira samo `vendor` i `public/build` | tačno |
| `routes/web.php:4` — `use Domain\Hello;` | tačno |
| `demo-breaks.sh:24` — `trap restore EXIT INT TERM` | tačno (`SIGKILL` se zaista ne može uhvatiti) |
| `demo-breaks.sh:69-74` — `python3` upisuje u `composer.json` | tačno |
| header linije 5-6 ne navode `python3` | tačno |

**Devet od devet tačno, nijedna izmišljena referenca.** To je merilo važnije od samog pronalaženja kvarova — agent koji izmišlja brojeve linija je gori od nepostojećeg.

## Ocena po unapred postavljenim kriterijumima

| Kriterijum | Ishod |
|---|---|
| Nalazi `BrokenNamespace.php` | prošao |
| Nalazi `WrongFileName.php` | prošao |
| **Ne** prijavljuje ispravan `Hello.php` | prošao — izričito ga zove čistim |
| Citira pravilo umesto da izmišlja konvenciju | prošao |

Treći red je bio najvažniji: lažno pozitivan nalaz na ispravnom fajlu ruši poverenje u ceo izveštaj i gori je od propuštenog nalaza.

## Šta smo uradili povodom ovoga

**U ovom repozitorijumu** — zaštita primera na tri mesta gde se buka zaista čita, v. [ADR-11](../decisions/ADR-11-examplepsr-ostaje-pokvaren.md):

- `Dockerfile` — komentar iznad `RUN composer dump-autoload`, imenuje oba fajla i kaže da se ne ispravljaju
- `README.md` — nova sekcija 10 „Dva očekivana upozorenja u build logu"; stara sekcija 10 postala 11
- `ExamplePSR/AUTOLOADING-BREAKS.md` — pasus zašto upozorenja nisu opciona
- `composer.json` **nije diran** — strogi JSON ne podnosi komentar

**U plugin-u** (`ai-toolkit`):

- `skills/laravel-structure/SKILL.md` — dodato pravilo gde pripada prozna dokumentacija, odlučeno pitanjem „da li dokument ima smisla odvojen od koda koji opisuje?". Uz eksplicitnu napomenu da `.md` i `.sh` u PSR-4 korenu **nisu** prekršaj.
- `agents/structure-reviewer.md` — proširena sekcija *Observations*. Agent je prijavio nalaze van svoje tri deklarisane kategorije, što je specifikacija zabranjivala. Nalazi su bili vredni, pa je izmenjena specifikacija, ne ponašanje.

## Nije popravljeno

`demo-breaks.sh` — nedeklarisan `python3` u header-u i `trap` koji ne preživljava `SIGKILL`. Zabeleženo u [`LEARNINGS.md`](../../LEARNINGS.md), svesno ostavljeno.
