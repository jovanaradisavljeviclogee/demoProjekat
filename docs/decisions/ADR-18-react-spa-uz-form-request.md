# ADR-18 — Validacija ostaje u Form Requestu i vraća `422`, forma se ne iscrtava na serveru

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** tiket traži da frontend bude React single-page aplikacija, a istovremeno opisuje validaciju jezikom serverskog Blade toka: „Validate in a Form Request", „the form is redisplayed with every entered value kept", „the error shown at code field". Ponovno iscrtavanje forme sa zadržanim vrednostima je opis Laravelovog `back()->withInput()` obrasca, koji podrazumeva da stranicu sastavlja server. SPA je po definiciji ne sastavlja.

**Opcije:** (a) Form Request ostaje na serveru i vraća `422` sa mapom `errors`, React je mapira na polja; (b) Inertia.js, pa kontroleri vraćaju props-e a validacija radi kroz standardni redirect-back tok; (c) klasične Blade stranice sa React ostrvima za tabelu, formu i dijalog.

**Odluka:** (a).

**Obrazloženje:** (c) otpada prvo — to nije single-page aplikacija, pa obara PM-FR-80 i PM-FR-81 da bi doslovno ispunilo rečenicu o ponovnom iscrtavanju forme. Kad se dva zahteva sudare, pada onaj koji je opis mehanizma, ne onaj koji je opis proizvoda.

(b) je ozbiljan kandidat: Inertia postoji upravo da bi serverski validacioni tok radio sa React komponentama, i tekst tiketa bi ispunila gotovo od reči do reči. Odbačena je zbog cene koja ne pripada ovom tiketu — nov paket na obe strane, sopstveni sloj za rutiranje i deljenje stanja, i ekran koji više nije SPA nego Inertia aplikacija. Za tri ekrana nad pet zapisa to je infrastruktura teža od funkcionalnosti koju nosi.

Ono što (a) rešava bolje nego što izgleda: zahtev „svaka uneta vrednost je zadržana" u SPA nije ni potrebno ispuniti — vrednosti nikada ne napuštaju React state, pa se nemaju gde izgubiti. Serverski tok mora da ih vraća kroz `withInput` baš zato što se stranica gradi iznova. Suština zahteva je da korisnik ne prekucava formu, i ona je ispunjena jače, ne slabije.

Bitno je da odluka **ne** pomera validaciju u browser. Sva pravila iz PM-FR-51…57 ostaju u Form Request klasama na serveru. Laravel ih razrešava pre ulaska u metodu kontrolera, pa PM-FR-41 — „ništa se ne upisuje dok sva pravila ne prođu" — važi po konstrukciji, a ne po disciplini onoga ko piše kontroler.

**Posledice:** klijent mora tražiti `Accept: application/json`, inače Laravel na neuspelu validaciju odgovara redirekcijom umesto statusom `422` i React ne dobija ništa upotrebljivo. Ključevi grešaka za nizove stižu u tačkastom obliku (`currencies.0`), pa ih React mora svesti na ime polja da bi poruka stala uz pravi kontroler. Provera na strani klijenta se ne dodaje: dva mesta sa istim pravilima razilaze se, a serversko je ono koje odlučuje.
