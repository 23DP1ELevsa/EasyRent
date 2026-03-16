# EasyRent

EasyRent ir pilna steka tīmekļa projekts ar:
- `backend/` — Laravel 12 API (PHP)
- `frontend/` — Vue 3 + Vuetify lietotāja saskarni

Šis README apraksta, kā no nulles palaist projektu lokāli.

## Prasības

- PHP `8.2+`
- Composer `2+`
- Node.js `18+` (ieteicams `20+`) un `npm`
- Git

> Piezīme: pēc noklusējuma backend ir konfigurēts uz `sqlite` (`backend/.env.example`).

## Projekta struktūra

```text
EasyRent/
├─ backend/    Laravel API
├─ frontend/   Vue SPA
└─ README.md
```

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

Ja izmanto `sqlite`, izveido failu `backend/database/database.sqlite` (ja vēl nav):

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
```

Tad izpildi:

```bash
php artisan key:generate
php artisan migrate
```

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
- `php artisan test` — palaiž testus
- `composer run dev` — vienā komandā palaiž Laravel serveri, queue listeneri, logus un Vite (backend pusei)

### Frontend (`frontend/`)

- `npm run dev` — attīstības serveris
- `npm run build` — produkcijas būvējums
- `npm run preview` — lokāls produkcijas preview
- `npm run lint` — ESLint ar automātisku labošanu

## Biežākās problēmas

### `php artisan serve` nepalaižas

Pārbaudi:
- vai esi mapē `backend/`
- vai `.env` eksistē
- vai veikts `php artisan key:generate`
- vai DB ir pieejama un `php artisan migrate` izpildās bez kļūdām

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