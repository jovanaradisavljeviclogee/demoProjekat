# design.md — Arhitektura administracije payment metoda

> Status: **Arhitektura (faza 2)** — opis kako je sistem sastavljen, pre pisanja koda.
> Ovaj dokument **ne sadrži implementaciju**: nema sadržaja PHP klasa ni React komponenti. Imenuje komponente, definiše odgovornosti, opisuje tokove i obrazlaže odluke.

Ulaz je [`spec.md`](spec.md). Izlaz je [`tasks.md`](tasks.md).

---

## 1. Pregled

Pet slojeva, svaki sa jednom odgovornošću i jasnom granicom prema susedu:

```
  React SPA  ──HTTP/JSON──▶  Rute  ──▶  Kontroler  ──▶  Servis  ──▶  Repozitorijum
 (browser)                          (tanak)      (pravila)   (jedini zna izvor)
                                       ▲
                                       │
                                 Form Request
                                 (validacija)
```

Ključna granica je poslednja: **repozitorijum je jedino mesto koje zna da su podaci u nizu.** Servis, kontroler i React vide interfejs. Zamena niza bazom menja jednu klasu i nijednu drugu (PM-FR-04, PM-FR-06).

---

## 2. Komponente

### 2.1 `PaymentMethodRepositoryInterface`

- **Odgovornost:** ugovor za čitanje i pisanje payment metoda i za listu dozvoljenih kodova.
- **Granica:** ne zna za HTTP, ne zna za validaciju, ne sortira za prikaz.
- **Operacije:** `all`, `find`, `create`, `update`, `setActive`, `delete`, `codeExists`, `allowedCurrencies`, `allowedCountries`.

`codeExists($code, $exceptId = null)` stoji na interfejsu namerno: jedinstvenost koda je svojstvo skupa podataka, a skup zna samo repozitorijum. Da je provera u servisu, servis bi morao da povuče ceo niz — a to je tačno ono znanje o izvoru koje PM-FR-04 zabranjuje.

### 2.2 `ArrayPaymentMethodRepository`

- **Odgovornost:** jedina implementacija ugovora. Nosi statički seed niz i dva niza dozvoljenih kodova. Dodeljuje `id` novoj metodi.
- **Granica:** ne zna ko je poziva.
- **Stanje:** seed je konstanta u klasi; radna kopija je u sesiji (ADR-16).

Dodela `id` je ovde, a ne u servisu: sledeći slobodan broj se ne može izračunati bez uvida u postojeće zapise, a taj uvid po PM-FR-04 ima samo repozitorijum.

### 2.3 `PaymentMethodService`

- **Odgovornost:** jedina zavisnost kontrolera. Sortira listu za prikaz i loguje brisanje.
- **Granica:** ne validira (to je Form Request) i ne zna odakle podaci dolaze (to je repozitorijum).
- **Ključno:** `toggleActive` i `delete` su dve metode koje se **ne pozivaju međusobno** (PM-FR-69, PM-C-04).

### 2.4 `PaymentMethodServiceProvider`

- **Odgovornost:** veže interfejs za `ArrayPaymentMethodRepository`.
- **Granica:** ne radi ništa drugo.

Zaseban provider, ne `AppServiceProvider` (PM-FR-05): kad kasnije stigne baza, izmena je jedna linija u fajlu koji postoji samo zbog ovog vezivanja.

### 2.5 `PaymentMethodController`

- **Odgovornost:** prima zahtev, poziva servis, vraća odgovor.
- **Granica:** bez grananja, bez validacije, bez pristupa nizu.

### 2.6 `StorePaymentMethodRequest` / `UpdatePaymentMethodRequest` / `ToggleActiveRequest`

- **Odgovornost:** sva pravila iz PM-FR-51…57.
- **Granica:** ne upisuju ništa.

Dve klase umesto jedne sa granama: jedina razlika je izuzimanje tekućeg `id` iz provere jedinstvenosti, a grananje po `$this->route()` unutar jednog pravila je tiši izvor greške nego dve eksplicitne klase. Zajednička pravila žive u zaštićenoj osnovnoj klasi.

`ToggleActiveRequest` nosi jedno pravilo — `active` je obavezna logička vrednost. Bez njega `$request->boolean('active')` čita izostanak polja i svaku neprepoznatu vrednost kao `false`, pa prazan ili pokvaren zahtev **gasi** metodu i vraća `200`. Prekidač koji na besmislen ulaz odgovara isključivanjem je tiši kvar od onog koji zahtev odbije, jer poziv izgleda kao da je uspeo.

Iz istog razloga je `active` obavezan i pri čuvanju forme: kao neobavezno polje sa podrazumevanim `false`, izmena koja ga izostavi tiho gasi metodu — a gašenje i izmena su po PM-FR-69 dve različite radnje.

Laravel razrešava Form Request **pre** ulaska u metodu kontrolera. PM-FR-41 („ništa se ne upisuje dok sva pravila ne prođu") time nije stvar discipline nego konstrukcije — kontroler se ne izvrši ako validacija padne.

### 2.7 React SPA

| Komponenta | Odgovornost |
|---|---|
| `App` | rutiranje između tri ekrana |
| `PaymentMethodListPage` | tabela, prazno stanje, poruka o uspehu |
| `PaymentMethodFormPage` | ista forma za create i edit, praćenje izmenjenosti |
| `PaymentMethodTable` | redovi i kolone |
| `ActiveSwitch` | trenutna promena stanja |
| `MultiSelectTags` | višestruki izbor sa uklonjivim tagovima |
| `TypeRadioGroup` | `Redirect` / `iFrame` |
| `DeleteDialog` | modalni dijalog |
| `EmptyState` | objašnjenje i akcija kad nema metoda |
| `FieldError` | poruka uz polje |
| `FlashMessage` | poruka o uspehu |

---

## 3. Ključni tokovi

### 3.1 PM-UC-01 — Pregled liste

1. Browser traži `/admin/payment-methods`; Laravel vraća Blade shell sa mount tačkom.
2. React se montira i traži `GET /admin/api/payment-methods`.
3. Kontroler poziva servis; servis traži `all()` i sortira po `sortOrder`.
4. Odgovor nosi listu i dozvoljene kodove, pa forma kasnije ne traži drugi zahtev.

### 3.2 PM-UC-02 — Switch

1. React šalje `PATCH /admin/api/payment-methods/{id}/active` sa novim stanjem.
2. Kontroler poziva `PaymentMethodService::toggleActive`.
3. Servis poziva `setActive` na repozitorijumu i vraća izmenjenu metodu.
4. React zamenjuje taj jedan red.

Ruta, metoda servisa i metoda repozitorijuma su odvojene od brisanja duž cele putanje — nema zajedničke tačke u kojoj bi se dve radnje mogle spojiti (PM-C-04).

### 3.3 PM-UC-06 — Odbijena validacija

1. React šalje `POST` sa sadržajem forme.
2. Form Request se razrešava, pravilo pada, Laravel vraća `422` sa mapom `errors`.
3. Kontroler se **nikada ne izvrši**; niz je netaknut (PM-AC-14).
4. React mapira `errors` na polja. Vrednosti su u React state-u, pa se ništa ne gubi (PM-FR-59).

### 3.4 PM-UC-07 — Brisanje

1. `Delete` u redu otvara `DeleteDialog` — nijedan zahtev još nije poslat (PM-AC-18).
2. Potvrda šalje `DELETE /admin/api/payment-methods/{id}`.
3. Servis traži metodu, poziva `delete` na repozitorijumu, upisuje log sa `id`, `name`, `code`.
4. React uklanja red i prikazuje poruku o uspehu.

Veze sa valutama i zemljama su polja unutar samog zapisa, pa nestaju zajedno sa njim. Zasebna radnja nije potrebna, ali dijalog to i dalje mora reći (PM-FR-62), jer korisniku obim brisanja nije vidljiv iz strukture podataka.

---

## 4. Rute

| Metoda | Putanja | Odgovornost |
|---|---|---|
| `GET` | `/admin/payment-methods/{any?}` | Blade shell; `where('any', '.*')` hvata i deep link (PM-FR-82) |
| `GET` | `/admin/api/payment-methods` | lista + dozvoljeni kodovi |
| `GET` | `/admin/api/payment-methods/{id}` | jedna metoda |
| `POST` | `/admin/api/payment-methods` | kreiranje |
| `PUT` | `/admin/api/payment-methods/{id}` | izmena |
| `PATCH` | `/admin/api/payment-methods/{id}/active` | uključivanje i isključivanje; traži `active` kao logičku vrednost |
| `DELETE` | `/admin/api/payment-methods/{id}` | brisanje |

Sve stoji u `routes/web.php` — razlog u ADR-17.

Shell ruta se registruje **posle** API ruta. `{any?}` sa `.*` inače proguta i `/admin/api/...`, pa bi svaki JSON poziv vratio HTML.

---

## 5. Odluke

Serija je zajednička sa [`docs/design.md`](../../design.md) i [`docs/decisions/`](../../decisions/). Odluke ovog tiketa su **ADR-16 do ADR-19**, svaka u svom fajlu:

| ADR | Odluka |
|---|---|
| [ADR-16](../../decisions/ADR-16-staticki-niz-u-sesiji.md) | Statički niz je seed, radna kopija u sesiji |
| [ADR-17](../../decisions/ADR-17-json-rute-u-web-php.md) | JSON rute u `web.php`, ne u `api.php` |
| [ADR-18](../../decisions/ADR-18-react-spa-uz-form-request.md) | React SPA uz serverski Form Request i `422` |
| [ADR-19](../../decisions/ADR-19-vite-dev-server-na-hostu.md) | Vite dev server na hostu, ne u kontejneru |

---

## 6. Nefunkcionalni zahtevi

| # | Kategorija | Zahtev | Granica |
|---|---|---|---|
| PM-N-01 | Performanse | Lista se iscrtava bez čekanja na više od jednog zahteva | tačno jedan `GET` pri montiranju; dozvoljeni kodovi stižu u istom odgovoru |
| PM-N-02 | Performanse | Skup je mali i ostaje u memoriji | do ~50 metoda; sortiranje i filtriranje nad nizom, bez petlje koja zove repozitorijum |
| PM-N-03 | Bezbednost | Svaki zahtev koji menja stanje nosi CSRF token | `POST`, `PUT`, `PATCH`, `DELETE` kroz `web` grupu; token iz meta taga |
| PM-N-04 | Bezbednost | Nijedna korisnička vrednost se ne iscrtava kao HTML | React podrazumevano escape-uje; `dangerouslySetInnerHTML` se ne koristi |
| PM-N-05 | Pristupačnost | Dijalog je upotrebljiv tastaturom | `role="dialog"`, `aria-modal`, fokus zarobljen, `Esc` otkazuje |
| PM-N-06 | Observability | Brisanje ostavlja trag | `Log::info` sa `id`, `name`, `code`; deaktivacija ne loguje |
| PM-N-07 | Veličina stanja | Radna kopija nema praktično ograničenje veličine | `file` driver; mereno do 30 metoda bez gubitka upisa (ADR-16) |

---

## 7. Rizici

| ID | Rizik | Uticaj | Mitigacija |
|---|---|---|---|
| **PM-R-01** | `{any?}` shell ruta proguta API putanje | svaki JSON poziv vraća HTML; ceo ekran mrtav | API rute se registruju pre shell rute; test koji traži JSON na `/admin/api/payment-methods` (ugrožen PM-AC-01) |
| **PM-R-02** | ~~Sesijski cookie prekorači 4 KB~~ — **ostvario se pri merenju** | od šeste metode `POST` vraća `201`, a lista se ne menja; `codeExists` propušta duplikate | zatvoreno: `SESSION_DRIVER=file`, v. ADR-16. Provereno do 30 metoda bez gubitka |
| **PM-R-07** | Prekidač ili izmena bez `active` gase metodu tiho | radnja koju pošiljalac nije tražio, uz status `200` | `active` je obavezan u sva tri Form Requesta; PM-AC-28, PM-AC-29 |
| **PM-R-08** | `id` obrisane metode se dodeli novoj | otvoren link `/{id}/edit` počne da menja drugu metodu | monoton brojač u sesiji, nezavisan od sadržaja niza; PM-AC-30 |
| **PM-R-03** | `public/build` volume zadrži stari bundle | izmene JSX-a nevidljive, build „prošao" | ADR-19; README sekcija 8 već opisuje uklanjanje `dockertask_app-build` |
| **PM-R-04** | Toggle implementiran preko `update` | deaktivacija i brisanje se spajaju, PM-C-04 pao | odvojena ruta, odvojena metoda servisa, odvojena metoda repozitorijuma; PM-AC-24 |
| **PM-R-05** | `code` unique provera zaboravi izuzeće pri izmeni | metoda ne može da sačuva sopstveni kod | dve Form Request klase umesto grananja; PM-AC-10 |
| **PM-R-06** | Brisanje pozvano bez dijaloga | PM-C-03, defekt po specifikaciji | dijalog je jedini put do `DELETE` poziva; PM-AC-18 |

---

## 8. Traceability

| Zahtev | Pokriva |
|---|---|
| PM-FR-01…03 | `ArrayPaymentMethodRepository` |
| PM-FR-04…06 | `PaymentMethodRepositoryInterface` + `PaymentMethodServiceProvider` |
| PM-FR-07 | `PaymentMethodController` |
| PM-FR-10…20 | rute + `PaymentMethodListPage`, `PaymentMethodTable`, `ActiveSwitch`, `EmptyState` |
| PM-FR-30…42 | `PaymentMethodFormPage`, `MultiSelectTags`, `TypeRadioGroup`, `FieldError` |
| PM-FR-50…59 | `StorePaymentMethodRequest`, `UpdatePaymentMethodRequest`, tok 3.3 |
| PM-FR-60…68 | `DeleteDialog`, `PaymentMethodService::delete`, tok 3.4 |
| PM-FR-69 | tok 3.2 i 3.4, razdvojeni duž cele putanje |
| PM-FR-80…82 | `App`, shell ruta sa `{any?}` |

---

## 9. Otvorena pitanja

| ID | Pitanje | Status | Odluka |
|---|---|---|---|
| PM-Q-04 | Da li shell ruta treba da bude zaštićena autentikacijom | **otvoreno** | Van opsega ovog tiketa (spec 2.2). Otvara se kad stigne admin autentikacija. |

> Nove nejasnoće se upisuju ovde i razrešavaju sa naručiocem — ne rešavaju se pretpostavkom.
