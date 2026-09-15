#!/bin/sh
# entrypoint za `app` servis. Radi tacno cetiri stvari: APP_KEY, zapisivost
# storage-a, migracije seme, predaja kontrole FPM-u.
#
# Namerno NE radi:
#   - db:seed — izmereno u talasu 2 da NIJE idempotentan: drugo pokretanje dize
#     broj korisnika sa 11 na 21 (sidro zapis ostaje jedan zbog updateOrCreate,
#     ali se 10 factory korisnika doda svaki put). Automatski seed bi posle
#     deset restarta dao 101 korisnika i obesmislio AC-06. Seed ostaje svesna
#     radnja developera iz UC-03.
#   - composer install — zavisnosti dolaze iz image-a kroz app-vendor (ADR-04),
#   - config:cache — kesirana konfiguracija bi env() svela na null, pa bi seed
#     pao na citanju SEED_USER_PASSWORD.
set -eu

# Ugovor 4.2: ista apsolutna putanja u `app` i `web` (design.md 3.1, rizik R-07).
cd /var/www/html

# 1) APP_KEY — NFR-02: posle `cp .env.example .env` nema rucnih koraka,
#    a Laravel bez kljuca ne radi.
if [ -f .env ]; then
    if ! grep -qE '^APP_KEY=.+' .env; then
        php artisan key:generate --force
    fi

    # Laravel Dotenv je immutable i ne prepisuje promenljivu koja je vec
    # definisana u okruzenju. Sa svezim .env-om env_file unosi APP_KEY kao
    # prazan string, pa bi FPM worker-i videli prazno iako kljuc od maloapre
    # stoji u .env-u — i prvi zahtev bi pao sa MissingAppKeyException.
    if [ -z "${APP_KEY:-}" ]; then
        APP_KEY="$(sed -n 's/^APP_KEY=//p' .env | head -n 1)"
        export APP_KEY
    fi
fi

# 2) FR-51 / rizik R-02: bind mount ne prevodi vlasnistvo. Ako se uid sa hosta ne
#    poklapa sa appuser-om, Laravel bi pukao tek na prvom logu, nejasnom porukom.
#    Kontejner radi kao non-root, pa chown nije opcija — proverava se i pada
#    odmah, uz poruku koja imenuje lek.
for dir in storage bootstrap/cache; do
    mkdir -p "$dir"
    if [ ! -w "$dir" ]; then
        echo "entrypoint: '$dir' nije zapisiv za $(id -un) (uid $(id -u)); uskladi APP_UID/APP_GID sa vlasnikom fajlova na hostu" >&2
        exit 1
    fi
done

# 3) Migracije pri svakom startu. Laravel 12 drzi sesije u bazi, pa bez tabele
#    `sessions` svaka stranica koja dira sesiju vraca HTTP 500 na svezem stack-u.
#    Ponavljanje je bezbedno: drugo pokretanje daje "Nothing to migrate", a
#    `depends_on: service_healthy` garantuje da je baza spremna pre starta.
#
#    Pad migracije gasi kontejner umesto da ga pusti dalje. Razlog: FPM sa
#    nepotpunom semom vracao bi 500 na svaki zahtev i to bi izgledalo kao greska
#    aplikacije, dok kontejner koji ne startuje jednoznacno pokazuje uzrok u
#    `docker compose ps` i logovima. Uz to `web` zavisi od zdravog `app`-a, pa
#    ovako ni ne pocinje da prima saobracaj nad neispravnom semom.
if ! php artisan migrate --force; then
    echo "entrypoint: php artisan migrate nije uspeo; kontejner se gasi da FPM ne bi radio nad nepotpunom semom. Proveri servis db i DB_* vrednosti u .env" >&2
    exit 1
fi

# 4) exec: FPM preuzima PID 1, pa `docker stop` (SIGTERM) stize direktno njemu i
#    graceful shutdown radi. Bez exec-a signal bi zavrsio na ljusci.
#    Fallback postoji jer ENTRYPOINT ponistava CMD iz baznog image-a.
[ "$#" -gt 0 ] || set -- php-fpm
exec "$@"
