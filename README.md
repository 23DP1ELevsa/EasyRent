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

## Aktivizācijas e-pastu pārbaude produkcijā

1. Reģistrē pa vienam testa kontam ar adresi, kuru kontrolē Gmail, Inbox.lv un Outlook. Katram mēģinājumam pārlūka **Network** cilnē no `POST /api/auth/register` vai `POST /api/auth/verification/resend` atbildes pieraksti `X-Request-ID` un pieprasījuma laiku (UTC). Salīdzinājumam izmanto arī `POST /api/auth/forgot-password` un atsevišķi pieraksti tā ID.
2. Railway backend žurnālos atrodi `Account email send result.` ar attiecīgo `request_id`. Pārbaudi `mail_type`, `recipient_domain` un `outcome`. `accepted` apzīmē Resend pieņemtu sūtījumu. Kļūmes gadījumā pārbaudi `error`. Publiska atkārtotas nosūtīšanas atbilde ir vienāda arī adresēm, kurām vēstule nepienākas, un šādā gadījumā sūtīšanas ieraksta var nebūt.
3. Resend **Emails** skatā atver ierakstu pēc Laravel `resend_id`. Pārbaudi saņēmēju, notikumu secību un saņēmēja servera atbildi. `Delivered` nozīmē, ka saņēmēja pasta serveris ziņojumu pieņēma. [Resend piegādes detaļas](https://resend.com/changelog/improved-bounced-and-delivery-details)
4. Katrā pastkastē pārbaudi iesūtni, mēstules un pārējās mapes. Ja serveris vēstuli pieņēma, bet tā nav atrodama, lūdz saņēmēja pasta administratoram pārbaudīt piegādes izsekošanu vai karantīnu pēc UTC laika un Resend ierakstā norādītā SMTP `Message-ID`. Šādi var izmeklēt arī `rvt.lv` piegādes. Atbalsta pieteikumā pietiek ar korelācijas ID un pasta servera statusu. [Resend piegādes diagnostika](https://resend.com/guides/email/users-never-got-login-code)
5. Atver testa vēstulē esošo saiti tajā pašā pārlūkā 15 minūšu laikā un pārbaudi automātisko pierakstīšanos. Ar atsevišķu testa kontu atver saiti citā pārlūkā vai Gmail lietotnē telefonā. Saites apstiprināšana tur prasa atsevišķu darbību, un paroles kontam var būt jāiestata jauna parole. Saite e-pasta apstiprināšanai ir derīga 24 stundas.

## Drošības piezīmes

- API autentificētie maršruti izmanto `Bearer` tokenus ar Laravel Sanctum.
- Google pierakstīšanās izmanto Laravel Sanctum sesiju. [Google OAuth iestatīšana un pārbaude Railway](docs/google-oauth.md).
- Lietotāja profils, rezervācijas, atsauksmju rediģēšana un pakalpojumu sniedzēja CRUD darbības ir aizsargātas ar autentifikāciju.
- Paroles tiek glabātas tikai hashētā veidā.
- Kontaktforma un autentifikācijas maršruti ir ierobežoti ar `throttle` middleware.

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
