# Spec: Domenski model — migracije, Eloquent modeli i seeder

> Status: **Specifikacija (faza 1)** — zaključavanje zahteva pre implementacije.
> Izvor istine za šemu je [`data.md`](../../data.md). Ovaj dokument ne prepisuje tabele iz `data.md`; on definiše **šta mora biti tačno** kada je posao gotov i kako se to dokazuje.
> Ovaj dokument ne sadrži arhitekturu, imena klasa ni kod — to je `design.md`.

---

## Problem

Projekat ima podignuto Docker okruženje i Laravel scaffold, ali nema domenski model. `data.md` opisuje deset tabela, njihove relacije i devet invarijanti — ništa od toga ne postoji u bazi, pa se nijedan deo aplikacije (konfiguracija konekcije, payment metode, logovanje) ne može početi graditi.

## Problem description

`docs/spec.md` i `docs/design.md` pokrivaju **okruženje** — kontejnere, portove, build. Aplikacija je tamo izričito van opsega ("aplikacija je nosilac za verifikaciju okruženja, ne proizvod sam po sebi"). Baza danas sadrži samo Laravel podrazumevane tabele: `users` (sa scaffold kolonama), `password_reset_tokens`, `sessions`, `cache`, `jobs`.

`data.md` je u međuvremenu isporučen kao opis domena i proglašen izvorom istine za migracije, modele i seeder-e. On se na jednom mestu **sukobljava sa zatečenim stanjem**: scaffold `users` nosi `updated_at`, `email_verified_at` i `remember_token`, a `data.md` propisuje da `users` ima samo `id`, `name`, `email`, `password`, `created_at`.

Pogođeni su svi koji dalje rade na aplikaciji: bez šeme nema modela, bez modela nema ni jednog ekrana. Dodatno, `data.md` nosi pravila koja se ne vide iz šeme (maskiranje kredencijala u logu, `api_secret` koji nikad ne izlazi ka klijentu, `connections` sa tačno jednim redom) — ako se ne zaključaju sada, prekršiće se prvi put kad neko doda kontroler.

---

## Use cases

### UC1 — Podizanje baze iz čistog klona
- **Actor:** developer
- **Trigger:** `php artisan migrate:fresh --seed` u `app` kontejneru
- **Flow:** migracije se izvršavaju redosledom iz `data.md`; seeder popunjava korisnike
- **Outcome:** sve tabele iz `data.md` postoje sa propisanim kolonama, tipovima i ključevima; `connections` ima tačno jedan red; korisnici postoje

### UC2 — Konfiguracija konekcije ka provajderu
- **Actor:** prodavac (kroz budući konfiguracioni ekran)
- **Trigger:** čuvanje PSPID-a, API ključa i API secret-a
- **Flow:** aplikacija čita jedini red iz `connections` i ažurira ga
- **Outcome:** red je ažuriran; broj redova u `connections` je i dalje 1; `api_secret` nije vraćen u odgovoru

### UC3 — Neuspešna prijava se loguje
- **Actor:** neautentifikovani posetilac
- **Trigger:** pogrešni kredencijali
- **Flow:** aplikacija upisuje log zapis pre nego što je iko autentifikovan
- **Outcome:** log zapis postoji sa `user_id = null`; kod koji ga posle čita ne puca

### UC4 — Provera unetih kredencijala
- **Actor:** aplikacija
- **Trigger:** korisnik je sačuvao kredencijale u `connections`
- **Flow:** vrednosti se porede sa aktivnim nalozima u `provider_accounts`
- **Outcome:** rezultat provere je poznat; `provider_accounts` nije izmenjen

### UC5 — Prikaz payment metoda
- **Actor:** prodavac
- **Trigger:** otvaranje liste payment metoda
- **Flow:** za svaku metodu se čitaju njene valute i zemlje
- **Outcome:** svaka metoda nosi svoju listu valuta i zemalja

### UC6 — Uklanjanje korisničkog naloga
- **Actor:** administrator
- **Trigger:** brisanje reda iz `users`
- **Flow:** baza poništava vezu na logovima tog korisnika
- **Outcome:** log zapisi i dalje postoje, sa `user_id = null`

---

## Requirements

**Šema**

- **R1** — Svaka tabela navedena u `data.md` postoji u bazi posle `migrate`, sa tačno onim kolonama i tipovima koje `data.md` navodi — ni jednom kolonom više.
- **R2** — `users` posle migracije nema kolone `updated_at`, `email_verified_at` ni `remember_token`.
- **R3** — Migracije se izvršavaju redosledom iz sekcije *Redosled migracija* u `data.md`, tako da nijedan strani ključ ne pokazuje na tabelu koja još ne postoji.
- **R4** — Jedinstveni indeksi postoje na: `users.email`, `payment_methods.code`, `currencies.code`, `countries.code`, `settings.key`.
- **R5** — `payment_method_currency` i `payment_method_country` nemaju sopstvenu `id` kolonu.
- **R6** — Podrazumevane vrednosti su primenjene u bazi: `connections.environment = test`, `connections.is_connected = false`, `payment_methods.is_active = false`, `payment_methods.sort_order = 0`.
- **R7** — Timestamp kolone postoje samo na `users` i `logs`, i to samo `created_at`.
- **R8** — Svaka migracija se može vratiti unazad: `migrate:rollback` do praznog stanja prolazi bez greške.

**Invarijante iz `data.md`**

- **R9** — Posle `migrate` (bez seed-a) `connections` sadrži tačno jedan red, sa praznim kredencijalima.
- **R10** — Ne postoji putanja u aplikativnom kodu koja kreira ili briše red u `connections`.
- **R11** — `api_secret` nije prisutan u serijalizovanom obliku modela koji predstavlja `connections` (niz ili JSON).
- **R12** — Ne postoji putanja u aplikativnom kodu koja upisuje u `provider_accounts`; upisuje samo seeder.
- **R13** — Sav pristup podacima iz `provider_accounts` ide kroz jednu tačku u kodu; ne postoji drugi upit nad tom tabelom nigde u `app/`.
- **R14** — `logs.user_id` sme da bude `null`, i čitanje korisnika sa log zapisa bez korisnika vraća `null` umesto greške.
- **R15** — Brisanje reda iz `users` postavlja `logs.user_id` na `null` za sve njegove logove; nijedan red iz `logs` se ne briše.
- **R16** — Nijedna tabela osim `logs` nema kolonu `user_id`.

**Modeli**

- **R17** — Za svaku tabelu iz `data.md` osim dve pivot tabele postoji Eloquent model.
- **R18** — Svaki model osim `users` i `logs` ima isključene automatske timestamp-ove; `users` i `logs` upisuju samo `created_at`.
- **R19** — Cast-ovi navedeni u sekciji *Implementacija* u `data.md` su primenjeni: čitanje `is_connected`, `is_active`, `sort_order`, `verified_at` i `context` vraća tip koji `data.md` propisuje, a ne string iz baze.
- **R20** — Relacije opisane u `data.md` su dostupne sa oba kraja: payment metoda → valute i zemlje, valuta i zemlja → payment metode, korisnik → logovi, log → korisnik.
- **R21** — Postoji način da se dobije jedini red iz `connections` bez kreiranja novog reda.
- **R22** — Postoji način da se pročita vrednost podešavanja po ključu sa fallback-om, i da se vrednost upiše po ključu.
- **R23** — `password` se nikad ne čuva u plain tekstu i nije prisutan u serijalizovanom obliku modela korisnika.

**Seeder**

- **R24** — Korisnici se sejuju iz zasebnog seeder fajla, ne iz `DatabaseSeeder`.
- **R25** — Seed je idempotentan za fiksnog korisnika: dvostruko pokretanje ne pravi duplikat i ne puca na jedinstvenom indeksu za `email`.
- **R26** — Seed ne postavlja vrednosti za kolone koje posle R2 više ne postoje.
- **R27** — Postojeća provera trajnosti podataka iz `docs/spec.md` (AC-07, AC-08) i dalje prolazi — fiksni korisnik `seed-anchor@example.test` postoji posle seed-a.

---

## Acceptance criteria

| Requirement | Prolazi kada |
|---|---|
| R1 | `SHOW COLUMNS` za svaku od 10 tabela vraća tačno skup kolona iz `data.md` |
| R2 | `SHOW COLUMNS FROM users` ne sadrži `updated_at`, `email_verified_at`, `remember_token` |
| R3 | `php artisan migrate:fresh` na praznoj bazi prolazi bez greške o nepostojećoj tabeli |
| R4 | `SHOW INDEX` na svakoj od 5 tabela prijavljuje `Non_unique = 0` za navedenu kolonu |
| R5 | `SHOW COLUMNS` za obe pivot tabele ne sadrži `id` |
| R6 | `INSERT` bez tih kolona daje red sa `test`, `0`, `0`, `0` |
| R7 | Upit nad `information_schema.columns` za `created_at`/`updated_at` vraća samo `users.created_at` i `logs.created_at` |
| R8 | `php artisan migrate:fresh` pa `php artisan migrate:rollback --step=<broj>` završava bez greške |
| R9 | `SELECT COUNT(*) FROM connections` posle `migrate:fresh` (bez `--seed`) vraća `1`, sa `NULL` kredencijalima |
| R10 | `grep` za `create(`/`delete(`/`firstOrCreate(` nad modelom konekcije u `app/` i `routes/` vraća 0 pogodaka |
| R11 | `toArray()` nad modelom konekcije ne sadrži ključ `api_secret` |
| R12 | `grep` za upis nad modelom provajderskog naloga van `database/` vraća 0 pogodaka |
| R13 | Svi upiti nad `provider_accounts` u `app/` nalaze se u tačno jednom fajlu |
| R14 | Log zapis sa `user_id = null` se učita i pristup korisniku vraća `null`, bez izuzetka |
| R15 | Posle `DELETE FROM users WHERE id = X`, `SELECT` nad `logs` vraća iste redove sa `user_id IS NULL` |
| R16 | Upit nad `information_schema.columns WHERE column_name = 'user_id'` vraća samo `logs` (i Laravel `sessions`) |
| R17 | Svaki od 8 modela se instancira i `getTable()` vraća očekivano ime tabele |
| R18 | `INSERT` preko modela ne pokušava da upiše timestamp kolonu koja ne postoji; nijedan `updated_at` se ne upisuje |
| R19 | `var_dump` učitanog reda pokazuje `bool`, `int`, `DateTime`/`Carbon` i `array`, ne `string` |
| R20 | Za svaku od 6 relacija, poziv relacije nad učitanim modelom vraća očekivane redove |
| R21 | Dvostruki poziv helper-a vraća isti `id`, a `COUNT(*)` ostaje `1` |
| R22 | Čitanje nepostojećeg ključa vraća prosleđeni fallback; posle upisa, čitanje vraća upisanu vrednost |
| R23 | `SELECT password FROM users` vraća bcrypt hash; `toArray()` nad korisnikom ne sadrži `password` |
| R24 | Fajl korisničkog seeder-a postoji u `database/seeders/`; `DatabaseSeeder` ga poziva i sam ne sadrži logiku sejanja |
| R25 | `db:seed` pokrenut dva puta uzastopno prolazi oba puta; `COUNT(*) WHERE email = 'seed-anchor@example.test'` je `1` |
| R26 | `db:seed` ne baca grešku o nepoznatoj koloni |
| R27 | `SELECT` za `seed-anchor@example.test` posle `migrate:fresh --seed` vraća tačno jedan red |

---

## Scope

**U opsegu:**
- `database/migrations/` — izmena postojeće migracije za `users` i nove migracije za preostalih 9 tabela
- `app/Models/` — Eloquent modeli za tabele iz `data.md`
- Jedna tačka pristupa podacima iz `provider_accounts` (mesto joj određuje `design.md`)
- `database/seeders/` — nov seeder za korisnike i izmena `DatabaseSeeder` da ga poziva
- `database/factories/UserFactory.php` — usklađivanje sa `users` posle R2

**Van opsega — ne sme se dirati:**
- `compose.yaml`, `Dockerfile`, `docker/`, `.env*`, `.dockerignore` — okruženje je gotovo i pokriveno sa `docs/spec.md`
- `routes/`, `app/Http/` — nijedan kontroler ni ruta u ovom poslu
- `resources/`, `public/`, Vite konfiguracija
- `ExamplePSR/` — namerno pokvaren, vidi ADR-11
- Laravel podrazumevane migracije za `cache`, `jobs`, `password_reset_tokens`, `sessions`

---

## Non-goals

- **Seeder-i za referentne podatke.** `data.md` navodi seeder-e za `currencies`, `countries`, `provider_accounts`, `settings` i `payment_methods`. Naručilac je odlučio da se u ovoj iteraciji pravi **samo korisnički seeder**; te tabele ostaju prazne posle seed-a. Tabele i modeli se ipak prave sada, jer redosled migracija i strani ključevi ne trpe parcijalnu šemu.
- **Bilo koji ekran ili API.** Konfiguracioni ekran, lista payment metoda i prijava su korisnički scenariji koji objašnjavaju *zašto* šema izgleda ovako — ne grade se ovde.
- **Autentifikacija i autorizacija.** `users` dobija šemu, ne login tok.
- **Faza 2 izdvajanja `provider_accounts` u zaseban servis.** Ovde se samo poštuje granica koja tu promenu čini malom (R13); sam servis se ne pravi.
- **Maskiranje kredencijala u `logs.context` kao izvršni kod.** Invarijanta 8 iz `data.md` nema ko da je prekrši dok ne postoji kod koji piše logove; ovde se zapisuje kao pravilo, ne kao filter.
- **Testovi kao isporučeni artefakt.** Acceptance criteria se proveravaju komandama; PHPUnit paket za domenski model nije tražen.

---

## Open questions

| # | Pitanje | Vlasnik | Blokira |
|---|---|---|---|
| Q1 | Da li `logs.level` ostaje `varchar` (kako `data.md` navodi u tipu) ili prelazi u `enum`? `data.md` nabraja četiri vrednosti, ali tip je `varchar`. Specifikacija prati tip, ne nabrajanje. | naručilac | ne |
| Q2 | Koja podešavanja ulaze u `settings` i sa kojim podrazumevanim vrednostima? Nije potrebno sada jer je seeder za `settings` van opsega, ali blokira tu tabelu čim se seeder bude pravio. | naručilac | ne |
| Q3 | Da li `connections` i `provider_accounts` treba da imaju indeks na `pspid`? `data.md` ga ne traži; provera iz UC4 bi ga koristila. | naručilac | ne |
| Q4 | `data.md` ne navodi dužine za `varchar` kolone. Prati se Laravel podrazumevano (255) osim za `char(3)` i `char(2)` koje `data.md` izričito navodi. | naručilac | ne |

---

## Odluke naručioca zabeležene pre pisanja ovog dokumenta

| # | Pitanje | Odluka |
|---|---|---|
| D1 | Sudar scaffold `users` i `data.md` | Menja se **postojeća** migracija `0001_01_01_000000_create_users_table.php`; `password_reset_tokens` i `sessions` ostaju netaknuti |
| D2 | Obim seeder-a | Za sada **samo korisnički** seeder, i to u **novom** fajlu, ne u `DatabaseSeeder` |
| D3 | Verifikacija | Migracije i seed se **izvršavaju** u Docker okruženju kao dokaz, ne samo pišu |
