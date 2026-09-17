# ADR-13 — `users` se usklađuje izmenom postojeće migracije, ne novom `ALTER` migracijom

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** [`data.md`](../../data.md) je proglašen izvorom istine za migracije, modele i seeder-e. On propisuje da `users` ima tačno pet kolona: `id`, `name`, `email`, `password`, `created_at` — i izričito navodi „Bez `updated_at`". Zatečena Laravel scaffold migracija `database/migrations/0001_01_01_000000_create_users_table.php` kreira uz to i `email_verified_at`, `remember_token` i pun `timestamps()` par. Isti fajl kreira i `password_reset_tokens` i `sessions`, koje `data.md` uopšte ne pominje jer su Laravel framework tabele, ne domen.

Migracija nije izvršena nigde osim u lokalnim dev bazama koje se ionako podižu iz seed-a. Nema produkcije, nema deljene baze, nema migrisanih podataka koje bi prepisivanje ugrozilo.

**Opcije:** (a) prepisati `Schema::create('users', …)` u postojećoj migraciji; (b) ostaviti postojeću migraciju netaknutu i dodati novu `ALTER` migraciju koja dropuje tri kolone; (c) ostaviti `users` kakav jeste i primeniti `data.md` samo na nove tabele.

**Odluka:** (a). Potvrđeno od naručioca pre pisanja specifikacije.

**Obrazloženje:** (c) otpada odmah — `data.md` nije opis novih tabela nego opis domena, i tabela koja mu ne odgovara znači da dokument više nije izvor istine ni za šta.

Pravi izbor je između (a) i (b). (b) je ispravan refleks svuda gde je migracija već izvršena van kontrole autora: istorija ostaje linearna i nijedan tuđi `migrate` se ne prepisuje pod nogama. Ovde taj uslov ne postoji — jedina mesta gde je migracija izvršena su dev baze koje se podižu sa `migrate:fresh --seed`.

Cena (b) je trajna: čitalac koji otvori `create_users_table.php` vidi `email_verified_at` i `rememberToken()`, kolone koje u bazi ne postoje, i mora da zna da negde dalje u nizu postoji fajl koji ih uklanja. `users` prestaje da bude čitljiv iz jednog fajla. Pošto je cena (a) jednokratna — jedan `migrate:fresh` po developeru — a cena (b) se plaća pri svakom čitanju, bira se (a).

`password_reset_tokens` i `sessions` u istom fajlu ostaju **nedirnuti**. Nisu domen, `data.md` ih ne pominje, i Laravel session driver zavisi od `sessions`.

**Posledice:** svaki developer sa postojećom lokalnom bazom mora jednom da pokrene `docker compose exec app php artisan migrate:fresh --seed`; `migrate` sam po sebi neće primetiti izmenu, jer je migracija već zabeležena kao izvršena. To je zabeleženo kao rizik RK1 u [`docs/domenProblema/design.md`](../domenProblema/design.md) i kao izričit korak u `tasks.md`.

`sessions` i dalje nosi kolonu `user_id`. Invarijanta 7 iz `data.md` („nijedna tabela osim `logs` ne nosi `user_id`") tiče se domenskih tabela; `sessions` je van tog skupa i acceptance criterion za R16 je formulisan tako da je izuzima. Zabeleženo kao RK4.

Ako verifikacija e-maila ikad bude tražena, `email_verified_at` se ne vraća tiho — to je nov zahtev i ide kroz `spec.md`. Zabeleženo kao RK3.
