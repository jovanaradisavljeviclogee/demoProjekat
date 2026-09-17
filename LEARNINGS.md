# Learnings

Jedna datirana stavka po radnoj sesiji, najnovija na vrhu: šta je promenjeno, šta je pošlo naopako, i ispravka.

---

## 2026-09-17 — administracija payment metoda (React SPA)

### Promenjeno

- Ekran `/admin/payment-methods` kao React single-page aplikacija: lista, forma za create/edit i modalni dijalog za brisanje. Dokumentacija u `docs/features/payment-methods/`, odluke kao ADR-16 do ADR-19.
- Uveden `PaymentMethodRepositoryInterface` sa jedinom implementacijom `ArrayPaymentMethodRepository`, vezan u zasebnom `PaymentMethodServiceProvider`. Statički niz u kodu je jedini izvor; tiket izričito zabranjuje bazu i fajlove.
- React, react-dom, react-router-dom i `@vitejs/plugin-react` dodati u `package.json`; ulaz Vite-a prebačen sa `resources/js/app.js` na `resources/js/app.jsx`.
- `SESSION_DRIVER=cookie` dopisan u `.env` i `.env.example` (ADR-16).
- `phpunit.xml` dopunjen `<server>` linijama — v. nalaz ispod; izmena važi za ceo projekat, ne samo za ovaj ekran.
- `tests/Feature/PaymentMethodTest.php`: 19 testova nad kriterijumima iz `spec.md`.
- `README.md` dobio sekciju 12 o `npm run dev` na hostu; `CLAUDE.md` dobio izuzetak od pravila „sve kroz `app` kontejner" (ADR-19).

### Šta je pošlo naopako

**Obrisao sam tabele čije migracije postoje na ovoj grani.** Zadatak je počeo na `feature/laravel_and_docker`, gde `database/migrations/` zaista nema payment tabele, a `app/Models/` ima samo `User`. Baza je pritom imala devet payment tabela i dvanaest redova u `migrations`. Zaključak je bio da su ostatak napuštenog pokušaja, i vlasnica ga je potvrdila istim opisom — „nemam migracije, to sam nešto pokušavala". Tabele su obrisane, a njihovi redovi uklonjeni iz `migrations`.

Grana je zatim promenjena na `data_and_payment_page`, koja nosi commit `643822f` sa **svih devet migracija, osam modela i `data.md`**. Iste te tabele su tamo commitovan kod, ne ostatak. Provera je bila tačna za granu na kojoj je izvršena i pogrešna za granu na kojoj je posao trebalo da se radi — a to su bile dve različite grane.

**Četiri ADR-a su dobila zauzete brojeve.** ADR-13, ADR-14 i ADR-15 su napisani u toku ove sesije, dok su ADR-13, ADR-14 i ADR-15 sa istim brojevima već postojali u commit-u `643822f`. `docs/decisions/` je nakratko imao tri para fajlova sa istim brojem i različitim sadržajem — tačno ono što `CLAUDE.md` zabranjuje rečenicom da oznaka mora ostati jednoznačna.

**Masovna zamena je pogodila sopstveni rezultat.** Prebrojavanje je izvedeno kao lanac zamena `ADR-16→19, 15→18, 14→17, 13→16` nad istim fajlovima. Pošto se `ADR-13-staticki-niz-u-sesiji` zamenjuje u `ADR-16-staticki-niz-u-sesiji`, a pravilo `ADR-16→ADR-19` je u istom prolazu, tri linka su postala `ADR-19-staticki-niz-u-sesiji.md` — fajl koji ne postoji.

**Ekran je „radio" četiri puta zaredom, a nijednom se nije video u pregledaču.** Rute su vraćale `200`, testovi su prolazili, `curl` je vraćao ispravan JSON i ispravan HTML. Stranica je svejedno bila bela. Četiri različita uzroka, jedan za drugim, i nijedan nije ostavio trag na serverskoj strani:

| # | Poruka u konzoli | Uzrok |
|---|---|---|
| 1 | — | dva Vite servera: zaostao proces iz agentske sesije držao `5173`, novi tiho prešao na `5174` i tamo upisao `public/hot` |
| 2 | `ERR_ADDRESS_INVALID` | pokretanje sa golim `--host` upisalo `http://[::]:5173` u `public/hot` — IPv6 wildcard je adresa **vezivanja**, ne adresa **pristupa**; pregledač je odbija pre nego što pošalje ijedan paket |
| 3 | `blocked by CORS policy`, `ERR_FAILED 200` | stranicu servira Apache na `8080`, module Vite na `5173` — dva porekla. Vite 7 ima pooštrenu podrazumevanu CORS politiku i vraćao je `Allow-Origin: 5173` |
| 4 | `can't detect preamble` | u Blade shell-u nedostajao `@viteReactRefresh` **pre** `@vite` |

Prva tri su okruženje, četvrti je moj propust u kodu. I baš četvrti najbolje pokazuje zašto ništa od ovoga nije bilo uhvaćeno: `@viteReactRefresh` u produkcionom build-u ne ispisuje ništa, pa je `vite build` prolazio potpuno čist. Subagent koji je pisao frontend prijavio je „105 modules transformed, built in 1.32s" kao dokaz završenosti — i bio je u pravu za build, a ekran svejedno nije radio.

Svaki sledeći kvar se video tek kad se prethodni ukloni. Vlasnica je morala četiri puta da prekopira konzolu da bi se stiglo do kraja.

**Dva fajla su se razlikovala samo po veličini slova.** `resources/js/app.jsx` (montiranje) i `resources/js/App.jsx` (ruter) stajali su u istom folderu. Na Linuxu su to dva fajla; na Windows-u i macOS-u jedan. Vlasnica je prijavila da „`app.jsx` lokalno ne postoji" — zapravo ga je gledala kroz Windows, gde se ta dva imena sudaraju i vidi se samo jedno.

To nije bio problem prikaza nego portabilnosti: klon ove grane na Windows ili Mac znači da jedan fajl pregazi drugi i `npm run build` pukne porukom koja ne imenuje uzrok. `git config core.ignorecase` je na ovoj mašini prazno, pa git nije upozoravao.

**`SESSION_DRIVER=cookie` je tiho gubio svaki upis od šeste metode nadalje.** ADR-16 je prvo izabrao `cookie` driver da radna kopija ne dodirne ni bazu ni disk, a ograničenje kolačića od ~4 KB zabeležio kao rizik PM-R-02 sa `file` driverom kao izlazom. Rizik se ostvario odmah, na petoj metodi iz seed-a plus jednoj novoj.

Način otkazivanja je bio najgori mogući: `POST` vraća `201` sa novododeljenim `id`-jem, klijent prikaže poruku o uspehu, a lista ostane nepromenjena. Nema greške, nema izuzetka, nema zapisa u logu — kolačić se prosto odbaci. Mereno nizom uzastopnih kreiranja: zahtevi za metode 7 do 13 svi su vratili `201`, a lista je ostala na šest. Od te tačke bi i `codeExists` počeo da propušta duplikate, jer više ne vidi zapise koji „postoje".

Nalaz nije došao od mene nego od subagenta koji je pisao HTTP sloj, kao prijava izvan njegovog vlasništva.

**Prekidač je na prazno telo gasio metodu.** `PATCH .../active` nije imao Form Request, pa je `$request->boolean('active')` čitao izostanak polja i svaku neprepoznatu vrednost kao `false`. Prazan zahtev je isključivao metodu i vraćao `200`. Isto je važilo za `PUT` bez polja `active`. Deaktivacija je time postajala sporedni efekat pokvarenog zahteva — a PM-FR-69 traži da gašenje bude zasebna, namerna radnja.

**`nextId()` je reciklirao `id` obrisane metode.** `max(id) + 1` posle brisanja poslednje metode dodeljuje upravo njen broj, pa otvorena kartica sa linkom `/admin/payment-methods/{id}/edit` neprimetno počinje da menja drugu metodu.

**Početna stranica je ostala da traži obrisani fajl.** `resources/views/welcome.blade.php:15` je i dalje imao `@vite([... 'resources/js/app.js'])` pošto je ulaz Vite-a prebačen na `app.jsx`. Sa pokrenutim dev serverom stranica se i dalje otvarala, pa se ništa nije primetilo; posle produkcionog build-a manifest nema taj ključ i `GET /` pada sa `ViteManifestNotFoundException`. Kvar je bio nevidljiv tačno u režimu u kom se radi.

**Testovi se nisu izvršavali u `testing` okruženju — ni pre ovog tiketa.** Prvi pokušaj pokretanja dao je `419 CSRF token mismatch` na svakom `POST`, `PUT`, `PATCH` i `DELETE`. Uzrok nije bio u testu: `app()->environment()` je vraćalo `local`, pa `VerifyCsrfToken::runningUnitTests()` nije bio tačan, a `config('database.default')` je bilo `mysql` umesto `sqlite`.

Lanac je specifičan za ovo okruženje. `compose.yaml` učitava `.env` kroz `env_file`, pa `APP_ENV`, `DB_CONNECTION` i ostale postaju **prave procesne promenljive** u `app` kontejneru i završe u `$_SERVER`. `phpdotenv` čita adaptere redom `$_SERVER` → `$_ENV` → `putenv`, a PHPUnit-ov `<env force="true">` postavlja `getenv` i `$_ENV`, ali **`$_SERVER` ne dira**. Prvi adapter je pobeđivao, i to tiho: nijedan test nije padao pre ovog tiketa jer nijedan postojeći test nije slao zahtev koji menja stanje niti dodirivao bazu.

### Ispravka

- `vite.config.js` dobio `host: '0.0.0.0'`, `strictPort: true`, `origin: 'http://localhost:5173'` i `cors.origin` vezan za `APP_URL` iz `.env` preko `loadEnv`. Zakucan `8080` bi tiho prestao da važi čim neko promeni `WEB_HOST_PORT`.
- `@viteReactRefresh` dodat u `resources/views/admin/payment-methods.blade.php`, pre `@vite`.
- `resources/js/app.jsx` preimenovan u `main.jsx`; korenska komponenta ostaje `App.jsx`. Provera da se sudar ne vrati: `ls resources/js | tr 'A-Z' 'a-z' | sort | uniq -d` mora biti prazno.
- `README` sekcija 12 dobila tabelu „Ako je stranica prazna" — poruka iz konzole → uzrok → rešenje, za sva četiri slučaja — i napomenu da postojeći `.env` treba dopuniti sa `SESSION_DRIVER=file`.
- `SESSION_DRIVER` prebačen na `file`; ADR-16 prepisan tako da `cookie` stoji kao **odbačena opcija sa izmerenim razlogom**, a ne kao izbor sa napomenom. Provereno posle izmene: trideset metoda u listi, nijedan izgubljen upis.
- `active` je sada obavezna logička vrednost u sva tri Form Requesta; dodat `ToggleActiveRequest`. Prazan ili besmislen prekidački zahtev vraća `422` umesto da ugasi metodu.
- Brojač `id`-jeva je monoton i čuva se u sesiji odvojeno od niza, pa brisanje ne oslobađa identitet.
- `welcome.blade.php` više ne traži JS ulaz — ta stranica nema React da montira, pa joj je ostao samo CSS.
- `phpunit.xml` je dobio `<server ... force="true"/>` linije uz postojeće `<env>`, za `APP_ENV`, `DB_CONNECTION`, `DB_DATABASE`, `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` i `MAIL_MAILER`. Provereno: `env=testing`, `db=sqlite`. Suita je posle toga 22 testa, 68 tvrdnji, sve prolazi.
- `php artisan migrate --force` vratio je svih devet tabela. Stanje je provereno brojanjem: `users` 11 redova i `connections` 1 red, isto kao pre brisanja. Jedina razlika je da su tabele prazne kao i pre — sve osim `connections` su i ranije imale nula redova.
- ADR-ovi ove sesije prebrojani su na **ADR-16 do ADR-19**; serija `docs/decisions/` sada ide od ADR-10 do ADR-19 bez dvojnika i bez preskoka.
- Svaki link ka ADR fajlu proveren je postojanjem fajla, ne pogledom — četiri od četiri se razrešavaju.

**Najvažnija pouka ove sesije: za frontend „ruta vraća 200" nije dokaz da ekran radi.** Sve serverske provere — status kod, JSON, HTML, `artisan test` — bile su zelene dok je korisnik gledao belu stranicu. Jedini instrument koji je pokazivao uzrok bila je konzola pregledača. Task koji isporučuje ekran nije završen dok taj ekran nije otvoren, a `vite build` koji prolazi ne zamenjuje to, jer dev i produkcioni put imaju različite kvarove.

**Peta pouka: imena fajlova koja se razlikuju samo po veličini slova su kvar, ne stil.** Razvojna mašina je Linux, ali repozitorijum se klonira i drugde.

**Treća pouka: rizik zapisan u dokumentu nije rizik kojim se upravlja.** PM-R-02 je bio uredno opisan, sa tačnom granicom i tačnim izlazom, i svejedno je pušten u kod. Zapis je opisao šta će se desiti, ali ništa nije proverilo da li se desilo. Rizik čije je merenje jeftino — ovde je to bilo sedam uzastopnih `POST` zahteva — meri se pre nego što se zapiše kao prihvaćen.

**Pouka: grana je deo premise, ne pozadina.** Pitanje „postoje li migracije za ove tabele" nema odgovor bez imena grane. Pre svake nepovratne radnje nad zajedničkim stanjem — a baza to jeste — proverava se `git branch --show-current`, ne samo radno stablo.

**Druga pouka: pre dodele novog ADR broja, izlistaj `docs/decisions/`.** Sledeći slobodan broj se ne pamti iz ranijeg čitanja dokumentacije; on se očitava u trenutku pisanja, jer ga je neko drugi mogao zauzeti u međuvremenu.

### Ostavljeno namerno

- `app/Models/PaymentMethod.php` i pripadajuće migracije ostaju netaknuti. Ovaj tiket ih ne koristi — traži statički niz — pa Eloquent model i `ArrayPaymentMethodRepository` postoje uporedo, a ekran ide kroz repozitorijum. Spajanje ta dva je posao tiketa koji uvodi bazu iza istog interfejsa.
- Dve konvencije za dokumentaciju po tiketu: `docs/domenProblema/` i `docs/features/payment-methods/`. Obe su upisane u `CLAUDE.md` kako stoje; ujednačavanje nije rađeno da ne bi pomeralo tuđe putanje u istom PR-u.

---

## 2026-09-17 — domenski model

### Promenjeno

- Domenski model iz `data.md` preveden u šemu: deset tabela, osam Eloquent modela, jedan repozitorijum i jedan korisnički seeder. Dokumentacija u `docs/domenProblema/` (`spec.md`, `design.md`, `tasks.md`), odluke kao ADR-13, ADR-14 i ADR-15.
- `users` usklađen sa `data.md` izmenom **postojeće** migracije, ne novom `ALTER` migracijom (ADR-13). Uklonjeni `updated_at`, `email_verified_at` i `remember_token`.
- Sejanje korisnika izmešteno iz `DatabaseSeeder` u nov `UserSeeder`; `DatabaseSeeder` je sada samo orkestrator.
- Uveden `app/Repositories/` sa `ProviderAccountRepository` kao jedinom tačkom upita nad `provider_accounts` (ADR-14). Bez izmene `composer.json` — `App\Repositories` pada pod postojeći PSR-4 koren.

### Šta je pošlo naopako

**NFR je bio napisan tako da ne može da prođe.** N11 u `design.md` i poslednji pasus ADR-14 tražili su da „broj fajlova u `app/` koji **pominju** `ProviderAccount` ostane 1". Model i repozitorijum su neizbežno dva fajla — model mora da postoji da bi repozitorijum imao šta da vrati. `grep` provera bi pala na prvom PR-u koji uvodi baš tu strukturu, i to na PR-u koji pravilo poštuje. Nalaz je došao od subagenta koji je task izvršavao, ne od pisca dokumenta.

**Uklanjanje kolone bi oborilo login.** Brisanje `remember_token` iz `users` ostavlja Laravel session guard da pri svakom „remember me" prijavljivanju izda `UPDATE` nad kolonom koje više nema — `SQLSTATE[42S22] Unknown column 'remember_token'`. `data.md` kolonu ne pominje, pa je zahtev bio ispravan; posledica po framework nije bila predviđena ni u `spec.md` ni u `design.md`.

**Šema se ne može verifikovati po tasku.** Prvi plan je predviđao da svaki task pokrene svoju proveru nad bazom. Ne može: `payment_method_currency` ima strani ključ na `currencies`, `logs` na `users`, pa `migrate:fresh` pada dok god ijedan fajl iz talasa nedostaje — i to ne govori ništa o kvalitetu taska koji ga je pokrenuo.

### Ispravka

- N11 i ADR-14 prepisani na ono što je zaista merljivo: **upiti** u tačno jednom fajlu, ne pominjanja. Uz to je u ADR-14 razrešeno prividno protivrečje — u fazi 2 nestaje tabela, ne nužno i klasa, jer `findActive()` vraća `?ProviderAccount` i tip ostaje deo potpisa.
- `User` dobio `protected $rememberTokenName = '';`. Time `getRememberToken()` i `setRememberToken()` postaju no-op, pa guard ne izdaje `UPDATE`. Provereno da otkazuje zatvoreno: `Recaller::valid()` odbija kolačić, `retrieveByToken()` se prekida na `null` tokenu — nema putanje za falsifikovan kolačić. Dodat i docblock upozorenje da `MustVerifyEmail` sada čita kolonu koje nema.
- `tasks.md` §1.1 eksplicitno deli odgovornost: talasi 1 i 2 dokazuju **fajl** (`php -l`, `grep`, čitanje uz `data.md`), talas 3 dokazuje **bazu**. Acceptance criteria koji traže bazu zato stoje i pod T9. To nije duplo pokrivanje nego podela — task piše, integracija dokazuje.

### Zabeleženo, nije promenjeno

- **Ponovljen `db:seed` gomila factory korisnike.** Izmereno u T9: posle dva pokretanja tabela ima **21** korisnika, ne 11. `updateOrCreate` štiti anchor (`COUNT(*) = 1`), pa R25 i AC prolaze kako su napisani, ali `README` i AC-06 iz `docs/spec.md` govore o 11 korisnika. Uz to `fake()->unique()->safeEmail()` de-duplicira samo unutar jednog procesa i ne zna za redove koji su već u tabeli — sudar na `users.email` je moguć iako se u ovom izvršavanju nije desio.
- **`Connection` krije `api_secret`, ali ne i `api_key`; `ProviderAccount` krije oba.** Asimetrija je namerna i prati `data.md`: tamo je samo `connections.api_secret` označen sa „nikad se ne vraća u browser". Za `provider_accounts` `data.md` ne propisuje `$hidden` uopšte, pa je krivanje oba ključa stroža odluka izvršioca — tuđe tajne, ne podaci prodavca. Nijedna ne krši izvor istine.
- **`down()` u `0001_01_01_000000_create_users_table.php` je preokrenut** u obrnuti redosled od `up()` — `sessions`, `password_reset_tokens`, `users`. ADR-13 obećava da su `password_reset_tokens` i `sessions` netaknuti i to i dalje važi za njihove `Schema::create` blokove; promenjen je samo redosled `dropIfExists` poziva. Ponašajno je inertno, jer među te tri tabele nema stranog ključa. Zapisano ovde da se ne čita kao protivrečnost sa ADR-13.

- `Connection` nema `$fillable`, pa `current()->update([...])` baca `MassAssignmentException`. UC2 u `design.md` opisuje ažuriranje jedinog reda, ali pozivalac (konfiguracioni ekran) je Non-goal ove iteracije. Dodavanje `$fillable` bi otvorilo mass-assignment putanju za `api_secret`, pa odluka čeka fazu u kojoj ekran zaista postoji.
- `Connection::current()` koristi `first()` bez `ORDER BY`. Na nivou baze ništa ne prikiva `connections` na jedan red — nema ni unique indeksa ni check ograničenja. Dok invarijanta važi, ponašanje je tačno.
- `settings.key` nasleđuje `utf8mb4_unicode_ci`, pa su i unique indeks i `where` neosetljivi na veličinu slova: `Setting::get('LOGO_URL')` nalazi red `logo_url`. `data.md` kolaciju ne propisuje.
- `settings.value` je `nullable`. `data.md` ga vodi kao običan `varchar` bez oznake `required` — za razliku od `name` kolona drugde, koje `required` nose izričito — pa je odsustvo obaveznosti pročitano kao nullable.
- `provider_accounts` nema indeks ni unique nad `(pspid, environment)`. `data.md` ih ne traži; unique bi usput učinio `first()` determinističkim, ali bi mogao da se sudari sa seeder-om koji još nije napisan.

## 2026-09-16

### Promenjeno

- Instaliran i omogućen plugin `demo-project@ai-toolkit` (`.claude/settings.local.json`). Projekat sada dobija konvencije ovog okruženja kroz skill-ove, umesto da se ponavljaju u svakom razgovoru.
- `ai workflow/` preimenovan u `docs/`. `spec.md`, `design.md` i `tasks.md` su zadržali sadržaj.
- `docs/` izuzet iz `.gitignore` — dokumentacija se sada verzioniše.
- `.dockerignore` ažuriran: tri putanje `ai workflow/*.md` → `docs/*.md`.
- Dodat `CLAUDE.md` koji imenuje plugin i pokazuje na `docs/` i na ovaj fajl.

### Šta je pošlo naopako

**Razmak u imenu foldera.** `ai workflow/` je od početka imao razmak. Svaka komanda nad njim morala je pod navodnicima, a bez njih se tumači kao dva argumenta. Isti tip greške se u toku iste sesije pojavio dvaput i u `ai-toolkit` repozitorijumu, gde je uzrokovao da se skill uopšte ne učita.

**Dokumentacija je bila gitignorisana.** `.gitignore` je sadržao `/ai workflow/*.md`, pa `spec.md`, `design.md` i `tasks.md` nisu bili u repozitorijumu — postojali su samo na disku ove mašine. To čini nemogućim pravilo „docs/ se ažurira u istom PR-u kao kod", jer ignorisan fajl ne može da bude deo PR-a. Istovremeno je značilo da bi ta tri dokumenta nestala pri svežem klonu.

**`git mv` nije prošao.** Pokušaj preimenovanja završio je sa `fatal: source directory is empty` — git je folder video kao prazan upravo zato što su svi fajlovi u njemu bili ignorisani. Poruka ne imenuje pravi uzrok.

### Ispravka

- Obično `mv` umesto `git mv`, pošto git nije pratio nijedan fajl.
- Uklonjena linija `/ai workflow/*.md` iz `.gitignore`, bez zamene — `docs/` se od sada verzioniše, pa pravilo „docs/ se ažurira u istom PR-u kao kod" postaje izvodljivo.
- Tri putanje u `.dockerignore` prepisane na `docs/`, da dokumentacija i dalje ne ulazi u Docker build kontekst. Bez toga bi preimenovanje tiho poništilo to pravilo.

### Testiranje plugin-a

Devet skill-ova provereno stvarnim pitanjima o ovom okruženju. Kriterijum nije bio da odgovor pogodi temu, nego da sadrži **konkretne vrednosti iz ovog projekta** — generičan Laravel odgovor znači da se skill nije učitao.

| Skill | Testno pitanje | Ishod |
|---|---|---|
| `docker-env` | kako pokrenuti `artisan migrate` ovde | prošao |
| `composer-autoload` | `Class "Domain\Foo" not found` | prošao |
| `laravel-structure` | gde ide nov controller | prošao |
| `laravel-init` | nova Laravel aplikacija na ovom okruženju | prošao |
| `writing-specs` | spec za `/health` endpoint | prošao |
| `writing-design` | `design.md` na osnovu spec-a | prošao |
| `writing-tasks` | podela na taskove | prošao |
| `executing-tasks` | početak implementacije po `tasks.md` | prošao |
| `performance-review` | provera da li je izmena spora | prošao |

Devet od devet, sa konkretnim vrednostima u odgovorima. Nijedna ispravka skill-a nije bila potrebna posle ovog kruga.

### Test agenta `structure-reviewer`

Agenti su napisani kasnije u istoj sesiji, pa je `structure-reviewer` pokrenut nad ovim repozitorijumom.

**Slučaj.** Grana `feature/laravel_and_docker`, meta `ExamplePSR/`. Taj folder ima unapred poznat tačan odgovor: `Hello.php` je ispravan i služi kao kontrola, `BrokenNamespace.php` deklariše `namespace Domain\Wrong` gde mapiranje traži `Domain`, a `WrongFileName.php` deklariše `class MismatchedClass` u fajlu drugog imena.

**Rezultat.** Našao oba kvara, nije prijavio `Hello.php`, i citirao pravila iz skill-ova umesto da izmisli konvenciju. Svih devet navedenih brojeva linija provereno je protiv repozitorijuma — svih devet tačno.

### Šta je test otkrio o ovom repozitorijumu

**Dva trajna upozorenja u svakom build logu.** `composer.json:77` ima `"optimize-autoloader": true`, pa je svaki dump optimizovan. One dve `does not comply ... Skipping.` linije, koje `AUTOLOADING-BREAKS.md` opisuje kao dijagnostiku koja se pokreće namerno, zapravo izlaze iz svakog `composer install`, svakog `dump-autoload` i svakog Docker build-a na `Dockerfile:47`.

Exit kod ostaje `0` i ništa ne pada. Rizik je drugačiji: tekst upozorenja imenuje fajl i pravilo, ali ne pokazuje nazad na objašnjenje. Prvi ko bude čistio bučan log „popravio" bi `BrokenNamespace.php` i preimenovao `WrongFileName.php` — i time tiho uništio dve trećine primera.

**Rupa u standardu, ne u repozitorijumu.** Nijedan skill nije govorio gde pripada prozna dokumentacija — pored koda koji objašnjava, ili u `docs/`. Agent je to prijavio kao nedostatak pravila, a ne kao prekršaj. Pravilo je posle toga dopunjeno u skill-u `laravel-structure`.

### Ispravka

- `Dockerfile` — komentar iznad `RUN composer dump-autoload`: upozorenja su očekivana, izvor su dva imenovana fajla, ne ispravljati ih.
- `README.md` — nova sekcija 10, „Dva očekivana upozorenja u build logu", sa tačnim tekstom poruka i napomenom da CI koji upozorenja tretira kao greške treba da ih izuzme poimence, umesto da se uklone primeri. Stara sekcija 10 postala je 11.
- `ExamplePSR/AUTOLOADING-BREAKS.md` — pasus koji objašnjava zašto upozorenja nisu opciona.
- `composer.json` **nije diran** — strogi JSON ne podnosi komentar.

### Otvoreno

`ExamplePSR/demo-breaks.sh` koristi `python3` (linije 69-74), a header na linijama 5-6 ga ne navodi kao preduslov — na mašini bez `python3` skripta pada nejasno. Uz to, `trap` na liniji 24 vraća `composer.json` pri normalnom izlasku i pri prekidu, ali ne preživljava `SIGKILL`; u tom slučaju u praćenom fajlu ostaje `Domain\ => PogresanFolder/`. Nije popravljeno.

### Test agenta `env-doctor`

**Slučaj.** Zdravo okruženje — sva tri kontejnera `Up (healthy)`. Namerno: nije se testiralo da nađe kvar, nego **da ga ne izmisli** i da ništa ne pokvari. [Zapis](docs/agent-outputs/2026-09-16-env-doctor-health-check.md).

**Rezultat.** Verdikt „nema kvara" uz devet nabrojanih provera. Svih devet proverljivih tvrdnji tačno — 111 paketa, `User::count()` 11, pet pinovanih verzija, tri HTTP odziva 200, 27 Xdebug poruka u logu. Nijedna lozinka u izlazu.

Dva ponašanja vrednija od samog verdikta: rekao je **šta nije mogao da proveri i zašto** (`public/build` bez pokretanja Vite build-a) umesto da pogodi, i uz preporuku za brisanje zaostalog volume-a naveo da je `zadatak-docker_db_data` nepovratan.

**Bezbednosna provera prošla.** Kontejneri `Up 2 hours` → `Up 3 hours` — nastavak rada, ne restart. Volume-i netaknuti, `.env` sha nepromenjen (`250c1d7662b361ed`).

### Nalaz koji je bio naša greška

`env-doctor` je prijavio da `.dockerignore` ne pokriva `docs/decisions/` ni `docs/agent-outputs/`.

To nije bilo zatečeno stanje. Pri preimenovanju `ai workflow/` → `docs/` ranije iste sesije, tri putanje su prepisane **fajl po fajl** umesto na ceo folder. Novi poddirektorijumi zato nisu bili obuhvaćeni, a `Dockerfile:42` je `COPY . .` — dokumentacija bi ušla u build kontekst i u image.

Pouka je opštija od ovog slučaja: **kad se pravilo prepisuje pri preimenovanju, prepiši ga na najširem nivou koji je i dalje tačan.** Nabrajanje pojedinačnih fajlova je bilo tačno u trenutku pisanja i pogrešno tri sata kasnije.

**Ispravka:** četiri linije zamenjene jednim unosom `docs/`, uz komentar zašto folder a ne nabrajanje.

### Ostavljeno namerno

- **Xdebug ne stiže do IDE-a** pod WSL2 — `host.docker.internal` pokazuje na Windows host. Nije kvar kontejnera; `XDEBUG_MODE=off` ućutkuje buku kad breakpointi ne trebaju.
- **Zaostali `zadatak-docker-*` kontejneri i volume-i.** `zadatak-docker_db_data` drži bazu drugog projekta i brisanje je nepovratno — odluka vlasnika tog projekta, ne posledica ove dijagnostike.
- **`demo-breaks.sh`** — nedeklarisan `python3`, `trap` ne preživljava `SIGKILL`.
