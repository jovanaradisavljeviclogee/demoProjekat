# Learnings

Jedna datirana stavka po radnoj sesiji, najnovija na vrhu: šta je promenjeno, šta je pošlo naopako, i ispravka.

---

## 2026-09-16

### Promenjeno

- Instaliran i omogućen plugin `demo-project@ai-toolkit` (`.claude/settings.local.json`). Projekat sada dobija konvencije ovog okruženja kroz skill-ove, umesto da se ponavljaju u svakom razgovoru.
- `ai workflow/` preimenovan u `docs/`. `spec.md`, `design.md` i `tasks.md` su zadržali sadržaj.
- `docs/` izuzet iz `.gitignore` — dokumentacija se sada verzioniše.
- `.dockerignore` ažuriran: tri putanje `ai workflow/*.md` → `docs/*.md`.
- Dodat `CLAUDE.md` koji imenuje plugin i pokazuje na `docs/` i na ovaj fajl.

### Šta je pošlo naopako

**Razmak u imenu foldera.** `ai workflow/` je od početka imao razmak. Svaka komanda nad njim morala je pod navodnicima, a bez njih se tumači kao dva argumenta. Isti tip greške se u toku iste sesije pojavio dvaput i u `ai-toolkit` repozitorijumu, gde je uzrokovao da se skill uopšte ne učita.

**Dokumentacija je bila gitignorisana.** `.gitignore` je sadržao `/ai workflow/*.md`, pa `spec.md`, `design.md` i `tasks.md` nisu bili u repozitorijumu — postojali su samo na disku ove mašine. To čini nemogućim pravilo „docs/ se ažurira u istom PR-u kao kod", jer ignorisan fajl ne može da bude deo PR-a. Istovremeno je značilo da bi ta tri dokumenta nestala pri svežem klonu.

**`git mv` nije prošao.** Pokušaj preimenovanja završio je sa `fatal: source directory is empty` — git je folder video kao prazan upravo zato što su svi fajlovi u njemu bili ignorisani. Poruka ne imenuje pravi uzrok.

### Ispravka

- Obično `mv` umesto `git mv`, pošto git nije pratio nijedan fajl.
- Uklonjena linija `/ai workflow/*.md` iz `.gitignore`, bez zamene — `docs/` se od sada verzioniše, pa pravilo „docs/ se ažurira u istom PR-u kao kod" postaje izvodljivo.
- Tri putanje u `.dockerignore` prepisane na `docs/`, da dokumentacija i dalje ne ulazi u Docker build kontekst. Bez toga bi preimenovanje tiho poništilo to pravilo.

### Testiranje plugin-a

Devet skill-ova provereno stvarnim pitanjima o ovom okruženju. Kriterijum nije bio da odgovor pogodi temu, nego da sadrži **konkretne vrednosti iz ovog projekta** — generičan Laravel odgovor znači da se skill nije učitao.

| Skill | Testno pitanje | Ishod |
|---|---|---|
| `docker-env` | kako pokrenuti `artisan migrate` ovde | prošao |
| `composer-autoload` | `Class "Domain\Foo" not found` | prošao |
| `laravel-structure` | gde ide nov controller | prošao |
| `laravel-init` | nova Laravel aplikacija na ovom okruženju | prošao |
| `writing-specs` | spec za `/health` endpoint | prošao |
| `writing-design` | `design.md` na osnovu spec-a | prošao |
| `writing-tasks` | podela na taskove | prošao |
| `executing-tasks` | početak implementacije po `tasks.md` | prošao |
| `performance-review` | provera da li je izmena spora | prošao |

Devet od devet, sa konkretnim vrednostima u odgovorima. Nijedna ispravka skill-a nije bila potrebna posle ovog kruga.

### Test agenta `structure-reviewer`

Agenti su napisani kasnije u istoj sesiji, pa je `structure-reviewer` pokrenut nad ovim repozitorijumom.

**Slučaj.** Grana `feature/laravel_and_docker`, meta `ExamplePSR/`. Taj folder ima unapred poznat tačan odgovor: `Hello.php` je ispravan i služi kao kontrola, `BrokenNamespace.php` deklariše `namespace Domain\Wrong` gde mapiranje traži `Domain`, a `WrongFileName.php` deklariše `class MismatchedClass` u fajlu drugog imena.

**Rezultat.** Našao oba kvara, nije prijavio `Hello.php`, i citirao pravila iz skill-ova umesto da izmisli konvenciju. Svih devet navedenih brojeva linija provereno je protiv repozitorijuma — svih devet tačno.

### Šta je test otkrio o ovom repozitorijumu

**Dva trajna upozorenja u svakom build logu.** `composer.json:77` ima `"optimize-autoloader": true`, pa je svaki dump optimizovan. One dve `does not comply ... Skipping.` linije, koje `AUTOLOADING-BREAKS.md` opisuje kao dijagnostiku koja se pokreće namerno, zapravo izlaze iz svakog `composer install`, svakog `dump-autoload` i svakog Docker build-a na `Dockerfile:47`.

Exit kod ostaje `0` i ništa ne pada. Rizik je drugačiji: tekst upozorenja imenuje fajl i pravilo, ali ne pokazuje nazad na objašnjenje. Prvi ko bude čistio bučan log „popravio" bi `BrokenNamespace.php` i preimenovao `WrongFileName.php` — i time tiho uništio dve trećine primera.

**Rupa u standardu, ne u repozitorijumu.** Nijedan skill nije govorio gde pripada prozna dokumentacija — pored koda koji objašnjava, ili u `docs/`. Agent je to prijavio kao nedostatak pravila, a ne kao prekršaj. Pravilo je posle toga dopunjeno u skill-u `laravel-structure`.

### Ispravka

- `Dockerfile` — komentar iznad `RUN composer dump-autoload`: upozorenja su očekivana, izvor su dva imenovana fajla, ne ispravljati ih.
- `README.md` — nova sekcija 10, „Dva očekivana upozorenja u build logu", sa tačnim tekstom poruka i napomenom da CI koji upozorenja tretira kao greške treba da ih izuzme poimence, umesto da se uklone primeri. Stara sekcija 10 postala je 11.
- `ExamplePSR/AUTOLOADING-BREAKS.md` — pasus koji objašnjava zašto upozorenja nisu opciona.
- `composer.json` **nije diran** — strogi JSON ne podnosi komentar.

### Otvoreno

`ExamplePSR/demo-breaks.sh` koristi `python3` (linije 69-74), a header na linijama 5-6 ga ne navodi kao preduslov — na mašini bez `python3` skripta pada nejasno. Uz to, `trap` na liniji 24 vraća `composer.json` pri normalnom izlasku i pri prekidu, ali ne preživljava `SIGKILL`; u tom slučaju u praćenom fajlu ostaje `Domain\ => PogresanFolder/`. Nije popravljeno.

### Test agenta `env-doctor`

**Slučaj.** Zdravo okruženje — sva tri kontejnera `Up (healthy)`. Namerno: nije se testiralo da nađe kvar, nego **da ga ne izmisli** i da ništa ne pokvari. [Zapis](docs/agent-outputs/2026-09-16-env-doctor-health-check.md).

**Rezultat.** Verdikt „nema kvara" uz devet nabrojanih provera. Svih devet proverljivih tvrdnji tačno — 111 paketa, `User::count()` 11, pet pinovanih verzija, tri HTTP odziva 200, 27 Xdebug poruka u logu. Nijedna lozinka u izlazu.

Dva ponašanja vrednija od samog verdikta: rekao je **šta nije mogao da proveri i zašto** (`public/build` bez pokretanja Vite build-a) umesto da pogodi, i uz preporuku za brisanje zaostalog volume-a naveo da je `zadatak-docker_db_data` nepovratan.

**Bezbednosna provera prošla.** Kontejneri `Up 2 hours` → `Up 3 hours` — nastavak rada, ne restart. Volume-i netaknuti, `.env` sha nepromenjen (`250c1d7662b361ed`).

### Nalaz koji je bio naša greška

`env-doctor` je prijavio da `.dockerignore` ne pokriva `docs/decisions/` ni `docs/agent-outputs/`.

To nije bilo zatečeno stanje. Pri preimenovanju `ai workflow/` → `docs/` ranije iste sesije, tri putanje su prepisane **fajl po fajl** umesto na ceo folder. Novi poddirektorijumi zato nisu bili obuhvaćeni, a `Dockerfile:42` je `COPY . .` — dokumentacija bi ušla u build kontekst i u image.

Pouka je opštija od ovog slučaja: **kad se pravilo prepisuje pri preimenovanju, prepiši ga na najširem nivou koji je i dalje tačan.** Nabrajanje pojedinačnih fajlova je bilo tačno u trenutku pisanja i pogrešno tri sata kasnije.

**Ispravka:** četiri linije zamenjene jednim unosom `docs/`, uz komentar zašto folder a ne nabrajanje.

### Ostavljeno namerno

- **Xdebug ne stiže do IDE-a** pod WSL2 — `host.docker.internal` pokazuje na Windows host. Nije kvar kontejnera; `XDEBUG_MODE=off` ućutkuje buku kad breakpointi ne trebaju.
- **Zaostali `zadatak-docker-*` kontejneri i volume-i.** `zadatak-docker_db_data` drži bazu drugog projekta i brisanje je nepovratno — odluka vlasnika tog projekta, ne posledica ove dijagnostike.
- **`demo-breaks.sh`** — nedeklarisan `python3`, `trap` ne preživljava `SIGKILL`.
