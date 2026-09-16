# spec.md — Docker development okruženje za Laravel aplikaciju

> Status: **Specifikacija (faza 1)** — zaključavanje zahteva pre implementacije.
> Ovaj dokument ne sadrži kod niti implementaciju; on definiše šta se gradi i kako se dokazuje da je urađeno.

---

## 1. Pregled i cilj

Cilj je Docker-based development okruženje koje se iz **čistog klona repozitorijuma** podiže jednom komandom:

```bash
docker compose up -d --build
```

Okruženje pokreće Laravel aplikaciju kroz tri odvojena kontejnera (`web`, `app`, `db`), sa PHP 8.3 preko FPM-a, Apache-jem 2.4 kao reverse proxy/web serverom i MySQL bazom čiji podaci žive u imenovanom Docker volume-u.

**Polazna tačka:** projekat je prazan (greenfield). Laravel aplikacija se kreira kao deo ovog zadatka; ne postoji zatečeni aplikativni kod.

---

## 2. Opseg

### 2.1 U opsegu
- `../Dockerfile` (multi-stage), `compose.yaml`, `.env.example`, `.dockerignore`, `README.md`, Apache vhost konfiguracija.
- Scaffold Laravel aplikacije (`composer create-project`) dovoljan da sve acceptance criteria budu proverljive — uključujući `../public`, `artisan`, podrazumevane migracije i seeder-e, `package.json` + Vite konfiguraciju.
- Lokalni development workflow: hot izmene PHP koda, Xdebug debugging, migracije i seed-ovi.

### 2.2 Van opsega
- Produkcijski deployment, orkestracija van Docker Compose-a (Kubernetes, Swarm).
- CI/CD pipeline.
- Poslovna logika aplikacije (aplikacija je nosilac za verifikaciju okruženja, ne proizvod sam po sebi).
- HTTPS/TLS terminacija, sertifikati, domen konfiguracija.
- Mail, queue worker, cache (Redis) i sličan dodatni infrastrukturni sloj — nije traženo taskom.

---

## 3. Verzije i pinovanje

### 3.1 Verzije zadate taskom (obavezne, nisu predmet izbora)

| Komponenta | Zahtevana vrednost |
|---|---|
| PHP | 8.3, izvršava se kroz FPM |
| Apache HTTP Server | 2.4 |
| Web port (host:kontejner) | `8080:80` |
| MySQL port (host:kontejner) | `3307:3306` |
| FastCGI endpoint | `app:9000` |
| Frontend toolchain | Node.js + npm, Vite (Laravel default) |

### 3.2 Verzije koje biramo (task ih ne propisuje) — **potvrđeno od naručioca**

| Komponenta | Izabrana verzija | Obrazloženje |
|---|---|---|
| MySQL | 8.4.x (LTS linija) | aktuelna LTS linija, dugoročna podrška |
| Node.js | 24.x (Active LTS) | zahtev taska je "currently supported pinned Node version" |
| Composer | 2.8.x | stabilna 2.x linija |
| Laravel | 12.x | zvanično podržava PHP 8.3 |
| Base OS slojeva | Debian `bookworm` varijante | konzistentnost između build i runtime stage-ova |

### 3.3 Pravila pinovanja
- **PIN-01** Svaki `FROM` koristi eksplicitan tag sa punom `major.minor.patch` verzijom (npr. `php:8.3.x-fpm-bookworm`), nikada `latest` i nikada goli `major` tag.
- **PIN-02** Verzije alata koji se instaliraju unutar image-a (Composer, Xdebug, Node paketi preko lock fajla) takođe se pinuju na tačnu verziju.
- **PIN-03** Tačni patch tagovi se fiksiraju i verifikuju (`docker pull` + provera u kontejneru) na početku faze implementacije, a stvarno verifikovane vrednosti se upisuju u `../README.md`.
- **PIN-04** `../composer.lock` i `package-lock.json` se commit-uju u repozitorijum i koriste se za deterministički install (`composer install`, `npm ci`).

---

## 4. Use cases

### UC-01 — Prvo podizanje okruženja iz čistog klona
- **Akter:** developer
- **Preduslov:** instaliran Docker Engine i Docker Compose v2; repozitorijum sveže kloniran; nema prethodnih volume-a.
- **Koraci:** `cp .env.example .env` → `docker compose up -d --build`
- **Očekivani ishod:** sva tri servisa su podignuta i zdrava; migracije su automatski primenjene (FR-74); aplikacija odgovara na `http://localhost:8080` bez ijednog dodatnog ručnog koraka.

### UC-02 — Izmena PHP izvornog koda tokom rada
- **Akter:** developer
- **Preduslov:** okruženje je podignuto.
- **Koraci:** izmeni PHP fajl u izvornom kodu (npr. controller ili Blade template) → osveži stranicu u browseru.
- **Očekivani ishod:** izmena je odmah vidljiva, bez `docker compose build` i bez restarta kontejnera.

### UC-03 — Migracije i seed
- **Akter:** developer (seed), okruženje (migracije)
- **Preduslov:** `db` servis je zdrav.

**Migracije — automatske.** Primenjuju se pri svakom startu `app` kontejnera (FR-74), bez radnje developera. Razlog: Laravel podrazumevano čuva sesije u bazi, pa aplikacija bez migrirane šeme vraća HTTP 500 i UC-01 ne bi mogao da se ispuni. Operacija je idempotentna — ponovljeno pokretanje prijavljuje `Nothing to migrate`.

**Seed — ručni i svestan.** `docker compose exec app php artisan db:seed`
- **Očekivani ishod:** komanda se završava uspešno (exit code 0); seed upisuje poznat, deterministički skup zapisa (v. FR-34…FR-36) koji se može proveriti upitom.
- **Zašto ostaje ručan:** seed **nije** idempotentan u delu sa factory zapisima — svako pokretanje dodaje nove korisnike (izmereno: 11 → 21). Automatsko pokretanje pri svakom startu naduvavalo bi bazu i obesmislilo AC-06.

### UC-04 — Debug sesija sa Xdebug breakpoint-om
- **Akter:** developer sa IDE-om (PhpStorm / VS Code)
- **Preduslov:** `XDEBUG_MODE`, `XDEBUG_CLIENT_HOST` i `XDEBUG_CLIENT_PORT` postavljeni u `../.env`; IDE sluša na tom portu; path mapping podešen prema uputstvu iz README-a.
- **Koraci:** postavi breakpoint → otvori stranicu na `localhost:8080`.
- **Očekivani ishod:** izvršavanje se zaustavlja na breakpoint-u; vidljiv je stack i vrednosti promenljivih.

### UC-05 — Izmena zavisnosti (Composer ili npm)
- **Akter:** developer
- **Koraci:** izmeni `../composer.json`/`composer.lock` ili `package.json`/`package-lock.json` → `docker compose up -d --build`
- **Očekivani ishod:** odgovarajući build stage se ponovo izvršava i nove zavisnosti su prisutne; nepovezani stage-ovi ostaju keširani.

### UC-06 — Gašenje i ponovno podizanje uz očuvanje podataka
- **Akter:** developer
- **Koraci:** `docker compose down` → `docker compose up -d`
- **Očekivani ishod:** podaci iz baze (migracije i seed-ovi) su i dalje prisutni.

### UC-07 — Potpuni reset okruženja
- **Akter:** developer
- **Koraci:** `docker compose down -v`
- **Očekivani ishod:** imenovani volume sa MySQL podacima je obrisan; naredni `up` startuje praznu bazu.

### UC-08 — Pristup bazi sa host mašine
- **Akter:** developer / DB klijent (npr. DataGrip, `mysql` CLI)
- **Koraci:** konekcija na `127.0.0.1:3307` sa kredencijalima iz `../.env`.
- **Očekivani ishod:** uspešna konekcija i pregled šeme.

### UC-09 — Provera zdravlja i redosleda startovanja servisa
- **Akter:** developer
- **Koraci:** `docker compose ps`
- **Očekivani ishod:** svi servisi prikazuju health status; `app` čeka da `db` bude zdrav, `web` čeka da `app` bude zdrav.

---

## 5. Funkcionalni zahtevi

### 5.1 Orkestracija i topologija servisa

- **FR-01** Okruženje se sastoji od tačno tri servisa: `web`, `app`, `db`.
- **FR-02** `web` servis je Apache 2.4 i izlaže port `8080:80` na host.
- **FR-03** `app` servis je PHP 8.3 FPM i **ne** izlaže port na host (dostupan je samo unutar Compose mreže).
- **FR-04** `db` servis je MySQL i izlaže port `3307:3306` na host.
- **FR-05** Međusobna komunikacija servisa ide isključivo preko Compose service name-a (`app`, `db`); nijedna IP adresa se ne hardkoduje.
- **FR-06** Svaki servis ima definisan `healthcheck` (`interval`, `timeout`, `retries`, `start_period`).
- **FR-07** Zavisnosti servisa su izražene kroz `depends_on` sa `condition: service_healthy`: `app` zavisi od `db`, `web` zavisi od `app`.
- **FR-08** Sva tri servisa su na zajedničkoj Compose mreži.

### 5.2 PHP runtime (`app`)

- **FR-10** PHP je verzije 8.3 i izvršava se kroz PHP-FPM (SAPI `FPM/FastCGI`).
- **FR-11** PHP-FPM sluša na TCP portu `9000` unutar kontejnera.
- **FR-12** Instalirane su PHP ekstenzije neophodne za Laravel: `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`, `opcache`, `gd`, `exif`, `pcntl`, plus one koje Laravel 12 skeleton zahteva.
- **FR-13** **Xdebug** je instaliran i aktivan u razvojnom režimu, sa pinovanom verzijom.
- **FR-14** Composer je prisutan u `app` runtime-u sa pinovanom verzijom, tako da `composer --version` i `php artisan` rade unutar kontejnera.
- **FR-15** Aplikativni radni direktorijum unutar kontejnera je konzistentan između `app` i `web` servisa (isti apsolutni put), da bi Apache i FPM razrešavali `SCRIPT_FILENAME` na isti fajl.

### 5.3 Web sloj (`web`)

- **FR-20** Apache 2.4 ima učitane module `proxy` i `proxy_fcgi`.
- **FR-21** Vhost prosleđuje PHP zahteve na `proxy:fcgi://app:9000` (npr. kroz `<FilesMatch \.php$>` + `SetHandler`).
- **FR-22** `DocumentRoot` je direktorijum `../public` Laravel aplikacije.
- **FR-23** Vhost sadrži Laravel front-controller rewrite pravila (svi ne-fajl zahtevi idu na `index.php`).
- **FR-24** Vhost je isporučen kao zaseban konfiguracioni fajl u repozitorijumu i kopira se u image (nije inline u `../Dockerfile`-u).
- **FR-25** Pristup direktorijumima izvan `../public` je zabranjen kroz Apache konfiguraciju.
- **FR-26** Apache ima pristup statičkim fajlovima iz `../public` (uključujući Vite build izlaz), tako da ih servira direktno bez prolaska kroz FPM.

### 5.4 Baza podataka (`db`)

- **FR-30** MySQL podaci se čuvaju u **imenovanom Docker volume-u**; bind mount za MySQL data direktorijum je zabranjen.
- **FR-31** MySQL kredencijali (root lozinka, ime baze, korisnik, lozinka) dolaze isključivo iz environment varijabli definisanih u `../.env`.
- **FR-32** `healthcheck` za `db` proverava stvarnu spremnost servera (npr. `mysqladmin ping`), ne samo da proces postoji.
- **FR-33** Laravel `DB_HOST` je `db` (service name), `DB_PORT` je `3306` (interni port), dok je `3307` samo host-side mapiranje.
- **FR-34** Repozitorijum sadrži seed sadržaj preko Laravel `DatabaseSeeder`-a; oslanjanje isključivo na prazan podrazumevani seeder nije dovoljno.
- **FR-35** Seed upisuje **deterministički** prepoznatljiv skup zapisa u podrazumevanu `users` tabelu: jedan fiksni "sidro" zapis sa unapred poznatim `email`-om (konstanta definisana u seeder-u, ne nasumična) plus određen broj zapisa generisanih kroz factory. Fiksni zapis postoji da bi AC-07 i AC-08 mogli da se provere jednim determinističkim SQL upitom.
- **FR-36** Lozinka seed korisnika **nije hardkodovana** u kodu — čita se iz environment varijable (`SEED_USER_PASSWORD`) koja je deklarisana u `../.env.example`, u skladu sa C-02.

### 5.5 Docker build (multi-stage)

- **FR-40** `../Dockerfile` koristi multi-stage build sa tri jasno odvojena stage-a:
  1. **Composer dependencies stage** — instalira PHP zavisnosti,
  2. **Frontend assets stage** — Node.js + npm + Vite build,
  3. **Runtime stage** — finalni PHP 8.3 FPM image.
- **FR-41** Node.js postoji **isključivo** u frontend build stage-u.
- **FR-42** U finalnom runtime image-u ne postoji ni Node.js binary ni `node_modules` direktorijum.
- **FR-43** Node verzija je pinovana i pripada trenutno podržanoj (supported) liniji.
- **FR-44** Composer stage prvo kopira samo `../composer.json` i `composer.lock`, pa tek onda izvršava install — da bi sloj sa zavisnostima ostao keširan kada se menja samo aplikativni izvorni kod.
- **FR-45** Frontend stage prvo kopira samo `../package.json` i `package-lock.json`, pa izvršava `npm ci`, i tek onda kopira frontend izvorne fajlove (`resources/`, `vite.config.js`) i pokreće build.
- **FR-46** Runtime stage kopira samo build artefakte iz prethodnih stage-ova (`../vendor`, kompajlirani asseti u `public/build`), ne i njihove build alate.
- **FR-47** `../.dockerignore` isključuje iz build konteksta najmanje: `.git`, `node_modules`, `vendor`, `.env`, `.idea`, `storage/logs`, lokalne artefakte i sve tajne.

### 5.6 Bezbednost i runtime korisnik

- **FR-50** Aplikacija u `app` kontejneru se izvršava kao **non-root** korisnik; PHP-FPM master i worker procesi ne rade kao `root`.
- **FR-51** Vlasništvo nad zapisivim Laravel direktorijumima (`../storage`, `bootstrap/cache/`) odgovara tom non-root korisniku.
- **FR-52** Nijedna lozinka, API ključ ili token nije hardkodovan u `../Dockerfile`, `compose.yaml` ili vhost konfiguraciji.
- **FR-53** Nijedna IP adresa nije hardkodovana; adresiranje ide preko service name-ova i env varijabli.
- **FR-54** Docker socket (`/var/run/docker.sock`) se ne mount-uje ni u jedan kontejner.
- **FR-55** Tajne ne smeju završiti u slojevima image-a — ne prosleđuju se kroz `ENV`/`ARG` koji ostaju u istoriji image-a, niti se kopiraju u image.
- **FR-56** `../.env` se ne kopira u image; u kontejner ulazi u vreme izvršavanja (kroz Compose `env_file`/`environment` i bind mount izvornog koda).

### 5.7 Xdebug konfiguracija

- **FR-60** Xdebug `client_host` i `client_port` se konfigurišu kroz environment varijable (`XDEBUG_CLIENT_HOST`, `XDEBUG_CLIENT_PORT`), bez hardkodovanih vrednosti u image-u.
- **FR-61** Xdebug režim rada se kontroliše kroz `XDEBUG_MODE` (npr. `off`, `debug`, `develop,debug`), tako da se debug može isključiti bez rebuild-a. `XDEBUG_MODE` je **jedini** prekidač: debugger se aktivira na svaki zahtev dok je mod `debug`, bez dodatnog trigger-a (cookie, GET/POST parametar ili `XDEBUG_SESSION`).
- **FR-62** `../.env.example` sadrži ove varijable sa podrazumevanim, bezbednim vrednostima i komentarima.
- **FR-63** README dokumentuje IDE podešavanje: port, `idekey`, path mapping (kontejnerski put ↔ lokalni put) i napomenu o razrešavanju host adrese na Linux/WSL2 i Docker Desktop okruženjima.

### 5.8 Development workflow

- **FR-70** Izvorni kod aplikacije je bind-mount-ovan u `app` kontejner, tako da su izmene PHP fajlova vidljive bez rebuild-a.
- **FR-71** `web` kontejner ima pristup istom `../public` sadržaju, tako da su promene statičkih fajlova takođe odmah vidljive.
- **FR-72** `../vendor` i build asseti iz image-a ne smeju biti "pregaženi" bind mount-om na način koji razbija prvo podizanje — ponašanje mora biti eksplicitno definisano i dokumentovano.
- **FR-73** OPcache konfiguracija u dev režimu ne sme keširati PHP fajlove tako da izmene nisu vidljive (validacija timestamp-ova uključena ili OPcache isključen u dev-u).
- **FR-74** Migracije baze se primenjuju automatski pri svakom startu `app` kontejnera, pre nego što servis počne da prima saobraćaj. Seed se **ne** pokreće automatski (v. UC-03). Otkaz migracija ne sme proći tiho.

### 5.9 Dokumentacija (`../README.md`)

- **FR-80** README dokumentuje preduslove i setup korake iz čistog klona.
- **FR-81** README dokumentuje start/stop komande (`up -d --build`, `down`, `down -v`, `logs`, `ps`).
- **FR-82** README dokumentuje listu servisa sa portovima i njihovom ulogom.
- **FR-83** README dokumentuje pokretanje migracija i seed-ova.
- **FR-84** README dokumentuje Xdebug setup na strani IDE-a i env varijabli.
- **FR-85** README dokumentuje ponašanje persistencije podataka (`down` čuva, `down -v` briše).
- **FR-86** README dokumentuje **sve pinovane i stvarno verifikovane verzije** (PHP, Apache, MySQL, Node, Composer, Laravel, Xdebug), zajedno sa komandama kojima su verifikovane.
- **FR-87** README dokumentuje veličinu finalnog image-a i **tri najveća sloja**, sa komandom kojom su izmereni.

---

## 6. Nefunkcionalni zahtevi

- **NFR-01 Reproducibilnost:** isti klon + isti lock fajlovi + isti pinovani tagovi daju funkcionalno isto okruženje na drugoj mašini.
- **NFR-02 Jedna komanda za start:** ceo stack se podiže sa `docker compose up -d --build`, bez ručnih koraka posle kopiranja `../.env`.
- **NFR-03 Efikasan rebuild:** izmena isključivo PHP izvornog koda ne sme invalidirati Composer install sloj niti frontend `npm ci` sloj.
- **NFR-04 Veličina image-a:** finalni runtime image ne sadrži build alate (Node, npm, dev toolchain koji nije potreban u runtime-u); veličina se meri i dokumentuje.
- **NFR-05 Čitljivost:** konfiguracija je razdvojena po fajlovima (vhost, `../.env.example`, `compose.yaml`) i komentarisana gde nije očigledna.
- **NFR-06 Prenosivost:** okruženje radi na Linux hostu i pod WSL2; mesta gde se ponašanje razlikuje (npr. Xdebug host) su dokumentovana.

---

## 7. Ograničenja i zabrane

Doslovno iz taska, obavezno:

- **C-01** Zabranjen `latest` tag za bilo koji image ili alat.
- **C-02** Zabranjene hardkodovane lozinke.
- **C-03** Zabranjene hardkodovane IP adrese.
- **C-04** Zabranjen mount Docker socket-a.
- **C-05** Zabranjen bind mount za MySQL podatke (mora imenovani volume).
- **C-06** Node.js sme postojati isključivo u build stage-u, nikada u runtime image-u.
- **C-07** Proces aplikacije ne sme raditi kao `root`.

---

## 8. Deliverables

| Fajl | Svrha |
|---|---|
| `../Dockerfile` | multi-stage build (composer deps / frontend assets / runtime) |
| `../compose.yaml` | definicija servisa `web`, `app`, `db`, mreže, volume-a, healthcheck-ova |
| `../.env.example` | šablon environment varijabli bez stvarnih tajni |
| `../.dockerignore` | kontrola build konteksta |
| `../README.md` | dokumentacija prema FR-80…FR-87 |
| Apache vhost konfiguracija | zaseban fajl sa `DocumentRoot` na `../public` i `proxy_fcgi` na `app:9000` |
| Laravel aplikacija (scaffold) | nosilac za verifikaciju acceptance criteria |

---

## 9. Acceptance criteria

Svaki kriterijum ima konkretnu komandu/postupak verifikacije i očekivani rezultat. Svi se izvršavaju nad okruženjem podignutim iz čistog klona.

| ID | Kriterijum | Verifikacija | Očekivano |
|---|---|---|---|
| **AC-01** | Aplikacija je dostupna na `localhost:8080` | `curl -I http://localhost:8080` | HTTP `200` i Laravel welcome stranica u browseru |
| **AC-02** | PHP je 8.3 | `docker compose exec app php -v` | `PHP 8.3.x` |
| **AC-03** | PHP radi kao FPM/FastCGI | `phpinfo()` kroz browser na `localhost:8080` | `Server API` = `FPM/FastCGI` |
| **AC-04** | Composer je pinovane verzije | `docker compose exec app composer --version` | tačno verzija navedena u README-u |
| **AC-05** | Node nije prisutan u runtime image-u | `docker compose exec app sh -c 'command -v node \|\| echo ABSENT'` i provera da `node_modules` ne postoji u image-u | `ABSENT`; `node_modules` ne postoji |
| **AC-06** | Migracije i seed-ovi prolaze | migracije automatski pri startu (FR-74), pa `docker compose exec app php artisan db:seed` | exit code `0`, bez grešaka; sidro zapis iz FR-35 postoji u `users` |
| **AC-07** | Podaci preživljavaju `down` | `docker compose down` → `docker compose up -d` → `php artisan tinker` / SQL upit za sidro zapis iz FR-35 | zapis i dalje postoji, bez ponovnog pokretanja migracija |
| **AC-08** | Podaci se brišu sa `down -v` | `docker compose down -v` → `docker compose up -d` → isti upit kao u AC-07 | baza je prazna: `users` ne sadrži nijedan zapis, ni sidro. Tabele postoje jer FR-74 ponovo primenjuje migracije na prazan volume — dokaz brisanja su **podaci**, ne šema |
| **AC-09** | Xdebug breakpoint radi | breakpoint u IDE-u + zahtev na `localhost:8080` | izvršavanje se zaustavlja na breakpoint-u |
| **AC-10** | PHP izmene vidljive bez rebuild-a | izmeni PHP fajl → osveži stranicu | izmena vidljiva, bez `build` i bez restarta |
| **AC-11** | Composer install sloj ostaje keširan | izmeni samo PHP fajl → `docker compose build` | u izlazu build-a Composer install korak je `CACHED` |
| **AC-12** | Aplikacija radi kao non-root | `docker compose exec app id` i `docker compose exec app ps -o user= -p 1` | uid ≠ 0, korisnik ≠ `root` |
| **AC-13** | Nema tajni u istoriji image-a | `docker image history --no-trunc <image>` + pretraga za lozinkama/ključevima | nijedan pogodak |
| **AC-14** | Veličina image-a i tri najveća sloja dokumentovani | `docker image ls` i `docker image history` | vrednosti upisane u README |
| **AC-15** | Servis-to-servis komunikacija po imenu | pregled `../compose.yaml` i vhost-a | koriste se `app:9000` i `db`, nijedna IP adresa |
| **AC-16** | Health checks i zavisnosti rade | `docker compose ps` posle `up -d` | svi servisi `healthy`; startovanje poštuje `depends_on` redosled |
| **AC-17** | Nema zabranjenih obrazaca | pregled `../Dockerfile`, `compose.yaml`, vhost-a | nema `latest`, hardkodovanih lozinki/IP-jeva, docker socket mount-a, bind mount-a za MySQL |

---

## 10. Traceability matrica (zahtev iz taska → FR → AC)

| Zahtev iz taska | FR | AC |
|---|---|---|
| Odvojeni kontejneri web/app/db | FR-01…FR-04 | AC-16 |
| web: Apache 2.4 na `8080:80` | FR-02, FR-20 | AC-01 |
| app: PHP 8.3 FPM | FR-03, FR-10, FR-11 | AC-02, AC-03 |
| db: MySQL na `3307:3306` | FR-04, FR-30…FR-33 | AC-06, AC-07 |
| PHP 8.3 kroz FPM/FastCGI | FR-10, FR-11 | AC-02, AC-03 |
| Potrebne PHP ekstenzije uključujući Xdebug | FR-12, FR-13 | AC-06, AC-09 |
| Apache `proxy_fcgi` → `app:9000` | FR-20, FR-21 | AC-01, AC-15 |
| `../public` kao DocumentRoot | FR-22, FR-23, FR-26 | AC-01 |
| MySQL podaci u imenovanom volume-u | FR-30, C-05 | AC-07, AC-08 |
| `php artisan migrate --seed` uspeva (seed sadržaj) | FR-34…FR-36 | AC-06, AC-07, AC-08 |
| Automatske migracije pri startu kontejnera | FR-74 | AC-01, AC-06 |
| Xdebug host i port kroz env varijable | FR-60…FR-63 | AC-09 |
| Non-root runtime korisnik | FR-50, FR-51, C-07 | AC-12 |
| Health checks i service dependencies | FR-06, FR-07 | AC-16 |
| Komunikacija po Compose service name-u | FR-05, FR-33 | AC-15 |
| Multi-stage build (composer / frontend / runtime) | FR-40 | AC-04, AC-05 |
| Node samo u build stage-u, pinovan, odsutan u runtime-u | FR-41…FR-43, C-06 | AC-05 |
| Layer caching za Composer i frontend zavisnosti | FR-44, FR-45, NFR-03 | AC-11 |
| Sve verzije eksplicitno pinovane | PIN-01…PIN-04, C-01 | AC-02, AC-04, AC-17 |
| Bez `latest`, lozinki, IP-jeva, docker socket-a, bind mount-a za DB | C-01…C-05, FR-52…FR-56 | AC-13, AC-17 |
| README sadržaj | FR-80…FR-87 | AC-14 |
| PHP izmene bez rebuild-a | FR-70…FR-73 | AC-10 |
| Veličina image-a i tri najveća sloja | FR-87, NFR-04 | AC-14 |

---

## 11. Otvorena pitanja

| ID | Pitanje | Status | Odluka |
|---|---|---|---|
| Q-01 | Potvrda izabranih verzija iz sekcije 3.2 (MySQL 8.4, Node 24, Composer 2.8, Laravel 12) | **zatvoreno** (2026-09-15) | Naručilac potvrdio predložene verzije; sekcija 3.2 je obavezujuća. |
| Q-02 | Da li je potreban dodatni seed sadržaj preko podrazumevanog `DatabaseSeeder`-a | **zatvoreno** (2026-09-15) | Seed sadržaj se dodaje; formalizovano kroz FR-34…FR-36 i AC-06…AC-08. |

Nove nejasnoće se upisuju ovde i razrešavaju sa naručiocem — ne rešavaju se pretpostavkom.
