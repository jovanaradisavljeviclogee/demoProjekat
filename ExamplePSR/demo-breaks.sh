#!/usr/bin/env bash
#
# Reprodukuje tri nacina da se pokvari PSR-4 autoloading i ispisuje TACNE poruke.
#
# Pokretanje iz korena projekta:   bash ExamplePSR/demo-breaks.sh
# Preduslov: stack je podignut (docker compose up -d)
#
# Skripta je bezbedna za ponavljanje. Kvar 1 zahteva privremenu izmenu
# composer.json-a; `trap` vraca original i pri prekidu (Ctrl+C) ili gresci, da
# projekat nikada ne ostane sa pokvarenim mapiranjem.

set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

BACKUP="$(mktemp)"
cp composer.json "$BACKUP"

restore() {
    cp "$BACKUP" composer.json
    rm -f "$BACKUP"
    # Vrati autoloader u ispravno stanje bez obzira kako smo izasli.
    docker compose exec -T -e XDEBUG_MODE=off app composer dump-autoload --quiet 2>/dev/null
}
trap restore EXIT INT TERM

# XDEBUG_MODE=off jer bi inace Xdebug pri svakom CLI pozivu ispisao upozorenje da
# ne moze da se poveze na IDE, sto bi zaprljalo poruke koje bas hocemo da uhvatimo.
run_php() {
    docker compose exec -T -e XDEBUG_MODE=off app php -r "$1" 2>&1
}

# Pokusa da instancira klasu i ispise tacan tip i poruku greske.
probe() {
    run_php "require 'vendor/autoload.php';
        try {
            \$o = new \\$1();
            echo 'USPEH: ', \$o->message(), PHP_EOL;
        } catch (\\Throwable \$e) {
            echo get_class(\$e), ': ', \$e->getMessage(), PHP_EOL;
        }"
}

# Pokusa klasu i U ISTOM PROCESU proveri da li je fajl stvarno ukljucen.
# Mora biti isti proces: get_included_files() vidi samo tekuce izvrsavanje.
probe_with_include_check() {
    run_php "require 'vendor/autoload.php';
        try {
            \$o = new \\$1();
            echo 'USPEH: ', \$o->message(), PHP_EOL;
        } catch (\\Throwable \$e) {
            echo get_class(\$e), ': ', \$e->getMessage(), PHP_EOL;
        }
        \$f = realpath('$2');
        echo '    -> fajl ', (\$f && in_array(\$f, get_included_files(), true) ? 'JESTE' : 'NIJE'), ' ucitan', PHP_EOL;"
}

hr() { printf '\n%s\n' '────────────────────────────────────────────────────────────'; }

hr
echo "KONTROLNA TACKA — sve ispravno"
echo "  Domain\\Hello  <-  ExamplePSR/Hello.php  <-  mapiranje Domain\\ => ExamplePSR/"
echo
probe_with_include_check 'Domain\Hello' 'ExamplePSR/Hello.php'

hr
echo "KVAR 1 — POGRESAN PSR-4 MAPPING u composer.json"
echo "  Domain\\ => PogresanFolder/    (fajl Hello.php je netaknut i ispravan)"
echo
python3 - <<'PY'
import json, io, collections
d = json.load(io.open('composer.json'), object_pairs_hook=collections.OrderedDict)
d['autoload']['psr-4']['Domain\\'] = 'PogresanFolder/'
io.open('composer.json', 'w').write(json.dumps(d, indent=4) + '\n')
PY
docker compose exec -T -e XDEBUG_MODE=off app composer dump-autoload --quiet 2>/dev/null
echo "  putanja koju Composer sada racuna (vendor/composer/autoload_psr4.php):"
docker compose exec -T app sh -c "grep \"'Domain\" vendor/composer/autoload_psr4.php" 2>/dev/null | sed 's/^/    /'
echo
probe_with_include_check 'Domain\Hello' 'ExamplePSR/Hello.php'
cp "$BACKUP" composer.json
docker compose exec -T -e XDEBUG_MODE=off app composer dump-autoload --quiet 2>/dev/null

hr
echo "KVAR 2 — POGRESAN NAMESPACE u fajlu"
echo "  ExamplePSR/BrokenNamespace.php deklarise  namespace Domain\\Wrong  umesto  Domain"
echo
probe_with_include_check 'Domain\BrokenNamespace' 'ExamplePSR/BrokenNamespace.php'
echo
echo "  ^ fajl JESTE ucitan — to je ono po cemu se ovaj kvar razlikuje od druga dva."
echo "    Composer je izracunao tacnu putanju i otvorio fajl, ali klasa u njemu"
echo "    nosi drugo ime, pa trazena i dalje ne postoji."

hr
echo "KVAR 3 — POGRESNO IME FAJLA"
echo "  klasa Domain\\MismatchedClass zivi u ExamplePSR/WrongFileName.php"
echo
probe_with_include_check 'Domain\MismatchedClass' 'ExamplePSR/WrongFileName.php'
echo
echo "  fajl postoji na disku, ali pod imenom po kome PSR-4 nikada ne trazi:"
ls -1 ExamplePSR/*.php | sed 's/^/    /'

hr
echo "NAJKORISNIJA DIJAGNOSTIKA — composer dump-autoload -o imenuje kvar 2 i 3"
echo "  Optimizovani autoloader skenira SADRZAJ fajlova i prijavi svaki koji"
echo "  ne postuje PSR-4 pravilo. Runtime greska kaze samo 'not found'; ovo kaze zasto."
echo
docker compose exec -T -e XDEBUG_MODE=off app composer dump-autoload -o 2>&1 \
    | grep -iE 'does not comply|Generated' | sed 's/^/  /'
echo
echo "  U classmap iz ExamplePSR/ je usla samo ispravna klasa:"
docker compose exec -T app sh -c "grep 'ExamplePSR' vendor/composer/autoload_classmap.php" 2>/dev/null | sed 's/^/  /'

hr
echo "Vracam autoloader u normalno stanje..."
# `restore` iz trap-a odradi ostatak.
