# EasyRent

EasyRent ir pilna steka tīmekļa projekts ar:
- `backend/` — Laravel 12 API (PHP)
- `frontend/` — Vue 3 + Vuetify lietotāja saskarni

Šis README apraksta, kā no nulles palaist projektu lokāli.

Programmu var izmantot arī tiešsaistē: `https://easy-rent-gamma.vercel.app`

## Prasības

- PHP `8.2+`
- Composer `2+`
- Node.js `18+` (ieteicams `20+`) un `npm`
- Git

> Piezīme: pēc noklusējuma backend ir konfigurēts uz XAMPP MySQL (`backend/.env.example`).

## Projekta struktūra

```text
EasyRent/
├─ backend/    Laravel API
├─ frontend/   Vue SPA
└─ README.md
```

## Izmantotie rīki un tehnoloģijas

- Laravel 12
- Laravel Sanctum API autentifikācijai
- Vue 3
- Vuetify 3
- Vite 7
- vite-plugin-pwa PWA funkcionalitātei
- Leaflet interaktīvajai kartei
- XAMPP ar ieslēgtu Apache un MySQL

## Ātrais starts

### 1) Backend uzstādīšana

Atver termināli mapē `backend` un izpildi:

```bash
composer install
```

Izveido `.env` failu (PowerShell):

```powershell
Copy-Item .env.example .env
```

Palaid XAMPP un ieslēdz `Apache` un `MySQL`. Tad izveido datubāzi `easyrent` caur phpMyAdmin vai MySQL konsoli.

phpMyAdmin variantā:

1. Atver `http://localhost/phpmyadmin`
2. Spied `New`
3. Izveido datubāzi ar nosaukumu `easyrent`

MySQL konsoles variantā:

```sql
CREATE DATABASE easyrent CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Pēc noklusējuma projekts izmanto šādus backend `.env` DB parametrus, kas atbilst tipiskam XAMPP setup:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=easyrent
DB_USERNAME=root
DB_PASSWORD=
```

Ja tavā XAMPP MySQL lietotājam `root` ir parole, ieliec to `DB_PASSWORD` laukā failā `backend/.env`.

Tad izpildi:

```bash
php artisan key:generate
php artisan migrate
```

Tas izveidos visas Laravel tabulas XAMPP MySQL datubāzē, tāpēc dati vairs netiks glabāti SQLite failā.

Palaid API serveri:

```bash
php artisan serve
```

Backend būs pieejams: `http://127.0.0.1:8000`

---

### 2) Frontend uzstādīšana

Atver otru termināli mapē `frontend` un izpildi:

```bash
npm install
npm run dev
```

Frontend būs pieejams: `http://localhost:3000`

## Frontend API konfigurācija

Failā `frontend/.env` jābūt:

```dotenv
VITE_API_BASE_URL=http://127.0.0.1:8000
```

Ja backend darbojas uz cita hosta/porta, atjauno šo vērtību un pārstartē `npm run dev`.

## Noderīgas komandas

### Backend (`backend/`)

- `php artisan serve` — startē API serveri
- `php artisan migrate` — palaiž migrācijas
- `php artisan migrate:fresh --seed` — pārbūvē DB un aizpilda datus (ja ir seederi)
- `php artisan test` — palaiž visus backend testus
- `php artisan test tests/Feature` — palaiž tikai feature testus
- `php artisan test tests/Feature/AuthContactAndReviewTest.php` — palaiž jaunos auth, kontaktformas un atsauksmju feature testus
- `php artisan test --filter review` — palaiž testus pēc nosaukuma filtra
- `composer test` — notīra konfigurācijas kešu un palaiž backend testus
- `composer run dev` — vienā komandā palaiž Laravel serveri, queue listeneri, logus un Vite (backend pusei)
- `composer run setup` — sagatavo backend vidi; ja `easyrent` datubāze vēl nav izveidota XAMPP pusē, migrācijas tiek izlaistas bez fatālas kļūdas

### Frontend (`frontend/`)

- `npm run dev` — attīstības serveris
- `npm run build` — produkcijas būvējums
- `npm run preview` — lokāls produkcijas preview
- `npm run lint` — ESLint ar automātisku labošanu

## PWA

Frontend ir konfigurēts kā progresīvā tīmekļa lietotne. Produkcijas būvējumā tiek ģenerēts service worker un manifest fails, lai lietotni varētu instalēt aplikācijas formātā.

Lai pārbaudītu PWA lokāli:

```bash
cd frontend
npm run build
npm run preview
```

Pēc tam atver `http://localhost:4173` un pārbaudi pārlūkā instalēšanas iespēju (`Install app` / `Add to home screen`).

## Drošības piezīmes

- API autentificētie maršruti izmanto `Bearer` tokenus ar Laravel Sanctum.
- Google pierakstīšanās izmanto Laravel Sanctum sesiju. [Google OAuth iestatīšana un pārbaude Railway](docs/google-oauth.md).
- Lietotāja profils, rezervācijas, atsauksmju rediģēšana un pakalpojumu sniedzēja CRUD darbības ir aizsargātas ar autentifikāciju.
- Paroles tiek glabātas tikai hashētā veidā.
- Kontaktforma un autentifikācijas maršruti ir ierobežoti ar `throttle` middleware.

## Konta dzēšana

Profila sadaļā **Konta dzēšana** klients vai pakalpojumu sniedzējs var dzēst savu kontu. Dzēšanu bloķē aktīvas un gaidāmas rezervācijas, kā arī maksājumi statusā `gaida`. Neapmaksāts rezervācijas pieteikums bez maksājuma ieraksta beidzas rezervācijas sākuma laikā. Sistēmā pašlaik nav atsevišķas atmaksu vai strīdu uzskaites.

### API

| Maršruts | Darbība |
| --- | --- |
| `GET /api/profile/deletion-check` | Atgriež `can_delete`, `blocking_reservations_count` un identitātes apstiprināšanas veidu. Bloķēšanas iemesls ir `active_or_upcoming_reservations` vai `pending_payments`; ja vajadzīgs, atgriež arī `blocking_payments_count`. |
| `POST /oauth/google/deletion` | Ar aktīvu Google tīmekļa sesiju un CSRF marķieri sāk atkārtotu Google autorizāciju. Piesaistītā Google konta apstiprinājums der piecas minūtes tajā pašā pārlūka sesijā. |
| `DELETE /api/profile/me` | Pieņem `{"confirmation":"DZĒST","password":"…"}` kontam ar paroli vai `{"confirmation":"DZĒST"}` pēc Google apstiprināšanas Google-only kontam. Atgriež `204` pēc dzēšanas, `409` bloķējošu saistību dēļ un `422` nepareiza vai trūkstoša apstiprinājuma gadījumā. |
| `GET /api/rezervacijas/{id}/invoice` | Ļauj autentificētam rezervācijas dalībniekam lejupielādēt izsniegtu rēķinu HTML formātā. |

Konta dzēšanas transakcija aizstāj profila datus ar tukšām vērtībām, aizpilda `persona.deleted_at` un atsauc Sanctum marķierus. Tīmekļa sesiju noslēdz un datubāzē glabātās sesijas iztīra pēc konta ID. Vecie klienta, sniedzēja, transporta, rezervācijas un maksājuma ID saglabā esošās saites. Sniedzēja transportam aizpilda `archived_at`, noņem adresi un reģistrācijas numuru un paslēpj sludinājumus. Atkārtota reģistrācija izveido jaunu kontu ar saviem ID. Rezervācijas izveide, apmaksa un atcelšana izmanto tās pašas kontu rindu bloķēšanas darbības kā dzēšana.

Rezervācija glabā transporta marku, modeli, veidu, pārnesumkārbu un degvielas veidu rezervācijas izveides brīdī. Esošām rezervācijām migrācija izmanto pieejamos transporta datus migrācijas laikā. Vēsturē otra puse redz dzēsta dalībnieka apzīmējumu un saglabātās rezervācijas detaļas. Izsniegto rēķinu dalībnieku dati un transporta numurs tiek šifrēti finanšu arhīvā. Vecajiem rēķiniem arhīva ierakstu pievieno pirms pirmās iesaistītās puses anonimizēšanas, izmantojot tajā brīdī pieejamos datus. Iepriekš e-pastā izsniegtās kopijas saglabā sākotnējo saturu.

Finanšu arhīva piekļuve ir paredzēta rezervācijas dalībniekiem caur rēķina maršrutu un pilnvarotiem grāmatvedības darbiniekiem. Pirms produkcijas palaišanas datu pārzinim jānosaka un jāpiemēro glabāšanas un dzēšanas kārtība rēķiniem, citiem darījumu datiem un rezerves kopijām, ieskaitot izņēmumus nenoslēgtām saistībām. Repizitorijā nav automātiska arhīva dzēšanas termiņa. [VDAR 5. un 17. pants](https://eur-lex.europa.eu/eli/reg/2016/679/oj) · [Grāmatvedības likuma 28. pants](https://likumi.lv/ta/id/324249-gramatvedibas-likums)

### Pārbaude

1. Izolētā testēšanas vidē izveido klientu, sniedzēju un vismaz divus transportlīdzekļus. Izveido vienu gaidāmu rezervāciju. Pārbaudi, ka abos profilos redzams bloķējošo rezervāciju skaits un dzēšanas API atgriež `409`.
2. Ar pabeigtu, apmaksātu rezervāciju pārbaudi abus scenārijus, dzēšot vispirms klientu un atsevišķi vispirms sniedzēju. Otras puses vēsturē pārbaudi transporta nosaukumu, datumus, summu, apmaksas statusu un lejupielādējamu rēķinu. Pēc sniedzēja dzēšanas pārbaudi arī karti, katalogu un mēģinājumu no jauna rezervēt viņa transportu.
3. Pārbaudi nepareizu paroli, Google apstiprināšanu ar citu Google kontu un Google kontu ar nepabeigtu profilu. Pēc dzēšanas pārbaudi veco marķieri un sesiju citā pārlūkā, kā arī atkārtotu reģistrāciju ar iepriekšējo e-pastu.
4. Palaid `php artisan test` mapē `backend`. Paralēlo pieprasījumu testam izveido jaunu, tukšu lokālu MySQL vai MariaDB datubāzi ar nosaukumu, kas sākas ar `easyrent_qa_`. Iestati `EASYRENT_TEST_MYSQL_RACE=1`, `DB_CONNECTION=mysql`, `DB_DATABASE` un `DB_URL` uz šo datubāzi un palaid `php vendor/bin/phpunit --group mysql-race`. Katram atkārtotam testa izpildījumam izmanto jaunu tukšu datubāzi.
5. Frontend mapē palaid Vite un `npm run test:catalog:browser`, lai Chromium pārbaudītu dzēšanas saskarni ar simulētām API atbildēm.

### Izvietošana Railway

1. Izmēģini migrācijas un pārbaudes staging vidē ar tādu pašu MySQL versiju kā produkcijā. Pārbaudi esošā `APP_KEY` pieejamību, Google callback adresi, drošās sīkdatnes un `SANCTUM_STATEFUL_DOMAINS`. Saglabā šifrēšanas atslēgu kopā ar atkopšanas procedūru. Atslēgas maiņas laikā saglabā iepriekšējo atslēgu `APP_PREVIOUS_KEYS`, kamēr tā vajadzīga arhīva atšifrēšanai.
2. Pirms migrēšanas izveido un pārbaudi atjaunojamu MySQL rezerves kopiju. Uz migrēšanas un izvietošanas laiku apturi lietotāju plūsmu un fona procesus, kas raksta šajās tabulās. Tas ļauj pabeigt veco rezervāciju momentuzņēmumus un novērš vienlaicīgu rezervāciju izveidi ar veco kodu.
3. Backend servisam iestati Railway pre-deploy komandu `php artisan migrate --force`. Pārbaudi `php artisan migrate:status` un momentuzņēmumu aizpildi ar `SELECT COUNT(*) FROM rezervacija WHERE transporta_snapshot IS NULL` pirms plūsmas atjaunošanas. Tad izvieto frontend un izpildi pārbaudes ar testu kontiem. [Railway pre-deploy komandas](https://docs.railway.com/deployments/pre-deploy-command)
4. Kad dzēšana ir izmantota, incidenta laikā saglabā migrēto shēmu un izmanto labotu būvējumu ar kontu un sludinājumu filtriem. Kļūmes laikā apturi plūsmu, kamēr labojums ir gatavs. Pirms jebkuras atjaunošanas no rezerves kopijas saskaņo, kā atkārtoti piemērot pēc kopijas izveides pieprasītās dzēšanas.

## Testēšana

Backend pusē ir feature testi, kas pārbauda:
- API health endpoint
- autentifikāciju un autorizāciju
- klienta un pakalpojumu sniedzēja reģistrāciju
- logout plūsmu
- rezervāciju izveidi un apmaksu
- transporta izveides piekļuves kontroli
- kontaktformas e-pasta nosūtīšanu
- atsauksmju statistiku un rediģēšanas piekļuves kontroli

Palaist visus backend testus:

```bash
cd backend
php artisan test
```

Palaist tikai feature testus:

```bash
cd backend
php artisan test tests/Feature
```

Palaist tikai vienu konkrētu testu failu:

```bash
cd backend
php artisan test tests/Feature/AuthContactAndReviewTest.php
```

Palaist testus pēc nosaukuma vai konkrētas metodes:

```bash
cd backend
php artisan test --filter review
php artisan test --filter test_client_can_update_only_their_own_review
```

Alternatīvi vari izmantot Composer skriptu:

```bash
cd backend
composer test
```

Kā pārbaudīt rezultātu:
- `PASS` nozīmē, ka tests izturēts.
- `FAIL` nozīmē, ka zemāk būs redzams testa nosaukums, kļūda un rinda, kur pārbaude neizgāja.
- Kopsavilkumā skaties `Tests:` un `Assertions:`, lai redzētu, cik testi un pārbaudes izpildījās.

Pašreiz pārbaudītais jaunais tests iziet veiksmīgi ar rezultātu `6 passed` un `41 assertions`.

## Biežākās problēmas

### `php artisan serve` nepalaižas

Pārbaudi:
- vai esi mapē `backend/`
- vai `.env` eksistē
- vai veikts `php artisan key:generate`
- vai XAMPP `MySQL` ir palaists
- vai datubāze `easyrent` eksistē
- vai `backend/.env` ir pareizi `DB_*` parametri
- vai `php artisan migrate` izpildās bez kļūdām

### `npm run dev` nepalaižas frontendā

Pārbaudi:
- vai esi mapē `frontend/`
- vai palaists `npm install`
- vai Node.js versija ir `18+`

### Frontend nevar sasniegt API

Pārbaudi:
- `frontend/.env` vērtību `VITE_API_BASE_URL`
- vai backend tiešām darbojas uz `http://127.0.0.1:8000`
- vai nav CORS vai porta konflikta problēmas

## API health pārbaude

Pēc backend palaišanas vari pārbaudīt:

```text
GET http://127.0.0.1:8000/api/health
```

Sagaidāmā atbilde:

```json
{"status":"ok"}
```
