# ngCloud — ORQA evaluacijski zadatak

Laravel web aplikacija za privatnu pohranu datoteka, izrađena prema specifikaciji evaluacijskog zadatka.

## Tehnologije

- Laravel 11
- PHP 8.2 ili noviji, uz ispunjene zahtjeve zaključanih Composer ovisnosti
- MySQL
- Blade
- Bootstrap 5.3.8 i Bootstrap Icons
- PHPUnit i Laravel Pint

## Napomena o verziji frameworka

Laravel 11 korišten je jer ga zahtijeva specifikacija zadatka. Projekt sadrži ograničenu Composer iznimku za sigurnosna
upozorenja paketa `laravel/framework` verzije `11.57.0`.

Ta iznimka ne otklanja ranjivosti. Projekt je pripremljen za lokalnu evaluaciju; javna produkcijska primjena zahtijeva
zaseban pregled ovisnosti i konfiguracije.

## Pokretanje

PHP treba imati potrebne ekstenzije, uključujući pdo_mysql, a za testove i pdo_sqlite. Bootstrap i Bootstrap Icons
učitavaju se preko CDN-a, pa je za njihov prikaz potrebna internetska veza.
Potrebna je zasebna MySQL baza. Nakon kopiranja `.env.example` u `.env`, upišite svoje pristupne podatke za bazu.

```bash
composer install
cp .env.example .env

# Uredite DB_* postavke u .env prije nastavka.

php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan serve
```

Aplikacija je dostupna na `http://127.0.0.1:8000`.
Nije potrebno pokretati npm ni `storage:link`.

### Demo korisnici

**Demo računi stvaraju se samo u okruženjima APP_ENV=local i APP_ENV=testing**

| Korisničko ime | Lozinka    | Uloge                 |
|----------------|------------|-----------------------|
| `ivan`         | `password` | `user`                |
| `ana`          | `password` | `user`                |
| `marko`        | `password` | `user`, `super_admin` |
| `superadmin`   | `password` | `super_admin`         |

## Provjere

```bash
php artisan test
vendor/bin/pint --test
```

## Ograničenje uploada

Pojedinačna datoteka ograničena je na 2 MiB kako bi aplikacija radila s uobičajenim zadanim PHP upload limitom, bez
dodatnih parametara pri pokretanju. Ukupna korisnička kvota provjerava se zasebno.

Za povećanje limita potrebno je uskladiti validaciju `max:2048` u `DocumentController` s PHP postavkama
`upload_max_filesize` i `post_max_size`.

## Korištenje AI alata

AI mi je pomogao u razradi pristupa, objašnjenju pojedinih rješenja i pisanju automatiziranih testova.
Aplikacijski kod implementirao sam uz službenu Laravel dokumentaciju te samostalno provjeravao ponašanje
aplikacije i rezultate testova.
