# ADR-10 — AI dokumentacija se verzioniše, u `docs/`

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-16 · **Status:** prihvaćeno

**Kontekst:** `.gitignore` je sadržao `/ai workflow/*.md`, pa `spec.md`, `design.md` i `tasks.md` nisu bili u repozitorijumu — postojali su samo na jednoj mašini i nestali bi pri svežem klonu. Uz to, folder je imao razmak na početku imena. Pravilo iz zadatka traži da se dokumentacija ažurira u istom pull request-u kao kod na koji se odnosi.

**Opcije:** (a) verzionisati i preimenovati u `docs/`; (b) ostaviti gitignorisano i održavati lokalno; (c) verzionisati pod postojećim imenom `ai workflow/`.

**Odluka:** (a).

**Obrazloženje:** ignorisan fajl ne može biti deo pull request-a — ne stiže do recenzenta, ne postoji u grani, ne postoji u diff-u. Time (b) čini traženo pravilo neizvodljivim, a ne samo nezgodnim. Razmak u imenu, koji (c) zadržava, traži navodnike u svakoj komandi i već je oborio `git mv` porukom `fatal: source directory is empty` — koja imenuje simptom, ne uzrok, jer je folder bio „prazan" upravo zato što su svi fajlovi u njemu bili ignorisani.

**Posledice:** tri putanje u `.dockerignore` moraju da prate preimenovanje, inače dokumentacija ponovo ulazi u Docker build kontekst. Dokumentacija od sada ulazi u code review, što znači da menjanje spec-a više nije privatan čin.
