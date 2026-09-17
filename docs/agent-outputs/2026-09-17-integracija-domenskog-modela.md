# 2026-09-17 — Integracija domenskog modela (T9)

**Agent:** glavni agent (talas 3 iz [`docs/domenProblema/tasks.md`](../domenProblema/tasks.md))
**Predmet:** provera svih acceptance criteria iz [`docs/domenProblema/spec.md`](../domenProblema/spec.md) nad živom bazom
**Okruženje:** `app`, `db`, `web` — sva tri `Up (healthy)`; MySQL 8.4, PHP 8.3, Laravel 12

> Izlazi su zabeleženi **doslovno**, ne prepričano. Sekcija *Provera tvrdnji* na kraju: izveštaj je tvrdnja dok se ne proveri.

---

## 1. `migrate:fresh` — redosled migracija (R3)

```
  Dropping all tables ........................................... 58.56ms DONE
   INFO  Preparing database.
  Creating migration table ...................................... 16.18ms DONE
   INFO  Running migrations.
  0001_01_01_000000_create_users_table .......................... 68.13ms DONE
  0001_01_01_000001_create_cache_table .......................... 45.16ms DONE
  0001_01_01_000002_create_jobs_table ........................... 63.37ms DONE
  2026_09_17_000001_create_currencies_table ..................... 22.42ms DONE
  2026_09_17_000002_create_countries_table ...................... 21.62ms DONE
  2026_09_17_000003_create_payment_methods_table ................ 24.61ms DONE
  2026_09_17_000004_create_payment_method_currency_table ........ 77.26ms DONE
  2026_09_17_000005_create_payment_method_country_table ......... 75.43ms DONE
  2026_09_17_000006_create_logs_table ........................... 47.98ms DONE
  2026_09_17_000007_create_connections_table .................... 15.45ms DONE
  2026_09_17_000008_create_settings_table ....................... 21.58ms DONE
  2026_09_17_000009_create_provider_accounts_table .............. 16.25ms DONE
```

Nijedna greška o nepostojećoj tabeli. **R3 prolazi.**

---

## 2. `connections` ima tačno jedan prazan red (R9)

Pokrenuto posle `migrate:fresh` **bez** `--seed`:

```
R9 connections row count: 1
R9 row: {"id":1,"pspid":null,"api_key":null,"api_secret":null,"environment":"test","is_connected":0,"verified_at":null}
```

Jedan red, svi kredencijali `NULL`, `environment` = `test`, `is_connected` = `0`. **R9 prolazi.** Time je potvrđena i odluka D6: red kreira migracija, ne seeder.

---

## 3. Šema svih deset tabela (R1, R2, R5, R6)

```
== users ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   name                 varchar(255)                 null=NO  default=NULL     key=
   email                varchar(255)                 null=NO  default=NULL     key=UNI
   password             varchar(255)                 null=NO  default=NULL     key=
   created_at           timestamp                    null=YES default=NULL     key=
== currencies ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   code                 char(3)                      null=NO  default=NULL     key=UNI
   name                 varchar(255)                 null=NO  default=NULL     key=
== countries ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   code                 char(2)                      null=NO  default=NULL     key=UNI
   name                 varchar(255)                 null=NO  default=NULL     key=
== payment_methods ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   name                 varchar(255)                 null=NO  default=NULL     key=
   code                 varchar(255)                 null=NO  default=NULL     key=UNI
   type                 enum('redirect','iframe')    null=NO  default=NULL     key=
   is_active            tinyint(1)                   null=NO  default='0'      key=
   sort_order           int                          null=NO  default='0'      key=
== payment_method_currency ==
   payment_method_id    bigint unsigned              null=NO  default=NULL     key=PRI
   currency_id          bigint unsigned              null=NO  default=NULL     key=PRI
== payment_method_country ==
   payment_method_id    bigint unsigned              null=NO  default=NULL     key=PRI
   country_id           bigint unsigned              null=NO  default=NULL     key=PRI
== logs ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   user_id              bigint unsigned              null=YES default=NULL     key=MUL
   level                varchar(255)                 null=NO  default=NULL     key=
   message              varchar(255)                 null=NO  default=NULL     key=
   context              json                         null=YES default=NULL     key=
   created_at           timestamp                    null=YES default=NULL     key=
== connections ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   pspid                varchar(255)                 null=YES default=NULL     key=
   api_key              varchar(255)                 null=YES default=NULL     key=
   api_secret           varchar(255)                 null=YES default=NULL     key=
   environment          enum('test','live')          null=NO  default='test'   key=
   is_connected         tinyint(1)                   null=NO  default='0'      key=
   verified_at          timestamp                    null=YES default=NULL     key=
== settings ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   key                  varchar(255)                 null=NO  default=NULL     key=UNI
   value                varchar(255)                 null=YES default=NULL     key=
== provider_accounts ==
   id                   bigint unsigned              null=NO  default=NULL     key=PRI
   pspid                varchar(255)                 null=NO  default=NULL     key=
   api_key              varchar(255)                 null=NO  default=NULL     key=
   api_secret           varchar(255)                 null=NO  default=NULL     key=
   environment          enum('test','live')          null=NO  default=NULL     key=
   is_active            tinyint(1)                   null=NO  default=NULL     key=
```

- **R1 prolazi** — svih deset tabela nosi tačno kolone iz `data.md`, nijednu više.
- **R2 prolazi** — `users` ima pet kolona; nema `updated_at`, `email_verified_at` ni `remember_token`.
- **R5 prolazi** — nijedna pivot tabela nema `id`; obe imaju složeni `PRI`.
- **R6 prolazi (deo)** — `connections.environment` default `test`, `is_connected` default `0`, `payment_methods.is_active` i `sort_order` default `0`.

`INSERT` bez tih kolona, kao nezavisna potvrda R6:

```
payment_methods is_active=0 sort_order=0
```

---

## 4. Jedinstveni indeksi (R4)

```
   users              email    Non_unique=0  key=users_email_unique
   payment_methods    code     Non_unique=0  key=payment_methods_code_unique
   currencies         code     Non_unique=0  key=currencies_code_unique
   countries          code     Non_unique=0  key=countries_code_unique
   settings           key      Non_unique=0  key=settings_key_unique
```

Svih pet. **R4 prolazi.**

---

## 5. Timestamp kolone u celoj šemi (R7)

```
   job_batches.created_at
   jobs.created_at
   logs.created_at
   password_reset_tokens.created_at
   users.created_at
```

Od **domenskih** tabela samo `users.created_at` i `logs.created_at`. `job_batches`, `jobs` i `password_reset_tokens` su Laravel framework tabele, izričito van opsega u `spec.md` §Scope. **Nigde nijedan `updated_at`.** **R7 prolazi.**

---

## 6. `user_id` u celoj šemi (R16)

```
   logs
   sessions
```

`sessions` je Laravel framework tabela za sesije, van `data.md` i van opsega — to je tačno izuzetak predviđen kao **RK4** u `design.md`, i acceptance criterion za R16 ga imenuje. Od domenskih tabela samo `logs`. **R16 prolazi.**

---

## 7. Ponašanje modela (R11, R19, R21, R22, R23)

```
=== R11 api_secret never serialized ===
   toArray keys: id,pspid,api_key,environment,is_connected,verified_at
   api_secret present in toArray? NO
   api_secret present in toJson? NO

=== R19 casts ===
   is_connected: boolean
   verified_at (null): NULL
   verified_at (set): Illuminate\Support\Carbon

=== R21 current() idempotent ===
   id first=1 second=1  count=1

=== R22 Setting get/set ===
   missing key w/ fallback: 'FALLBACK'
   after set: 'dark'
   after overwrite: 'light'  rows=1
```

- **R11 prolazi** — `api_secret` je postavljen na `SECRET-VALUE` i sačuvan, pa ipak ne postoji ni u `toArray()` ni u `toJson()`.
- **R19 prolazi** — `bool`, `Carbon`, i niže `array` za `context`; nijedan string iz baze.
- **R21 prolazi** — dva poziva, isti `id`, `COUNT(*)` ostaje `1`.
- **R22 prolazi** — fallback za nepostojeći ključ, upis, pa prepis bez drugog reda.

```
=== R17/R18 ===
   User             table=users                    timestamps=yes  CREATED_AT=created_at  UPDATED_AT=null
   Log              table=logs                     timestamps=yes  CREATED_AT=created_at  UPDATED_AT=null
   Connection       table=connections              timestamps=no   CREATED_AT=created_at  UPDATED_AT=updated_at
   Setting          table=settings                 timestamps=no   CREATED_AT=created_at  UPDATED_AT=updated_at
   PaymentMethod    table=payment_methods          timestamps=no   CREATED_AT=created_at  UPDATED_AT=updated_at
   Currency         table=currencies               timestamps=no   CREATED_AT=created_at  UPDATED_AT=updated_at
   Country          table=countries                timestamps=no   CREATED_AT=created_at  UPDATED_AT=updated_at
   ProviderAccount  table=provider_accounts        timestamps=no   CREATED_AT=created_at  UPDATED_AT=updated_at
```

**R17 prolazi** — svih osam modela mapira na očekivanu tabelu. **R18 prolazi** — `User` i `Log` upisuju samo `created_at` (`UPDATED_AT = null`), preostalih šest ne upisuje nijedan timestamp (`usesTimestamps() = false`; vrednosti `CREATED_AT`/`UPDATED_AT` su nasleđene podrazumevane konstante i nemaju efekta dok je `$timestamps = false`).

---

## 8. Relacije i integritet (R14, R15, R20, R23) i NFR N2

```
=== R20 N-N relations, both directions ===
   method -> currencies: EUR,RSD
   method -> countries:  RS
   currency -> methods:  card
   country  -> methods:  card
   R19 is_active type=boolean  sort_order type=integer

=== N2 query count for eager load ===
   queries: 3

=== R14 log without user ===
   user_id: NULL  user(): NULL
   R19 context type: array value={"ip":"10.0.0.1"}

=== R15 deleting a user nulls its logs, never deletes them ===
   R23 stored password is bcrypt: YES
   R23 password in toArray: NO
   R20 user -> logs: 1
   logs before delete=2 after delete=2
   orphaned log user_id: NULL
   orphaned log ->user: NULL
```

- **R20 prolazi** — sve četiri N—N putanje i obe 1—N putanje.
- **N2 potvrđen merenjem** — `PaymentMethod::with(['currencies','countries'])` je **tačno 3 upita**, ne `1 + 2N`.
- **R14 prolazi** — log sa `user_id = null` se učita, `->user` vraća `null`, bez izuzetka.
- **R15 prolazi** — brisanje je izvedeno **sirovim SQL-om** (`DB::delete("DELETE FROM users …")`), dakle mimo Eloquent-a, i strani ključ je svejedno poništio vezu: `logs` ima isti broj redova pre i posle (2 → 2), a `user_id` je `NULL`. Ovo je dokaz za D5 — da je invarijanta u bazi, observer bi ovde bio zaobiđen.
- **R23 prolazi** — lozinka je bcrypt (`$2y$`), `toArray()` je ne sadrži.

---

## 9. Seed (R25, R27)

```
=== FIRST db:seed ===
  Database\Seeders\UserSeeder ........................................ RUNNING
  Database\Seeders\UserSeeder .................................... 446 ms DONE

=== SECOND db:seed (R25 idempotency) ===
  Database\Seeders\UserSeeder ........................................ RUNNING
  Database\Seeders\UserSeeder .................................... 414 ms DONE
exit=0
```

```
R25 anchor rows: 1
R27 anchor exists after seed: YES
total users after TWO seeds: 21
```

- **R25 prolazi** — oba pokretanja prošla (`exit=0`), anchor postoji u tačno jednom primerku.
- **R27 prolazi** — `seed-anchor@example.test` postoji posle seed-a, pa AC-07 i AC-08 iz `docs/spec.md` ostaju proverljivi.

**Nalaz uz R25, izmereno a ne pretpostavljeno:** ukupan broj korisnika posle dva seed-a je **21**, ne 11. `updateOrCreate` štiti anchor, ali 10 factory redova se dodaje pri svakom pokretanju. Vidi *Otvoreno* §12.

---

## 10. Rollback (R8)

```
   INFO  Rolling back migrations.
  2026_09_17_000009_create_provider_accounts_table .............. 16.07ms DONE
  2026_09_17_000008_create_settings_table ........................ 8.90ms DONE
  2026_09_17_000007_create_connections_table ..................... 7.79ms DONE
  2026_09_17_000006_create_logs_table ........................... 10.15ms DONE
  2026_09_17_000005_create_payment_method_country_table .......... 9.36ms DONE
  2026_09_17_000004_create_payment_method_currency_table ........ 11.86ms DONE
  2026_09_17_000003_create_payment_methods_table ................. 7.45ms DONE
  2026_09_17_000002_create_countries_table ....................... 9.70ms DONE
  2026_09_17_000001_create_currencies_table ...................... 8.09ms DONE
  0001_01_01_000002_create_jobs_table ........................... 25.13ms DONE
  0001_01_01_000001_create_cache_table .......................... 16.34ms DONE
  0001_01_01_000000_create_users_table .......................... 25.26ms DONE
```

```
tables after rollback: 1
```

Preostala tabela je `migrations`. Nijedna greška o stranom ključu — pivoti padaju pre tabela na koje pokazuju. Posle toga `migrate` ponovo podiže celu šemu bez greške. **R8 prolazi.**

---

## 11. Statičke provere (R10, R12, R13, R26)

```
=== R10: no create/delete path for Connection ===
   hits: 0
=== R12/R13: provider_accounts access points ===
app/Models/ProviderAccount.php
app/Repositories/ProviderAccountRepository.php
   files issuing QUERIES: 1
=== R26: seeder references no dropped columns ===
0
```

- **R10 prolazi** — nula `Connection::create` / `firstOrCreate` / `updateOrCreate` putanja u `app/` i `routes/`.
- **R12 prolazi** — nijedna putanja upisa u `provider_accounts` van `database/`.
- **R13 prolazi** — `provider_accounts` se pominje u dva fajla, ali **upit postavlja tačno jedan**: repozitorijum. Model je Eloquent mapiranje i sam ne postavlja upite.
- **R26 prolazi** — nula referenci na uklonjene kolone u seeder-ima i factory-ju; `db:seed` je i prošao bez greške o nepoznatoj koloni.

---

## 12. Rezultat po zahtevima

| R-id | Ishod | Dokaz |
|---|---|---|
| R1 | prolazi | §3 |
| R2 | prolazi | §3 |
| R3 | prolazi | §1 |
| R4 | prolazi | §4 |
| R5 | prolazi | §3 |
| R6 | prolazi | §3 |
| R7 | prolazi | §5 |
| R8 | prolazi | §10 |
| R9 | prolazi | §2 |
| R10 | prolazi | §11 |
| R11 | prolazi | §7 |
| R12 | prolazi | §11 |
| R13 | prolazi | §11 |
| R14 | prolazi | §8 |
| R15 | prolazi | §8 |
| R16 | prolazi | §6 |
| R17 | prolazi | §7 |
| R18 | prolazi | §7 |
| R19 | prolazi | §7, §8 |
| R20 | prolazi | §8 |
| R21 | prolazi | §7 |
| R22 | prolazi | §7 |
| R23 | prolazi | §8 |
| R24 | prolazi | §11, `DatabaseSeeder` bez logike sejanja |
| R25 | prolazi | §9 |
| R26 | prolazi | §11 |
| R27 | prolazi | §9 |

**27 od 27.** Nijedan AC nije preskočen.

---

## 13. Otvoreno — nalazi koji nisu kvarovi ali traže odluku

1. **Factory redovi se gomilaju pri ponovljenom seed-u.** Posle dva `db:seed` tabela ima 21 korisnika, ne 11. AC za R25 traži da oba pokretanja prođu i da anchor bude jedinstven — oboje važi — ali `README` i AC-06 iz `docs/spec.md` govore o 11 korisnika. Uz to, `fake()->unique()->safeEmail()` de-duplicira samo unutar jednog procesa i ne zna za redove koji su već u tabeli, pa je sudar na `users.email` moguć iako se u ovom izvršavanju nije desio. Nalaz je prijavio T8, a merenje ga je potvrdilo.
2. **`Connection` nema `$fillable`**, pa `Connection::current()->update([...])` baca `MassAssignmentException`. UC2 opisuje ažuriranje jedinog reda, ali pozivalac je Non-goal ove iteracije. Dodavanje `$fillable` bi otvorilo mass-assignment putanju za `api_secret`.
3. **`Connection::current()` koristi `first()` bez `ORDER BY`.** Na nivou baze ništa ne prikiva `connections` na jedan red — nema ni unique indeksa ni check ograničenja.
4. **`logs` nema indeks na `(level, created_at)`.** Jedina tabela u šemi koja raste bez granice. `data.md` ga ne traži, a nema ni koda koji čita logove da bi se izmerio.
5. **`logs.message` je `varchar(255)`.** Duga poruka izuzetka bi pukla u strict mode-u, i to baš pri upisu kvara. `data.md` navodi `varchar` bez dužine.
6. **`logs.created_at` nema `useCurrent()`**, pa bi upis koji zaobilazi Eloquent tiho upisao `NULL`.
7. **`settings.key` je case-insensitive** pod `utf8mb4_unicode_ci` — `Setting::get('LOGO_URL')` nalazi red `logo_url`.
8. **`provider_accounts` nema indeks ni unique nad `(pspid, environment)`.**
9. **Referentne tabele su prazne** — svesna posledica odluke naručioca da se u ovoj iteraciji pravi samo korisnički seeder.

---

## 14. Provera tvrdnji

Ovaj izveštaj je tvrdnja dok se ne proveri. Kako proveriti unazad:

| Tvrdnja | Kako se proverava |
|---|---|
| Šema odgovara `data.md` | `docker compose exec app php artisan migrate:fresh`, pa upit iz §3 nad `information_schema.columns` |
| `connections` ima jedan red bez seed-a | `migrate:fresh` **bez** `--seed`, pa `SELECT COUNT(*) FROM connections` |
| `api_secret` ne izlazi | postaviti vrednost, sačuvati, pa `Connection::current()->toJson()` |
| Brisanje korisnika ne briše logove | `DELETE FROM users WHERE id = X` **sirovim SQL-om**, pa `SELECT` nad `logs` |
| Eager load je 3 upita | `DB::enableQueryLog()` pa `PaymentMethod::with([...])->get()` |
| Seed je idempotentan za anchor | `db:seed` dvaput, pa `COUNT(*) WHERE email = 'seed-anchor@example.test'` |
| Jedna tačka pristupa | `grep -rlnE 'ProviderAccount::query\|ProviderAccount::where\|DB::table\(.provider_accounts' app/` |

**Šta ovaj izveštaj ne dokazuje:** da su invarijante otporne na budući kod. Nijedan kontroler, ruta ni ekran ne postoji, pa invarijante 2, 3 i 8 iz `data.md` danas nema ko da prekrši. One su zapisane u modelima kao pravilo, ne iznuđene izvršnim filterom. Prvi PR koji doda pisca logova ili konfiguracioni ekran mora ih ponovo dokazati.
