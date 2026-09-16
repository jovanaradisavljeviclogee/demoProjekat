# Multi-stage build za Laravel dev okruženje (spec.md 5.5, design.md 5).
# Četiri stage-a: composer-deps -> vendor/, frontend-assets -> public/build,
# runtime -> finalni `app` image, web -> finalni `web` image.
# Svi tagovi su pinovani na major.minor.patch (PIN-01, C-01).

# =============================================================================
# STAGE 1: composer-deps  ->  izlaz: /var/www/html/vendor
# =============================================================================
# Osnova je PHP 8.3 image, a ne `composer:2.8.12`: taj image interno nosi PHP 8.4,
# pa bi `composer install` razrešio zavisnosti za pogrešnu platformu, dok je
# composer.lock generisan pod PHP 8.3.33. Uzima se samo binarni fajl.
FROM php:8.3.33-fpm-bookworm AS composer-deps

COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer

# Composer raspakuje dist arhive kroz ZipArchive ili `unzip`; PHP bazni image nema
# ni jedno ni drugo. `unzip` ostaje isključivo u ovom build stage-u (NFR-04).
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*

# Composer odbija da radi kao root bez ove potvrde. Nije tajna i ne postoji u
# finalnom image-u, jer se slojevi build stage-a ne ugrađuju u runtime.
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# --- GRANICA CACHE-A (design.md 5.3) ------------------------------------------
# Kopiraju se samo manifesti. Sve ispod ove tačke zavisi isključivo od
# composer.json/composer.lock, pa izmena PHP koda ne invalidira `composer install`
# i taj korak ostaje CACHED (FR-44, NFR-03, AC-11).
COPY composer.json composer.lock ./

# `require-dev` se NE izostavlja: faker je potreban factory-jima iz FR-35, bez
# njega `artisan migrate --seed` pada i AC-06 ne prolazi (ugovor 4.5).
# `--no-scripts --no-autoloader`: post-autoload-dump poziva `artisan
# package:discover`, koji zahteva izvorni kod — a on u ovom sloju još ne postoji.
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader
# --- KRAJ GRANICE CACHE-A -----------------------------------------------------

COPY . .

# Autoloader se dovršava tek sada, kada izvorni kod postoji (design.md 5.1, korak 4).
# Optimizacija i preferred-install dolaze iz `config` sekcije composer.json-a i
# ne ponavljaju se ovde.
#
# OČEKIVANA UPOZORENJA — NE ISPRAVLJATI IH.
# Pošto je `optimize-autoloader: true` (composer.json:77), svaki dump je
# optimizovan, pa ovaj korak UVEK ispiše dve linije oblika
# `... does not comply with psr-4 autoloading standard ... Skipping.` za:
#     ExamplePSR/BrokenNamespace.php   — namespace Domain\Wrong umesto Domain
#     ExamplePSR/WrongFileName.php     — class MismatchedClass u fajlu drugog imena
# Ta dva fajla su NAMERNO pokvarena i služe kao nastavni primer; objašnjenje je
# u ExamplePSR/AUTOLOADING-BREAKS.md. Exit kod ostaje 0 i build prolazi ispravno.
# "Čišćenje" ovih upozorenja uništava dve trećine primera.
RUN composer dump-autoload

# =============================================================================
# STAGE 2: frontend-assets  ->  izlaz: /var/www/html/public/build
# =============================================================================
FROM node:24.21.0-bookworm-slim AS frontend-assets

WORKDIR /var/www/html

# --- GRANICA CACHE-A (design.md 5.3) ------------------------------------------
# Isti razlog kao u stage-u 1: `npm ci` zavisi samo od lock fajla, pa izmena
# resources/ ne tera ponovno preuzimanje zavisnosti (FR-45).
COPY package.json package-lock.json ./
RUN npm ci
# --- KRAJ GRANICE CACHE-A -----------------------------------------------------

COPY resources ./resources
COPY vite.config.js ./

RUN npm run build

# =============================================================================
# STAGE 3: runtime  ->  finalni `app` image
# =============================================================================
# Počinje od čistog PHP baznog image-a. Node, npm i node_modules se nikada ne
# kopiraju, pa ne postoje ni u jednom sloju finalnog image-a — to je mehanizam
# koji garantuje FR-41, FR-42, C-06 i AC-05 (brisanje na kraju ne bi pomoglo,
# jer bi fajlovi ostali vidljivi u `docker history`).
FROM php:8.3.33-fpm-bookworm AS runtime

# PHP ekstenzije (FR-12), Xdebug (FR-13) i FastCGI klijent (ADR-09) u jednom
# sloju, sa čišćenjem u istom sloju — inače build zavisnosti i apt keš trajno
# ostaju u istoriji image-a.
#
# `mbstring` iz FR-12 nije naveden: zvanični PHP image ga već nosi statički
# ugrađenog, pa bi ponovna instalacija dala upozorenje "module already loaded"
# pri svakom pozivu PHP-a. Zahtev je ispunjen prisustvom ekstenzije.
#
# apt-mark/ldd obrazac je preuzet iz zvanične dokumentacije PHP image-a: naivan
# `purge --auto-remove` nad *-dev paketima povukao bi i runtime biblioteke
# (npr. libicu72) i polomio `intl`, jer dpkg ne zna da PHP .so fajlovi zavise od
# njih. `libfcgi-bin` se eksplicitno vraća u `manual` da preživi purge.
#
# `awk` korak mora skinuti `/` odnosno `/usr/` prefiks i pretvoriti putanju u
# glob: bookworm je usrmerge sistem (`/lib` -> `/usr/lib`), pa `dpkg-query -S`
# nad putanjom kakvu `ldd` ispisuje ne pogađa ništa za pakete zavedene pod
# `/usr/lib/...` — libpng16-16, libzip4, libjpeg62-turbo i libfreetype6 bi tada
# ostali `auto` i purge bi ih uklonio, pa se `gd` i `zip` ne bi učitali.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfcgi-bin \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip; \
    pecl install xdebug-3.5.3; \
    docker-php-ext-enable xdebug; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark libfcgi-bin; \
    ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*/%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query -S \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

# Composer u runtime-u, da `composer --version` i `php artisan` rade unutar
# kontejnera (FR-14, AC-04).
COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer

# Konfiguracija po mapiranju iz ugovora 4.3. Prefiks `zz-` obezbeđuje da se ovi
# fajlovi učitaju posle podrazumevanih iz baznog image-a.
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/app/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY docker/app/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ARG-ovi stoje neposredno pre upotrebe da promena uid-a ne invalidira skup sloj
# sa ekstenzijama. Ovo su identifikatori, ne tajne (design.md 10.2), pa njihovo
# prisustvo u istoriji image-a ne narušava FR-55/AC-13.
ARG APP_UID=1000
ARG APP_GID=1000

# Vrednosti se usklađuju sa vlasnikom fajlova na hostu; neusklađen uid je najčešći
# način na koji non-root postavka pukne preko bind mount-a (rizik R-02).
RUN set -eux; \
    groupadd --gid "${APP_GID}" appuser; \
    useradd --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /bin/bash appuser

# Ista apsolutna putanja kao u `web` image-u. Različite putanje bi značile da FPM
# ne nalazi fajl koji mu Apache pošalje kroz SCRIPT_FILENAME, pa bi svaki PHP
# zahtev vraćao "File not found" (FR-15, design.md 3.1, rizik R-07).
WORKDIR /var/www/html

RUN set -eux; \
    mkdir -p storage bootstrap/cache; \
    chown appuser:appuser /var/www/html; \
    chown -R appuser:appuser storage bootstrap/cache

# Samo artefakti iz build stage-ova, nikada njihovi alati (FR-46). Ove dve
# putanje su ujedno tačke na koje se montiraju `app-vendor` i `app-build` volume-i
# i iz kojih se oni pune pri prvom startu (ADR-04).
COPY --from=composer-deps --chown=appuser:appuser /var/www/html/vendor ./vendor
COPY --from=frontend-assets --chown=appuser:appuser /var/www/html/public/build ./public/build

# PID 1 mora biti non-root da bi FPM master i worker-i radili kao appuser
# (FR-50, C-07, AC-12).
USER appuser

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# =============================================================================
# STAGE 4: web  ->  finalni `web` image
# =============================================================================
# Debian + apache2 paket umesto httpd image-a (ADR-03). Pošto Apache verzija više
# nije u tagu image-a, pinuju se obe strane: bazni image i verzija paketa
# (PIN-01, PIN-02, rizik R-01).
FROM debian:12.15-slim AS web

# `curl` je HTTP klijent za healthcheck `web` servisa iz compose.yaml; bazni
# Debian slim image nema nijedan, pa servis nikada ne bi postao `healthy` i
# `depends_on` lanac bi stao (FR-06, AC-16).
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        apache2=2.4.68-1~deb12u1 \
        curl; \
    rm -rf /var/lib/apt/lists/*; \
    a2enmod proxy proxy_fcgi rewrite; \
    a2dissite 000-default

# Vhost je zaseban fajl u repozitorijumu, ne inline konfiguracija (FR-24),
# po mapiranju iz ugovora 4.3.
COPY docker/web/vhost.conf /etc/apache2/sites-available/000-app.conf

# Healthcheck sadržaj stoji van DocumentRoot-a i servira se kroz Alias, da
# provera zdravlja `web` servisa ne dodiruje PHP ni bazu (ADR-09).
COPY docker/web/healthz /var/www/healthz/index.html

RUN a2ensite 000-app

# Ista putanja kao u runtime image-u — v. obrazloženje uz WORKDIR u stage-u 3.
WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
