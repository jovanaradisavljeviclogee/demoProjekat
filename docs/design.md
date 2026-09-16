# design.md — Arhitektura Docker development okruženja

> Status: **Arhitektura (faza 2)** — opis kako je sistem sastavljen, pre pisanja koda.
> Ovaj dokument **ne sadrži implementaciju**: nema sadržaja `../Dockerfile`-a, `compose.yaml`-a ni vhost konfiguracije. Imenuje komponente, definiše odgovornosti, opisuje tokove i obrazlaže odluke.

---

## 1. Uvod i odnos prema `ai forkflow/spec.md`

`ai forkflow/spec.md` odgovara na pitanje **šta** se gradi (UC-01…UC-09, FR-01…FR-87, C-01…C-07) i **kako se dokazuje** da je urađeno (AC-01…AC-17).

`design.md` odgovara na pitanje **kako je sistem sastavljen** — koje komponente postoje, koja je čija odgovornost, kako teče zahtev, kako je organizovan build, gde žive podaci i tajne, i zašto je svaka odluka doneta baš tako.

**Pravilo koje ovaj dokument poštuje:** dizajn ne uvodi nove zahteve. Svaka arhitektonska odluka realizuje postojeći FR iz `ai forkflow/spec.md`. Ako se tokom dizajna pojavi potreba za novim zahtevom, on se ne pretpostavlja — upisuje se u sekciju 14 kao pitanje za naručioca.

---

## 2. Arhitektonski pregled

```
              HOST MAŠINA
      ┌───────────────────────────────────────────────────────┐
      │  browser            DB klijent           IDE (Xdebug) │
      │     │ :8080            │ :3307              ▲ :9003   │
      └─────┼──────────────────┼────────────────────┼─────────┘
            │                  │                    │
  ══════════╪══════════════════╪════════════════════╪══════════
            ▼                  │                    │  Docker
      ┌───────────┐            │                    │
      │    web    │            │                    │
      │ Apache 2.4│            │                    │
      │   :80     │            │                    │
      └─────┬─────┘            │                    │
            │ FastCGI          │                    │
            │ app:9000         │                    │
            ▼                  │                    │
      ┌───────────┐            │                    │
      │    app    │────────────┼────────────────────┘
      │ PHP 8.3   │  Xdebug ka hostu (obrnut smer)
      │   FPM     │            │
      └─────┬─────┘            │
            │ db:3306          │
            ▼                  ▼
      ┌───────────────────────────┐
      │            db             │
      │        MySQL 8.4          │
      └─────────────┬─────────────┘
                    │
        ┌───────────▼───────────┐
        │  volume: db-data      │
        └───────────────────────┘

      Compose mreža: svi servisi se adresiraju po imenu (web, app, db)
```

| Servis | Uloga | Bazni image | Host port | Zavisi od |
|---|---|---|---|---|
| `web` | HTTP ulazna tačka, servira statiku, prosleđuje PHP | Debian bookworm + `apache2` paket | `8080:80` | `app` (healthy) |
| `app` | Izvršava PHP kod, Composer, Artisan, Xdebug | `php:8.3.x-fpm-bookworm` | — (nije izložen) | `db` (healthy) |
| `db` | Perzistentno skladište podataka | `mysql:8.4.x` | `3307:3306` | — |

Ključna osobina topologije: **`app` nema mapiran port na host**. Jedini put do PHP-a vodi kroz `web`. To smanjuje površinu izloženosti i čini `web → app` komunikaciju isključivo internom (FR-03, FR-05).

---

## 3. Tok zahteva (runtime)

```
  Browser
     │  GET http://localhost:8080/neka-ruta
     ▼
  ┌──────────────────────────────────────────────────────┐
  │  web (Apache 2.4)                                    │
  │  DocumentRoot = <projekat>/public                    │
  │                                                      │
  │  1. Da li traženi fajl fizički postoji u public/ ?   │
  │     ├── DA  → serviraj direktno (CSS, JS, slike)     │
  │     │         PHP se uopšte ne poziva                │
  │     └── NE  → rewrite na /index.php                  │
  │                                                      │
  │  2. Zahtev za *.php → proxy_fcgi                     │
  └───────────────────────┬──────────────────────────────┘
                          │ FastCGI protokol
                          │ SCRIPT_FILENAME = /var/www/html/public/index.php
                          ▼
  ┌──────────────────────────────────────────────────────┐
  │  app (PHP-FPM 8.3, sluša na 0.0.0.0:9000)            │
  │  master proces → worker iz poola                     │
  │  worker izvršava Laravel front controller            │
  └───────────────────────┬──────────────────────────────┘
                          │ PDO / mysqli
                          ▼
  ┌──────────────────────────────────────────────────────┐
  │  db (MySQL 8.4, interni port 3306)                   │
  └──────────────────────────────────────────────────────┘
```

### 3.1 Zašto putanja projekta mora biti identična u oba kontejnera

Ovo je najlakše mesto za grešku u Apache + FPM postavci i zato je izdvojeno.

Apache ne šalje PHP kod FPM-u. Šalje mu **apsolutnu putanju do fajla** u promenljivoj `SCRIPT_FILENAME`. FPM zatim taj fajl otvara **sa svog fajlsistema**. Ako je projekat u `web` kontejneru na `/var/www/html`, a u `app` kontejneru na `/app`, Apache će poslati `/var/www/html/public/index.php`, FPM će tu putanju tražiti kod sebe, neće je naći i odgovoriće greškom `File not found` — iako oba kontejnera imaju kod.

Zbog toga je u dizajnu **ista apsolutna putanja `/var/www/html` u oba kontejnera**, i to je razlog postojanja FR-15. Tu putanju koriste i bind mount i `DocumentRoot` i FPM pool.

### 3.2 Zašto Apache uopšte mora imati fajlove

`web` servis nije čist reverse proxy. On servira statiku direktno iz `../public` (FR-26) i mora da proveri da li fajl postoji pre rewrite-a na `index.php` (FR-23). Obe operacije zahtevaju stvarni pristup fajlsistemu. Zato `web` mount-uje isti kod kao `app` — ne zato što izvršava PHP, nego zato što odlučuje **šta jeste PHP a šta nije**.

---

## 4. Servisi — odgovornosti i granice

### 4.1 `web` — Apache 2.4

**Bazni image:** Debian bookworm (slim) + `apache2` paket iz Debian repozitorijuma.

**Odgovornosti:**
- Prihvata HTTP saobraćaj na internom portu 80, izložen kao 8080 na hostu (FR-02).
- Servira statičke fajlove iz `../public`, uključujući Vite build izlaz (FR-26).
- Prosleđuje PHP zahteve na `app:9000` preko `proxy_fcgi` (FR-20, FR-21).
- Sprovodi Laravel front-controller rewrite (FR-23).
- Odbija pristup svemu izvan `../public` (FR-25).

**Nije odgovornost:** izvršavanje PHP koda, pristup bazi, Composer, Artisan. `web` nema PHP interpreter.

**Konfiguracija:** vhost je zaseban fajl u repozitorijumu (FR-24), po Debian rasporedu — ide u `sites-available/`, aktivira se, a podrazumevani Debian sajt se deaktivira. Moduli `proxy`, `proxy_fcgi`, `rewrite` i `headers` uključuju se standardnim Debian mehanizmom.

**Healthcheck:** posebna, namenska ruta koju **Apache servira sam**, bez dodirivanja PHP-a. To je namerno: kada bi healthcheck išao na `/` kroz PHP i bazu, `web` bi bio prijavljen kao nezdrav svaki put kada aplikacija ima grešku ili je baza spora — iako je web server savršeno ispravan. Healthcheck servisa treba da meri **taj** servis (FR-06).

**Runtime korisnik:** Apache zadržava svoj standardni model — master proces se pokreće kao `root` da bi vezao privilegovani port 80, a radni procesi odmah spuštaju privilegije na `www-data`. Zahtev FR-50/C-07 se odnosi na **aplikaciju**, tj. na `app` kontejner u kome se izvršava PHP kod; to je granica koja se ovde poštuje. Alternativa (Apache na nepriviligovanom portu unutar kontejnera) nije uzeta jer task eksplicitno propisuje mapiranje `8080:80`.

### 4.2 `app` — PHP 8.3 FPM

**Bazni image:** zvanični `php:8.3.x-fpm-bookworm`, pinovan na tačnu patch verziju (PIN-01).

**Odgovornosti:**
- Izvršava PHP kod kroz FPM, sluša na TCP `9000` (FR-10, FR-11).
- Nosi PHP ekstenzije potrebne Laravel-u i Xdebug (FR-12, FR-13).
- Nosi Composer, pa `composer` i `php artisan` rade unutar kontejnera (FR-14).
- Domaćin je za jednokratne komande — migracije, seed, tinker (UC-03).

**Nije odgovornost:** HTTP terminacija, TLS, serviranje statike, frontend build.

**Bazni image je odabran namerno kao `-fpm`, ne `-cli` ni `-apache`.** `-apache` varijanta bi spojila web i app u jedan kontejner i prekršila FR-01. `-cli` nema FPM. `-fpm` daje tačno ono što je traženo.

**Healthcheck:** provera da FPM zaista **odgovara na FastCGI protokolu**, a ne samo da proces postoji. Razlika je bitna: PHP-FPM master može biti živ dok su svi worker-i zaglavljeni ili je pool iscrpljen — provera po PID-u bi takav kontejner prijavila kao zdrav. Zato se proverava kroz stvarnu FastCGI konekciju na `127.0.0.1:9000`, što traži mali dodatni FastCGI klijent u runtime image-u. To je svesna cena od nekoliko stotina kilobajta za healthcheck koji nešto zaista znači (FR-06, AC-16).

**Runtime korisnik:** namenski non-root korisnik (v. sekciju 10). FPM pool konfiguracija eksplicitno postavlja tog korisnika kao vlasnika worker procesa.

### 4.3 `db` — MySQL 8.4

**Bazni image:** zvanični `mysql:8.4.x` (LTS linija), pinovan na tačnu patch verziju.

**Odgovornosti:**
- Perzistentno skladištenje podataka u imenovanom volume-u (FR-30).
- Izlaganje porta `3307:3306` za alate na hostu (FR-04, UC-08).

**Konfiguracija:** isključivo kroz environment varijable iz `../.env` (FR-31). Nijedan kredencijal nije u `compose.yaml` niti u image-u (C-02).

**Healthcheck:** provera da server prihvata konekcije i odgovara na ping — ne samo da je proces startovan. MySQL kontejner pri prvom pokretanju prolazi kroz inicijalizaciju baze koja traje osetno duže od starta procesa; u tom prozoru proces postoji ali server ne prima konekcije. Zato healthcheck ima i `start_period` koji pokriva inicijalizaciju (FR-32).

**Zašto je zdravlje `db` presudno za redosled:** `app` čeka da `db` bude *healthy*, ne samo *started*. Bez toga bi prvi `php artisan migrate --seed` mogao pasti na odbijenu konekciju (rizik R-05).

---

## 5. Build arhitektura (multi-stage)

### 5.1 Tok artefakata kroz stage-ove

```
   ┌──────────────────────────┐   ┌──────────────────────────┐
   │ STAGE 1: composer-deps   │   │ STAGE 2: frontend-assets │
   │                          │   │                          │
   │ osnova: PHP + Composer   │   │ osnova: Node 24 + npm    │
   │                          │   │                          │
   │ 1. COPY composer.json    │   │ 1. COPY package.json     │
   │         composer.lock    │   │         package-lock.json│
   │ 2. install zavisnosti    │   │ 2. npm ci                │
   │    ── granica cache-a ── │   │    ── granica cache-a ── │
   │ 3. COPY izvorni kod      │   │ 3. COPY resources/,      │
   │ 4. dovrši autoloader     │   │         vite config      │
   │                          │   │ 4. vite build            │
   │ IZLAZ: vendor/           │   │ IZLAZ: public/build      │
   └───────────┬──────────────┘   └───────────┬──────────────┘
               │                              │
               │      samo artefakti,         │
               │      nikad alati             │
               ▼                              ▼
   ┌──────────────────────────────────────────────────────────┐
   │ STAGE 3: runtime                                         │
   │ osnova: php:8.3.x-fpm-bookworm                           │
   │                                                          │
   │ • PHP ekstenzije + Xdebug (pinovan)                      │
   │ • Composer binarni fajl (pinovana verzija)               │
   │ • non-root korisnik                                      │
   │ • vendor/ iz STAGE 1                                     │
   │ • public/build iz STAGE 2                                │
   │                                                          │
   │ NEMA: Node, npm, node_modules, Vite                      │
   └──────────────────────────────────────────────────────────┘
```

### 5.2 Zašto Node ne može završiti u runtime image-u

Ovo nije stvar brisanja na kraju — brisanje ne bi ni pomoglo, jer obrisan fajl i dalje postoji u ranijem sloju i vidi se u `docker history`.

Mehanizam je strukturni: runtime stage **počinje od čistog PHP baznog image-a** i iz frontend stage-a uzima isključivo direktorijum sa kompajliranim asset-ima. Node binary i `node_modules` nikada ne bivaju kopirani, pa ne postoje ni u jednom sloju finalnog image-a. Slojevi build stage-ova se ne ugrađuju u finalni image (FR-41, FR-42, C-06, AC-05).

### 5.3 Layer caching — zašto redosled kopiranja nije kozmetika

Docker invalidira sloj i **sve slojeve posle njega** čim se promeni ulaz tog sloja. Ako bi se ceo projekat kopirao pre instalacije zavisnosti, izmena jednog reda u kontroleru bi promenila ulaz sloja sa `COPY`, invalidirala ga, i time naterala `composer install` da se izvrši ponovo — pri svakom buildu.

Zato je u oba build stage-a redosled isti i namerno rascepljen:

1. kopiraju se **samo manifest fajlovi** (`../composer.json` + `composer.lock`, odnosno `package.json` + `package-lock.json`),
2. izvršava se instalacija zavisnosti,
3. **tek onda** se kopira aplikativni izvorni kod.

Posledica: izmena PHP fajla menja ulaz tek za korak 3. Koraci 1 i 2 imaju nepromenjen ulaz i ostaju `CACHED` (FR-44, FR-45, NFR-03). To je tačno ono što AC-11 meri.

Obrnuto, izmena `../composer.lock` ispravno invalidira korak 2 — zavisnosti se ponovo instaliraju, što je i željeno ponašanje iz UC-05.

### 5.4 Uloga `../.dockerignore`

`../.dockerignore` nije samo optimizacija brzine. Ima dve uloge u ovom dizajnu:

1. **Tačnost cache-a** — bez njega lokalni `../vendor`, `node_modules/` ili `storage/logs` ulaze u build kontekst, pa se hash konteksta menja pri svakoj lokalnoj promeni i cache puca bez razloga.
2. **Bezbednost** — sprečava da `../.env` sa stvarnim kredencijalima uđe u build kontekst i završi u sloju image-a (FR-47, FR-56, AC-13).

---

## 6. Struktura projekta

```
dockerTask/
├── app/                      ─┐
├── bootstrap/                 │
├── config/                    │
├── database/                  │  Laravel aplikacija
│   ├── migrations/            │  (standardni raspored, ne odstupa se)
│   ├── factories/             │
│   └── seeders/               │
├── public/                    │  ← DocumentRoot za Apache
├── resources/                 │  ← ulaz za Vite build
├── routes/                    │
├── storage/                   │  ← zapisiv, vlasništvo non-root korisnika
└── tests/                    ─┘
│
├── docker/                   ─┐
│   ├── app/                   │
│   │   ├── php.ini            │  dev override-i (OPcache, prikaz grešaka)
│   │   ├── xdebug.ini         │  čita vrednosti iz env varijabli
│   │   ├── fpm-pool.conf      │  korisnik poola, listen adresa
│   │   └── entrypoint.sh      │  priprema pri startu
│   └── web/                   │
│       ├── vhost.conf         │  DocumentRoot + proxy_fcgi + rewrite
│       └── healthz            │  statički odgovor za healthcheck
│                             ─┘
├── .dockerignore
├── .env.example
├── compose.yaml
├── Dockerfile
├── README.md
├── design.md                     ← ovaj dokument
└── spec.md
```

**Obrazloženje grupisanja:** sve što je specifično za kontejnerizaciju živi pod `../docker`, grupisano **po servisu**. Laravel raspored se ne dira — svako ko poznaje Laravel odmah se snalazi, a infrastrukturni fajlovi se ne mešaju sa aplikativnim. `Dockerfile` i `compose.yaml` ostaju u korenu jer ih Docker tu podrazumevano traži.

---

## 7. Konfiguracija i tok environment varijabli

```
  .env.example  (u repozitorijumu, bez stvarnih vrednosti)
        │
        │  developer kopira jednom, ručno
        ▼
  .env  (lokalno, NIJE u repozitorijumu, NIJE u build kontekstu)
        │
        ▼
  compose.yaml  ── interpolacija ──► vrednosti za portove i build argumente
        │
        └─ env_file / environment ──► promenljive unutar kontejnera u runtime-u
```

**Ključno svojstvo:** `../.env` ulazi u kontejner **u vreme izvršavanja**, nikada u vreme build-a. Zato nijedna tajna ne postaje sloj image-a (FR-55, FR-56) i `docker history` ostaje čist (AC-13).

| Grupa | Promenljive | Potrošač | Svrha |
|---|---|---|---|
| Aplikacija | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | `app` | Laravel runtime konfiguracija |
| Konekcija ka bazi | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `app` | `DB_HOST` je ime servisa `db`, `DB_PORT` je **interni** 3306 (FR-33) |
| Inicijalizacija baze | `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | `db` | kreiranje baze i naloga pri prvom startu |
| Host portovi | `WEB_HOST_PORT`, `DB_HOST_PORT` | Compose | podrazumevano 8080 i 3307; promenljivo bez izmene `../compose.yaml` |
| Xdebug | `XDEBUG_MODE`, `XDEBUG_CLIENT_HOST`, `XDEBUG_CLIENT_PORT`, `XDEBUG_IDE_KEY` | `app` | FR-60, FR-61 |
| Seed | `SEED_USER_PASSWORD` | `app` | lozinka seed korisnika se ne hardkoduje (FR-36) |
| Identitet | `APP_UID`, `APP_GID` | build + runtime | usklađivanje non-root korisnika sa vlasnikom fajlova na hostu (v. 10.2) |

`../.env.example` sadrži imena, komentare i bezbedne podrazumevane vrednosti — **nikada stvarne kredencijale** (FR-62).

---

## 8. Podaci i persistencija

| Volume | Montira se na | Sadržaj | Zašto imenovani volume |
|---|---|---|---|
| `db-data` | `/var/lib/mysql` u `db` | MySQL fajlovi baze | obavezno po FR-30 i C-05 |
| `app-vendor` | `/var/www/html/vendor` | PHP zavisnosti iz build-a | overlay preko bind mount-a (v. 9.2) |
| `app-build` | `/var/www/html/public/build` | Vite build izlaz | overlay preko bind mount-a, deljen sa `web` |

### 8.1 Ponašanje kroz životni ciklus

| Komanda | Kontejneri | `db-data` | Posledica |
|---|---|---|---|
| `docker compose stop` | zaustavljeni | ostaje | brz nastavak rada |
| `docker compose down` | uklonjeni | **ostaje** | podaci prežive (AC-07) |
| `docker compose down -v` | uklonjeni | **obrisan** | čist start, prazna baza (AC-08) |

Ova razlika je jedina stvar koja razdvaja AC-07 od AC-08 i zato je eksplicitno dokumentovana u README-u (FR-85).

### 8.2 Zašto bind mount za MySQL nije opcija

Zabrana iz C-05 nije proizvoljna. Bind mount host direktorijuma za `/var/lib/mysql` donosi konkretne probleme:

- **Vlasništvo i dozvole** — MySQL zahteva da data direktorijum pripada njegovom korisniku. Na bind mount-u vlasništvo dolazi sa hosta, što na Linuxu izaziva greške pri startu, a na Docker Desktop-u prolazi kroz prevodilački sloj.
- **Performanse** — MySQL radi intenzivan nasumični I/O; preko bind mount-a na non-Linux hostovima to je osetno sporije.
- **Semantika brisanja** — `docker compose down -v` briše volume, ali **ne** briše host direktorijum. AC-08 tada ne bi mogao proći bez ručnog brisanja fajlova, što bi značilo da postavka ne ispunjava kriterijum.

Imenovani volume rešava sva tri: Docker upravlja vlasništvom, I/O ide kroz nativni driver, a `-v` ga zaista briše.

---

## 9. Development workflow arhitektura

### 9.1 Mount strategija

```
   HOST: ./dockerTask                    KONTEJNER: /var/www/html
   ┌──────────────────────┐              ┌──────────────────────────┐
   │ app/                 │──── bind ───►│ app/                     │
   │ routes/              │──── bind ───►│ routes/                  │
   │ resources/           │──── bind ───►│ resources/               │
   │ config/, database/   │──── bind ───►│ config/, database/       │
   │ public/*.php, .css   │──── bind ───►│ public/                  │
   │                      │              │                          │
   │ (vendor/ ne postoji  │              │ vendor/       ◄── volume │
   │  na hostu)           │              │ public/build/ ◄── volume │
   └──────────────────────┘              └──────────────────────────┘
                                          ▲ bind mount je ispod,
                                            volume-i su overlay preko njega
```

| Izvor | Odredište | Tip | Servisi | Razlog |
|---|---|---|---|---|
| koren projekta | `/var/www/html` | bind | `app`, `web` | PHP izmene vidljive odmah (FR-70, FR-71, AC-10) |
| `app-vendor` | `/var/www/html/vendor` | volume | `app` | štiti zavisnosti iz image-a od bind mount-a (FR-72) |
| `app-build` | `/var/www/html/public/build` | volume | `app`, `web` | build asseti dostupni Apache-u za serviranje |
| `db-data` | `/var/lib/mysql` | volume | `db` | persistencija (FR-30) |

### 9.2 Overlay mehanizam — problem i rešenje

**Problem koji FR-72 imenuje.** Build ugradi `../vendor` u image. Zatim Compose bind-mount-uje koren projekta sa hosta preko `/var/www/html`. Bind mount **potpuno zaklanja** sadržaj tog direktorijuma iz image-a. Pošto na hostu `vendor/` ne postoji (nije u repozitorijumu), aplikacija u kontejneru odjednom nema nijednu zavisnost i pada na prvom `require`. Isto važi za `public/build`.

**Rešenje.** Na dve konkretne putanje se montiraju imenovani volume-i, **preko** bind mount-a. Docker montira ugnežđene tačke redom po dubini, pa uži mount pobeđuje nad širim:

- `/var/www/html` → sadržaj sa hosta (izvorni kod, izmene instant)
- `/var/www/html/vendor` → sadržaj volume-a, netaknut bind mount-om
- `/var/www/html/public/build` → sadržaj volume-a

Pri **prvom** kreiranju praznog volume-a Docker ga inicijalizuje sadržajem koji na toj putanji postoji u image-u. Tako `../vendor` i `public/build` iz build-a stižu u volume automatski, a bind mount ih ne dodiruje.

**Poznata zamka koju ovaj izbor nosi.** Inicijalizacija iz image-a dešava se **samo dok je volume prazan**. Kada se `../composer.json` promeni i image ponovo izgradi, postojeći `app-vendor` volume **zadržava stari sadržaj** — novi `vendor/` iz image-a neće biti primenjen. To je rizik R-04; rešava se uklanjanjem tog volume-a ili pokretanjem instalacije unutar kontejnera, i mora biti dokumentovano u README-u jer se u suprotnosti ispoljava kao zbunjujuće "zavisnost je instalirana ali je nema".

### 9.3 OPcache i vidljivost izmena

PHP OPcache kešira kompajliran bytecode. Sa podrazumevanom produkcijskom konfiguracijom, izmenjen PHP fajl se ne primećuje do isteka intervala ili restarta FPM-a — što bi direktno oborilo AC-10, i to na način koji izgleda kao da bind mount ne radi.

Dizajn zato u dev režimu koristi PHP konfiguraciju koja proverava vremenske oznake fajlova pri svakom zahtevu (FR-73). Cena je zanemarljiva u razvoju, a ponašanje je ono koje developer očekuje.

### 9.4 Xdebug — smer konekcije

Najčešći izvor konfuzije: **Xdebug ne sluša, nego zove.**

```
   ┌──────────────┐                              ┌────────────────┐
   │  app         │                              │  IDE na hostu  │
   │  (Xdebug)    │  ── inicira TCP konekciju ──►│  SLUŠA :9003   │
   │              │     ka XDEBUG_CLIENT_HOST    │                │
   └──────────────┘                              └────────────────┘
```

IDE je server, kontejner je klijent. Zbog toga kontejner mora znati **adresu hosta**, što je upravo razlog postojanja `XDEBUG_CLIENT_HOST` (FR-60) — i razlog zašto se ta vrednost razlikuje po platformi (rizik R-03).

**Path mapping.** IDE vidi fajl na host putanji, Xdebug prijavljuje kontejnersku putanju `/var/www/html/...`. Bez mapiranja tog para IDE ne ume da poveže breakpoint sa fajlom i debugger se "ne aktivira" iako je konekcija uspostavljena. Mapiranje `koren projekta na hostu` ↔ `/var/www/html` je obavezan korak u README-u (FR-63).

**Prekidač bez rebuild-a.** Xdebug je uvek instaliran, ali `XDEBUG_MODE` određuje da li radi. Postavljanje na `off` vraća performanse bez ponovnog građenja image-a (FR-61).

---

## 10. Bezbednosna arhitektura

### 10.1 Non-root model

U `app` kontejneru se kreira namenski korisnik sa svojom grupom. FPM pool je konfigurisan da worker procese pokreće kao taj korisnik, a vlasništvo nad `../storage` i `bootstrap/cache/` se postavlja na njega (FR-50, FR-51, C-07). Provera je `id` unutar kontejnera i vlasnik procesa (AC-12).

### 10.2 Problem uid/gid neslaganja

Ovo je najčešći način na koji non-root postavka pukne u praksi, pa je rešeno u dizajnu, a ne prepušteno slučaju.

Bind mount **ne prevodi vlasništvo**. Fajl koji na hostu pripada korisniku sa uid 1000 u kontejneru se vidi kao vlasništvo uid 1000 — bez obzira kako se taj korisnik tamo zove. Ako korisnik u kontejneru ima drugi uid, nastaje jedan od dva ishoda:

- kontejner ne može da piše u `../storage` → Laravel puca pri prvom logu ili keš zapisu;
- kontejner piše kao root → fajlovi na hostu postaju root-vlasništvo i developer ih ne može izmeniti iz IDE-a.

**Rešenje:** uid i gid korisnika u kontejneru su **build argumenti** (`APP_UID`, `APP_GID`) sa podrazumevanom vrednošću 1000, koja odgovara prvom korisniku na tipičnom Linux/WSL2 hostu. Developer sa drugim uid-om ga podesi u `../.env` i ponovo izgradi image. Ovo su identifikatori, ne tajne, pa njihovo prisustvo u istoriji image-a ne narušava AC-13.

### 10.3 Gde tajne smeju, a gde ne smeju

| Mesto | Dozvoljeno? | Obrazloženje |
|---|---|---|
| `ENV`/`ARG` u `../Dockerfile`-u | **Ne** | vrednost ostaje trajno u istoriji image-a (AC-13) |
| `COPY` `../.env` u image | **Ne** | tajna postaje sloj; `../.dockerignore` to sprečava (FR-47) |
| Hardkodovano u `../compose.yaml` | **Ne** | fajl je u repozitorijumu (C-02) |
| Runtime env iz `../.env` | **Da** | `../.env` nije u repozitorijumu ni u build kontekstu |

Isto pravilo važi za adrese: nigde se ne upisuje IP, koriste se imena servisa (`app`, `db`) koja razrešava Compose DNS (C-03, FR-05, AC-15).

### 10.4 Granice izloženosti

Hostu su izloženi tačno dva porta: `8080` (web) i `3307` (baza, za razvojne alate). `app` nije izložen. Docker socket se ne montira ni u jedan kontejner (C-04) — nijedan servis nema razlog da upravlja Docker-om, a takav mount bi kontejneru dao kontrolu nad host mašinom.

---

## 11. Arhitektonske odluke

### ADR-01 — Tri odvojena kontejnera
**Kontekst:** treba pokrenuti web server, PHP i bazu.
**Opcije:** (a) jedan kontejner sa sve tri komponente; (b) tri odvojena servisa.
**Odluka:** tri odvojena servisa.
**Obrazloženje:** traženo taskom (FR-01). Svaki servis se nezavisno skalira, nadgleda i restartuje; healthcheck po servisu je smislen; kvar jednog ne maskira stanje drugog.
**Posledice:** potrebna je mrežna konfiguracija i eksplicitan redosled startovanja kroz `depends_on`.

### ADR-02 — Apache + PHP-FPM preko FastCGI umesto `mod_php`
**Kontekst:** Apache može izvršavati PHP ugrađenim modulom.
**Odluka:** FastCGI ka zasebnom FPM procesu.
**Obrazloženje:** `mod_php` zahteva PHP unutar Apache procesa, što obara ADR-01. FPM daje nezavisno upravljanje poolom i tražen je taskom (FR-21).
**Posledice:** uvodi se zahtev iz 3.1 — putanja projekta mora biti identična u oba kontejnera.

### ADR-03 — Debian + `apache2` paket umesto `httpd:2.4.x`
**Kontekst:** postoji zvanični `httpd` image sa Apache verzijom direktno u tagu.
**Opcije:** (a) `httpd:2.4.x`; (b) Debian bazni image + `apache2` iz repozitorijuma.
**Odluka:** (b), **potvrđeno od naručioca**.
**Obrazloženje:** Debian raspored (`sites-available/`, standardno uključivanje modula) poznatiji je i čitljiviji timu, a vhost kao zaseban fajl prirodno se uklapa u taj model.
**Posledice — moraju se priznati:** Apache patch verzija više nije u tagu image-a, nego zavisi od stanja Debian repozitorijuma. Pinovanje po PIN-01/PIN-02 zato zahteva **dvostruko zaključavanje**: bazni Debian image na tačan tag, i `apache2` paket na tačnu verziju pri instalaciji. Rizik da ta verzija nestane iz repozitorijuma obrađen je kao R-01.

### ADR-04 — Imenovani volume overlay za `../vendor` i `public/build`
**Kontekst:** FR-72 traži eksplicitno rešenje sudara bind mount-a i artefakata iz image-a.
**Opcije:** (a) volume overlay; (b) entrypoint sinhronizuje `../vendor` na host pri startu; (c) bind mount samo pojedinačnih aplikativnih poddirektorijuma.
**Odluka:** (a), **potvrđeno od naručioca**.
**Obrazloženje:** čuva instant vidljivost PHP izmena, ne zagađuje host `vendor/` direktorijumom, ne usporava start, i ne zahteva izmenu `compose.yaml` pri dodavanju novog direktorijuma u projekat.
**Posledice:** zamka ustajalog volume-a posle izmene zavisnosti (R-04), koja mora biti dokumentovana u README-u.

### ADR-05 — Tri build stage-a sa rascepljenim kopiranjem
**Kontekst:** treba istovremeno postići mali runtime image i stabilan cache.
**Odluka:** `composer-deps`, `frontend-assets`, `runtime`; u svakom build stage-u manifest fajlovi se kopiraju pre izvornog koda.
**Obrazloženje:** razdvaja sporo i retko promenljivo (zavisnosti) od brzog i često promenljivog (izvorni kod). Bez rascepa AC-11 ne može proći.
**Posledice:** `../Dockerfile` je duži i zahteva komentare o granicama cache-a.

### ADR-06 — Imenovani volume za MySQL
**Kontekst:** podaci moraju preživeti `down` i nestati sa `down -v`.
**Odluka:** imenovani volume; bind mount zabranjen.
**Obrazloženje:** v. 8.2 — dozvole, performanse i semantika brisanja.
**Posledice:** podaci nisu direktno vidljivi na hostu; pristup ide kroz port 3307 ili `exec`.

### ADR-07 — Non-root sa konfigurabilnim uid/gid
**Kontekst:** non-root je zahtev, ali naivna implementacija razbija bind mount.
**Odluka:** namenski korisnik čiji su uid/gid build argumenti.
**Obrazloženje:** v. 10.2.
**Posledice:** promena uid-a zahteva rebuild image-a.

### ADR-08 — Xdebug uvek prisutan, upravljan environment-om
**Kontekst:** Xdebug usporava izvršavanje kada je aktivan.
**Opcije:** (a) zaseban build target sa i bez Xdebug-a; (b) uvek instaliran, aktivnost kroz `XDEBUG_MODE`.
**Odluka:** (b).
**Obrazloženje:** FR-61 traži prekidač bez rebuild-a. Zaseban target bi značio ponovno građenje pri svakom uključivanju debuggera.
**Posledice:** ekstenzija je prisutna u image-u i kada se ne koristi — prihvatljivo za razvojno okruženje, koje je jedini opseg ovog zadatka.

### ADR-09 — Healthcheck meri servis, ne ceo lanac
**Kontekst:** lako je napisati healthcheck koji prolazi kroz sve slojeve.
**Odluka:** `web` proverava sopstvenu statičku rutu; `app` proverava FastCGI odziv; `db` proverava prihvatanje konekcija.
**Obrazloženje:** healthcheck koji zavisi od nizvodnih servisa prijavljuje lažne kvarove i čini `depends_on` redosled beskorisnim — svi servisi bi postajali zdravi tek istovremeno.
**Posledice:** `app` runtime image dobija mali FastCGI klijent; `web` dobija namensku rutu.

---

## 12. Rizici i mitigacije

| ID | Rizik | Uticaj | Mitigacija |
|---|---|---|---|
| **R-01** | `apache2` verzija pinovana pri instalaciji nestane iz Debian repozitorijuma posle security update-a | build puca na čistoj mašini; ugroženi PIN-01/PIN-02 i AC-17 | pinovati i bazni Debian image i verziju paketa; zabeležiti tačne vrednosti u README-u; predvideti proceduru osvežavanja pina kada build padne |
| **R-02** | uid/gid u kontejneru se ne poklapa sa vlasnikom fajlova na hostu | greške pri pisanju u `../storage`, ili root-vlasništvo fajlova na hostu; ugroženi AC-10 i AC-12 | `APP_UID`/`APP_GID` kao build argumenti sa podrazumevanom vrednošću 1000 (v. 10.2) |
| **R-03** | `XDEBUG_CLIENT_HOST` se razlikuje između Linux/WSL2 i Docker Desktop okruženja | breakpoint se ne aktivira; ugrožen AC-09 | vrednost isključivo kroz env; dodati host→gateway mapiranje za `app` servis; dokumentovati obe varijante u README-u |
| **R-04** | `app-vendor` volume zadrži stari sadržaj posle izmene zavisnosti | nove zavisnosti "nedostaju" iako je build uspeo; ugrožen UC-05 | dokumentovati u README-u kao obavezan korak pri izmeni `../composer.json`: ukloniti volume ili pokrenuti instalaciju u kontejneru |
| **R-05** | `app` pokuša konekciju pre nego što je MySQL inicijalizovan | prvi `migrate --seed` pada; ugrožen AC-06 | `depends_on` sa uslovom `service_healthy` + `start_period` u healthcheck-u `db` koji pokriva prvu inicijalizaciju |
| **R-06** | OPcache sakrije izmene PHP fajlova | izgleda kao da bind mount ne radi; ugrožen AC-10 | dev PHP konfiguracija sa proverom vremenskih oznaka (FR-73) |
| **R-07** | Putanje projekta se raziđu između `web` i `app` | Apache vraća `File not found` na svaki PHP zahtev | ista apsolutna putanja `/var/www/html` u oba kontejnera (v. 3.1, FR-15) |

---

## 13. Mapiranje arhitekture na `ai forkflow/spec.md`

| Deo dizajna | Sekcija | Realizuje |
|---|---|---|
| Topologija tri servisa, Compose mreža | 2 | FR-01…FR-05, FR-08 |
| Tok zahteva, jedinstvena putanja projekta | 3 | FR-15, FR-21…FR-23, FR-26 |
| `web` — image, moduli, vhost, healthcheck | 4.1 | FR-02, FR-20…FR-26, FR-06 |
| `app` — image, ekstenzije, FPM, Composer | 4.2 | FR-03, FR-10…FR-14, FR-06 |
| `db` — image, kredencijali, healthcheck | 4.3 | FR-04, FR-31…FR-33, FR-06 |
| Redosled startovanja | 4.3, 12 (R-05) | FR-07 |
| Tri build stage-a, tok artefakata | 5.1, 5.2 | FR-40…FR-43, FR-46, C-06 |
| Rascepljeno kopiranje i granice cache-a | 5.3 | FR-44, FR-45, NFR-03 |
| `../.dockerignore` — cache i tajne | 5.4 | FR-47, FR-56 |
| Struktura direktorijuma | 6 | FR-24, FR-51 |
| Tok env varijabli, tabela promenljivih | 7 | FR-31, FR-33, FR-60…FR-62, FR-36 |
| Volume-i i životni ciklus | 8 | FR-30, C-05 |
| Mount strategija i overlay | 9.1, 9.2 | FR-70…FR-72 |
| OPcache dev režim | 9.3 | FR-73 |
| Xdebug smer, path mapping, prekidač | 9.4 | FR-60…FR-63 |
| Non-root model i uid/gid | 10.1, 10.2 | FR-50, FR-51, C-07 |
| Politika tajni i adresa | 10.3 | FR-52, FR-53, FR-55, FR-56, C-02, C-03 |
| Granice izloženosti | 10.4 | FR-03, C-04 |
| Strategija pinovanja (ADR-03, R-01) | 11, 12 | PIN-01…PIN-04, C-01 |
| Rizici sa uticajem na dokumentaciju | 12 | FR-80…FR-87 |

Sve grupe zahteva iz `ai forkflow/spec.md` (5.1–5.9) imaju pokriće u dizajnu. Nijedna komponenta u ovom dokumentu ne postoji bez zahteva koji je opravdava.

---

## 14. Otvorena pitanja

Nema otvorenih pitanja u ovoj fazi. Sve odluke koje su zahtevale ulaz naručioca (nivo detalja dokumenta, bazni image za `web`, strategija za `../vendor`) potvrđene su i zabeležene kao ADR-03 i ADR-04.

Nove nejasnoće se upisuju ovde i razrešavaju sa naručiocem — ne rešavaju se pretpostavkom.
