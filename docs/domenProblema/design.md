# Design: Domenski model — migracije, Eloquent modeli i seeder

> Status: **Arhitektura (faza 2)** — kako se gradi ono što [`spec.md`](spec.md) traži.
> Ovaj dokument **ne uvodi nijedan nov funkcionalni zahtev.** Sve što ovde stoji realizuje postojeći `R-id`. Ako se pokaže da nedostaje ponašanje, menja se `spec.md`, ne ovaj dokument.
> Izvor istine za šemu ostaje [`data.md`](../../data.md) i ovde se **ne prepisuje**.

---

## 1. Overview

Domenski model je čisto sloj podataka: deset tabela, osam Eloquent modela, jedna repozitorijumska klasa i jedan seeder. Nema kontrolera, ruta ni servisa — `spec.md` ih je izričito stavio van opsega.

Oblik rešenja u jednoj rečenici: **šema se opisuje isključivo migracijama, ponašanje modela isključivo Eloquent konfiguracijom, a tri invarijante koje Eloquent ne ume da iznudi (`connections` ima jedan red, `provider_accounts` je read-only, `api_secret` ne izlazi) štite se strukturom koda — jedan helper, jedan repozitorijum, jedna `$hidden` lista — a ne dogovorom.**

Sve što odstupa od Laravel podrazumevanog (`$timestamps = false`, `UPDATED_AT = null`, pivot bez `id`) posledica je `data.md`, ne stilskog izbora.

---

## 2. Architecture

```
  database/migrations/                      app/Models/
  ┌──────────────────────────┐              ┌───────────────────────────┐
  │ 1. users      (izmena)   │──────────────│ User          UPDATED_AT=null
  │ 2. currencies            │──────────────│ Currency      $timestamps=false
  │ 3. countries             │──────────────│ Country       $timestamps=false
  │ 4. payment_methods       │──────────────│ PaymentMethod $timestamps=false
  │ 5. payment_method_currency ──┐          │                    │  belongsToMany
  │ 6. payment_method_country  ──┤ pivot,   │                    │  (bez model klase)
  │                              │ bez modela                    ▼
  │ 7. logs                  │──────────────│ Log           UPDATED_AT=null
  │ 8. connections (1 red)   │──────────────│ Connection    ::current(), $hidden
  │ 9. settings              │──────────────│ Setting       ::get()/::set()
  │10. provider_accounts     │──────────────│ ProviderAccount  bez $fillable
  └──────────────────────────┘              └──────────┬────────────────┘
                                                       │ jedina tačka pristupa
                                            ┌──────────▼────────────────┐
                                            │ app/Repositories/         │
                                            │ ProviderAccountRepository │
                                            └───────────────────────────┘

  database/seeders/
  ┌──────────────────────────┐
  │ DatabaseSeeder  ─ call ─▶ UserSeeder ─▶ users
  │ (samo orkestracija)      │             (anchor + factory redovi)
  └──────────────────────────┘
```

### 2.1 Sloj migracija — `database/migrations/`

- **Odgovornost:** definisanje šeme, ključeva, indeksa, podrazumevanih vrednosti i referencijalnog ponašanja. Jedini upis podataka koji sloj radi je jedan prazan red u `connections` (R9).
- **Granica:** ne zna ništa o modelima, ne poziva Eloquent. `connections` red se ubacuje kroz `DB::table()`, ne kroz model — migracija ne sme da zavisi od klase koja se u budućnosti može preimenovati ili obrisati.
- **Redosled:** vlasništvo nad redosledom nosi timestamp prefiks imena fajla. Poredak iz `data.md` se preslikava jedan na jedan; nijedan strani ključ ne pokazuje unapred.

| # | Fajl | Tabela |
|---|---|---|
| 1 | `0001_01_01_000000_create_users_table.php` *(izmena postojećeg)* | `users`, `password_reset_tokens`, `sessions` |
| 2 | `2026_09_17_000001_create_currencies_table.php` | `currencies` |
| 3 | `2026_09_17_000002_create_countries_table.php` | `countries` |
| 4 | `2026_09_17_000003_create_payment_methods_table.php` | `payment_methods` |
| 5 | `2026_09_17_000004_create_payment_method_currency_table.php` | `payment_method_currency` |
| 6 | `2026_09_17_000005_create_payment_method_country_table.php` | `payment_method_country` |
| 7 | `2026_09_17_000006_create_logs_table.php` | `logs` |
| 8 | `2026_09_17_000007_create_connections_table.php` | `connections` + 1 red |
| 9 | `2026_09_17_000008_create_settings_table.php` | `settings` |
| 10 | `2026_09_17_000009_create_provider_accounts_table.php` | `provider_accounts` |

Postojeće `0001_01_01_000001_create_cache_table.php` i `0001_01_01_000002_create_jobs_table.php` ostaju netaknute i izvršavaju se između #1 i #2. Nemaju relacije ni na šta iz `data.md`.

### 2.2 Sloj modela — `app/Models/`

Osam klasa pod `App\Models`, po konvenciji iz `laravel-structure` (putanja = namespace = ime klase). Pivot tabele **nemaju** model klase — `belongsToMany` ih adresira imenom tabele.

| Klasa | Odgovornost | Granica |
|---|---|---|
| `User` | identitet; `logs()` | ne zna za HTTP; ne nosi nijednu konfiguracionu vezu (R16) |
| `Log` | zapis događaja; `user()` koji sme da vrati `null` | ne formatira i ne filtrira poruke |
| `Connection` | jedini red konfiguracije; `current()` | **nema** `create()` ni `delete()` putanju (R10) |
| `ProviderAccount` | čitanje validnih naloga | **nema** `$fillable`, pa nema mass assignment putanje upisa (R12) |
| `Setting` | par ključ–vrednost; `get()`/`set()` | ne zna koji ključevi postoje — to je stvar seeder-a, koji je van opsega |
| `PaymentMethod` | metoda plaćanja; `currencies()`, `countries()` | ne zna za redosled prikaza van `sort_order` kolone |
| `Currency`, `Country` | referentni podaci; inverzni `paymentMethods()` | read-only u praksi, ali bez tehničke zabrane — puni ih seeder |

### 2.3 Tačka pristupa — `app/Repositories/ProviderAccountRepository.php`

- **Odgovornost:** jedini kod u `app/` koji sme da postavi upit nad `provider_accounts` (R13).
- **Granica:** čita, nikad ne piše. Ne zna za `connections` — poređenje kredencijala je posao pozivaoca, koji u ovoj iteraciji ne postoji.
- **Zašto klasa a ne statičke metode na modelu:** u fazi 2 tabela izlazi iz aplikacije i postaje zaseban servis. Tada se menja telo ove klase; njen potpis i svi pozivaoci ostaju. Statičke metode na modelu bi u tom trenutku značile da model mora da preživi nestanak svoje tabele.
- **Namespace:** `App\Repositories`, pod postojećim PSR-4 korenom `App\` → `app/`. **Ne dira `composer.json`** i ne traži `composer dump-autoload` za novi koren.

### 2.4 Sloj seeder-a — `database/seeders/`

- `UserSeeder` — **nov fajl**, nosi svu logiku sejanja korisnika: anchor korisnik (`seed-anchor@example.test`, R27) i `FACTORY_USER_COUNT` factory redova, uz `SEED_USER_PASSWORD` iz okruženja.
- `DatabaseSeeder` — postaje **samo orkestrator**: `$this->call([UserSeeder::class])`. Nema sopstvenu logiku (R24).
- `UserFactory` — uklanjaju se `email_verified_at` i `remember_token` iz `definition()`, i metoda `unverified()` koja bez te kolone nema smisla (R26).

Seeder-i za `currencies`, `countries`, `provider_accounts`, `settings` i `payment_methods` su **Non-goal** iz `spec.md`. Njihove tabele posle seed-a ostaju prazne. `DatabaseSeeder::call()` je pripremljena lista — dodavanje seeder-a kasnije je jedan red.

---

## 3. Key flows

### UC1 — Podizanje baze iz čistog klona
1. `docker compose exec app php artisan migrate:fresh --seed`
2. Migracije #1…#10 se izvršavaju redosledom timestamp prefiksa.
3. Migracija #8 posle `Schema::create` radi `DB::table('connections')->insert([])` → jedan red sa svim `NULL` kredencijalima i podrazumevanim `environment = test`, `is_connected = false`.
4. `DatabaseSeeder::run()` poziva `UserSeeder`.
5. `UserSeeder` čita `SEED_USER_PASSWORD`, baca `RuntimeException` ako nije postavljen, hešuje ga **jednom** i pravi anchor korisnika kroz `updateOrCreate` + factory redove.

### UC2 — Konfiguracija konekcije
1. Pozivalac dobija jedini red: `Connection::current()` → `static::query()->first()`.
2. Menja atribute i poziva `save()`.
3. `COUNT(*)` ostaje `1` — jer nijedna putanja u kodu ne zove `create()` ni `firstOrCreate()` (R10, R21).
4. Ako model ide u odgovor, `api_secret` ispada kroz `$hidden` (R11).

### UC3 — Log bez korisnika
1. Kod upisuje `Log` sa `user_id = null` — kolona je `nullable` (R14).
2. `context` se prosleđuje kao PHP niz; `array` cast ga serijalizuje u `json` kolonu.
3. Čitalac zove `$log->user` i dobija `null`, ne izuzetak. `belongsTo` na `null` ključu nikad ne postavlja upit.

### UC4 — Provera kredencijala
1. Pozivalac uzima vrednosti iz `Connection::current()`.
2. Poziva `ProviderAccountRepository` — jedina tačka (R13).
3. Repozitorijum postavlja upit nad `provider_accounts`, filtriran po `is_active` i `environment`.
4. Ništa se ne upisuje (R12).

### UC5 — Prikaz payment metoda
1. `PaymentMethod::with(['currencies', 'countries'])->orderBy('sort_order')->get()`.
2. Eager loading daje **3 upita ukupno** bez obzira na broj metoda (N2), umesto `1 + 2N`.
3. `belongsToMany` na `payment_method_currency` / `payment_method_country`, bez pivot modela.

### UC6 — Brisanje korisnika
1. `DELETE FROM users WHERE id = X`.
2. MySQL primenjuje `ON DELETE SET NULL` sa stranog ključa `logs.user_id`.
3. Log redovi ostaju, `user_id` postaje `NULL` (R15). Nema observer-a, nema koda koji bi mogao da se zaobiđe.

---

## 4. Decisions

Numeracija `D-id` je lokalna za ovaj dokument. Odluke koje su skupe za promenu i tiču se celog projekta promovisane su u zajedničku ADR seriju — nastavak od **ADR-13**, jedan fajl po odluci u [`docs/decisions/`](../decisions/). Tamo stoji puna sekcija *Opcije*; ovde stoji odluka i odbačena alternativa u jednom redu.

### D1 — `users` se dovodi u sklad izmenom postojeće migracije → **[ADR-13](../decisions/ADR-13-users-migracija-se-prepisuje.md)**
- **Chosen:** prepisuje se `Schema::create('users', …)` u `0001_01_01_000000_create_users_table.php`. `password_reset_tokens` i `sessions` u istom fajlu ostaju nedirnuti.
- **Why:** `data.md` je proglašen izvorom istine; šema u kodu mora da se čita isto kao `data.md`. Odluka naručioca (D1 u `spec.md`).
- **Rejected:** nova `ALTER` migracija koja dropuje tri kolone — istorija ostaje linearna, ali čitalac migracija vidi kolone koje ne postoje i mora da prati dva fajla da bi znao šta je `users`.

### D2 — Model se zove `Log`, uprkos sudaru sa `Illuminate\Support\Facades\Log` → **[ADR-15](../decisions/ADR-15-model-log-zadrzava-ime.md)**
- **Chosen:** `App\Models\Log`, tačno kako `data.md` propisuje.
- **Why:** `data.md` je ugovor; preimenovanje modela znači da dokument i kod više ne govore isto.
- **Rejected:** `LogEntry` — uklanja sudar, ali uvodi razliku između imena tabele, imena u dokumentu i imena klase, koju svaki novi čitalac mora da nauči.
- **Mitigation:** sudar je samo u `use` izjavama unutar jednog fajla. Fajl koji koristi oba piše `use App\Models\Log;` i fasadu poziva kao `\Log::` ili preko `logger()` helper-a.

### D3 — `provider_accounts` se čita kroz repozitorijum klasu → **[ADR-14](../decisions/ADR-14-provider-accounts-repozitorijum.md)**
- **Chosen:** `App\Repositories\ProviderAccountRepository`.
- **Why:** `data.md` traži jednu tačku pristupa zbog faze 2. Klasa daje mesto koje se u fazi 2 menja iznutra, bez dodirivanja pozivalaca.
- **Rejected:** statičke metode na `ProviderAccount` modelu — kraće, ali u fazi 2 model ostaje bez tabele; i `Eloquent` scope-ovi, koji ne sprečavaju da neko postavi upit mimo njih.

### D4 — `Connection::current()` nikad ne kreira red
- **Chosen:** `current()` vraća `static::query()->first()`. Red kreira migracija.
- **Why:** invarijanta 1 iz `data.md`. Ako reda nema, to je pokvarena migracija, i treba da bude vidljivo kao `null`, ne tiho zakrpljeno.
- **Rejected:** `firstOrCreate()` — deluje bezbedno, ali pretvara propalu migraciju u tiho kreiran red sa praznim kredencijalima, i time briše jedini signal da je baza u pogrešnom stanju.

### D5 — `ON DELETE SET NULL` na nivou baze, ne u aplikaciji
- **Chosen:** `$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()`.
- **Why:** invarijanta 6 iz `data.md` mora da važi i za `DELETE` pokrenut iz `mysql` klijenta, ne samo kroz Eloquent.
- **Rejected:** `deleting` observer na `User` modelu — radi samo kad brisanje ide kroz model, i tiho se zaobilazi svakim `truncate`-om ili direktnim upitom.

### D6 — Prazan red u `connections` ubacuje migracija kroz `DB::table()`
- **Chosen:** `DB::table('connections')->insert(['environment' => 'test'])` odmah posle `Schema::create`, u istoj migraciji.
- **Why:** invarijanta 1; migracija je jedino mesto koje se garantovano izvrši tačno jednom po bazi.
- **Rejected:** seeder — seed je opcion (`migrate` bez `--seed` je legitiman) i idempotentan po dizajnu, pa bi `connections` ostao prazan u pola scenarija. Odbačen i `Connection::create()` u migraciji, jer bi migracija zavisila od modela.

### D7 — `enum` kolone su MySQL native `ENUM`
- **Chosen:** `$table->enum('environment', ['test', 'live'])`, `$table->enum('type', ['redirect', 'iframe'])`.
- **Why:** `data.md` izričito kaže `enum`; baza odbija nevalidnu vrednost i kad upis ne ide kroz aplikaciju.
- **Rejected:** `varchar` + validacija u aplikaciji — fleksibilnije za buduće vrednosti, ali ostavlja bazi da prihvati bilo šta, što je tačno ono što `data.md` ovom kolonom sprečava.
- **Prihvaćena cena:** dodavanje treće vrednosti traži `ALTER TABLE`. Zabeleženo kao RK6.

### D8 — `logs.level` ostaje `varchar`
- **Chosen:** `$table->string('level')`.
- **Why:** `data.md` u koloni *tip* piše `varchar`, a četiri nivoa nabraja u koloni *pravila*. Specifikacija prati navedeni tip (Q1 u `spec.md`).
- **Rejected:** `enum` sa četiri nivoa — doslednije sa D7, ali bi bilo čitanje između redova `data.md`-a. Q1 je otvoreno pitanje za naručioca; dok ne odgovori, tip ostaje onakav kakav piše.

### D9 — Pivot tabele bez `id` i bez model klase
- **Chosen:** `$table->foreignId(...)->constrained()->cascadeOnDelete()` za obe kolone, plus složeni primarni ključ nad njima.
- **Why:** `data.md` izričito zabranjuje `id` i timestamp kolone. Složeni PK usput sprečava duplu vezu iste metode i iste valute.
- **Rejected:** `$table->id()` iz navike — prekršilo bi R5; i pivot model klasa, koja nema šta da nosi jer pivot nema sopstvenih atributa.
- **Napomena:** `cascadeOnDelete` na pivotu ne krši invarijantu 6 — ona se tiče `logs`, gde je veza na osobu, a ne veze između dva referentna podatka.

### D10 — Sejanje korisnika izlazi iz `DatabaseSeeder` u `UserSeeder`
- **Chosen:** nov fajl `database/seeders/UserSeeder.php`; `DatabaseSeeder` samo `call()`-uje.
- **Why:** odluka naručioca (D2 u `spec.md`). Uz to, `DatabaseSeeder` kao orkestrator znači da dodavanje seeder-a za referentne podatke kasnije ne dira postojeći kod.
- **Rejected:** ostaviti logiku u `DatabaseSeeder` — manje fajlova, ali svaki novi seeder posle toga ili nastavlja da gomila logiku na istom mestu, ili pravi nekonzistentnost.

### D11 — `ANCHOR_EMAIL` konstanta se seli, ne kopira
- **Chosen:** `UserSeeder::ANCHOR_EMAIL` je jedina definicija; `DatabaseSeeder::ANCHOR_EMAIL` se uklanja.
- **Why:** AC-07 i AC-08 iz `docs/spec.md` gađaju tu vrednost. Dve definicije se razilaze prvi put kad neko promeni jednu.
- **Rejected:** ostaviti konstantu u `DatabaseSeeder` kao alias — nula troška danas, dve istine sutra.

### D12 — `varchar` dužine ostaju Laravel podrazumevane
- **Chosen:** `$table->string(...)` bez dužine (255), osim `char(3)` za `currencies.code` i `char(2)` za `countries.code`, koje `data.md` navodi eksplicitno.
- **Why:** `data.md` ne navodi dužine (Q4). Podrazumevana vrednost je poznata i dokumentovana; izmišljanje brojeva bi bilo izmišljanje zahteva.
- **Rejected:** biranje "razumnih" dužina po koloni (npr. `varchar(64)` za `pspid`) — izgleda promišljeno, ali nijedan od tih brojeva ne stoji u `data.md`.

---

## 5. Non-functional requirements

| # | Kategorija | Zahtev | Granica |
|---|---|---|---|
| N1 | Performance | `migrate:fresh --seed` na praznoj bazi | < 30 s u `app` kontejneru, uključujući 1 bcrypt heš i 11 korisnika |
| N2 | Performance | Učitavanje payment metoda sa valutama i zemljama | tačno **3** upita bez obzira na broj metoda (`with(['currencies','countries'])`); nikad `1 + 2N` |
| N3 | Performance | Sejanje korisnika | tačno **1** `Hash::make()` poziv po pokretanju seed-a, ne po korisniku — bcrypt je namerno spor |
| N4 | Performance | Pretraga po prirodnim ključevima (`code`, `key`, `email`) | kroz unique indeks; nijedan `full table scan` na tim kolonama |
| N5 | Performance | Strani ključevi | svaka FK kolona je indeksirana (MySQL to radi automatski uz `constrained()`); `JOIN` preko pivota ne skenira tabelu |
| N6 | Security | `api_secret` u serijalizaciji `Connection` modela | **0** pojavljivanja u `toArray()` / `toJson()` izlazu (R11) |
| N7 | Security | `password` u bazi i serijalizaciji | uvek bcrypt hash (`hashed` cast); **0** pojavljivanja u `toArray()` |
| N8 | Security | Upis u `provider_accounts` iz `app/` | **0** putanja; model bez `$fillable` nema mass assignment ulaz |
| N9 | Security | Kredencijali u `logs.context` | **0** dozvoljenih; pravilo je zabeleženo u modelu kao docblock. Izvršni filter nije u opsegu — vidi Non-goals u `spec.md` i RK7 |
| N10 | Security | Tajne u seeder-u | `SEED_USER_PASSWORD` isključivo iz okruženja; seeder puca ako nije postavljen, nikad ne pada na literal (C-02 iz `docs/spec.md`) |
| N11 | Maintainability | Fajlovi u `app/` koji **postavljaju upit** nad `provider_accounts` | tačno **1** — `ProviderAccountRepository` (R13). `ProviderAccount` model se pominje, ali ne postavlja upit; fajlova koji tabelu pominju je dakle **2**, a upitnih tačaka **1**. Provereno `grep`-om. |
| N12 | Maintainability | Novi PSR-4 korenovi | **0** — sve ide pod postojeći `App\`; `composer.json` se ne menja |
| N13 | Reliability | Ponovljeno pokretanje `db:seed` | idempotentno za anchor korisnika: `COUNT(*) = 1` posle N pokretanja (R25) |
| N14 | Reliability | Rollback | svaka nova migracija ima `down()`; `migrate:rollback` do nule prolazi bez greške (R8) |
| N15 | Observability | Šema logovanja | `logs` nosi `level`, `message`, `context`, `created_at` i opcionu vezu na korisnika. Rotacija, retencija i pisanje u tabelu nisu u opsegu ove faze |

---

## 6. Risks

| # | Rizik | Uticaj | Mitigacija |
|---|---|---|---|
| RK1 | `migrate:fresh` briše postojeću dev bazu | Gubitak lokalnih podataka developera | Komanda se izvršava svesno, u dev okruženju čiji je sadržaj ionako seed. Zabeleženo u `tasks.md` kao korak, ne kao usput. **Prihvaćeno.** |
| RK2 | `App\Models\Log` vs `Illuminate\Support\Facades\Log` | Tiho pogrešan `use` → poziv fasade tamo gde se očekuje model, ili obrnuto | D2: sudar je po fajlu, ne globalan. Docblock na modelu imenuje sudar. Statička analiza bi ga uhvatila, ali nije u opsegu. |
| RK3 | Uklanjanje `email_verified_at` ruši `MustVerifyEmail` | Ako neko kasnije uključi verifikaciju e-maila, Laravel će tražiti kolonu koje nema | `data.md` je izričit da kolone nema. Kad verifikacija bude tražena, to je nov zahtev i ide kroz `spec.md`. **Prihvaćeno, zabeleženo.** |
| RK4 | `sessions.user_id` narušava doslovno čitanje R16 | Lažni pozitiv u proveri "nijedna tabela osim `logs` nema `user_id`" | `sessions` je Laravel framework tabela, van `data.md`, i izričito je van opsega u `spec.md`. AC za R16 je formulisan tako da je izuzima. |
| RK5 | Faza 2 izvlači `provider_accounts` iz aplikacije | Ako se do tada pojave upiti van repozitorijuma, promena postaje prepisivanje | N11 je merljiv `grep`-om i proverava se na svakom PR-u kroz `structure-reviewer`. |
| RK6 | Dodavanje treće vrednosti u `enum` traži `ALTER TABLE` | Migracija umesto izmene konfiguracije | Svesna cena D7. Dve vrednosti po koloni su ono što `data.md` traži; treća je nov zahtev. **Prihvaćeno.** |
| RK7 | Kredencijali u `logs.context` | Tajna u bazi i u svakom backup-u | U ovoj fazi nema koda koji piše logove, pa nema ko da prekrši invarijantu. Pravilo je zapisano u modelu. Filter postaje obavezan **istim PR-om** kojim se doda prvi pisac logova. |
| RK8 | Prazne referentne tabele posle seed-a | `payment_methods` bez valuta i zemalja izgleda kao bug | Svesna posledica Non-goal-a iz `spec.md`. `DatabaseSeeder` nosi komentar koji imenuje šta nedostaje i zašto. |
| RK9 | `settings.key` je rezervisana reč u MySQL-u | Sirov SQL nad tom kolonom puca bez backtick-a | Laravel query builder citira identifikatore automatski. Rizik postoji samo za ručno pisan SQL; zabeleženo u docblock-u modela. |

---

## 7. Traceability

| Requirement | Pokriva |
|---|---|
| R1 | §2.1 sloj migracija, sve tabele #1…#10 |
| R2 | D1 / ADR-13, migracija #1 |
| R3 | §2.1 tabela redosleda, timestamp prefiksi |
| R4 | Migracije #1, #2, #3, #4, #9 — `unique()` |
| R5 | D9, migracije #5, #6 |
| R6 | D7, migracije #3, #8 — `default()` |
| R7 | §2.2 `$timestamps = false` / `UPDATED_AT = null`; migracije bez `timestamps()` |
| R8 | N14; `down()` u svakoj migraciji |
| R9 | D6, migracija #8 |
| R10 | D4, `Connection` model — nema `create`/`delete` putanje |
| R11 | N6, `Connection::$hidden` |
| R12 | N8, `ProviderAccount` bez `$fillable` |
| R13 | D3 / ADR-14, N11, `ProviderAccountRepository` |
| R14 | UC3 flow, migracija #7 `nullable()`, `Log::user()` |
| R15 | D5, migracija #7 `nullOnDelete()` |
| R16 | RK4, §2.2 granica `User` modela |
| R17 | §2.2 — 8 modela, pivot bez klase |
| R18 | §2.2 konfiguracija timestamp-ova |
| R19 | §2.2 cast-ovi po `data.md` §Implementacija |
| R20 | UC5, UC3, UC6 flow-ovi; `belongsToMany` / `hasMany` / `belongsTo` |
| R21 | D4, `Connection::current()` |
| R22 | §2.2 `Setting::get()` / `Setting::set()` |
| R23 | N7, `User` `hashed` cast + `$hidden` |
| R24 | D10, §2.4 `UserSeeder` |
| R25 | N13, `updateOrCreate` u `UserSeeder` |
| R26 | §2.4 izmena `UserFactory` |
| R27 | D11, `UserSeeder::ANCHOR_EMAIL` |

---

## 8. Otvorena pitanja iz faze dizajna

Nijedno pitanje nije blokirajuće. Q1…Q4 iz `spec.md` ostaju otvorena i dizajn ih rešava konzervativno — prateći ono što `data.md` doslovno kaže (D8, D12), ne ono što bi se moglo pretpostaviti.
