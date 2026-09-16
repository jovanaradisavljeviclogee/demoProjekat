# ADR-12 — Projekat koristi plugin `demo-project@ai-toolkit`

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-16 · **Status:** prihvaćeno

**Kontekst:** konvencije ovog okruženja — u kom kontejneru ide koja komanda, da je `DB_PORT` interni `3306` a ne host `3307`, da se `vendor/` ne pravi na hostu — ponavljale su se u svakom razgovoru. Isto važi za standard pisanja specifikacije. Znanje je živelo u istoriji chat-a i u glavi onoga ko je radio.

**Opcije:** (a) instalirati plugin `demo-project@ai-toolkit`; (b) prepisati konvencije u `CLAUDE.md` ovog projekta; (c) ne raditi ništa i objašnjavati svaki put.

**Odluka:** (a). Omogućen u `.claude/settings.local.json`.

**Obrazloženje:** (b) radi, ali `CLAUDE.md` je u kontekstu u **svakoj** sesiji, pa se plaća i kada se ne dodiruje Docker; skill se učitava tek kad se zadatak poklopi sa opisom. Ozbiljniji problem je što bi (b) napravio drugu kopiju pravila koja se s vremenom razilazi od skill-a — a razišla pravila su gora od nepostojećih, jer im se veruje.

Zato `CLAUDE.md` ovog projekta **imenuje** plugin i upućuje na skill-ove, ali ne prepisuje njihov sadržaj.

**Posledice:** projekat zavisi od spoljnog repozitorijuma `jovanaradisavljeviclogee/ai-toolkit`; ko ga nema, nema ni konvencije. Izmena konvencije radi se tamo, ne ovde. Pozitivna strana iste medalje: ispravka stiže u sve projekte koji plugin koriste, bez kopiranja.

**Provereno:** devet skill-ova testirano stvarnim pitanjima o ovom okruženju, svih devet je odgovorilo konkretnim vrednostima iz projekta umesto generičnim Laravel tekstom — v. [`LEARNINGS.md`](../../LEARNINGS.md).
