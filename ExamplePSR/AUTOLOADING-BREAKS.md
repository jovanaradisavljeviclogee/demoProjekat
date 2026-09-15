# Tri načina da se pokvari PSR-4 autoloading

Sve poruke u ovom dokumentu su **stvarno uhvaćene** pokretanjem `ExamplePSR/demo-breaks.sh`
u `app` kontejneru (PHP 8.3.33, Composer 2.8.12). Nijedna nije prepisana iz dokumentacije.

Reprodukcija:

```bash
bash ExamplePSR/demo-breaks.sh
```

---

## Kontrolna tačka

Da bi kvar imao smisla, prvo mora postojati nešto ispravno. Tri stvari moraju da se poklope:

| Karika | Vrednost |
|---|---|
| Mapiranje u `composer.json` | `"Domain\\": "ExamplePSR/"` |
| Putanja fajla | `ExamplePSR/Hello.php` |
| Deklaracija u fajlu | `namespace Domain;` + `class Hello` |

```
USPEH: Hello from Domain
    -> fajl JESTE ucitan
```

**Kako PSR-4 uopšte radi.** Autoloader ne pretražuje disk. Kada zatreba `Domain\Hello`, on
uzme registrovani prefiks `Domain\`, zameni ga putanjom `ExamplePSR/`, ostatak imena pretvori
u putanju i doda `.php`. Dobije `ExamplePSR/Hello.php` i pogleda **samo tu**. Ako tamo nema
fajla, ili fajl ne definiše baš tu klasu, odustaje.

Zato sva tri kvara ispod daju poruku istog oblika — ali svaki lomi **drugu kariku**.

---

## Kvar 1 — pogrešan PSR-4 mapping

**Šta je promenjeno:** u `composer.json` mapiranje je postalo `"Domain\\": "PogresanFolder/"`.
Fajl `ExamplePSR/Hello.php` je ostao netaknut i savršeno ispravan.

Posle `composer dump-autoload`, `vendor/composer/autoload_psr4.php` sadrži:

```php
'Domain\\' => array($baseDir . '/PogresanFolder'),
```

**Tačna greška:**

```
Error: Class "Domain\Hello" not found
    -> fajl NIJE ucitan
```

**Zašto.** Autoloader računa putanju iz mapiranja. Pošto mapiranje pokazuje na direktorijum
koji ne postoji, izračunata putanja je `PogresanFolder/Hello.php`. Autoloader tu ništa ne
nalazi i nikada ni ne pogleda u `ExamplePSR/`.

**Po čemu se prepoznaje.** Ovo je jedini od tri kvara kod koga je **sam fajl ispravan**.
Klasa, namespace i ime fajla se savršeno poklapaju — greška je isključivo u konfiguraciji.
Ako `ExamplePSR/Hello.php` izgleda besprekorno a klasa se i dalje ne nalazi, prvo pogledaj:

```bash
docker compose exec app grep "'Domain" vendor/composer/autoload_psr4.php
```

Taj fajl pokazuje putanju koju Composer **stvarno** koristi, a ne onu koju misliš da koristi.

**Česta varijanta u praksi:** mapiranje je ispravno u `composer.json`, ali `composer dump-autoload`
nikada nije pokrenut posle izmene. Tada je `autoload_psr4.php` i dalje star. Izmena
`composer.json`-a **uvek** traži `dump-autoload`; dodavanje novih klasa u već mapiran
direktorijum ne traži.

---

## Kvar 2 — pogrešan namespace u fajlu

**Šta je pokvareno:** `ExamplePSR/BrokenNamespace.php` deklariše `namespace Domain\Wrong;`
umesto `namespace Domain;`. Ime fajla i ime klase se poklapaju, mapiranje je ispravno.

**Tačna greška:**

```
Error: Class "Domain\BrokenNamespace" not found
    -> fajl JESTE ucitan
```

**Zašto.** Autoloader je izračunao tačnu putanju, našao fajl i **otvorio ga**. Ali fajl u sebi
definiše `Domain\Wrong\BrokenNamespace`. Klasa `Domain\BrokenNamespace` nije definisana nigde,
pa PHP prijavljuje da ne postoji — iako je fajl uredno učitan.

**Po čemu se prepoznaje.** Ovo je jedini od tri kvara kod koga je fajl **stvarno učitan**.
To nije teorija — skripta to dokazuje kroz `get_included_files()` u istom procesu. Kod kvara 1
i kvara 3 piše `fajl NIJE ucitan`, ovde piše `JESTE`.

Praktična posledica: ako fajl ima efekte pri učitavanju (konstante, `define`, side-effect kod),
oni **će** se izvršiti, a klasa i dalje neće postojati. To ume da napravi vrlo zbunjujuće
ponašanje.

---

## Kvar 3 — pogrešno ime fajla

**Šta je pokvareno:** klasa `Domain\MismatchedClass` živi u `ExamplePSR/WrongFileName.php`.
Namespace je ispravan, mapiranje ispravno, klasa sintaksno besprekorna.

**Tačna greška:**

```
Error: Class "Domain\MismatchedClass" not found
    -> fajl NIJE ucitan
```

**Zašto.** PSR-4 traži fajl **isključivo po imenu klase**. Za `Domain\MismatchedClass`
izračuna `ExamplePSR/MismatchedClass.php` i gleda samo tamo. Taj fajl ne postoji.
`WrongFileName.php` sedi odmah pored, ali autoloader u njega nikada ne zaviri.

**Po čemu se prepoznaje.** Fajl **postoji na disku** i sadržaj mu je potpuno ispravan —
`ls ExamplePSR/` ga uredno pokazuje. To je kombinacija koja najviše zbunjuje: vidiš fajl
svojim očima, a autoloader tvrdi da klase nema.

**Napomena za Linux.** Fajlsistem razlikuje velika i mala slova, pa bi i `hello.php` umesto
`Hello.php` proizveo isti kvar. Na Windows-u i macOS-u to često prođe neprimećeno u razvoju
i pukne tek na serveru — klasičan „kod meni radi".

---

## Najkorisnija dijagnostika: `composer dump-autoload -o`

Runtime greška kaže samo *šta* nedostaje. Optimizovani autoloader kaže **zašto**.

`composer dump-autoload -o` gradi classmap tako što **pročita sadržaj** svakog fajla u
mapiranim direktorijumima i uporedi stvarno deklarisano ime klase sa PSR-4 pravilom. Svako
odstupanje prijavi poimence:

```
Class Domain\Wrong\BrokenNamespace located in ./ExamplePSR/BrokenNamespace.php does not comply
with psr-4 autoloading standard (rule: Domain\ => ./ExamplePSR). Skipping.

Class Domain\MismatchedClass located in ./ExamplePSR/WrongFileName.php does not comply
with psr-4 autoloading standard (rule: Domain\ => ./ExamplePSR). Skipping.

Generated optimized autoload files containing 6523 classes
```

Poruka imenuje **fajl**, **stvarno deklarisanu klasu** i **prekršeno pravilo**. To je najbrži
put do uzroka kod kvara 2 i 3.

Pošto Composer takve klase preskače, u classmap iz `ExamplePSR/` ulazi samo ispravna:

```php
'Domain\\Hello' => $baseDir . '/ExamplePSR/Hello.php',
```

Zato ni sa `-o` kvarovi 2 i 3 ne prorade — preskočena klasa nije u classmap-u, a optimizovani
autoloader se na njega oslanja.

---

## Sažetak

| | Kvar 1 mapping | Kvar 2 namespace | Kvar 3 ime fajla |
|---|---|---|---|
| Greška | `Class "Domain\Hello" not found` | `Class "Domain\BrokenNamespace" not found` | `Class "Domain\MismatchedClass" not found` |
| Fajl učitan? | **NE** | **DA** | **NE** |
| Šta je neispravno | konfiguracija | sadržaj fajla | ime fajla |
| Sam fajl ispravan? | da | ne | da |
| `dump-autoload -o` upozorava? | ne | **da** | **da** |
| Gde tražiti | `vendor/composer/autoload_psr4.php` | prva linija `namespace` u fajlu | `ls` + ime klase |

**Zaključak koji vredi zapamtiti:** poruka `Class ... not found` je uvek ista, pa sama po sebi
ne govori ništa. Uzrok se razdvaja sa dva pitanja — *da li je fajl uopšte učitan* i *da li se
`composer dump-autoload -o` žali*. Ta dva odgovora jednoznačno pokazuju koja je od tri karike
pukla.

---

## Fajlovi u ovom folderu

| Fajl | Uloga |
|---|---|
| `Hello.php` | ispravan primer — kontrolna tačka |
| `BrokenNamespace.php` | kvar 2, namerno pogrešan namespace |
| `WrongFileName.php` | kvar 3, ime fajla ≠ ime klase |
| `demo-breaks.sh` | reprodukuje sva tri kvara i vraća stanje |
| `AUTOLOADING-BREAKS.md` | ovaj dokument |

Fajlovi sa kvarovima su bezbedni — nijedan deo aplikacije ih ne referiše, pa ništa ne ruše.
`demo-breaks.sh` privremeno menja `composer.json` samo za kvar 1 i vraća original kroz `trap`,
i pri prekidu skripte.
