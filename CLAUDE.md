# Rad na ovom projektu

Docker development okruženje za Laravel aplikaciju: Apache 2.4 (`web`) → PHP 8.3 FPM (`app`) → MySQL 8.4 (`db`). Detalji okruženja su u [README.md](README.md).

## Plugin koji ovaj projekat koristi

**`demo-project@ai-toolkit`** — omogućen u `.claude/settings.local.json`, izvor je repozitorijum [ai-toolkit](https://github.com/jovanaradisavljeviclogee/ai-toolkit).

Skill-ovi se aktiviraju sami kada se zadatak poklopi sa njihovim opisom. Ne treba ih pozivati.

**Vezani za ovo okruženje:**

| Skill | Kada radi |
|---|---|
| `docker-env` | servisi, u kom kontejneru ide koja komanda, standardni kvarovi |
| `laravel-init` | podizanje nove Laravel aplikacije na ovom okruženju |
| `laravel-structure` | gde ide nov kod — `app/`, `routes/`, `config/`, `database/` |
| `composer-autoload` | PSR-4, registrovanje namespace-a, „class not found" |

**Tok rada, za nov posao:**

```
writing-specs → writing-design → writing-tasks → executing-tasks
```

Uz njih ide i `performance-review`.

Pravila iz tih skill-ova se **ne prepisuju ovde.** Ako nešto treba promeniti, menja se skill u `ai-toolkit` repozitorijumu — duplirana pravila se razilaze.

## Dokumentacija

**[`docs/`](docs/)** drži svu AI dokumentaciju ovog projekta i **verzioniše se zajedno sa kodom**:

| Fajl | Sadrži |
|---|---|
| [`docs/spec.md`](docs/spec.md) | zahtevi okruženja, acceptance criteria, granice |
| [`docs/design.md`](docs/design.md) | arhitektura, ADR odluke, nefunkcionalni zahtevi, rizici |
| [`docs/tasks.md`](docs/tasks.md) | podela na taskove i talase |

Tu idu i decision notes i zabeleženi izlazi agenata.

**[`LEARNINGS.md`](LEARNINGS.md)** — jedna datirana stavka po radnoj sesiji, najnovija na vrhu: šta je promenjeno, šta je pošlo naopako, i ispravka.

`.dockerignore` i dalje izuzima `docs/*.md` — dokumentacija je u repozitorijumu, ali nema šta da traži u Docker build kontekstu.

## Pravilo za pull request

`docs/` i `LEARNINGS.md` se ažuriraju **u istom pull request-u** kao kod na koji se odnose — nikada naknadno.

Svaki tiket nosi jednu dodatnu stavku u definition of done: dokumentacija odražava ono što je u njemu naučeno.


## Komande

Sve idu kroz `app` kontejner:

```bash
docker compose exec app php artisan <komanda>
docker compose exec app composer <komanda>
```

Nikad na hostu, nikad u `web`. Razlog i standardni kvarovi su u skill-u `docker-env`.
