# tasks.md — Plan implementacije administracije payment metoda

> Status: **Plan izvršenja (faza 3)** — razbijanje implementacije na taskove sa isključivim vlasništvom nad fajlovima.
> Ulaz su [`spec.md`](spec.md) i [`design.md`](design.md). Nijedan task ne sme uvesti zahtev kojeg u njima nema.

---

## 1. Nepregovaračka pravila

- **PM-P-01 — Zabrana izmišljanja.** Nijedna funkcionalnost, fajl, paket ni polje koje ne proizlazi iz `spec.md` ili `design.md`.
- **PM-P-02 — Isključivo vlasništvo.** Task piše samo u fajlove navedene pod *Vlasništvo*. Nijedan fajl nema dva vlasnika u istom talasu.
- **PM-P-03 — Ugovor o API-ju je zaključan.** Sekcija 2 je jedini izvor istine o oblicima zahteva i odgovora. Backend i frontend se pišu paralelno protiv nje.
- **PM-P-04 — Bez baze i bez fajlova.** Nijedna migracija, nijedan model, nijedan fajl kao skladište (PM-C-01, PM-C-02).
- **PM-P-05 — Git je van opsega.** Nijedan task ne commituje i ne pushuje. To radi vlasnica projekta.

---

## 2. Ugovor o API-ju

Zajednički oblik jedne metode, u svakom odgovoru:

```json
{ "id": 1, "name": "Bancontact", "code": "BCMC", "type": "redirect",
  "currencies": ["EUR"], "countries": ["BE"], "sortOrder": 1, "active": true }
```

| Metoda | Putanja | Telo zahteva | Odgovor |
|---|---|---|---|
| `GET` | `/admin/api/payment-methods` | — | `{ "data": [metoda…], "currencies": ["EUR"…], "countries": ["BE"…] }`, `data` sortirano po `sortOrder` rastuće |
| `GET` | `/admin/api/payment-methods/{id}` | — | `{ "data": metoda }` ili `404` |
| `POST` | `/admin/api/payment-methods` | sva polja osim `id` | `201` `{ "data": metoda }` ili `422` |
| `PUT` | `/admin/api/payment-methods/{id}` | sva polja osim `id` | `200` `{ "data": metoda }` ili `422` / `404` |
| `PATCH` | `/admin/api/payment-methods/{id}/active` | `{ "active": true\|false }` — obavezno | `200` `{ "data": metoda }`, `422` ako `active` nedostaje ili nije logička vrednost, ili `404` |
| `DELETE` | `/admin/api/payment-methods/{id}` | — | `200` `{ "message": "…" }` ili `404` |

Oblik greške je Laravel standard: `422` sa `{ "message": "…", "errors": { "code": ["…"], "currencies.0": ["…"] } }`.

`type` je `redirect` ili `iframe` — u interfejsu se prikazuju kao `Redirect` i `iFrame`.

**Početni niz** (PM-Q-01, odobreno):

| id | name | code | type | currencies | countries | sortOrder | active |
|---|---|---|---|---|---|---|---|
| 1 | Bancontact | BCMC | redirect | EUR | BE | 1 | true |
| 2 | iDEAL | IDEAL | redirect | EUR | NL | 2 | true |
| 3 | Credit Card | CARD | iframe | EUR, USD, GBP | BE, NL, DE, GB, US | 3 | true |
| 4 | PayPal | PAYPAL | redirect | EUR, USD | BE, NL, DE, US | 4 | false |
| 5 | SOFORT | SOFORT | redirect | EUR | DE, AT | 5 | true |

Dozvoljene valute: `EUR, USD, GBP, CHF, RSD` · Dozvoljene zemlje: `BE, NL, DE, AT, GB, US, RS`

---

## 3. Matrica vlasništva

| Fajl | Task |
|---|---|
| `app/Repositories/*` | T-01 |
| `app/Providers/PaymentMethodServiceProvider.php`, `bootstrap/providers.php` | T-01 |
| `package.json`, `vite.config.js`, `resources/views/admin/payment-methods.blade.php` | T-02 |
| `app/Services/*`, `app/Http/Requests/*`, `app/Http/Controllers/Admin/*`, `routes/web.php` | T-03 |
| `resources/js/**` | T-04 |
| `tests/Feature/PaymentMethodTest.php` | T-05 |
| `docs/decisions/ADR-16…ADR-19`, `LEARNINGS.md`, `README.md` | T-06 |

---

## 4. Taskovi

### T-01 — Sloj podataka
**Talas:** 1 · **Vlasništvo:** `app/Repositories/PaymentMethodRepositoryInterface.php`, `app/Repositories/ArrayPaymentMethodRepository.php`, `app/Providers/PaymentMethodServiceProvider.php`, `bootstrap/providers.php`

**Cilj**
Statički niz iza interfejsa, sa implementacijom vezanom u sopstvenom provideru.

**Obavezno**
- Seed niz i dva niza dozvoljenih kodova iz sekcije 2, kao konstante klase.
- Radna kopija u sesiji; seed se koristi kad sesija još nema ključ (ADR-16).
- `codeExists($code, $exceptId = null)` na interfejsu.
- `setActive` i `delete` su odvojene metode; nijedna ne poziva drugu.
- Provider registrovan u `bootstrap/providers.php`.

**Realizuje:** PM-FR-01…06, PM-C-05
**Definicija završenosti:** `docker compose exec app php artisan tinker --execute='dd(app(App\Repositories\PaymentMethodRepositoryInterface::class)->all());'` ispisuje pet metoda.

**Zabrane:** nijedan `use Illuminate\Http\*`; nijedno sortiranje za prikaz.

---

### T-02 — React toolchain i shell
**Talas:** 1 · **Vlasništvo:** `package.json`, `vite.config.js`, `resources/views/admin/payment-methods.blade.php`

**Cilj**
Postaviti React build i Blade stranicu na koju se SPA montira.

**Obavezno**
- `react`, `react-dom`, `react-router-dom`, `@vitejs/plugin-react` u `package.json`.
- `vite.config.js`: `react()` plugin, ulaz `resources/css/app.css` i `resources/js/app.jsx`.
- Blade shell: `<meta name="csrf-token">`, `@vite`, `<div id="app">`.

**Realizuje:** PM-FR-80
**Definicija završenosti:** `npm run build` na hostu prolazi bez greške.

**Zabrane:** ne dirati `resources/js/app.js` ni `bootstrap.js` (vlasništvo T-04).

---

### T-03 — Servis, validacija, kontroler, rute
**Talas:** 2 · **Vlasništvo:** `app/Services/PaymentMethodService.php`, `app/Http/Requests/PaymentMethodRequest.php`, `app/Http/Requests/StorePaymentMethodRequest.php`, `app/Http/Requests/UpdatePaymentMethodRequest.php`, `app/Http/Controllers/Admin/PaymentMethodController.php`, `routes/web.php` · **Zavisi od:** T-01

**Cilj**
Izložiti ugovor iz sekcije 2 kroz tanak kontroler i jedan servis.

**Obavezno**
- Kontroler injektuje **samo** `PaymentMethodService`.
- Sva pravila iz PM-FR-51…57 u Form Requestu; `Update` izuzima `{id}` iz provere jedinstvenosti.
- `toggleActive` i `delete` su odvojene metode servisa koje se ne pozivaju međusobno.
- `Log::info` samo pri uspešnom brisanju, sa `id`, `name`, `code`.
- API rute registrovane **pre** shell rute `{any?}` (rizik PM-R-01).

**Realizuje:** PM-FR-07, PM-FR-10…12, PM-FR-31, PM-FR-41, PM-FR-50…58, PM-FR-67…69, PM-FR-82
**Definicija završenosti:** `curl -s localhost:8080/admin/api/payment-methods` vraća JSON sa pet metoda sortiranih po `sortOrder`.

**Zabrane:** nijedna validacija u kontroleru ili servisu; nijedan pristup nizu mimo repozitorijuma.

---

### T-04 — React aplikacija
**Talas:** 2 · **Vlasništvo:** `resources/js/**` · **Zavisi od:** T-02

**Cilj**
Sva tri ekrana protiv ugovora iz sekcije 2.

**Obavezno**
- Rute: `/admin/payment-methods`, `/create`, `/:id/edit`.
- Tabela sa kolonama tačno ovim redom: order, name, code, type, currencies, countries, active, actions.
- Switch menja stanje odmah; neaktivan red zatamnjen; metoda ostaje u listi.
- Prazno stanje sa objašnjenjem i `Add payment method`.
- Ista forma za create i edit; naslov `New payment method` ili naziv metode.
- Multi-select sa uklonjivim tagovima; radio par `Redirect` / `iFrame`.
- `422` se mapira na polja; unete vrednosti ostaju.
- `Cancel` traži potvrdu samo ako je forma menjana.
- `DeleteDialog`: naslov, dva pasusa iz PM-FR-62 i PM-FR-63, `Cancel` levo i crveni `Delete` desno, `role="dialog"`, `aria-modal`, zarobljen fokus, `Esc` otkazuje.

**Realizuje:** PM-FR-13…20, PM-FR-30…42, PM-FR-59…66, PM-FR-81
**Definicija završenosti:** svih osam ručnih provera iz `spec.md` sekcije 6 prolazi u browseru.

**Zabrane:** nijedan `dangerouslySetInnerHTML`; brisanje se ne poziva nigde osim iz dijaloga.

---

### T-05 — Testovi
**Talas:** 3 · **Vlasništvo:** `tests/Feature/PaymentMethodTest.php` · **Zavisi od:** T-03

**Cilj**
Pokriti svaki automatski proverljiv acceptance criterion.

**Realizuje:** PM-AC-01, PM-AC-09…14, PM-AC-23, PM-AC-24
**Definicija završenosti:** `docker compose exec app php artisan test` prolazi u celosti.

---

### T-06 — Dokumentacija
**Talas:** 3 · **Vlasništvo:** `docs/decisions/ADR-16…ADR-19`, `LEARNINGS.md`, `README.md`

**Cilj**
Zatvoriti pravilo iz `CLAUDE.md`: dokumentacija se ažurira u istom pull request-u kao kod.

**Obavezno**
- Četiri ADR-a, svaki sa obaveznom sekcijom **Opcije**.
- Nova datirana stavka u `LEARNINGS.md`, najnovija na vrhu.
- `README.md`: kako se pokreće frontend u razvoju (ADR-19).

**Definicija završenosti:** nijedan ADR nema praznu sekciju *Opcije*; numeracija se nastavlja od ADR-12 bez preskoka.

---

## 5. Talasi

```
TALAS 1   T-01 (sloj podataka)        T-02 (React toolchain)
             │                           │
TALAS 2   T-03 (backend HTTP)  ◀────┘   T-04 (React aplikacija)
             │
TALAS 3   T-05 (testovi)              T-06 (dokumentacija)
```

Nijedan par u istom talasu ne deli nijedan fajl. T-03 i T-04 se pišu paralelno protiv zaključanog ugovora iz sekcije 2 — to je jedini razlog zbog kog taj ugovor postoji.

---

## 6. Završni ciklus

Svaki task pre „done" prolazi, tim redom: `code-review` → `security-review` → `performance-review` → `simplify`. Nalazi u fajlovima van vlasništva taska se **prijavljuju, ne popravljaju**.

Posle poslednjeg talasa glavni agent pokreće ista četiri pregleda nad celom izmenom i prolazi kroz svaki `PM-AC-` iz `spec.md`.

---

## 7. Otvorena pitanja

| ID | Pitanje | Status |
|---|---|---|
| — | nema | — |

> Nove nejasnoće se upisuju ovde i razrešavaju sa naručiocem — ne rešavaju se pretpostavkom.
