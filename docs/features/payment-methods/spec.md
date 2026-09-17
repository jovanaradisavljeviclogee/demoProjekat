# spec.md — Administracija payment metoda

> Status: **Specifikacija (faza 1)** — zaključavanje zahteva pre implementacije.
> Ovaj dokument ne sadrži kod niti implementaciju; on definiše šta se gradi i kako se dokazuje da je urađeno.

> Oznake u ovom dokumentu nose prefiks `PM-` (`PM-UC-`, `PM-FR-`, `PM-AC-`, `PM-C-`) da se ne mešaju sa serijom iz [`docs/spec.md`](../../spec.md), koja opisuje Docker okruženje. Serija **ADR-ova je zajednička** i nastavlja se u [`docs/decisions/`](../../decisions/).

---

## 1. Pregled i cilj

Administrator prodavnice mora da upravlja payment metodama koje se nude kupcu na naplati: da ih vidi u listi, doda novu, izmeni postojeću, privremeno je isključi, ili je trajno obriše.

Cilj ovog tiketa je **kompletan ekran, bez sloja za trajno čuvanje**. Podaci žive u statičkom nizu u kodu, iza repository interfejsa. Zamena tog niza bazom je poseban posao i sme da dira samo jednu klasu.

---

## 2. Opseg

### 2.1 U opsegu

- Lista payment metoda sa svim kolonama i akcijama.
- Forma za kreiranje i izmenu, ista za oba slučaja.
- Modalni dijalog za potvrdu brisanja.
- Serverska validacija u Form Request klasi.
- Statički niz kao jedini izvor podataka, iza repository interfejsa.
- React single-page aplikacija kao frontend.

### 2.2 Van opsega

- Baza podataka, migracije, Eloquent modeli.
- Fajlovi kao skladište podataka (JSON, CSV, YAML).
- Autentikacija i autorizacija administratora.
- Prikaz payment metoda kupcu na naplati.
- Prevod interfejsa; jezik interfejsa je engleski.

---

## 3. Use cases

### PM-UC-01 — Pregled liste
- **Akter:** administrator
- **Preduslov:** aplikacija je podignuta.
- **Koraci:** otvori `/admin/payment-methods`.
- **Očekivani ishod:** tabela payment metoda sortirana po koloni order, sa kolonama order, name, code, type, currencies, countries, active switch i actions.

### PM-UC-02 — Uključivanje i isključivanje metode
- **Akter:** administrator
- **Preduslov:** lista sadrži bar jednu metodu.
- **Koraci:** klikne na active switch u redu metode.
- **Očekivani ishod:** stanje se menja odmah, bez dijaloga i bez otvaranja forme. Isključena metoda ostaje u listi, red je zatamnjen, switch je off. Ponovni klik je vraća u uključeno stanje.

### PM-UC-03 — Kreiranje metode
- **Akter:** administrator
- **Preduslov:** —
- **Koraci:** klikne `Add payment method` → popuni formu → `Save`.
- **Očekivani ishod:** metoda je dodata u niz i vidljiva u listi na poziciji koju određuje sort order.

### PM-UC-04 — Izmena metode
- **Akter:** administrator
- **Preduslov:** metoda postoji.
- **Koraci:** klikne `Edit` u redu metode → izmeni polja → `Save`.
- **Očekivani ishod:** izmene su vidljive u listi; naslov forme je bio naziv te metode.

### PM-UC-05 — Odustajanje od izmenjene forme
- **Akter:** administrator
- **Preduslov:** forma je otvorena i bar jedno polje je promenjeno.
- **Koraci:** klikne `Cancel`.
- **Očekivani ishod:** traži se potvrda. Potvrdom se vraća na listu bez ijedne izmene; odustajanjem ostaje u formi.

### PM-UC-06 — Odbijena validacija
- **Akter:** administrator
- **Preduslov:** forma je otvorena.
- **Koraci:** unese duplirani `code` → `Save`.
- **Očekivani ishod:** zahtev je odbijen, greška stoji uz polje `code`, sve unete vrednosti su zadržane, niz je nepromenjen.

### PM-UC-07 — Brisanje metode
- **Akter:** administrator
- **Preduslov:** metoda postoji.
- **Koraci:** klikne `Delete` u redu metode → u dijalogu klikne `Delete`.
- **Očekivani ishod:** metoda i njene veze sa valutama i zemljama su uklonjene, korisnik je na listi, prikazana je poruka o uspehu, zapisan je log.

### PM-UC-08 — Odustajanje od brisanja
- **Akter:** administrator
- **Preduslov:** dijalog za brisanje je otvoren.
- **Koraci:** klikne `Cancel`.
- **Očekivani ishod:** dijalog se zatvara, ništa nije promenjeno.

### PM-UC-09 — Prazna lista
- **Akter:** administrator
- **Preduslov:** nijedna metoda ne postoji.
- **Koraci:** otvori `/admin/payment-methods`.
- **Očekivani ishod:** umesto tabele stoji kratko objašnjenje i akcija `Add payment method`.

---

## 4. Funkcionalni zahtevi

### 4.1 Izvor podataka

- **PM-FR-01** Payment metode se čuvaju u statičkom nizu napisanom u kodu. Svaki zapis ima `id`, `name`, `code`, `type`, `currencies`, `countries`, `sortOrder` i `active`.
- **PM-FR-02** `currencies` i `countries` su liste kodova, ne objekata.
- **PM-FR-03** Dozvoljeni kodovi valuta i dozvoljeni kodovi zemalja stoje u sopstvenim nizovima. To su jedine vrednosti koje forma nudi za izbor i jedine koje validacija prihvata.
- **PM-FR-04** Niz je dostupan isključivo kroz repository interfejs. Nijedan sloj iznad repozitorijuma ne sme znati odakle podaci dolaze.
- **PM-FR-05** Implementacija repozitorijuma se vezuje za interfejs u sopstvenom service provideru, ne u `AppServiceProvider`.
- **PM-FR-06** Svaka operacija čitanja i pisanja prolazi kroz tu jednu klasu, tako da zamena niza bazom ne dira nijedan drugi fajl.
- **PM-FR-07** Kontroler injektuje tačno jedan servis i ne sadrži logiku.

### 4.2 Lista

- **PM-FR-10** Lista je dostupna na ruti `/admin/payment-methods`.
- **PM-FR-11** Tabela ima kolone, ovim redom: order, name, code, type, currencies, countries, active switch, actions.
- **PM-FR-12** Lista je sortirana rastuće po `sortOrder`. Taj redosled je onaj kojim se metode prikazuju kupcu.
- **PM-FR-13** Active switch menja stanje odmah, u oba smera, bez dijaloga i bez otvaranja forme. Zahtev prekidača mora nositi traženo stanje kao logičku vrednost; prazan ili besmislen zahtev se odbija, a ne tumači kao isključivanje.
- **PM-FR-14** Isključivanje metode je nikada ne uklanja iz liste.
- **PM-FR-15** Neaktivan red je vizuelno zatamnjen, a njegov switch je u off položaju.
- **PM-FR-16** Kolona actions sadrži `Edit` i `Delete`.
- **PM-FR-17** `Edit` otvara formu za tu metodu.
- **PM-FR-18** `Delete` otvara dijalog za potvrdu. Brisanje direktno iz liste ne postoji.
- **PM-FR-19** Kad nijedna metoda ne postoji, tabela se zamenjuje kratkim objašnjenjem i akcijom `Add payment method`.
- **PM-FR-20** `Add payment method` otvara formu za kreiranje.

### 4.3 Forma

- **PM-FR-30** Ista forma opslužuje kreiranje i izmenu.
- **PM-FR-31** Rute su `/admin/payment-methods/create` i `/admin/payment-methods/{id}/edit`.
- **PM-FR-32** Pri kreiranju su polja prazna, a naslov je `New payment method`.
- **PM-FR-33** Pri izmeni je naslov naziv metode koja se menja.
- **PM-FR-34** Polje `name` je tekstualno i predstavlja ono što kupac vidi.
- **PM-FR-35** Polje `code` je tekstualno i koristi ga sistem, ne čovek.
- **PM-FR-36** Polje `type` je par radio dugmadi: `Redirect` i `iFrame`. Odlučuje da li kupac napušta prodavnicu ili se plaćanje prikazuje u ugnežđenom okviru.
- **PM-FR-37** Polja `currencies` i `countries` dozvoljavaju višestruki izbor i prikazuju izabrane kodove kao tagove koji se mogu ukloniti.
- **PM-FR-38** Ista valuta i ista zemlja smeju pripadati većem broju metoda.
- **PM-FR-39** Polje `sort order` prima ceo broj i određuje poziciju u listi.
- **PM-FR-40** Polje `active` ima isto značenje kao switch u listi i **obavezno je**. Zahtev koji ga izostavi se odbija — podrazumevana vrednost bi tiho ugasila metodu, a gašenje je po PM-FR-69 druga radnja.
- **PM-FR-41** `Save` prvo validira. Ništa se ne upisuje dok sva pravila ne prođu.
- **PM-FR-42** `Cancel` vraća na listu bez upisa. Ako je forma menjana, prvo se traži potvrda.

### 4.4 Validacija

- **PM-FR-50** Validacija se izvršava u Form Request klasi, ne u kontroleru i ne u servisu.
- **PM-FR-51** `name` je obavezno.
- **PM-FR-52** `code` je obavezno i jedinstveno u nizu. Pri izmeni se iz provere izuzima metoda koja se menja.
- **PM-FR-53** `type` je obavezno i mora biti jedna od dve dozvoljene vrednosti.
- **PM-FR-54** `sortOrder` mora biti ceo broj.
- **PM-FR-55** Svaki kod valute mora biti iz niza dozvoljenih valuta.
- **PM-FR-56** Svaki kod zemlje mora biti iz niza dozvoljenih zemalja.
- **PM-FR-57** Obavezna je bar jedna valuta i bar jedna zemlja. Metoda koja ne važi nigde ne može biti sačuvana.
- **PM-FR-58** Svaka greška se prikazuje uz svoje polje.
- **PM-FR-59** Pri odbijenoj validaciji forma zadržava sve unete vrednosti i označava svako polje koje nije prošlo.

### 4.5 Brisanje

- **PM-FR-60** Brisanju uvek prethodi modalni dijalog za potvrdu.
- **PM-FR-61** Naslov dijaloga je `Delete payment method?`.
- **PM-FR-62** Prvi pasus imenuje metodu i obim brisanja, sa istaknutim nazivom i kodom u zagradi: `Bancontact (BCMC) will be permanently removed, together with its currency and country assignments.`
- **PM-FR-63** Drugi pasus glasi `This action cannot be undone.`
- **PM-FR-64** Dijalog ima dva dugmeta, poravnata desno: `Cancel` je sekundarno (obrub, bez ispune), `Delete` je primarno u destruktivnoj crvenoj boji i stoji desno od `Cancel`.
- **PM-FR-65** Dijalog je modalni: ekran iza njega ostaje vidljiv, ali nedostupan dok se na dijalog ne odgovori.
- **PM-FR-66** `Cancel` zatvara dijalog i ne menja ništa.
- **PM-FR-67** `Delete` uklanja metodu i njene veze sa valutama i zemljama, vraća korisnika na listu i prikazuje poruku o uspehu.
- **PM-FR-68** Uspešno brisanje upisuje log zapis.
- **PM-FR-69** Deaktivacija i brisanje su odvojene putanje koje se nikada ne pozivaju međusobno. Metoda koja treba samo da prestane da se nudi isključuje se, ne briše.

### 4.6 Frontend

- **PM-FR-80** Frontend je React single-page aplikacija.
- **PM-FR-81** Navigacija između liste i forme ne izaziva ponovno učitavanje stranice.
- **PM-FR-82** Direktno otvaranje bilo koje od tri rute, kao i osvežavanje stranice na njoj, prikazuje odgovarajući ekran.

---

## 5. Ograničenja i zabrane

- **PM-C-01** Bez baze podataka: ovaj tiket ne dodaje nijednu migraciju, nijedan Eloquent model i nijednu tabelu, i nijedan njegov fajl ne čita iz baze. Migracije i modeli koje je ranije uveo tiket domenskog modela (`docs/domenProblema/`) ostaju netaknuti i ovaj ekran ih ne dodiruje.
- **PM-C-02** Bez fajlova kao skladišta podataka.
- **PM-C-03** Brisanje direktno iz liste, bez potvrde, je defekt.
- **PM-C-04** Deaktivacija ne sme biti implementirana kroz brisanje, niti brisanje kroz deaktivaciju.
- **PM-C-05** Nijedan sloj iznad repozitorijuma ne sme sadržati literal iz statičkog niza.

---

## 6. Acceptance criteria

Svi kriterijumi se proveravaju nad aplikacijom podignutom na `http://localhost:8080`. Automatski se izvršavaju sa `docker compose exec app php artisan test`.

| ID | Kriterijum | Verifikacija | Očekivano |
|---|---|---|---|
| **PM-AC-01** | Lista je sortirana po `sortOrder` | `GET /admin/api/payment-methods` | JSON niz sa `sortOrder` u rastućem redosledu |
| **PM-AC-02** | Tabela ima tačno propisane kolone i redosled | vizuelno na `/admin/payment-methods` | order, name, code, type, currencies, countries, active, actions |
| **PM-AC-03** | Switch deluje odmah, bez dijaloga | klik na switch | red menja izgled bez navigacije i bez prozora |
| **PM-AC-04** | Isključivanje ne briše metodu | isključi pa prebroj redove | broj redova nepromenjen, `active=false` |
| **PM-AC-05** | Neaktivan red je zatamnjen | isključi metodu | red ima umanjenu vidljivost, switch off |
| **PM-AC-06** | Prazna lista daje objašnjenje i akciju | obriši sve metode | nema tabele; tekst i dugme `Add payment method` |
| **PM-AC-07** | Naslov pri kreiranju | otvori `/admin/payment-methods/create` | naslov `New payment method`, sva polja prazna |
| **PM-AC-08** | Naslov pri izmeni | otvori `/admin/payment-methods/1/edit` | naslov je naziv metode, polja popunjena |
| **PM-AC-09** | Duplirani `code` je odbijen | `POST` sa postojećim kodom | `422`, greška na ključu `code` |
| **PM-AC-10** | Unique izuzima metodu koja se menja | `PUT` metode njenim sopstvenim kodom | `200` |
| **PM-AC-11** | Prazan izbor valuta je odbijen | `POST` sa `currencies: []` | `422`, greška na ključu `currencies` |
| **PM-AC-12** | Nedozvoljen kod je odbijen | `POST` sa `currencies: ["XXX"]` | `422`, greška na ključu `currencies.0` |
| **PM-AC-13** | Nedozvoljen `type` je odbijen | `POST` sa `type: "popup"` | `422`, greška na ključu `type` |
| **PM-AC-14** | Odbijena validacija ne menja niz | `POST` koji padne, pa `GET` | broj metoda nepromenjen |
| **PM-AC-15** | Greške stoje uz svoja polja | pošalji nevalidnu formu u browseru | poruka ispod svakog pogođenog polja, unete vrednosti zadržane |
| **PM-AC-16** | `Cancel` na izmenjenoj formi traži potvrdu | promeni polje pa `Cancel` | prozor za potvrdu; odustajanje ostavlja u formi |
| **PM-AC-17** | `Cancel` na nepromenjenoj formi ne pita | odmah `Cancel` | povratak na listu bez pitanja |
| **PM-AC-18** | Brisanje ide isključivo kroz dijalog | klik na `Delete` u redu | otvara se dijalog; metoda još postoji |
| **PM-AC-19** | Dijalog imenuje metodu i obim | otvori dijalog | `Bancontact (BCMC) will be permanently removed, together with its currency and country assignments.` |
| **PM-AC-20** | Dijalog je modalni | otvori dijalog pa klikni pozadinu | pozadina vidljiva, klik nema efekta |
| **PM-AC-21** | `Cancel` u dijalogu ne menja ništa | otvori dijalog pa `Cancel` | metoda i dalje u listi |
| **PM-AC-22** | Brisanje uklanja metodu i vraća poruku | potvrdi brisanje | metoda nestala, poruka o uspehu na listi |
| **PM-AC-23** | Brisanje upisuje log | potvrdi brisanje, pogledaj log | zapis sa `id`, `name` i `code` obrisane metode |
| **PM-AC-24** | Deaktivacija ne loguje brisanje | isključi metodu, pogledaj log | nema zapisa o brisanju |
| **PM-AC-25** | SPA ne učitava stranicu ponovo | navigiraj lista → forma → lista | nema punog reload-a |
| **PM-AC-26** | Deep link radi | osveži na `/admin/payment-methods/3/edit` | forma te metode se otvara |
| **PM-AC-27** | Ovaj tiket ne uvodi ni migraciju ni model za payment metode | `git diff --name-only` nad granom | nijedan fajl u `database/migrations/` ni `app/Models/` nije dodat ovim tiketom |
| **PM-AC-28** | Prekidač odbija zahtev bez logičke vrednosti | `PATCH .../active` sa `{}` i sa `{"active":"maybe"}` | `422`, greška na ključu `active`; metoda nepromenjena |
| **PM-AC-29** | Izmena bez `active` je odbijena | `PUT` bez polja `active` | `422`, greška na ključu `active`; metoda nepromenjena |
| **PM-AC-30** | Brisanje ne oslobađa `id` za ponovnu upotrebu | kreiraj, obriši, pa opet kreiraj | drugi `id` je veći od prvog |

---

## 7. Traceability matrica

| Zahtev iz taska | PM-FR | PM-AC |
|---|---|---|
| Statički niz iza repository interfejsa, bindovan u sopstvenom provideru | PM-FR-01…07 | PM-AC-27 |
| Lista kroz rutu i tanak kontroler, sortirana po sortOrder | PM-FR-10…12 | PM-AC-01, PM-AC-02 |
| Switch deluje odmah, isključivanje ne briše | PM-FR-13…15 | PM-AC-03…05 |
| Prazno stanje | PM-FR-19, PM-FR-20 | PM-AC-06 |
| Jedna forma za create i edit | PM-FR-30…33 | PM-AC-07, PM-AC-08 |
| Polja forme | PM-FR-34…40 | PM-AC-15 |
| Validacija u Form Requestu | PM-FR-50…59 | PM-AC-09…15 |
| Cancel uz potvrdu | PM-FR-42 | PM-AC-16, PM-AC-17 |
| Modalni dijalog za brisanje | PM-FR-60…67 | PM-AC-18…22 |
| Log pri brisanju | PM-FR-68 | PM-AC-23 |
| Deaktivacija i brisanje razdvojeni | PM-FR-69 | PM-AC-24 |
| React SPA | PM-FR-80…82 | PM-AC-25, PM-AC-26 |
| Deaktivacija i brisanje se ne spajaju | PM-FR-13, PM-FR-40, PM-FR-69 | PM-AC-28…PM-AC-30 |

---

## 8. Otvorena pitanja

| ID | Pitanje | Status | Odluka |
|---|---|---|---|
| PM-Q-01 | Sadržaj početnog niza i liste dozvoljenih valuta i zemalja — task imenuje samo `Bancontact (BCMC)` | **zatvoreno** (2026-09-17) | Vlasnica odobrila pet metoda (Bancontact, iDEAL, Credit Card, PayPal, SOFORT), valute `EUR, USD, GBP, CHF, RSD`, zemlje `BE, NL, DE, AT, GB, US, RS`. |
| PM-Q-02 | Kako upisi preživljavaju zahtev bez baze i bez fajlova | **zatvoreno** (2026-09-17) | Statički niz je seed, radna kopija u sesiji. Obrazloženje u [ADR-16](../../decisions/ADR-16-staticki-niz-u-sesiji.md). |
| PM-Q-03 | Da li „no files" zabranjuje i log zapis | **zatvoreno** (2026-09-17) | Ne. Zabrana se odnosi na fajl kao skladište podataka; PM-FR-68 izričito traži log. |

> Nove nejasnoće se upisuju ovde i razrešavaju sa naručiocem — ne rešavaju se pretpostavkom.
