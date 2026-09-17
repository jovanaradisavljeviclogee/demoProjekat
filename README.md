# Docker development okruženje za Laravel

Razvojno okruženje koje Laravel aplikaciju pokreće kroz tri kontejnera: **Apache 2.4** (`web`) → **PHP 8.3 FPM** (`app`) → **MySQL 8.4** (`db`). Iz čistog klona se podiže kopiranjem `.env` i jednom `docker compose` komandom.

Opseg je isključivo lokalni razvoj. Produkcijski deployment, TLS i CI/CD nisu deo ovog okruženja.

---

## 1. Preduslovi

| Stavka | Provera |
|---|---|
| Docker Engine i Docker Compose v2 | `docker compose version` |
| Slobodni host portovi `8080` i `3307` | v. sekciju 4; menjaju se kroz `WEB_HOST_PORT` / `DB_HOST_PORT` u `.env` |

Na Linux/WSL2 hostu proveri i sopstveni uid/gid (`id -u`, `id -g`). Podrazumevano je `1000:1000` i to je vrednost sa kojom se gradi non-root korisnik `appuser` u `app` kontejneru. Ako je tvoj uid drugačiji, podesi `APP_UID` / `APP_GID` u `.env` **pre** prvog build-a — bind mount ne prevodi vlasništvo, pa neusklađen uid znači da `storage/` nije zapisiv i kontejner namerno pada sa porukom koja to kaže.

---

## 2. Podizanje iz čistog klona

```bash
cp .env.example .env
docker compose up -d --build
```

To je sve. Nema trećeg koraka.

Pri startu `app` kontejnera entrypoint sam odradi ono što bi inače bili ručni koraci:

1. generiše `APP_KEY` ako je prazan u `.env`,
2. proverava da su `storage/` i `bootstrap/cache/` zapisivi,
3. **pokreće migracije** (v. sekciju 5),
4. predaje kontrolu PHP-FPM-u.

Provera da je okruženje spremno:

```bash
curl -I http://localhost:8080     # HTTP/1.1 200 OK
docker compose ps                 # sva tri servisa: Up (healthy)
```

Aplikacija je na **http://localhost:8080**.

> Prvi `up -d --build` gradi četiri stage-a i traje osetno duže od narednih. `db` servis pri prvoj inicijalizaciji prazne baze takođe traje duže nego kasnije — `healthcheck` to pokriva kroz `start_period`, a `app` čeka da `db` bude `healthy`.

---

## 3. Start / stop komande

| Komanda | Efekat |
|---|---|
| `docker compose up -d --build` | gradi image-e i podiže stack; koristi se posle izmene `Dockerfile`-a ili zavisnosti |
| `docker compose up -d` | podiže stack sa postojećim image-ima |
| `docker compose ps` | status i health svakog servisa, mapirani portovi |
| `docker compose logs -f` | prati logove svih servisa |
| `docker compose logs -f app` | samo `app`; ovde se vidi izlaz migracija i PHP greške |
| `docker compose logs web` | Apache access i error log (idu na `stdout`/`stderr`; `/healthz` je izuzet iz access loga da ne bi zatrpao izlaz) |
| `docker compose stop` | zaustavlja kontejnere, zadržava ih i sve podatke |
| `docker compose down` | uklanja kontejnere i mrežu; **volume-i ostaju**, podaci prežive |
| `docker compose down -v` | uklanja kontejnere i **briše volume-e** — potpun reset, podaci baze nestaju |

Jednokratne komande idu kroz `app`:

```bash
docker compose exec app php artisan <komanda>
docker compose exec app composer <komanda>
```

---

## 4. Servisi, portovi i uloge

| Servis | Uloga | Host port | Interni port |
|---|---|---|---|
| `web` | Apache 2.4; `DocumentRoot` je `public/`, servira statiku direktno, PHP zahteve prosleđuje preko `proxy_fcgi` na `app:9000` | `8080` → 80 | 80 |
| `app` | PHP 8.3 FPM; izvršava aplikaciju, domaćin za Composer, Artisan i Xdebug | **nije izložen** | 9000 |
| `db` | MySQL 8.4; podaci u imenovanom volume-u `db-data` | `3307` → 3306 | 3306 |

Do PHP-a se dolazi isključivo kroz `web` — `app` namerno nema mapiran port na host.

Port `3307` postoji da bi se DB klijent sa hosta (DataGrip, `mysql` CLI) mogao povezati na `127.0.0.1:3307` sa kredencijalima iz `.env`. Unutar Compose mreže baza je i dalje `db:3306`, i to je ono što `DB_HOST` / `DB_PORT` u `.env` sadrže.

Koren projekta je bind-mount-ovan na `/var/www/html` u oba kontejnera, na **istu apsolutnu putanju**. Zato je izmena PHP fajla vidljiva na sledeće osvežavanje stranice, bez rebuild-a i bez restarta.

---

## 5. Migracije i seed

### Migracije — automatske

Pokreću se pri **svakom** startu `app` kontejnera, iz `docker/app/entrypoint.sh`, pre nego što FPM počne da prima saobraćaj. Ne pokrećeš ih ručno.

Razlog: Laravel 12 podrazumevano drži sesije u bazi. Bez tabele `sessions` sveže podignut stack vraća HTTP 500 na prvoj stranici, pa `docker compose up -d --build` ne bi bio dovoljan.

Operacija je idempotentna — drugi start ispisuje `Nothing to migrate`:

```bash
docker compose exec app php artisan migrate --force
#    INFO  Nothing to migrate.
```

**Ako migracija padne, kontejner se namerno gasi** (`exit 1`) umesto da pusti FPM nad nepotpunom šemom. Kako to izgleda i gde je uzrok:

```bash
docker compose ps        # app: Exited (1), a `web` nikad ne postaje healthy jer zavisi od zdravog `app`
docker compose logs app  # poslednji red imenuje neuspeh; iznad njega stoji izlaz artisan-a
```

Najčešći uzrok je neusklađenost `DB_*` vrednosti u `.env` sa `MYSQL_*` vrednostima — `app` se konektuje na bazu koju `db` kreira, pa `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` moraju odgovarati `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD`.

### Seed — ručno, svesno

```bash
docker compose exec app php artisan db:seed
```

Upisuje **11 korisnika**: jedan fiksni „sidro" zapis (`seed-anchor@example.test`) plus 10 zapisa iz factory-ja. Lozinka dolazi iz `SEED_USER_PASSWORD` u `.env`; ako ta promenljiva nije postavljena, seed pada sa eksplicitnom porukom umesto da upiše korisnike sa neupotrebljivom lozinkom.

**Zašto seed nije automatizovan kao migracije:** nije idempotentan. Izmereno — drugo pokretanje diže broj korisnika sa 11 na 21, jer se 10 factory zapisa doda svaki put. Sidro zapis preživljava jer se upisuje kroz `updateOrCreate`, ali bi automatski seed pri svakom startu naduvavao bazu bez ograničenja.

Provera rezultata:

```bash
docker compose exec app php artisan tinker --execute="echo \App\Models\User::count();"
docker compose exec app php artisan tinker --execute="echo \App\Models\User::where('email', 'seed-anchor@example.test')->count();"
```

---

## 6. Xdebug

Xdebug **3.5.3** je uvek instaliran. Da li radi zavisi isključivo od environment varijabli — nema posebnog build-a za debug.

### Environment varijable (`.env`)

| Promenljiva | Podrazumevano | Značenje |
|---|---|---|
| `XDEBUG_MODE` | `debug` | jedini prekidač: `debug` uključuje step debugger, `off` ga potpuno gasi |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` | adresa **hosta** na koju Xdebug zove IDE (v. dole) |
| `XDEBUG_CLIENT_PORT` | `9003` | port na kome IDE sluša |
| `XDEBUG_IDE_KEY` | `PHPSTORM` | `idekey` koji IDE očekuje |

Vrednosti se čitaju iz `.env` u runtime-u, pa se izmena primenjuje sa `docker compose up -d`. **Rebuild image-a nije potreban.**

### Kako se sesija pokreće

Konfiguracija ima `xdebug.start_with_request = yes`, pa se debugger aktivira na **svaki** zahtev dok je `XDEBUG_MODE=debug`. Nije potrebna browser ekstenzija, `XDEBUG_SESSION` cookie ni `?XDEBUG_TRIGGER` parametar. Kada debugger ne treba, postavi `XDEBUG_MODE=off` — to vraća pune performanse.

### Podešavanje IDE-a

1. IDE sluša na portu **9003** za dolazne PHP debug konekcije. Xdebug ne sluša, nego **zove** — IDE je server, kontejner je klijent.
2. `idekey` = **PHPSTORM** (isto što i `XDEBUG_IDE_KEY`).
3. **Path mapping je obavezan:**

   | Host | Kontejner |
   |---|---|
   | koren ovog projekta | `/var/www/html` |

   Bez mapiranja IDE ne ume da poveže breakpoint sa fajlom koji mu Xdebug prijavljuje kao `/var/www/html/...`. Simptom je da „debugger ne radi" iako je konekcija uspešno uspostavljena — ovo je najčešća greška u ovoj postavci.

4. Postavi breakpoint i otvori `http://localhost:8080`.

### `XDEBUG_CLIENT_HOST` zavisi od okruženja

Ovo je jedina vrednost koja se stvarno razlikuje od mašine do mašine.

| Okruženje | Podrazumevana vrednost |
|---|---|
| Linux host sa nativnim Docker Engine-om | radi — `compose.yaml` za `app` servis mapira `host.docker.internal:host-gateway` |
| Docker Desktop sa WSL2 backend-om, **IDE na Windows-u** | radi — `host.docker.internal` pokazuje upravo na Windows host |
| Docker Desktop sa WSL2 backend-om, **IDE unutar WSL2 distribucije** | **ne radi** — ime pokazuje na Windows host, ne na distribuciju; vrednost treba prilagoditi adresi te distribucije |

Na ovoj mašini (Docker Desktop + WSL2) izmereno je:

```bash
docker compose exec app getent ahosts host.docker.internal
# 192.168.65.254   (IPv4)
docker compose exec app getent hosts host.docker.internal
# fdc4:f303:9324::254   (IPv6)
```

Ta adresa je Windows host.

Verifikovano da lanac radi: Xdebug 3.5.3 šalje validan DBGp `<init>` paket (511 bajtova, `idekey="PHPSTORM"`, `protocol_version="1.0"`) na svaki zahtev, bez ikakvog trigger-a.

### Kada breakpoint ne radi

```bash
docker compose logs app | grep -i xdebug
```

Poruka `Could not connect to debugging client. Tried: host.docker.internal:9003` znači da je Xdebug aktivan i da zove — ali na drugoj strani niko ne sluša. Uzrok je IDE koji ne sluša, pogrešan port ili `XDEBUG_CLIENT_HOST` koji ne pokazuje na mašinu na kojoj IDE radi.

Ako konekcija postoji a izvršavanje se ne zaustavlja, uzrok je path mapping.

---

## 7. Persistencija podataka

MySQL podaci žive u imenovanom volume-u `db-data`, nikad u host direktorijumu.

| Komanda | Kontejneri | `db-data` | Podaci |
|---|---|---|---|
| `docker compose stop` | zaustavljeni | ostaje | ostaju |
| `docker compose down` | uklonjeni | **ostaje** | **ostaju** |
| `docker compose down -v` | uklonjeni | **obrisan** | **nestaju** |

Izmereno: 11 korisnika preživi `down` + `up -d`; posle `down -v` + `up -d` ima ih 0.

**Posle `down -v` tabele i dalje postoje** — migracije se automatski primene na prazan volume pri narednom startu. Dokaz da je reset odradio posao su **podaci**, ne šema:

```bash
docker compose exec app php artisan tinker --execute="echo \App\Models\User::count();"
# 0
```

---

## 8. Zamka: izmena zavisnosti i ustajali volume

`vendor/` i `public/build` ne postoje na hostu — dolaze iz image-a i montiraju se kao imenovani volume-i `app-vendor` i `app-build` preko bind mount-a korena projekta.

Docker puni imenovani volume sadržajem iz image-a **samo dok je volume prazan**. Posle izmene `composer.json` / `composer.lock` (odnosno `package.json` / `package-lock.json`) build ispravno proizvede novi `vendor/`, ali ga postojeći volume ne preuzme — zadržava stari sadržaj. Simptom je zbunjujuć: build je prošao, `composer` javlja da je zavisnost instalirana, a aplikacija je ne vidi.

Rešenje je ukloniti taj volume:

```bash
docker compose down                        # `down`, ne `down -v` — db-data ostaje netaknut
docker volume rm dockertask_app-vendor     # za frontend: dockertask_app-build
docker compose up -d --build
```

Volume se ne može ukloniti dok postoji kontejner koji ga koristi, otuda `down` pre brisanja. Imena volume-a su prefiksirana imenom Compose projekta — proveri ih sa `docker volume ls`.

---

## 9. Pinovane verzije

Sve vrednosti su pročitane iz pokrenutog okruženja, ne iz dokumentacije.

| Komponenta | Verzija | Komanda verifikacije |
|---|---|---|
| PHP (bazni image `php:8.3.33-fpm-bookworm`) | `8.3.33` | `docker compose exec app php -v` |
| PHP SAPI | `fpm-fcgi` | `docker compose exec app php-fpm -v` |
| Debian (bazni image `debian:12.15-slim`) | `12.15` | `docker compose exec web cat /etc/debian_version` |
| Apache | `2.4.68-1~deb12u1` | `docker compose exec web dpkg-query -W -f='${Version}\n' apache2` |
| MySQL | `8.4.11` | `docker compose exec db mysqld --version` |
| Node.js | `24.21.0` (npm `11.19.0`) | samo u build stage-u `frontend-assets`; u runtime image-u ga nema: `docker compose exec app sh -c 'command -v node \|\| echo ABSENT'` → `ABSENT` |
| Composer | `2.8.12` | `docker compose exec app composer --version` |
| Laravel | `12.69.2` | `docker compose exec app php artisan --version` |
| Xdebug | `3.5.3` | `docker compose exec app php -v` |

Apache verzija nije u tagu image-a nego dolazi iz Debian repozitorijuma, pa su pinovana oba sloja — bazni image **i** verzija `apache2` paketa pri instalaciji.

---

## 10. Dva očekivana upozorenja u build logu

Svaki `docker compose up -d --build` ispiše dve linije ovog oblika:

```
Class Domain\Wrong\BrokenNamespace located in ./ExamplePSR/BrokenNamespace.php does not comply
with psr-4 autoloading standard (rule: Domain\ => ./ExamplePSR). Skipping.

Class Domain\MismatchedClass located in ./ExamplePSR/WrongFileName.php does not comply
with psr-4 autoloading standard (rule: Domain\ => ./ExamplePSR). Skipping.
```

**To je ispravno ponašanje i ne treba ga popravljati.**

`ExamplePSR/BrokenNamespace.php` i `ExamplePSR/WrongFileName.php` su namerno pokvareni — služe kao nastavni primer dva od tri načina da se pokvari PSR-4 autoloading. Objašnjenje i uhvaćene poruke su u [`ExamplePSR/AUTOLOADING-BREAKS.md`](ExamplePSR/AUTOLOADING-BREAKS.md).

Zašto se vide uvek, a ne samo kad se `-o` otkuca namerno: `composer.json` ima `"optimize-autoloader": true`, pa je **svaki** dump optimizovan. Upozorenja izlaze iz `composer install`, iz običnog `composer dump-autoload`, i iz Docker build-a.

Exit kod ostaje `0` i build prolazi. Ako uvedeš CI koji tretira upozorenja kao greške, ta dva izuzmi poimence — ne uklanjaj primere.

---

## 11. Veličina image-a i najveći slojevi

Izmereno sa:

```bash
docker image ls
docker image history <image> --no-trunc --format '{{.Size}}|{{.CreatedBy}}' | sort -h -r | head -3
```

### `dockertask-app` — 801 MB

| Sloj | Veličina | Poreklo |
|---|---|---|
| `RUN apt-get install $PHPIZE_DEPS ...` | 331 MB | zvanični `php:8.3.33-fpm-bookworm`, nije naš |
| `RUN ... docker-php-ext-install + pecl install xdebug + libfcgi-bin` | 95.8 MB | naš sloj sa PHP ekstenzijama |
| Debian bookworm rootfs | 85.3 MB | bazni image |

Dva od tri najveća sloja dolaze iz zvaničnog baznog image-a. `PHPIZE_DEPS` ostaje zato što je deo tog sloja — uklanjanje bi razbilo `pecl`, koji gradi Xdebug. Naš sloj sa ekstenzijama već čisti apt keš i build zavisnosti u istom `RUN`-u.

### `dockertask-web` — 267 MB

| Sloj | Veličina | Poreklo |
|---|---|---|
| `RUN apt-get install apache2=2.4.68-1~deb12u1 curl` | 117 MB | naš sloj |
| Debian bookworm rootfs | 85.3 MB | bazni image |
| `RUN a2ensite 000-app` | 36.9 kB | naš sloj |

`curl` je u `web` image-u zato što `debian:12.15-slim` nema nijedan HTTP klijent, a bez njega healthcheck servisa ne bi imao čime da proveri `/healthz`.

Ni u jednom finalnom image-u nema Node-a, npm-a ni `node_modules` — frontend build živi isključivo u svom stage-u, a u runtime ulazi samo kompajlirani `public/build`.

---

## 12. Frontend u razvoju (React)

Administracija payment metoda (`/admin/payment-methods`) je React single-page aplikacija. Izvor je u `resources/js/`, a build radi Vite.

**Ovo je jedina komanda ovog projekta koja se pokreće na hostu, a ne kroz `app` kontejner.** Razlog je u [ADR-19](docs/decisions/ADR-19-vite-dev-server-na-hostu.md): runtime image namerno nema Node (sekcija 9), a `public/build` je imenovani volume koji se puni iz image-a samo dok je prazan (sekcija 8) — pa bi svaka izmena jedne komponente tražila pun rebuild i ručno brisanje volumena.

```bash
npm install     # jednom, posle klona ili izmene package.json
npm run dev     # drži pokrenuto dok radiš na frontendu
```

Vite sluša na `5173` i upisuje `public/hot`. Koren projekta je bind-mount-ovan, pa `@vite` direktiva u Blade-u taj fajl vidi iz kontejnera i šalje browser na dev server umesto na `public/build`. Aplikacija se i dalje otvara na **http://localhost:8080**, ne na `5173`.

Kad `npm run dev` nije pokrenut, servira se poslednji bundle iz `app-build` volumena.

### Ako je stranica prazna

Sve u nastavku daje **belu stranicu bez ijedne greške na serveru** — `curl` u svakom od ovih slučajeva vraća uredan `200`, pa se uzrok vidi isključivo u DevTools konzoli pregledača. Zato je konzola prva stvar koju treba pogledati, ne server log.

| Poruka u konzoli | Uzrok | Rešenje |
|---|---|---|
| `ERR_ADDRESS_INVALID` | `public/hot` sadrži `http://[::]:5173` — IPv6 wildcard, adresa vezivanja umesto adrese pristupa | `server.origin` u `vite.config.js`; ne pokretati sa golim `--host` |
| `blocked by CORS policy` uz `ERR_FAILED 200` | stranicu servira Apache na `8080`, module Vite na `5173` — dva porekla; Vite 7 ima pooštrenu podrazumevanu politiku | `server.cors.origin` u `vite.config.js`, vezan za `APP_URL` |
| `can't detect preamble` | u Blade-u nedostaje `@viteReactRefresh` **pre** `@vite` | dodati direktivu; u produkcionom build-u ona ne ispisuje ništa, zato `npm run build` prolazi čist i propust se vidi samo kroz dev server |
| stranica prazna, port nije `5173` | već pokrenut Vite drži port, novi tiho prelazi na `5174` i tamo upisuje `public/hot` | `strictPort: true` u `vite.config.js`; `pkill -f "node.*vite"` pa ponovo `npm run dev` |

Provera da je dev server ispravno postavljen:

```bash
cat public/hot          # mora biti http://localhost:5173, ne [::] i ne 127.0.0.1
```

**Sve ovo važi samo za dev server.** Produkcioni bundle nema ni CORS ni preambulu, pa `npm run build` može proći čist nad kodom koji u razvoju ne radi — i obrnuto. Zbog toga oba puta moraju biti provereni.

**Provera proizvodnog bundle-a** ide procedurom iz sekcije 8 — bez uklanjanja volumena novi bundle ne stiže do Apache-a, a build prolazi bez ijedne greške:

```bash
docker compose down
docker volume rm dockertask_app-build
docker compose up -d --build
```

### Postojeći `.env` treba dopuniti

`.env` je gitignorisan, pa izmene iz ove grane stižu samo kroz `.env.example`. Ko već ima svoj `.env`, mora ručno dodati:

```
SESSION_DRIVER=file
```

Bez toga važi podrazumevana vrednost iz `config/session.php`, a to je `database` — aplikacija radi, ali radna kopija payment metoda odlazi u `sessions` tabelu umesto u sesijski fajl, protivno [ADR-16](docs/decisions/ADR-16-staticki-niz-u-sesiji.md). Ništa ne pukne, pa se propust ne primeti.
