# EasyRent

Mūsdienīga tīmekļa platforma īpašumu iznomāšanai un pārvaldei. EasyRent sniedz vienkāršu un intuitīvu risinājumu īpašumu īrnieki un iznomātājiem.

## 📋 Projekta Apraksts

EasyRent ir pilnfunkcionāla Saas platforma, kas paredzēta rezidenciālo un komerciālo īpašumu īrniecībai. Platforma ļauj īpašniekiem:

- 🏠 Pārvaldīt savu īpašumā portfeli
- 💰 Izsekot maksājumus un īres naudas plūsmas
- 📞 Sazināties ar īrniekiem
- 📋 Pārvaldīt līgumus un dokumentus
- 📊 Analizēt īpašuma sniegumu

Īrniekiem platforma nodrošina:

- 🔍 Viegli meklēt un filtrēt īpašumus
- 📱 Apskatīt detalizētu informāciju par īpašumiem
- 💬 Tiešā saziņa ar īpašniekiem
- 📄 Digitāls līgums un dokumentu pārvaldība

## 🏗️ Projektā Struktūra

```
EasyRent/
├── backend/          # Laravel PHP API
├── frontend/         # Vue.js lietotāja saskarne
└── README.md         # Šis fails
```

### Backend

- **Framework:** Laravel 11
- **Valoda:** PHP 8.2+
- **Datubāze:** Konfigurējama (MySQL/PostgreSQL)
- **Autentifikācija:** Laravel Sanctum

**Galvenās mapes:**
- `app/Http/Controllers/` - API kontrolieri
- `app/Models/` - Datu modeļi
- `database/migrations/` - Datubāzes migrācijas
- `routes/` - Maršrutēšana
- `config/` - Konfigurācijas faili

### Frontend

- **Framework:** Vue 3
- **Build rīks:** Vite
- **Stili:** CSS
- **Linter:** ESLint

**Galvenās mapes:**
- `src/components/` - Vue komponentes
- `src/pages/` - Lapas/skaņi
- `src/router/` - Маршутизаторы
- `src/assets/` - Statiskās iespējas

## 🚀 Sākšana

### Priekšnoteikumi

- PHP 8.2 vai jaunāks
- Node.js 18+ ar npm
- Composer
- SQL datubāze (MySQL/PostgreSQL)

### Instalācija

#### 1. Klonēt repozitoriju

```bash
git clone <repository-url>
cd EasyRent
```

#### 2. Backend Iestatīšana

```bash
cd backend

# Instalēt PHP atkarības
composer install

# Kopēt .env failu
cp .env.example .env

# Ģenerēt aplikācijas atslēgu
php artisan key:generate

# Izpildīt datubāzes migrācijas
php artisan migrate

# (Iespējams) Seed datubāzi ar testa datiem
php artisan db:seed

# Sākt attīstības serveri
php artisan serve
```

Backend būs pieejams: `http://localhost:8000`

#### 3. Frontend Iestatīšana

```bash
cd ../frontend

# Instalēt JavaScript atkarības
npm install

# Sākt attīstības serveri
npm run dev
```

Frontend būs pieejams: `http://localhost:5173` (vai cits ports, ko norāda Vite)

## 📝 Konfigurācija

### Backend (.env)

Svarīgie mainīgie:
- `APP_URL` - Aplikācijas URL
- `DB_CONNECTION` - Datubāzes tips
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - Datubāzes savienojuma dati
- `SANCTUM_STATEFUL_DOMAINS` - Domēni, kuriem atļauts stateful pieprasījumi

### Frontend (.env)

- `VITE_API_URL` - Backend API URL

## 🧪 Testēšana

### Backend testi

```bash
cd backend

# Izpildīt vienības testus
php artisan test

# Ar detalizētu izeju
php artisan test --verbose
```

### Frontend testi

```bash
cd frontend

# Atkarībā no konfigurācijas
npm run test
```