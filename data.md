# Data model

Tabele koje kreiraju migracije. Ovaj dokument je izvor istine za migracije, modele i seeder-e.

Oznake: **PK** primary key, **FK** foreign key, **UQ** unique.

---

## users

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `name` | varchar | required |
| `email` | varchar | required, unique |
| `password` | varchar | hashovan, nikad u plain tekstu |
| `created_at` | timestamp | |

Bez `updated_at`.

**Relacije:** `users` 1—N `logs`. Ništa drugo.

`users` je povezan sa `logs` i ni sa čim drugim. Konfiguracija i payment metode pripadaju prodavnici, ne osobi koja ih menja, pa nemaju vlasnika. Ako bi nalog ikad morao da se ukloni, relacija se poništava umesto da kaskadira — brisanje osobe ne sme da izbriše zapis o tome šta je urađeno.

---

## connections

Drži tačno jedan red. Red kreira migracija, prazan. Ekran za konfiguraciju ga ažurira i nikad ne kreira novi.

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `pspid` | varchar | prazno dok se ne konfiguriše |
| `api_key` | varchar | prazno dok se ne konfiguriše |
| `api_secret` | varchar | prazno dok se ne konfiguriše, nikad se ne vraća u browser |
| `environment` | enum | `test` ili `live`, default `test` |
| `is_connected` | boolean | default `false` |
| `verified_at` | timestamp | `null` dok se prvi put uspešno ne poveže |

Bez timestamp kolona.

**Relacije:** nema. Kredencijali uneti u `connections` proveravaju se u odnosu na `provider_accounts`, ali to je provera vrednosti, ne strani ključ.

`api_secret` se nikad ne serijalizuje ka klijentu — ni u API odgovoru, ni u prosleđivanju ka view-u.

---

## provider_accounts

Kredencijali koje provajder smatra validnim. Ovo nisu podaci prodavca i aplikacija ih ne upisuje: puni ih seeder i samo se čitaju.

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `pspid` | varchar | |
| `api_key` | varchar | |
| `api_secret` | varchar | |
| `environment` | enum | `test` ili `live` |
| `is_active` | boolean | |

Bez timestamp kolona.

**Relacije:** nema.

U fazi 2 ova tabela izlazi iz aplikacije i postaje deo zasebnog provider servisa. To što je od početka odvojena od `connections` je ono što tu promenu čini malom umesto prepisivanjem — pa sav pristup treba da ide kroz jednu tačku (repozitorijum ili servis klasu), ne kroz upite razasute po kodu.

---

## payment_methods

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `name` | varchar | required |
| `code` | varchar | required, unique |
| `type` | enum | `redirect` ili `iframe`, required |
| `is_active` | boolean | default `false` |
| `sort_order` | integer | default `0` |

Bez timestamp kolona.

**Relacije:**
- N—N sa `currencies` preko `payment_method_currency`
- N—N sa `countries` preko `payment_method_country`

Jedna payment metoda podržava više valuta i više zemalja.

---

## currencies

Referentni podaci, puni ih seeder.

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `code` | char(3) | ISO 4217, unique |
| `name` | varchar | required |

Bez timestamp kolona.

**Relacije:** N—N sa `payment_methods` preko `payment_method_currency`.

---

## countries

Referentni podaci, puni ih seeder.

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `code` | char(2) | ISO 3166-1 alpha-2, unique |
| `name` | varchar | required |

Bez timestamp kolona.

**Relacije:** N—N sa `payment_methods` preko `payment_method_country`.

---

## payment_method_currency

Pivot tabela. Bez sopstvenog `id`, bez timestamp kolona.

| kolona | tip | pravila |
|---|---|---|
| `payment_method_id` | bigint | FK → `payment_methods.id` |
| `currency_id` | bigint | FK → `currencies.id` |

---

## payment_method_country

Pivot tabela. Bez sopstvenog `id`, bez timestamp kolona.

| kolona | tip | pravila |
|---|---|---|
| `payment_method_id` | bigint | FK → `payment_methods.id` |
| `country_id` | bigint | FK → `countries.id` |

---

## settings

Parovi ključ–vrednost. Svaki ključ ima podrazumevanu vrednost.

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `key` | varchar | unique |
| `value` | varchar | |

Bez timestamp kolona.

**Relacije:** nema.

---

## logs

| kolona | tip | pravila |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | bigint | FK → `users.id`, nullable |
| `level` | varchar | `debug`, `info`, `warning`, `error` |
| `message` | varchar | required |
| `context` | json | nullable, nikad ne sadrži kredencijale |
| `created_at` | timestamp | |

Bez `updated_at`.

**Relacije:** `logs` N—1 `users`, veza je opciona.

`user_id` je nullable namerno. Neuspešna prijava se upisuje pre nego što je iko autentifikovan. Kod koji čita log zapis mora da podnese odsustvo korisnika — nikad direktan pristup tipa `$log->user->name`, uvek provera ili fallback.

Ništa što se upiše u `context` ne sme da sadrži kredencijale: ni `api_key`, ni `api_secret`, ni lozinku, ni sirov payload zahteva koji ih nosi. Vrednosti se maskiraju pre upisa.

---

## Pregled relacija

| relacija | tip | preko |
|---|---|---|
| `payment_methods` ↔ `currencies` | N—N | `payment_method_currency` |
| `payment_methods` ↔ `countries` | N—N | `payment_method_country` |
| `users` → `logs` | 1—N | `logs.user_id`, nullable |
| `connections` | — | bez relacija |
| `settings` | — | bez relacija |
| `provider_accounts` | — | bez relacija |

---

## Invarijante

1. `connections` uvek ima tačno jedan red. Kreira ga migracija, aplikacija ga samo ažurira — nema `create` ni `delete` putanje.
2. `api_secret` iz `connections` nikad ne stiže do browsera.
3. `provider_accounts` je read-only za aplikaciju. Puni ga seeder, aplikacija ga samo čita, i pristup ide kroz jednu tačku zbog faze 2.
4. `currencies` i `countries` su referentni podaci koje puni seeder.
5. `logs.user_id` sme da bude `null` i svaki kod koji čita log mora to da podnese.
6. Brisanje korisnika poništava `logs.user_id`, nikad ne briše logove.
7. Nijedna tabela osim `logs` ne nosi `user_id`. Konfiguracija i payment metode nemaju vlasnika.
8. `logs.context` nikad ne sadrži kredencijale.
9. Timestamp kolone postoje samo na `users` i `logs`, i to samo `created_at`.

---

## Redosled migracija

1. `users`
2. `currencies`
3. `countries`
4. `payment_methods`
5. `payment_method_currency`
6. `payment_method_country`
7. `logs`
8. `connections` — migracija ubacuje jedan prazan red
9. `settings`
10. `provider_accounts`

---

## Seeder-i

| seeder | sadržaj |
|---|---|
| `currencies` | referentni podaci |
| `countries` | referentni podaci |
| `provider_accounts` | validni provajderski nalozi; jedini izvor upisa u ovu tabelu |
| `settings` | podrazumevana vrednost za svaki ključ |
| `payment_methods` | početni skup metoda i njihove veze sa valutama i zemljama |

`connections` nema seeder — prazan red kreira migracija.

---

## Implementacija (Laravel / Eloquent)

### Connection
- `$timestamps = false`
- casts: `is_connected` → `boolean`, `verified_at` → `datetime`
- `api_secret` u `$hidden`
- helper `Connection::current()` vraća jedini red; ne koristiti `create()` ni `firstOrCreate()`
- bez relacija

### ProviderAccount
- `$timestamps = false`
- casts: `is_active` → `boolean`
- bez `$fillable` — aplikacija ne piše u ovu tabelu
- pristup kroz repozitorijum ili servis klasu

### Setting
- `$timestamps = false`
- helperi `Setting::get($key, $default)` i `Setting::set($key, $value)`

### PaymentMethod
- `$timestamps = false`
- casts: `is_active` → `boolean`, `sort_order` → `integer`
- `currencies()`: `belongsToMany(Currency::class, 'payment_method_currency')`
- `countries()`: `belongsToMany(Country::class, 'payment_method_country')`

### Currency / Country
- `$timestamps = false`
- `paymentMethods()`: inverzni `belongsToMany` preko odgovarajuće pivot tabele

### User
- `const UPDATED_AT = null`
- `password`: `hashed` cast, u `$hidden`
- `logs()`: `hasMany(Log::class)`

### Log
- `const UPDATED_AT = null`
- casts: `context` → `array`
- `user()`: `belongsTo(User::class)`, može vratiti `null`
