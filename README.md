# Vienotā transportlīdzekļu īres platforma Latvijā

Šis repozitorijs ir **projekta skelets** (Frontend + Backend + DB) vienotai transportlīdzekļu īres platformai Latvijā.

Mērķis: vienā sistēmā apvienot dažādu transportlīdzekļu (auto, moto, velo, skrejriteņi u.c.) nomu, nodrošinot meklēšanu, filtrēšanu un rezervāciju/maksājumu/atsauksmju datu glabāšanu.

## Tehnoloģijas

- **Frontend:** Vue 3 + Vite + **Vuetify 3**
- **Backend:** Laravel (REST API)
- **Datubāze:** MySQL (InnoDB)
- Papildus: Docker Compose (pēc izvēles)

## Repozitorija struktūra

- `frontend/` – Vue + Vuetify aplikācija
- `backend-template/` – Laravel koda sagatave (migrācijas, modeļi, kontrolieri, maršruti, seederi)
- `scripts/` – palīgscripts, lai savā datorā ātri uzģenerētu pilnus projektus

> Kāpēc ir `backend-template/`? Šajā vidē netiek piegādāts pilns Laravel framework (`vendor/`). Ideja: izveido tīru Laravel projektu ar Composer un tad **iekopē** šeit sagatavoto kodu (vai izmanto skriptu).

## Funkcionalitāte (MVP)

- Home page ar:
  - transportlīdzekļu sarakstu,
  - filtriem: veids, atrašanās vieta, cenu diapazons,
  - datu ielādi no Laravel API.
- Laravel API ar endpointiem:
  - `GET /api/vehicle-types` – transportlīdzekļu veidi
  - `GET /api/vehicles` – saraksts ar filtriem (`type_id`, `q`, `min_price`, `max_price`, `city`)
  - `GET /api/vehicles/{id}` – detalizēts ieraksts
- MySQL shēma atbilstoši datu modelim: `persona`, `klients`, `pakalpojumu_sniedzejs`, `transportlidzekla_veids`, `transportlidzeklis`, `rezervacija`, `maksajums`, `atsauksme`.

## Ātrā palaišana ar Docker (ieteicams)

1) Instalē Docker Desktop.
2) Repozitorija saknē izpildi:

```bash
docker compose up -d --build
```

3) Atver:

- Frontend: `http://localhost:5173`
- Backend API: `http://localhost:8000/api/vehicles`

> Docker scenārijā backend konteinerī tiks izpildīts `composer install` un migrācijas.

## Palaišana lokāli (bez Docker)

### Backend (Laravel)

Prasības: PHP 8.2+, Composer, MySQL.

1) Izveido jaunu Laravel projektu:

```bash
composer create-project laravel/laravel backend
```

2) Iekopē sagataves failus:

```bash
cp -R backend-template/* backend/
```

3) Konfigurē `.env` (DB pieslēgums) un izpildi migrācijas + seederus:

```bash
cd backend
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Frontend (Vue + Vuetify)

Prasības: Node.js 18+.

```bash
cd frontend
npm install
npm run dev
```

## Konfigurācija

- Frontend API bāzes URL: `frontend/.env.example`
  - pēc noklusējuma: `VITE_API_BASE_URL=http://localhost:8000/api`

## Nākamie soļi (paplašinājumi)

- Autentifikācija (piem., Laravel Sanctum) un lomu pārvaldība.
- Rezervācijas izveide/atcelšana, maksājumu ieraksti, atsauksmes.
- Admin/partnera panelis transportlīdzekļu pārvaldībai.

