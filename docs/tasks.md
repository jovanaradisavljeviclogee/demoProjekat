# tasks.md — Plan implementacije i podela rada

> Status: **Plan izvršenja (faza 3)** — razbijanje implementacije na taskove sa isključivim vlasništvom nad fajlovima.
> Glavni agent koordinira; svaki task izvršava tačno jedan subagent-developer.

---

## 1. Svrha i odnos prema prethodnim dokumentima

| Dokument | Pitanje na koje odgovara |
|---|---|
| `ai forkflow/spec.md` | **Šta** se gradi i kako se dokazuje da je urađeno (FR, C, AC) |
| `design.md` | **Kako je sistem sastavljen** (komponente, tokovi, ADR, rizici) |
| `tasks.md` | **Ko šta radi, kojim redom i kada je gotov** |

Ovaj dokument ne uvodi nove zahteve. On preslikava postojeće zahteve na izvršne jedinice rada.

---

## 2. Nepregovaračka pravila za sve učesnike

Ova pravila važe podjednako za glavnog agenta i za svakog subagenta.

- **P-01 — Zabrana izmišljanja.** Ne sme se dodati nijedna funkcionalnost, fajl, servis, paket, promenljiva ni konfiguracija koja ne proizlazi iz `ai forkflow/spec.md` ili `design.md`.
- **P-02 — Zabrana tihe izmene.** Nijedan zahtev iz `ai forkflow/spec.md` niti odluka iz `design.md` ne sme se promeniti, zaobići ni "poboljšati". Ako je zahtev pogrešan, rad se zaustavlja i pitanje ide naručiocu.
- **P-03 — Isključivo vlasništvo nad fajlovima.** Subagent piše **samo** u fajlove navedene u njegovom tasku. Ni jedan fajl nema dva vlasnika u istom talasu.
- **P-04 — Ugovor je obavezujući.** Sve konstante iz sekcije 4 (imena servisa, putanje, imena promenljivih, verzije) koriste se doslovno. Subagent ih ne bira i ne menja.
- **P-05 — Eskalacija umesto pretpostavke.** Svaka nejasnoća se prijavljuje glavnom agentu i rad na tom delu staje. Glavni agent pita naručioca. Nagađanje nije dozvoljeno.
- **P-06 — Obavezan završni ciklus.** Nijedan task nije završen dok subagent ne odradi *code review*, *simplify* i *performance review* nad svojim radom (sekcija 7).
- **P-07 — Zabrane iz `ai forkflow/spec.md` važe bez izuzetka:** bez `latest` (C-01), bez hardkodovanih lozinki (C-02), bez hardkodovanih IP adresa (C-03), bez Docker socket mount-a (C-04), bez bind mount-a za MySQL (C-05), Node samo u build stage-u (C-06), aplikacija ne radi kao root (C-07).

---

## 3. Model izvršavanja

```
  TALAS 0 — temelj (sekvencijalno, 1 subagent)
  ┌──────────────────────────────────────────────┐
  │ T-00  Laravel scaffold + zaključavanje       │
  │       tačnih verzija                         │
  └───────────────────────┬──────────────────────┘
                          │ popunjava sekciju 4.6
                          ▼
  TALAS 1 — izgradnja (6 subagenata PARALELNO)
  ┌──────────┬──────────┬──────────┬──────────┬──────────┬──────────┐
  │ T-01     │ T-02     │ T-03     │ T-04     │ T-05     │ T-06     │
  │Dockerfile│docker/app│docker/web│ compose  │env+ignore│  seed    │
  └──────────┴──────────┴──────────┴──────────┴──────────┴──────────┘
                          │ svi izveštaji glavnom agentu
                          ▼
  TALAS 2 — integracija (glavni agent) + dokumentacija (1 subagent)
  ┌──────────────────────────────────────────────┐
  │ Build, podizanje, provera AC-01…AC-17,       │
  │ merenje veličine image-a i slojeva           │
  │            ↓ izmerene vrednosti              │
  │ T-07  README.md                              │
  └───────────────────────┬──────────────────────┘
                          ▼
  TALAS 3 — finalni pregled (glavni agent)
  ┌──────────────────────────────────────────────┐
  │ simplify + code review + performance review  │
  │ nad celim repozitorijumom                    │
  └──────────────────────────────────────────────┘
```

**Zašto T-00 ne može biti paralelan:** svi ostali taskovi pinuju verzije i referišu putanje iz Laravel rasporeda. Da svaki agent bira verzije sam, dobili bismo šest različitih pinova i nespojiv rezultat.

**Zašto T-07 ne može u talas 1:** FR-87 traži da README dokumentuje **izmerenu** veličinu finalnog image-a i tri najveća sloja. Te vrednosti ne postoje pre uspešnog build-a.

---

## 4. Ugovor o interfejsima

Obavezujuće konstante. Izvedene su iz `design.md` i ne menjaju se bez odobrenja naručioca.

### 4.1 Imena
| Stavka | Vrednost |
|---|---|
| Servisi | `web`, `app`, `db` |
| Compose mreža | `appnet` |
| Build stage-ovi | `composer-deps`, `frontend-assets`, `runtime`, `web` |
| Imenovani volume-i | `db-data`, `app-vendor`, `app-build` |
| Non-root korisnik/grupa u `app` | `appuser` / `appuser` |

### 4.2 Putanje u kontejnerima
| Stavka | Vrednost |
|---|---|
| Koren projekta (**identičan u `app` i `web`**, v. `design.md` 3.1) | `/var/www/html` |
| `DocumentRoot` | `/var/www/html/public` |
| FPM listen adresa | `0.0.0.0:9000` |
| Healthcheck ruta `web` servisa | `GET /healthz` → 200, servira Apache bez PHP-a |

### 4.3 Mapiranje konfiguracionih fajlova repo → image
| Fajl u repozitorijumu | Odredište u image-u |
|---|---|
| `../docker/app/php.ini` | `/usr/local/etc/php/conf.d/zz-app.ini` |
| `../docker/app/xdebug.ini` | `/usr/local/etc/php/conf.d/zz-xdebug.ini` |
| `../docker/app/fpm-pool.conf` | `/usr/local/etc/php-fpm.d/zz-pool.conf` |
| `../docker/app/entrypoint.sh` | `/usr/local/bin/entrypoint.sh` |
| `../docker/web/vhost.conf` | `/etc/apache2/sites-available/000-app.conf` |
| `../docker/web/healthz` | `/var/www/healthz/index.html` (van `DocumentRoot`-a, servira se kroz `Alias`) |

### 4.4 Environment promenljive
Imena su fiksna (izvor: `design.md` sekcija 7). Nijedan agent ne sme uvesti novu promenljivu bez eskalacije.

`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
`MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`
`WEB_HOST_PORT`, `DB_HOST_PORT`
`XDEBUG_MODE`, `XDEBUG_CLIENT_HOST`, `XDEBUG_CLIENT_PORT`, `XDEBUG_IDE_KEY`
`SEED_USER_PASSWORD`
`APP_UID`, `APP_GID`

Fiksne vrednosti: `DB_HOST=db`, `DB_PORT=3306` (interni), `WEB_HOST_PORT=8080`, `DB_HOST_PORT=3307`, `XDEBUG_CLIENT_PORT=9003`, `APP_UID=1000`, `APP_GID=1000`.

### 4.5 Dve tačke koje se lako previde
- **Composer instalira i `require-dev` zavisnosti.** Ovo je razvojno okruženje; `faker` je potreban za factory-je iz FR-35, a bez njega seed pada. `--no-dev` se **ne** koristi.
- **`entrypoint.sh` generiše `APP_KEY` ako je prazan.** NFR-02 traži da posle kopiranja `../.env` nema ručnih koraka; Laravel bez `APP_KEY` ne radi.
- **`entrypoint.sh` pokreće migracije pri svakom startu** (odluka O-09, FR-74). Bez toga sveže podignut stack vraća HTTP 500 jer Laravel drži sesije u bazi. Operacija je idempotentna — ponovljeni start daje `Nothing to migrate`.
- **`entrypoint.sh` NE pokreće seed.** `db:seed` nije idempotentan — izmereno 11 → 21 korisnika pri drugom pokretanju. Seed ostaje svesna radnja developera (UC-03).

### 4.6 Zaključane verzije
**ZAKLJUČANO od strane T-00.** Sve vrednosti su pročitane iz pokrenutog kontejnera, ne iz dokumentacije. Nijedan agent ih ne sme menjati ni zameniti.

| Komponenta | Pinovana verzija | Verifikovano komandom |
|---|---|---|
| PHP (bazni image) | `php:8.3.33-fpm-bookworm` | `docker run --rm php:8.3.33-fpm-bookworm php -v` → `PHP 8.3.33` |
| PHP FPM SAPI | isti image | `php-fpm -v` → `PHP 8.3.33 (fpm-fcgi)` |
| Debian (bazni image za `web`) | `debian:12.15-slim` | `cat /etc/debian_version` → `12.15`, codename `bookworm` |
| Apache 2.4 (`apache2` paket) | `2.4.68-1~deb12u1` | `apt-cache policy apache2` u `debian:12.15-slim` → Candidate |
| MySQL | `mysql:8.4.11` | `mysqld --version` → `Ver 8.4.11` |
| Node.js | `node:24.21.0-bookworm-slim` | `node -v` → `v24.21.0`, npm `11.19.0` |
| Composer | `composer:2.8.12` | `composer --version` → `Composer version 2.8.12` |
| Laravel | `12.69.2` | `php artisan --version` → `Laravel Framework 12.69.2` |
| Xdebug | `3.5.3` | `pecl install xdebug` pod PHP 8.3.33 → `with Xdebug v3.5.3` |

**Napomena uz Apache pin (rizik R-01, razrešen).** `apt-cache policy apache2` u `debian:12.15-slim` prijavljuje dve verzije: `2.4.68-1~deb12u1` iz `bookworm/main` i `2.4.67-1~deb12u3` iz `bookworm-security`. Provereno `dpkg --compare-versions`: **`2.4.68-1~deb12u1` je novija** i jeste ono što `apt-get install` stvarno razrešava. To je normalno stanje posle Debian point release-a — `main` nosi zbirnu verziju koja nadilazi poslednji security update. Pinuje se Candidate.

**Napomena za T-01 (nalaz T-00).** Image `composer:2.8.12` interno nosi PHP 8.4.14. Composer binarni fajl se mora `COPY --from`-ovati u PHP 8.3 sloj; taj image se **ne sme** koristiti kao osnova `composer-deps` stage-a, jer bi `composer install` tekao pod pogrešnim PHP-om i proizveo pogrešno razrešene zavisnosti.

---

## 5. Matrica vlasništva nad fajlovima

Svaki fajl ima **tačno jednog** vlasnika po talasu. Ovo je mehanizam koji čini paralelni rad bezbednim.

| Task | Talas | Isključivo vlasništvo |
|---|---|---|
| T-00 | 0 | ceo Laravel scaffold (`../app`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`, `artisan`, `.gitignore`) |
| T-01 | 1 | `../Dockerfile` |
| T-02 | 1 | `../docker/app/php.ini`, `docker/app/xdebug.ini`, `docker/app/fpm-pool.conf`, `docker/app/entrypoint.sh` |
| T-03 | 1 | `../docker/web/vhost.conf`, `docker/web/healthz` |
| T-04 | 1 | `../compose.yaml` |
| T-05 | 1 | `../.env.example`, `.dockerignore` |
| T-06 | 1 | `../database/seeders/DatabaseSeeder.php`, `database/factories/UserFactory.php` |
| T-07 | 2 | `../README.md` |

T-00 kreira osnovnu verziju fajlova koje kasnije preuzimaju T-05 (`../.env.example`), T-06 (seeder, factory) i T-07 (`README.md`). Od talasa 1 nadalje vlasništvo je isključivo kako je gore navedeno.

**Niko ne dira:** `ai forkflow/spec.md`, `design.md`, `tasks.md`. Ova tri fajla su u nadležnosti glavnog agenta.

---

## 6. Taskovi

### T-00 — Laravel scaffold i zaključavanje verzija
**Talas:** 0 · **Izvršilac:** 1 subagent · **Blokira:** sve ostalo

**Cilj**
1. Kreirati Laravel 12 projekat u korenu repozitorijuma, u standardnom rasporedu iz `design.md` sekcije 6.
2. Verifikovati i zaključati tačne verzije iz sekcije 4.6.

**Kako**
- Scaffold se izvršava **unutar pinovanog kontejnera**, ne alatima sa hosta — host ima PHP 8.5 i Composer 2.9.5, što ne odgovara ciljnim verzijama i može proizvesti `../composer.lock` nespojiv sa PHP 8.3.
- Za svaki image iz sekcije 4.6: povući ga i pročitati stvarnu verziju iz kontejnera, ne sa dokumentacije. Upisati tačan `major.minor.patch` tag i komandu kojom je dobijen.
- Za `apache2` paket: utvrditi tačnu verziju dostupnu u pinovanom Debian baznom image-u (v. rizik R-01 iz `design.md`).
- Za Xdebug: utvrditi tačnu verziju kompatibilnu sa PHP 8.3.

**Realizuje:** PIN-01…PIN-04, C-01, osnovu za FR-34…FR-36

**Definicija završenosti**
- Laravel se scaffold-uje bez grešaka; `artisan`, `../public/index.php`, `composer.lock`, `package-lock.json` postoje.
- Tabela 4.6 popunjena stvarno verifikovanim vrednostima; nijedna ćelija ne sadrži pretpostavku.
- `../composer.lock` je generisan pod PHP 8.3, ne pod verzijom sa hosta.

**Zabrane**
- Ne menjati podrazumevani Laravel raspored.
- Ne dodavati nijedan paket koji Laravel skeleton ne donosi.
- Ne pisati nijedan Docker artefakat — to je posao talasa 1.

---

### T-01 — `../Dockerfile` (multi-stage build)
**Talas:** 1 · **Vlasništvo:** `../Dockerfile`

**Cilj**
Napisati multi-stage `../Dockerfile` sa četiri stage-a iz ugovora 4.1:

| Stage | Osnova | Izlaz |
|---|---|---|
| `composer-deps` | PHP + Composer (pinovan) | `../vendor` |
| `frontend-assets` | Node (pinovan) | `../public/build` |
| `runtime` | `php:<pin>-fpm-bookworm` | finalni `app` image |
| `web` | Debian (pinovan) + `apache2` | finalni `web` image |

**Obavezno**
- Redosled kopiranja iz `design.md` 5.3: prvo manifest fajlovi → instalacija → **tek onda** izvorni kod. Granice cache-a označiti komentarom.
- `runtime` počinje od čistog PHP baznog image-a i uzima **samo artefakte** iz prethodnih stage-ova. Node, npm i `node_modules` se nikada ne kopiraju.
- PHP ekstenzije po FR-12 + Xdebug (FR-13) + Composer binarni fajl (FR-14), sve pinovano.
- Non-root korisnik `appuser` sa uid/gid iz build argumenata `APP_UID`/`APP_GID`, vlasništvo nad `../storage` i `bootstrap/cache/` (FR-50, FR-51).
- FastCGI klijent za healthcheck `app` servisa (ADR-09).
- `web` stage: `apache2` pinovane verzije, moduli `proxy`, `proxy_fcgi`, `rewrite`, `headers`, kopiranje fajlova po mapiranju 4.3, deaktivacija podrazumevanog Debian sajta.

**Realizuje:** FR-12…FR-14, FR-40…FR-46, FR-50, FR-51, PIN-01, PIN-02, C-01, C-06

**Definicija završenosti**
- Nijedan `FROM` ne koristi `latest` ni goli major tag.
- Nijedna tajna nije u `ENV`/`ARG` koji ostaju u istoriji image-a (FR-55).
- Izmena PHP fajla ne invalidira sloj sa `composer install` (AC-11).

---

### T-02 — Konfiguracija `app` servisa
**Talas:** 1 · **Vlasništvo:** `../docker/app/php.ini`, `docker/app/xdebug.ini`, `docker/app/fpm-pool.conf`, `docker/app/entrypoint.sh`

**Cilj**

| Fajl | Sadržaj |
|---|---|
| `php.ini` | dev override-i; OPcache podešen tako da se izmene fajlova vide odmah (FR-73, rizik R-06) |
| `xdebug.ini` | klijent host i port **isključivo** iz `XDEBUG_MODE`, `XDEBUG_CLIENT_HOST`, `XDEBUG_CLIENT_PORT`, `XDEBUG_IDE_KEY` — nijedna vrednost hardkodovana (FR-60, FR-61) |
| `fpm-pool.conf` | pool radi kao `appuser`, sluša na `0.0.0.0:9000`, uključen ping/status endpoint za healthcheck |
| `entrypoint.sh` | minimalna priprema pri startu |

**`entrypoint.sh` radi tačno ovo i ništa više**
1. Generiše `APP_KEY` ako je prazan (ugovor 4.5, izvedeno iz NFR-02).
2. Osigurava da su `../storage` i `bootstrap/cache/` zapisivi za `appuser`.
3. Predaje kontrolu PHP-FPM procesu.

**Realizuje:** FR-10, FR-11, FR-13, FR-50, FR-60, FR-61, FR-73

**Zabrane**
- **Ne pokretati seed automatski.** `db:seed` nije idempotentan (izmereno 11 → 21 korisnika), pa bi automatsko pokretanje naduvavalo bazu pri svakom restartu. **Migracije su izuzetak i jesu automatske** — v. odluku O-09 i FR-74.
- Ne upisivati nijednu podrazumevanu Xdebug host vrednost u `.ini` — vrednost dolazi iz okruženja.
- Ne dodavati PHP direktive koje nisu potrebne za neki FR.

---

### T-03 — Konfiguracija `web` servisa
**Talas:** 1 · **Vlasništvo:** `../docker/web/vhost.conf`, `docker/web/healthz`

**Cilj**
Apache vhost u Debian rasporedu (ADR-03) koji:
- postavlja `DocumentRoot` na `/var/www/html/public` (FR-22),
- prosleđuje PHP zahteve na `app:9000` preko `proxy_fcgi` (FR-21) — adresa isključivo po imenu servisa, nikada IP (C-03),
- sprovodi Laravel front-controller rewrite: postojeći fajl se servira direktno, sve ostalo ide na `index.php` (FR-23, FR-26),
- zabranjuje pristup svemu izvan `../public` (FR-25),
- izlaže `/healthz` kroz `Alias`, servirano od strane Apache-a bez dodirivanja PHP-a (ADR-09).

**Realizuje:** FR-20…FR-26, FR-06

**Zabrane**
- Ne uvoditi TLS, virtuelne domene ni dodatne module — van opsega po `ai forkflow/spec.md` 2.2.
- Ne menjati putanju `/var/www/html`; razlog je u `design.md` 3.1 i rizik R-07.

---

### T-04 — `../compose.yaml`
**Talas:** 1 · **Vlasništvo:** `../compose.yaml`

**Cilj**
Definisati tri servisa, mrežu, volume-e, healthcheck-ove i zavisnosti.

**Obavezno**
- `web` gradi `web` target, `app` gradi `runtime` target istog `../Dockerfile`-a.
- Portovi: `${WEB_HOST_PORT}:80` i `${DB_HOST_PORT}:3306`. `app` **ne** izlaže port (FR-03).
- Healthcheck po servisu, svaki meri **svoj** servis (ADR-09); `db` ima `start_period` koji pokriva prvu inicijalizaciju (rizik R-05).
- `depends_on` sa `condition: service_healthy`: `app` → `db`, `web` → `app` (FR-07).
- Mount strategija iz `design.md` 9.1: bind mount korena projekta na `/var/www/html`, plus volume overlay na `/var/www/html/vendor` i `/var/www/html/public/build`.
- Host→gateway mapiranje za `app` servis, da `XDEBUG_CLIENT_HOST` radi na Linux/WSL2 (rizik R-03).
- Svi kredencijali kroz `env_file`/`environment`; nijedna vrednost upisana u fajl (C-02).

**Realizuje:** FR-01…FR-08, FR-30, FR-33, FR-70…FR-72, C-02…C-05

**Zabrane**
- Bez `latest`, bez IP adresa, bez mount-a Docker socket-a, bez bind mount-a za `/var/lib/mysql`.
- Ne dodavati servise koje `ai forkflow/spec.md` ne traži (bez Redis-a, queue worker-a, mail catcher-a).

---

### T-05 — `../.env.example` i `.dockerignore`
**Talas:** 1 · **Vlasništvo:** `../.env.example`, `.dockerignore`

**Cilj**

`../.env.example`: sve promenljive iz ugovora 4.4, grupisane, sa kratkim komentarom po grupi i **bezbednim podrazumevanim vrednostima** (FR-62). Nijedan stvarni kredencijal. Za `XDEBUG_CLIENT_HOST` navesti podrazumevanu vrednost uz komentar o razlici između platformi (rizik R-03).

`../.dockerignore`: isključiti najmanje `.git`, `node_modules`, `vendor`, `.env`, `.idea`, `storage/logs`, lokalne artefakte (FR-47). Obe uloge iz `design.md` 5.4 — tačnost cache-a i sprečavanje da `.env` uđe u sloj image-a (FR-56, AC-13).

**Realizuje:** FR-31, FR-36, FR-47, FR-56, FR-62

**Zabrane**
- Ne uvoditi promenljivu koje nema u ugovoru 4.4.
- Ne isključiti `../public` ni `database/` iz build konteksta — potrebni su build-u.

---

### T-06 — Seed sadržaj
**Talas:** 1 · **Vlasništvo:** `../database/seeders/DatabaseSeeder.php`, `database/factories/UserFactory.php`

**Cilj**
Implementirati seed po FR-34…FR-36:
- jedan **fiksni "sidro" zapis** u `users` tabeli sa unapred poznatim, konstantnim e-mailom — da AC-07 i AC-08 imaju determinističku vrednost za upit,
- uz njega određen broj zapisa iz factory-ja,
- lozinka seed korisnika se čita iz `SEED_USER_PASSWORD` i **nigde se ne hardkoduje** (FR-36, C-02),
- seed mora biti idempotentan u meri u kojoj to Laravel podrazumevano omogućava, da ponovljeno pokretanje ne puca na duplikatu.

**Realizuje:** FR-34…FR-36, podržava AC-06, AC-07, AC-08

**Zabrane**
- Ne kreirati nove migracije ni modele — koristi se podrazumevana Laravel `users` tabela.
- Ne upisivati lozinku u kod ni u `../.env.example` kao stvarnu vrednost.

---

### T-07 — `../README.md`
**Talas:** 2 · **Vlasništvo:** `../README.md` · **Ulaz:** izmerene vrednosti od glavnog agenta

**Cilj**
Dokumentovati sve iz FR-80…FR-87:

| # | Sadržaj |
|---|---|
| 1 | Preduslovi i setup iz čistog klona |
| 2 | Start/stop komande (`up -d --build`, `down`, `down -v`, `logs`, `ps`) |
| 3 | Servisi, portovi, uloge |
| 4 | Migracije i seed |
| 5 | Xdebug: env varijable, IDE podešavanje, **path mapping**, razlika Linux/WSL2 vs Docker Desktop |
| 6 | Persistencija: šta preživljava `down`, šta briše `down -v` |
| 7 | Sve pinovane verzije iz tabele 4.6 + komande kojima su verifikovane |
| 8 | Izmerena veličina finalnog image-a i **tri najveća sloja** + komanda merenja |

**Obavezno dokumentovati i zamku R-04:** posle izmene `../composer.json` volume `app-vendor` zadržava stari sadržaj i mora se ukloniti. Bez ovoga developer dobija zbunjujuće "zavisnost je instalirana ali je nema".

**Realizuje:** FR-80…FR-87, AC-14

**Zabrane**
- Ne upisivati nijednu verziju ili meru koja nije stvarno izmerena. Prepisivanje iz dokumentacije umesto merenja obara AC-14.

---

## 7. Obavezan završni ciklus svakog subagenta

Nijedan task nije završen bez ova tri prolaza **nad sopstvenim radom**, redom.

### 7.1 Code review
- Svaki FR naveden u tasku je stvarno realizovan — proći listu, jedan po jedan.
- Nema hardkodovanih tajni, lozinki ni IP adresa (C-02, C-03).
- Nema `latest` ni golog major taga (C-01).
- Nema Docker socket mount-a (C-04), nema bind mount-a za MySQL (C-05).
- Sve adrese su imena servisa iz ugovora 4.1.
- Nema odstupanja od ugovora iz sekcije 4.

### 7.2 Simplify
- Nema duplirane konfiguracije — ista direktiva ili promenljiva na dva mesta.
- Nema mrtve konfiguracije: direktive, paketi ili promenljive koje ne služe nijednom FR-u se brišu.
- Nema zakomentarisanog koda ni ostataka eksperimentisanja.
- Komentari objašnjavaju **zašto**, ne **šta** — posebno na granicama cache-a i na mestima koja `design.md` označava kao zamke.
- Nema preterane apstrakcije: ako se nešto koristi jednom, ne uvodi se sloj indirekcije.

### 7.3 Performance review
- **Slojevi:** povezane `RUN` komande spojene; `apt` keš i privremeni fajlovi brišu se **u istom sloju** u kome su nastali, inače ostaju u istoriji image-a.
- **Cache:** redosled `COPY` naredbi poštuje granice iz `design.md` 5.3; ništa promenljivo ne stoji ispred nečega skupog.
- **Veličina:** nema instaliranih paketa koje nijedan FR ne traži; build alati ne cure u runtime stage.
- **Runtime:** healthcheck intervali su razumni — dovoljno česti da `depends_on` radi, dovoljno retki da ne troše resurse.

### 7.4 Izveštaj glavnom agentu
Svaki subagent na kraju vraća:
1. spisak fajlova koje je napisao,
2. mapiranje: koji FR/AC je pokriven i gde,
3. **šta je pronašao u sopstvenom reviewu i šta je zbog toga promenio**,
4. sve što je ostalo nejasno — kao pitanje, ne kao pretpostavku.

---

## 8. Integraciona verifikacija (glavni agent, talas 2)

| AC | Provera | Ko |
|---|---|---|
| AC-01 | `curl -I http://localhost:8080` → 200 | glavni agent |
| AC-02 | `php -v` u `app` → PHP 8.3.x | glavni agent |
| AC-03 | `phpinfo()` → `Server API = FPM/FastCGI` | glavni agent |
| AC-04 | `composer --version` → pinovana verzija | glavni agent |
| AC-05 | Node odsutan; `node_modules` ne postoji u image-u | glavni agent |
| AC-06 | `php artisan migrate --seed` → exit 0, sidro zapis postoji | glavni agent |
| AC-07 | `down` → `up -d` → sidro zapis i dalje postoji | glavni agent |
| AC-08 | `down -v` → `up -d` → baza prazna | glavni agent |
| AC-09 | DBGp konekcija iz kontejnera ka listeneru na 9003 | glavni agent (automatski) + **naručilac potvrđuje breakpoint u IDE-u** |
| AC-10 | izmena PHP fajla vidljiva bez rebuild-a | glavni agent |
| AC-11 | `composer install` korak `CACHED` posle izmene samo PHP koda | glavni agent |
| AC-12 | `id` u `app` → uid ≠ 0 | glavni agent |
| AC-13 | `docker image history --no-trunc` bez tajni | glavni agent |
| AC-14 | veličina image-a + tri najveća sloja izmereni | glavni agent → prosleđuje T-07 |
| AC-15 | samo imena servisa, nigde IP | glavni agent |
| AC-16 | `docker compose ps` → svi `healthy` | glavni agent |
| AC-17 | nema zabranjenih obrazaca u artefaktima | glavni agent |

Padne li bilo koji AC, ispravka ide **nazad vlasniku fajla**, ne u tuđi fajl. Ovo čuva isključivo vlasništvo i sprečava da se ispravke međusobno pregaze.

---

## 9. Finalni pregled glavnog agenta (talas 3)

Posle integracije, glavni agent radi ista tri prolaza — ali nad **celim repozitorijumom**, tražeći ono što pojedinačni agent po definiciji ne može videti:

**Simplify.** Preklapanje između fajlova različitih vlasnika: ista promenljiva definisana i u `../compose.yaml` i u `.env.example` bez razloga, ista PHP direktiva i u `php.ini` i u `fpm-pool.conf`, konfiguracija koja duplira Docker podrazumevanu vrednost.

**Code review.** Celovitost: prolaz kroz traceability matricu iz `ai forkflow/spec.md` sekcije 10 i proveru da svaki red ima stvarnu realizaciju. Konzistentnost ugovora iz sekcije 4 kroz sve fajlove. Usklađenost sa svakim ADR-om iz `design.md` sekcije 11.

**Performance review.** Ukupna veličina image-a i raspodela slojeva. Ponašanje cache-a na tri scenarija: izmena samo PHP koda, izmena `../composer.json`, izmena `package.json`. Vreme od `up -d` do svih `healthy`.

---

## 10. Eskalacija

```
  subagent naiđe na nejasnoću
        │
        ▼
  ZAUSTAVLJA rad na tom delu, ne nagađa
        │
        ▼
  prijavljuje glavnom agentu
        │
        ├── odgovor postoji u spec.md / design.md → glavni agent citira izvor
        │
        └── odgovor ne postoji → glavni agent pita NARUČIOCA
                                  odluka se upisuje u sekciju 11
```

---

## 11. Dnevnik odluka ove faze

| ID | Odluka | Obrazloženje |
|---|---|---|
| O-01 | `web` image se gradi kao **dodatni `web` target u istom `../Dockerfile`-u** | Deliverables u `ai forkflow/spec.md` navode `../Dockerfile` u jednini, a `design.md` sekcija 6 prikazuje samo jedan u korenu. Potvrđeno od naručioca. |
| O-02 | AC-09 se verifikuje **automatskom DBGp proverom**, uz finalnu potvrdu naručioca u IDE-u | Subagent nema IDE da klikne breakpoint; automatska provera dokazuje ceo lanac do IDE-a. Potvrđeno od naručioca. |
| O-03 | Apache loguje na `stdout`/`stderr`, `/healthz` izuzet iz access loga | FR-81 traži dokumentovan `docker compose logs`, bez ovoga prazna komanda. Potvrđeno od naručioca. |
| O-04 | Apache modul `headers` se ne uključuje | Nijedan FR ne proizvodi `Header` direktivu; svesno odstupanje od `design.md` 4.1. Potvrđeno od naručioca. |
| O-05 | `curl` se instalira u `web` stage | `debian:12.15-slim` nema HTTP klijent, a FR-06 traži healthcheck; bez toga `web` nikad nije `healthy`. |
| O-06 | FPM ping ruta je `/ping` | Koordinacija T-02 ↔ T-04; ugovor 4.2 je imenovao samo `web` rutu. |
| O-07 | `127.0.0.1` u `app` healthcheck-u ne krši C-03 | Loopback unutar sopstvenog kontejnera nije adresiranje servisa. |
| O-08 | `display_errors` se ne dodaje u `php.ini` | Nijedan FR ga ne traži; `APP_DEBUG` pokriva potrebu. |
| O-09 | **Migracije se pokreću automatski pri svakom startu `app` kontejnera; seed ostaje ručan** | Bez migracija sveže podignut stack vraća HTTP 500 (Laravel drži sesije u bazi). `migrate` je idempotentan (`Nothing to migrate`), `db:seed` nije — izmereno 11 → 21 korisnika pri ponovnom pokretanju. **Potvrđeno od naručioca kao konačna odluka**; `ai forkflow/spec.md` ažuriran (UC-01, UC-03, FR-74, AC-06). |
| O-10 | `xdebug.start_with_request = yes` | Xdebug log dokazao da se `default` ponaša kao trigger mod i bez `XDEBUG_SESSION` uopšte ne konektuje. FR-61 postavlja `XDEBUG_MODE` kao prekidač, pa dodatni trigger protivreči zahtevu. Potvrđeno od naručioca. |

---

## 12. Otvorena pitanja

Nema otvorenih pitanja. Obe dileme ove faze zatvorene su kao O-01 i O-02.

Nove nejasnoće se upisuju ovde kroz postupak iz sekcije 10 — ne rešavaju se pretpostavkom.
