# ADR-16 — Statički niz je seed, radna kopija payment metoda stoji u sesiji

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** tiket za administraciju payment metoda traži statički niz napisan u kodu kao jedini izvor podataka, izričito bez baze i bez fajlova, i istovremeno traži da svaka operacija „reads and writes that array" — kreiranje, izmenu, uključivanje i brisanje. Te dve rečenice se ne mogu obe ispuniti doslovno. PHP je shared-nothing: `private const SEED` se iznova konstruiše na svakom zahtevu, pa upis nestaje pre nego što korisnik vidi odgovor. Posledica nije kozmetička — PM-FR-67 traži da brisanje vrati korisnika na listu **sa porukom o uspehu**, a poruka o uspehu posle radnje koja se poništila je netačna poruka.

**Opcije:** (a) statički niz kao seed, radna kopija u sesiji preko `file` drivera; (b) isto, ali preko `cookie` drivera, pa podaci ne dodiruju ni disk ni bazu; (c) isto, preko podrazumevanog `database` drivera; (d) repozitorijum kao singleton, upis živi jedan zahtev; (e) React drži stanje, backend samo validira.

**Odluka:** (a).

**Obrazloženje:** (d) je najdoslovnije čitanje zabrane, i zato pada — posle brisanja bi refresh vratio metodu, a lista bi pokazivala jedno a server drugo. Gore od toga, provera jedinstvenosti koda iz PM-FR-52 gledala bi u seed, pa bi drugi uzastopni unos istog koda prošao. Zabrana bi bila ispoštovana po slovu, a tri zahteva oborena.

(e) pomera stanje u browser i time gubi serversku proveru jedinstvenosti nad stvarnim skupom — validacija bi mogla da sudi samo o onome što joj klijent pošalje, što validaciju čini savetom.

(b) je bio prvi izbor, i **izmeren je kao neispravan**. Sesijski kolačić ima tvrdo ograničenje od oko 4 KB. Sa pet metoda iz seed-a i po dve valute i tri zemlje po metodi, granica se dostiže na **šestoj** metodi. Ponašanje preko granice je najgora moguća vrsta kvara: `POST` i dalje vraća `201` sa novododeljenim `id`-jem, klijent prikaže poruku o uspehu, a lista ostaje nepromenjena — kolačić se tiho odbacuje, bez greške i bez traga u logu. Mereno je nizom uzastopnih kreiranja: metode 7 do 13 sve su vratile `201`, a lista je ostala na šest. Uz to bi `codeExists` od te tačke počeo da propušta duplikate, jer više ne vidi zapise koji „postoje".

(c) radi i nema ograničenje veličine, ali podatke odlaže u `sessions` tabelu. Tiket zabranjuje bazu, i premda je sesija infrastruktura okvira a ne skladište funkcionalnosti, ta razlika je tanka i tražila bi objašnjenje pri svakom pregledu.

(a) uklanja i jedno i drugo: nema ograničenja veličine, nema tabele. Sesijski fajl u `storage/framework/sessions/` je mehanizam okvira, isti onaj koji bi postojao i da ovog ekrana nema — a zabrana „no files" iz tiketa se odnosi na fajl kao **skladište podataka** funkcionalnosti, ne na infrastrukturu sesije. Provereno posle izmene: trideset metoda u listi, bez ijednog izgubljenog upisa.

Bitno je šta odluka **ne** menja. Statički niz ostaje napisan u kodu i ostaje jedini izvor početnog stanja. Sesija je mesto gde radna kopija leži, i za nju zna isključivo `ArrayPaymentMethodRepository`. Interfejs, servis, kontroler i React ne vide razliku, pa PM-FR-04 i PM-FR-06 važe neokrnjeno — zamena niza bazom i dalje dira jednu klasu.

**Posledice:** `SESSION_DRIVER=file` je dopisan u `.env` i `.env.example`, čime se menja ponašanje sesije za celu aplikaciju, ne samo za ovaj ekran; podrazumevana vrednost u `config/session.php` je `database`. Sesijski fajlovi žive u `storage/framework/sessions/`, koji entrypoint već proverava kao zapisiv, pa nov preduslov ne nastaje. Radna kopija je po pregledaču, pa dva administratora vide dve različite liste — za ekran bez autentikacije i bez baze to je prihvaćeno. `docker compose down -v` ne dira `storage/`, pa radna kopija preživljava reset volumena; briše je brisanje sesijskog fajla ili istek sesije.
