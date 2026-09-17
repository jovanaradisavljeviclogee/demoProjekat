# Tasks: Domenski model — migracije, Eloquent modeli i seeder

> Status: **Plan izvršenja (faza 3)** — razbijanje [`design.md`](design.md) na jedinice rada sa isključivim vlasništvom nad fajlovima.
> Ovaj dokument **ne uvodi nijedan nov zahtev.** Svaki task pokriva bar jedan `R-id` iz [`spec.md`](spec.md); nijedan `R-id` nije bez taska.

---

## 1. Pravila koja važe za svakog izvršioca

- **P-01 — Isključivo vlasništvo nad fajlovima.** Subagent piše **samo** u fajlove iz svog *Files touched*. Nijedan fajl nema dva vlasnika u istom talasu. Ako task zatreba fajl van svoje liste, to je **blokada koja se prijavljuje**, ne nešto u šta se tiho proširi.
- **P-02 — `data.md` je ugovor.** Imena tabela, kolona, tipovi, default vrednosti i `$hidden`/cast lista uzimaju se doslovno iz [`data.md`](../../data.md). Ništa se ne „poboljšava".
- **P-03 — Zabrana izmišljanja.** Nijedna kolona, indeks, relacija, metoda ni fajl koji ne proizlazi iz `spec.md` ili `design.md`.
- **P-04 — Nema novih PSR-4 korenova.** `composer.json` se ne dira (N12). Sve ide pod postojeći `App\` → `app/`.
- **P-05 — Sve komande idu kroz `app` kontejner.** `docker compose exec app php artisan …` / `composer …`. Nikad na hostu, nikad u `web`.
- **P-06 — Obavezan završni ciklus.** Nijedan task nije gotov dok izvršilac nad svojim radom ne odradi *code review*, *simplify* i *performance review*.
- **P-07 — Eskalacija umesto pretpostavke.** Nejasnoća se prijavljuje; rad na tom delu staje.

### 1.1 Zašto se u talasima 1 i 2 ne pokreće `migrate`

Šema je proverljiva samo kao celina: `payment_method_currency` ima strani ključ na `currencies`, `logs` na `users`. Dok svi fajlovi iz oba talasa ne postoje, `migrate:fresh` puca na tabeli koju još niko nije napisao — a to nije signal o kvalitetu taska koji ga je pokrenuo.

Zato je podela odgovornosti eksplicitna:

| Talas | Šta se proverava | Kako |
|---|---|---|
| 1 i 2 | da je **fajl** tačan | `php -l`, čitanje fajla uz `data.md`, `grep` |
| 3 | da je **baza** tačna | `migrate:fresh --seed` i svaki AC iz `spec.md` nad živom bazom |

Acceptance criteria koji traže bazu (R1, R3, R4, R6, R7, R8, R9) zato stoje i pod T9. To nije duplo pokrivanje nego podela: task piše, integracija dokazuje.

---

## 2. Model izvršavanja

```
  TALAS 1 — tabele bez stranih ključeva (5 subagenata PARALELNO)
  ┌──────────┬──────────┬──────────┬──────────┬──────────────┐
  │ T1       │ T2       │ T3       │ T4       │ T5           │
  │ users    │currencies│connections│ settings │provider_accs │
  │ +model   │+countries│ +1 red    │          │ +repozitorijum│
  │ +factory │ +modeli  │ +model    │ +model   │ +model       │
  └────┬─────┴────┬─────┴──────────┴──────────┴──────────────┘
       │          │
       │          └──────────────┐
       ├─────────────────┐       │
       ▼                 ▼       ▼
  TALAS 2 — tabele sa stranim ključevima + seeder (3 PARALELNO)
  ┌──────────────┬───────────────────────┬──────────────────┐
  │ T6  logs     │ T7 payment_methods    │ T8  UserSeeder   │
  │ FK → users   │    + 2 pivot tabele   │     DatabaseSeeder│
  │ (dep: T1)    │    FK → currencies,   │     (dep: T1)    │
  │              │       countries       │                  │
  │              │    (dep: T2)          │                  │
  └──────┬───────┴───────────┬───────────┴────────┬─────────┘
         └───────────────────┼────────────────────┘
                             ▼
  TALAS 3 — integracija i dokaz (GLAVNI AGENT)
  ┌────────────────────────────────────────────────────────┐
  │ T9  migrate:fresh --seed, provera AC-a za R1…R27,      │
  │     zapis izveštaja, LEARNINGS.md                      │
  └────────────────────────────────────────────────────────┘
```

---

## 3. Wave 1 — tabele bez stranih ključeva

### T1 — `users` se dovodi u sklad sa `data.md`
- **Goal:** `users` ima tačno pet kolona iz `data.md`, `User` model upisuje samo `created_at` i nosi relaciju ka logovima, a factory više ne pominje kolone kojih nema.
- **Covers:** R1, R2, R4, R7, R8, R16, R17, R18, R20, R23, R26
- **Files touched:**
  - `database/migrations/0001_01_01_000000_create_users_table.php` *(izmena)*
  - `app/Models/User.php` *(izmena)*
  - `database/factories/UserFactory.php` *(izmena)*
- **Depends on:** —
- **Obavezno:**
  - Iz `Schema::create('users')` uklanjaju se `email_verified_at`, `rememberToken()` i `timestamps()`; ostaje `$table->timestamp('created_at')->nullable()`. `email` zadržava `unique()`. **`password_reset_tokens` i `sessions` u istom fajlu se ne diraju** (ADR-13).
  - `User`: `const UPDATED_AT = null;`, `hashed` cast za `password`, `password` u `$hidden`, `logs(): HasMany` ka `App\Models\Log`. Uklanja se `email_verified_at` cast i `remember_token` iz `$hidden`.
  - `UserFactory::definition()` gubi `email_verified_at` i `remember_token`; metoda `unverified()` se uklanja jer bez kolone nema šta da radi.
- **Done when:**
  - `php -l` prolazi nad sva tri fajla.
  - `grep -n 'email_verified_at\|remember_token\|rememberToken\|timestamps()' database/migrations/0001_01_01_000000_create_users_table.php` ne vraća nijedan pogodak unutar `Schema::create('users'` bloka.
  - `grep -n 'email_verified_at\|remember_token' app/Models/User.php database/factories/UserFactory.php` vraća 0 pogodaka.
  - `app/Models/User.php` sadrži `UPDATED_AT = null`, `'password' => 'hashed'`, `'password'` u `$hidden` i metodu `logs()`.

### T2 — Referentne tabele `currencies` i `countries`
- **Goal:** obe referentne tabele postoje sa unique ISO kodom, i njihovi modeli nose inverznu vezu ka payment metodama.
- **Covers:** R1, R4, R7, R8, R17, R18, R20
- **Files touched:**
  - `database/migrations/2026_09_17_000001_create_currencies_table.php` *(nov)*
  - `database/migrations/2026_09_17_000002_create_countries_table.php` *(nov)*
  - `app/Models/Currency.php` *(nov)*
  - `app/Models/Country.php` *(nov)*
- **Depends on:** —
- **Obavezno:**
  - `currencies`: `id`, `char('code', 3)->unique()`, `string('name')`. Bez `timestamps()`.
  - `countries`: `id`, `char('code', 2)->unique()`, `string('name')`. Bez `timestamps()`.
  - Oba modela: `public $timestamps = false;`, `$fillable = ['code', 'name']`, `paymentMethods(): BelongsToMany` ka `PaymentMethod::class` preko `payment_method_currency` odnosno `payment_method_country`.
  - Obe migracije imaju `down()` sa `dropIfExists`.
- **Done when:**
  - `php -l` prolazi nad sva četiri fajla.
  - `grep -c 'timestamps()' ` nad obe migracije vraća `0`.
  - Obe migracije sadrže `->unique()` na `code`, i `char(` sa dužinom `3` odnosno `2`.
  - Oba modela sadrže `$timestamps = false` i `belongsToMany` sa tačnim imenom pivot tabele.

### T3 — `connections`: tabela sa tačno jednim redom i model bez `create` putanje
- **Goal:** `connections` postoji sa podrazumevanim vrednostima iz `data.md`, migracija ubacuje jedan prazan red, a model ga vraća bez ikakve mogućnosti da napravi drugi.
- **Covers:** R1, R6, R7, R8, R9, R10, R11, R17, R18, R19, R21
- **Files touched:**
  - `database/migrations/2026_09_17_000007_create_connections_table.php` *(nov)*
  - `app/Models/Connection.php` *(nov)*
- **Depends on:** —
- **Obavezno:**
  - Kolone: `id`, `string('pspid')->nullable()`, `string('api_key')->nullable()`, `string('api_secret')->nullable()`, `enum('environment', ['test','live'])->default('test')`, `boolean('is_connected')->default(false)`, `timestamp('verified_at')->nullable()`. Bez `timestamps()`.
  - Odmah posle `Schema::create`, u istoj migraciji: `DB::table('connections')->insert(['environment' => 'test'])`. **Ne preko modela** (ADR / D6).
  - `Connection`: `$timestamps = false`, casts `is_connected => boolean`, `verified_at => datetime`, `$hidden = ['api_secret']`, i `public static function current(): ?self` koja vraća `static::query()->first()`.
  - **Zabranjeno:** `create()`, `firstOrCreate()`, `updateOrCreate()`, `delete()` bilo gde u vezi sa ovim modelom.
- **Done when:**
  - `php -l` prolazi nad oba fajla.
  - Migracija sadrži `DB::table('connections')->insert(` i **ne** sadrži `Connection::`.
  - `grep -nE '::(create|firstOrCreate|updateOrCreate|delete)\(' app/Models/Connection.php` vraća 0 pogodaka.
  - `app/Models/Connection.php` sadrži `'api_secret'` unutar `$hidden` i metodu `current()`.

### T4 — `settings`: tabela i helperi za čitanje/upis po ključu
- **Goal:** `settings` drži parove ključ–vrednost sa unique ključem, a model nudi čitanje sa fallback-om i upis.
- **Covers:** R1, R4, R7, R8, R17, R18, R22
- **Files touched:**
  - `database/migrations/2026_09_17_000008_create_settings_table.php` *(nov)*
  - `app/Models/Setting.php` *(nov)*
- **Depends on:** —
- **Obavezno:**
  - Kolone: `id`, `string('key')->unique()`, `string('value')->nullable()`. Bez `timestamps()`.
  - `Setting`: `$timestamps = false`, `$fillable = ['key', 'value']`, `public static function get(string $key, ?string $default = null): ?string` i `public static function set(string $key, ?string $value): void` (kroz `updateOrCreate` po `key`).
  - Docblock koji imenuje da je `key` rezervisana reč u MySQL-u i da ručno pisan SQL traži backtick-e (RK9).
- **Done when:**
  - `php -l` prolazi nad oba fajla.
  - Migracija sadrži `->unique()` na `key` i ne sadrži `timestamps()`.
  - `app/Models/Setting.php` sadrži statičke metode `get` i `set` i `$timestamps = false`.

### T5 — `provider_accounts`: read-only tabela, model bez `$fillable` i jedina tačka pristupa
- **Goal:** `provider_accounts` postoji, model nema nijednu putanju upisa, i svaki upit nad tom tabelom živi u tačno jednom fajlu.
- **Covers:** R1, R7, R8, R12, R13, R17, R18, R19
- **Files touched:**
  - `database/migrations/2026_09_17_000009_create_provider_accounts_table.php` *(nov)*
  - `app/Models/ProviderAccount.php` *(nov)*
  - `app/Repositories/ProviderAccountRepository.php` *(nov)*
- **Depends on:** —
- **Obavezno:**
  - Kolone: `id`, `string('pspid')`, `string('api_key')`, `string('api_secret')`, `enum('environment', ['test','live'])`, `boolean('is_active')`. Bez `timestamps()`.
  - `ProviderAccount`: `$timestamps = false`, cast `is_active => boolean`, **bez `$fillable`** i **bez `$guarded`** — aplikacija ne piše u ovu tabelu. Docblock koji to imenuje i upućuje na fazu 2.
  - `ProviderAccountRepository` u namespace-u `App\Repositories`: metode koje **samo čitaju** — nalaženje aktivnog naloga po `pspid` i `environment`, i provera da li dati trojac kredencijala odgovara aktivnom nalogu. Bez ijedne metode koja piše.
  - Ovo je jedini fajl u `app/` koji sme da pomene `ProviderAccount` ili `provider_accounts`.
- **Done when:**
  - `php -l` prolazi nad sva tri fajla.
  - `grep -rln 'ProviderAccount\|provider_accounts' app/ | grep -v 'Repositories/ProviderAccountRepository.php' | grep -v 'Models/ProviderAccount.php'` vraća prazno.
  - `grep -nE '\$fillable|\$guarded' app/Models/ProviderAccount.php` vraća 0 pogodaka.
  - `grep -nE '->(save|update|insert|create|delete)\(' app/Repositories/ProviderAccountRepository.php` vraća 0 pogodaka.

---

## 4. Wave 2 — tabele sa stranim ključevima i seeder

### T6 — `logs`: opciona veza na korisnika i `SET NULL` na nivou baze
- **Goal:** `logs` postoji sa nullable vezom na korisnika, strani ključ na brisanju poništava vezu umesto da briše red, a model podnosi odsustvo korisnika.
- **Covers:** R1, R7, R8, R14, R15, R16, R17, R18, R19, R20
- **Files touched:**
  - `database/migrations/2026_09_17_000006_create_logs_table.php` *(nov)*
  - `app/Models/Log.php` *(nov)*
- **Depends on:** T1 *(strani ključ pokazuje na `users`, čiju šemu T1 prepisuje)*
- **Obavezno:**
  - Kolone: `id`, `foreignId('user_id')->nullable()->constrained()->nullOnDelete()`, `string('level')`, `string('message')`, `json('context')->nullable()`, `timestamp('created_at')->nullable()`. **Bez `updated_at`.**
  - `level` ostaje `string`, ne `enum` — `data.md` u koloni *tip* piše `varchar` (D8, Q1).
  - `Log`: `const UPDATED_AT = null;`, cast `context => array`, `$fillable = ['user_id', 'level', 'message', 'context']`, `user(): BelongsTo` ka `User::class`.
  - Docblock na klasi koji imenuje **dva** pravila: (1) `user()` sme da vrati `null`, nikad `$log->user->name` bez provere; (2) `context` nikad ne sme da sadrži `api_key`, `api_secret`, lozinku ni sirov payload — vrednosti se maskiraju pre upisa (invarijanta 8, RK7).
  - Docblock koji imenuje sudar sa `Illuminate\Support\Facades\Log` i način zaobilaženja (ADR-15).
- **Done when:**
  - `php -l` prolazi nad oba fajla.
  - Migracija sadrži `nullOnDelete()` i `->nullable()` na `user_id`, i **ne** sadrži `timestamps()` ni `updated_at`.
  - `app/Models/Log.php` sadrži `UPDATED_AT = null`, `'context' => 'array'` i metodu `user()`.
  - Docblock modela pominje i `null` korisnika i zabranu kredencijala u `context`.

### T7 — `payment_methods` i dve pivot tabele
- **Goal:** payment metode postoje sa unique kodom i podrazumevanim vrednostima, obe pivot tabele su bez `id` i bez timestamp-ova, a model nudi obe N—N relacije.
- **Covers:** R1, R4, R5, R6, R7, R8, R17, R18, R19, R20
- **Files touched:**
  - `database/migrations/2026_09_17_000003_create_payment_methods_table.php` *(nov)*
  - `database/migrations/2026_09_17_000004_create_payment_method_currency_table.php` *(nov)*
  - `database/migrations/2026_09_17_000005_create_payment_method_country_table.php` *(nov)*
  - `app/Models/PaymentMethod.php` *(nov)*
- **Depends on:** T2 *(pivot tabele imaju strane ključeve na `currencies` i `countries`)*
- **Obavezno:**
  - `payment_methods`: `id`, `string('name')`, `string('code')->unique()`, `enum('type', ['redirect','iframe'])`, `boolean('is_active')->default(false)`, `integer('sort_order')->default(0)`. Bez `timestamps()`.
  - Obe pivot tabele: **bez `$table->id()`**, bez `timestamps()`. Dve `foreignId(...)->constrained()->cascadeOnDelete()` kolone i složeni primarni ključ nad tim parom.
  - `PaymentMethod`: `$timestamps = false`, casts `is_active => boolean`, `sort_order => integer`, `$fillable = ['name','code','type','is_active','sort_order']`, `currencies(): BelongsToMany` preko `payment_method_currency`, `countries(): BelongsToMany` preko `payment_method_country`.
  - Ime pivot tabele se navodi **eksplicitno** u obe relacije — Laravel podrazumevano ime se poklapa, ali eksplicitno je ono što `data.md` propisuje.
- **Done when:**
  - `php -l` prolazi nad sva četiri fajla.
  - `grep -c '\$table->id()' ` nad obe pivot migracije vraća `0`.
  - `grep -c 'timestamps()' ` nad sve tri migracije vraća `0`.
  - `app/Models/PaymentMethod.php` sadrži `belongsToMany` sa doslovnim `'payment_method_currency'` i `'payment_method_country'`.

### T8 — Korisnički seeder u zasebnom fajlu
- **Goal:** sva logika sejanja korisnika živi u `UserSeeder`, a `DatabaseSeeder` je samo orkestrator koji ga poziva.
- **Covers:** R24, R25, R27
- **Files touched:**
  - `database/seeders/UserSeeder.php` *(nov)*
  - `database/seeders/DatabaseSeeder.php` *(izmena)*
- **Depends on:** T1 *(`User` model i `UserFactory` moraju biti usklađeni pre nego što seeder upiše red)*
- **Obavezno:**
  - `UserSeeder` preuzima **doslovno** postojeću logiku iz `DatabaseSeeder`: `ANCHOR_EMAIL = 'seed-anchor@example.test'`, `ANCHOR_NAME`, `FACTORY_USER_COUNT`, čitanje `SEED_USER_PASSWORD` iz okruženja sa `RuntimeException` ako nije postavljen, **jedan** `Hash::make()` poziv, `updateOrCreate` po `email`. Uključujući postojeće komentare — oni objašnjavaju zašto AC-07/AC-08 zavise od fiksne vrednosti.
  - `DatabaseSeeder` zadržava `WithoutModelEvents` i svodi se na `$this->call([UserSeeder::class]);`. Konstanta `ANCHOR_EMAIL` se **seli, ne kopira** (D11).
  - `DatabaseSeeder` nosi komentar koji imenuje da su seeder-i za `currencies`, `countries`, `provider_accounts`, `settings` i `payment_methods` svesno izostavljeni (Non-goal u `spec.md`, RK8) i da se dodaju u `call()` listu kad budu traženi.
- **Done when:**
  - `php -l` prolazi nad oba fajla.
  - `grep -n 'Hash::make\|updateOrCreate\|factory()' database/seeders/DatabaseSeeder.php` vraća 0 pogodaka.
  - `grep -c 'ANCHOR_EMAIL' database/seeders/DatabaseSeeder.php` vraća `0`; `database/seeders/UserSeeder.php` je sadrži.
  - `database/seeders/DatabaseSeeder.php` sadrži `$this->call(` sa `UserSeeder::class` i komentar o izostavljenim seeder-ima.

---

## 5. Wave 3 — integracija i dokaz

### T9 — Izvršavanje, provera svih acceptance criteria i zapis
- **Goal:** šema i podaci u živoj bazi odgovaraju `data.md`, svaki AC iz `spec.md` je proveren komandom, a rezultat je zapisan.
- **Covers:** R1, R3, R4, R5, R6, R7, R8, R9, R10, R11, R12, R13, R14, R15, R16, R17, R18, R19, R20, R21, R22, R23, R25, R26, R27
- **Files touched:**
  - `docs/agent-outputs/2026-09-17-integracija-domenskog-modela.md` *(nov)*
  - `LEARNINGS.md` *(izmena — stavka na vrh, po pravilu iz `CLAUDE.md`)*
- **Depends on:** T1, T2, T3, T4, T5, T6, T7, T8
- **Izvršilac:** glavni agent
- **Koraci:**
  1. `docker compose up -d` i provera da su `app` i `db` healthy.
  2. `docker compose exec app php artisan migrate:fresh` — **bez** `--seed`. Provera R9: `SELECT COUNT(*) FROM connections` mora biti `1`, sa `NULL` kredencijalima.
  3. `docker compose exec app php artisan db:seed` **dva puta uzastopno** — provera R25 i R27.
  4. `docker compose exec app php artisan migrate:rollback --step=<broj>` do nule pa ponovo `migrate` — provera R8.
  5. Provera šeme upitima nad `information_schema` za R1, R4, R5, R7, R16.
  6. Provera default vrednosti za R6 kroz `INSERT` bez tih kolona.
  7. Provera ponašanja modela kroz `php artisan tinker --execute=…` za R11, R14, R19, R20, R21, R22, R23.
  8. Provera R15: brisanje korisnika sa logovima, pa `SELECT` nad `logs`.
  9. `grep` provere za R10, R12, R13, R26.
- **Done when:**
  - Svih 27 redova u tabeli *Acceptance criteria* iz `spec.md` ima izvršenu komandu i zabeležen izlaz.
  - `docs/agent-outputs/2026-09-17-integracija-domenskog-modela.md` sadrži izlaze **doslovno**, ne prepričano, i sekciju *Provera tvrdnji* (pravilo iz `CLAUDE.md`).
  - `LEARNINGS.md` ima novu datiranu stavku na vrhu: šta je promenjeno, šta je pošlo naopako, ispravka.
  - Nijedan AC nije označen kao „nije provereno" bez imenovanog razloga.

---

## 6. Coverage

| Requirement | Task |
|---|---|
| R1 | T1, T2, T3, T4, T5, T6, T7, T9 |
| R2 | T1 |
| R3 | T9 |
| R4 | T1, T2, T4, T7, T9 |
| R5 | T7, T9 |
| R6 | T3, T7, T9 |
| R7 | T1, T2, T3, T4, T5, T6, T7, T9 |
| R8 | T1, T2, T3, T4, T5, T6, T7, T9 |
| R9 | T3, T9 |
| R10 | T3, T9 |
| R11 | T3, T9 |
| R12 | T5, T9 |
| R13 | T5, T9 |
| R14 | T6, T9 |
| R15 | T6, T9 |
| R16 | T1, T6, T9 |
| R17 | T1, T2, T3, T4, T5, T6, T7, T9 |
| R18 | T1, T2, T3, T4, T5, T6, T7, T9 |
| R19 | T3, T5, T6, T7, T9 |
| R20 | T1, T2, T6, T7, T9 |
| R21 | T3, T9 |
| R22 | T4, T9 |
| R23 | T1, T9 |
| R24 | T8 |
| R25 | T8, T9 |
| R26 | T1, T9 |
| R27 | T8, T9 |

Svaki `R-id` ima bar jedan task. Svaki task pokriva bar jedan `R-id`.

## 7. Provera sudara fajlova po talasu

**Talas 1** — T1 `{users migracija, User.php, UserFactory.php}`, T2 `{currencies mig, countries mig, Currency.php, Country.php}`, T3 `{connections mig, Connection.php}`, T4 `{settings mig, Setting.php}`, T5 `{provider_accounts mig, ProviderAccount.php, ProviderAccountRepository.php}` — **presek prazan**.

**Talas 2** — T6 `{logs mig, Log.php}`, T7 `{payment_methods mig, 2 pivot mig, PaymentMethod.php}`, T8 `{UserSeeder.php, DatabaseSeeder.php}` — **presek prazan**.

**Talas 3** — T9 sam.

Nijedna zavisnost ne pokazuje unapred: T6→T1, T7→T2, T8→T1 idu u prethodni talas; T9 zavisi od svih iz prethodna dva.

### Fajl koji bi bio sudar, i kako je izbegnut

`app/Models/User.php` treba i T1 (šema, cast-ovi, `$hidden`) i T6 (relacija `logs()` ka novom `Log` modelu). Da su oba u istom talasu, jedan bi prepisao drugog.

Rešeno tako da **T1 piše i `logs()` relaciju**, iako `App\Models\Log` u tom trenutku još ne postoji. PHP rešava ime klase tek pri pozivu, pa fajl prolazi `php -l` i bez nje; relacija se prvi put zaista izvršava u T9, kada `Log` postoji. Isto važi za `Currency::paymentMethods()` i `Country::paymentMethods()` u T2, koje pokazuju na `PaymentMethod` iz T7.

Alternativa je bila da svaki model dobije svoj task, pa relacije u treći talas — više talasa, manje paralelizma, i model koji se piše dvaput. Odbačeno.
