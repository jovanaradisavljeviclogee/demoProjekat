# ADR-17 — JSON rute payment metoda stoje u `routes/web.php`, ne u `routes/api.php`

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** React SPA komunicira sa serverom kroz JSON endpointe. Laravel 12 nudi zaseban `routes/api.php` sa sopstvenom middleware grupom, ali taj fajl u ovom projektu ne postoji, a `bootstrap/app.php` nema `api:` argument u `withRouting` — instalacija je minimalna, bez `install:api`. Uz to, po [ADR-16](ADR-16-staticki-niz-u-sesiji.md) radna kopija podataka stoji u sesiji, a `api` grupa je stateless i sesiju ne pokreće.

**Opcije:** (a) sve rute u `routes/web.php`; (b) dodati `routes/api.php`, registrovati ga u `bootstrap/app.php` i uvesti Sanctum za sesijsku autentikaciju; (c) dodati `routes/api.php` i na njega ručno zakačiti `StartSession` i `VerifyCsrfToken`.

**Odluka:** (a).

**Obrazloženje:** (b) rešava problem koji ovaj tiket nema. Sanctum postoji zbog tokena i zbog SPA na drugom domenu; ovde su React i Laravel na istom poreklu i sesijski kolačić već radi. Uvođenje paketa, njegove konfiguracije i njegovih migracija samo da bi se povratilo ono što `web` grupa daje besplatno je čista neto šteta — a migracije bi prekršile i zabranu baze iz PM-C-01.

(c) je (b) bez paketa, ali proizvodi `api.php` koji ima tačno middleware `web` grupe. To je `web` grupa pod drugim imenom, i sledeći čitalac mora da otkrije zašto se dve iste stvari zovu različito.

Ostaje cena koju (a) nosi: naziv fajla više ne razdvaja HTML od JSON-a. Razdvaja ih putanja — `/admin/api/...` naspram `/admin/payment-methods`, i to je vidljivo na svakoj liniji.

**Posledice:** redosled registracije postaje obavezujući. Shell ruta `/admin/payment-methods/{any?}` sa `where('any', '.*')` postoji zbog PM-FR-82, i ako se registruje pre API ruta, proguta i njih — svaki JSON poziv bi vratio HTML, a ekran bi bio potpuno mrtav uz odgovor sa statusom 200. Zato API rute idu prve, to je zapisano kao rizik PM-R-01, i testom se proverava da `/admin/api/payment-methods` vraća JSON. Kad projekat kasnije dobije pravi javni API, on dobija `routes/api.php` i ova odluka se ne primenjuje na njega.
