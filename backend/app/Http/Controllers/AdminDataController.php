<?php

namespace App\Http\Controllers;

use App\Models\AdministratoraDarbiba;
use App\Models\Atsauksme;
use App\Models\LietotajaIerobezojums as Restriction;
use App\Models\Maksajums;
use App\Models\ParkapumaZinojums as Report;
use App\Models\Persona;
use App\Models\Rezervacija;
use App\Models\TransportliedzieklsVeids;
use App\Models\Transportlidzeklis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AdminDataController extends Controller
{
    private const PERSON = 'persona_id,vards,uzvards';
    private const VEHICLE = 'transportlidzeklis_id,sniedzejs_id,veids_id,marka,modelis,statuss';
    private const ROLES = ['klients', 'pakalpojumu_sniedzejs', 'administrators'];

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['integer', Rule::in([7, 30, 90])]]);
        $days = (int) ($data['days'] ?? 30);
        $start = today()->subDays($days - 1);
        $registrations = Persona::withTrashed()->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $paid = Maksajums::where('statuss', 'apstiprinats');
        $payments = (clone $paid)->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT rezervacija_id) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $series = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $day = $start->copy()->addDays($offset)->toDateString();
            $series[] = ['date' => $day, 'registrations' => (int) ($registrations[$day] ?? 0), 'paid_bookings' => (int) ($payments[$day] ?? 0)];
        }

        return response()->json([
            'users' => Persona::count(),
            'roles' => Persona::selectRaw('loma, COUNT(*) as total')->groupBy('loma')->pluck('total', 'loma'),
            'new_registrations' => $registrations->sum(),
            'vehicles' => Transportlidzeklis::count(),
            'active_vehicles' => Transportlidzeklis::listed()->where('statuss', '!=', 'neaktivs')->count(),
            'hidden_vehicles' => Transportlidzeklis::whereNotNull('moderation_hidden_at')->count(),
            'bookings' => Rezervacija::selectRaw('apmaksas_statuss, COUNT(*) as total')->groupBy('apmaksas_statuss')->pluck('total', 'apmaksas_statuss'),
            'confirmed_payment_volume' => $paid->sum('summa'),
            'active_restrictions' => Restriction::active()->count(),
            'open_reports' => Report::whereIn('statuss', Report::OPEN)->count(),
            'series' => $series,
            'recent_bookings' => $this->bookingsQuery()->latest('rezervacija_id')->limit(6)->get(),
            'recent_actions' => $this->auditQuery()->latest('darbiba_id')->limit(6)->get(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'role' => [Rule::in(self::ROLES)],
            'verified' => ['boolean'],
            'status' => [Rule::in(['active', 'blocked', 'deleted'])],
            'restriction' => [Rule::in(Restriction::TYPES)],
            ...$this->dateRules(),
        ]);
        $query = $this->usersQuery();
        if (isset($filters['role'])) {
            $query->where('loma', $filters['role']);
        }
        if (isset($filters['verified'])) {
            $query->whereNull('email_verified_at', not: $request->boolean('verified'));
        }
        match ($filters['status'] ?? null) {
            'active' => $query->whereNull('deleted_at')->whereDoesntHave('restrictions', fn ($query) => $query->blocking()),
            'blocked' => $query->whereNull('deleted_at')->whereHas('restrictions', fn ($query) => $query->blocking()),
            'deleted' => $query->onlyTrashed(),
            default => null,
        };
        if (isset($filters['restriction'])) {
            $query->whereHas('restrictions', fn ($query) => $query->active()->where('veids', $filters['restriction']));
        }
        $this->dateFilters($query, $filters, 'created_at');

        return $this->page($request, $query, ['persona_id', 'vards', 'uzvards', 'epasts', 'loma', 'created_at', 'email_verified_at'],
            ['persona_id', 'vards', 'uzvards', 'epasts']);
    }

    public function user(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['restriction_page' => ['integer', 'between:1,100000']]);
        $user = $this->usersQuery()->findOrFail($id);
        // The activity predicate also drives the list filter and the write checks.
        $activeIds = $user->restrictions()->active()->pluck('ierobezojums_id');
        $restrictions = $user->restrictions()->latest('ierobezojums_id')->paginate(15, pageName: 'restriction_page', page: $data['restriction_page'] ?? 1)
            ->through(fn ($row) => $row->toArray() + ['active' => $activeIds->contains($row->getKey())]);

        return response()->json([
            'user' => $user,
            'restrictions' => $restrictions,
            'bookings' => $this->forParticipant($this->bookingsQuery(), $id)->count(),
            'vehicles' => $this->vehiclesQuery()->whereHas('sniedzejs', fn ($query) => $query->where('persona_id', $id))->count(),
            'reviews' => $this->reviewsQuery()->whereHas('klients', fn ($query) => $query->where('persona_id', $id))->count(),
            'reports' => $this->reportsForUser($this->reportsQuery(), $id)->count(),
            'audit' => $this->auditForUser($this->auditQuery(), $id)->count(),
        ]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => [Rule::in(['pieejams', 'aiznemts', 'neaktivs'])],
            'visibility' => [Rule::in(['visible', 'hidden', 'archived'])],
            'category' => ['integer', 'min:1'],
            'persona_id' => ['integer', 'min:1'],
        ]);
        $query = $this->vehiclesQuery();
        if (isset($data['status'])) {
            $query->where('statuss', $data['status']);
        }
        if (isset($data['persona_id'])) {
            $query->whereHas('sniedzejs', fn ($query) => $query->where('persona_id', $data['persona_id']));
        }
        if (isset($data['category'])) {
            $query->where('veids_id', $data['category']);
        }
        match ($data['visibility'] ?? null) {
            'visible' => $query->listed(),
            'hidden' => $query->whereNotNull('moderation_hidden_at'),
            'archived' => $query->whereNotNull('archived_at'),
            default => null,
        };

        return $this->page($request, $query, ['transportlidzeklis_id', 'marka', 'modelis', 'statuss', 'dienas_nomas_cena'],
            ['transportlidzeklis_id', 'marka', 'modelis', 'registracijas_numurs']);
    }

    public function vehicle(int $id): JsonResponse
    {
        return response()->json($this->vehiclesQuery()->findOrFail($id));
    }

    public function categories(Request $request): JsonResponse
    {
        $filters = $request->validate(['active' => ['boolean']]);
        $query = TransportliedzieklsVeids::withCount('transportlidzekli');
        if (isset($filters['active'])) {
            $query->whereNull('deactivated_at', not: ! $request->boolean('active'));
        }

        return $this->page($request, $query, ['veids_id', 'nosaukums', 'tips'], ['veids_id', 'nosaukums', 'tips']);
    }

    public function bookings(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => [Rule::in(['apmaksata', 'neapmaksata'])],
            'persona_id' => ['integer', 'min:1'],
            'vehicle_id' => ['integer', 'min:1'],
            ...$this->dateRules(),
        ]);
        $query = $this->bookingsQuery();
        if (isset($filters['status'])) {
            $query->where('apmaksas_statuss', $filters['status']);
        }
        if (isset($filters['vehicle_id'])) {
            $query->where('transportlidzeklis_id', $filters['vehicle_id']);
        }
        if (isset($filters['persona_id'])) {
            $this->forParticipant($query, $filters['persona_id']);
        }
        $this->dateFilters($query, $filters, 'izveides_datums');

        return $this->page($request, $query, ['rezervacija_id', 'sakuma_laiks', 'beigu_laiks', 'kopa_summa', 'apmaksas_statuss', 'izveides_datums'],
            ['rezervacija_id', 'transportlidzeklis_id']);
    }

    public function payments(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => [Rule::in(['gaida', 'apstiprinats', 'atteikts'])],
            'booking_id' => ['integer', 'min:1'],
            ...$this->dateRules(),
        ]);
        $query = Maksajums::with([
            'rezervacija.klients:klients_id,persona_id',
            'rezervacija.klients.persona' => $this->person(),
            'rezervacija.transportlidzeklis:'.self::VEHICLE,
            'rezervacija.transportlidzeklis.sniedzejs:sniedzejs_id,persona_id',
            'rezervacija.transportlidzeklis.sniedzejs.persona' => $this->person(),
        ]);
        if (isset($filters['status'])) {
            $query->where('statuss', $filters['status']);
        }
        if (isset($filters['booking_id'])) {
            $query->where('rezervacija_id', $filters['booking_id']);
        }
        $this->dateFilters($query, $filters, 'created_at');

        return $this->page($request, $query, ['maksajums_id', 'rezervacija_id', 'summa', 'statuss', 'created_at'],
            ['maksajums_id', 'rezervacija_id', 'rekins', 'tranzakcijas_numurs']);
    }

    public function reviews(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'persona_id' => ['integer', 'min:1'],
            'vehicle_id' => ['integer', 'min:1'],
            'rating' => ['integer', 'between:1,5'],
            'hidden' => ['boolean'],
        ]);
        $query = $this->reviewsQuery();
        if (isset($filters['persona_id'])) {
            $query->whereHas('klients', fn ($query) => $query->where('persona_id', $filters['persona_id']));
        }
        if (isset($filters['vehicle_id'])) {
            $query->where('transportlidzeklis_id', $filters['vehicle_id']);
        }
        if (isset($filters['rating'])) {
            $query->where('vertejums', $filters['rating']);
        }
        if (isset($filters['hidden'])) {
            $query->whereNull('moderation_hidden_at', not: $request->boolean('hidden'));
        }

        return $this->page($request, $query, ['atsauksme_id', 'vertejums', 'datums'], ['atsauksme_id', 'komentars']);
    }

    public function reports(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => [Rule::in([...Report::STATUSES, 'open'])],
            'target' => [Rule::in(Report::TARGETS)],
            'reason' => [Rule::in(Report::REASONS)],
            'persona_id' => ['integer', 'min:1'],
        ]);
        $query = $this->reportsQuery();
        if (isset($filters['status'])) {
            $query->whereIn('statuss', $filters['status'] === 'open' ? Report::OPEN : [$filters['status']]);
        }
        if (isset($filters['target'])) {
            $query->where('objekta_veids', $filters['target']);
        }
        if (isset($filters['reason'])) {
            $query->where('iemesls', $filters['reason']);
        }
        if (isset($filters['persona_id'])) {
            $this->reportsForUser($query, $filters['persona_id']);
        }

        return $this->page($request, $query, ['zinojums_id', 'statuss', 'created_at'], ['zinojums_id', 'objekta_id', 'apraksts']);
    }

    public function report(int $id): JsonResponse
    {
        $report = $this->reportsQuery()->findOrFail($id);
        $target = match ($report->objekta_veids) {
            'user' => $this->usersQuery()->find($report->objekta_id),
            'vehicle' => $this->vehiclesQuery()->find($report->objekta_id),
            'review' => $this->reviewsQuery()->find($report->objekta_id),
        };

        return response()->json(['report' => $report, 'target' => $target]);
    }

    public function audit(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'administrator_id' => ['integer', 'min:1'],
            'persona_id' => ['integer', 'min:1'],
            ...$this->dateRules(),
        ]);
        $query = $this->auditQuery();
        if (isset($filters['administrator_id'])) {
            $query->where('administrators_id', $filters['administrator_id']);
        }
        if (isset($filters['persona_id'])) {
            $this->auditForUser($query, $filters['persona_id']);
        }
        $this->dateFilters($query, $filters, 'created_at');

        return $this->page($request, $query, ['darbiba_id', 'created_at', 'darbiba'], ['darbiba_id', 'objekta_id', 'iemesls']);
    }

    private function page(Request $request, Builder $query, array $sort, array $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => [Rule::in($sort)],
            'direction' => [Rule::in(['asc', 'desc'])],
            'per_page' => ['integer', 'between:1,100'],
            'page' => ['integer', 'between:1,100000'],
        ]);
        if (filled($data['q'] ?? null)) {
            foreach (preg_split('/\s+/u', $data['q'], 8, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $pattern = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $numeric = ctype_digit($term) && strlen($term) <= 18;
                $query->where(function (Builder $match) use ($search, $term, $pattern, $numeric) {
                    $match->whereRaw('1 = 0');
                    foreach ($search as $column) {
                        if (str_ends_with($column, '_id')) {
                            if ($numeric) {
                                $match->orWhere($column, $term);
                            }
                        } else {
                            $match->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                        }
                    }
                });
            }
        }
        $key = $query->getModel()->getKeyName();
        $column = $data['sort'] ?? $key;
        $query->orderBy($column, $data['direction'] ?? 'desc');
        if ($column !== $key) {
            $query->orderByDesc($key);
        }

        return response()->json($query->paginate($data['per_page'] ?? 25));
    }

    private function usersQuery(): Builder
    {
        return Persona::withTrashed()
            ->select('persona_id', 'vards', 'uzvards', 'epasts', 'loma', 'created_at', 'deleted_at', 'email_verified_at', 'profile_completed')
            ->selectRaw('email_verified_at as verified_at, deleted_at as removed_at, profile_completed as completed')
            ->withCasts(['verified_at' => 'datetime', 'removed_at' => 'datetime', 'completed' => 'boolean'])
            ->withCount(['restrictions as active_restrictions' => fn ($query) => $query->active()])
            ->withExists(['restrictions as blocked' => fn ($query) => $query->blocking()]);
    }

    private function vehiclesQuery(): Builder
    {
        return Transportlidzeklis::with(['veids', 'sniedzejs:sniedzejs_id,persona_id', 'sniedzejs.persona' => $this->person()])
            ->withExists(['sniedzejs as owner_blocked' => fn ($query) => $query
                ->whereHas('persona.restrictions', fn ($query) => $query->blocking())])
            ->withAvg(['atsauksmes as videjais_vertejums' => fn ($query) => $query->visible()], 'vertejums');
    }

    private function bookingsQuery(): Builder
    {
        return Rezervacija::with([
            'klients:klients_id,persona_id', 'klients.persona' => $this->person(),
            'transportlidzeklis:'.self::VEHICLE,
            'transportlidzeklis.sniedzejs:sniedzejs_id,persona_id',
            'transportlidzeklis.sniedzejs.persona' => $this->person(),
            'maksajumi:maksajums_id,rezervacija_id,rekins,statuss,summa',
        ]);
    }

    private function reviewsQuery(): Builder
    {
        return Atsauksme::with(['klients:klients_id,persona_id', 'klients.persona' => $this->person(), 'transportlidzeklis:'.self::VEHICLE]);
    }

    private function reportsQuery(): Builder
    {
        return Report::with('reporter:'.self::PERSON);
    }

    private function auditQuery(): Builder
    {
        return AdministratoraDarbiba::with('administrator:'.self::PERSON);
    }

    private function person(): \Closure
    {
        return fn ($query) => $query->withTrashed()->select(explode(',', self::PERSON));
    }

    private function forParticipant(Builder $query, int $id): Builder
    {
        return $query->where(fn ($query) => $query
            ->whereHas('klients', fn ($query) => $query->where('persona_id', $id))
            ->orWhereHas('transportlidzeklis.sniedzejs', fn ($query) => $query->where('persona_id', $id)));
    }

    private function reportsForUser(Builder $query, int $id): Builder
    {
        return $query->where(fn ($query) => $query->where('persona_id', $id)
            ->orWhere(fn ($query) => $query->where('objekta_veids', 'user')->where('objekta_id', $id)));
    }

    private function auditForUser(Builder $query, int $id): Builder
    {
        return $query->where(fn ($query) => $query->where('administrators_id', $id)
            ->orWhere(fn ($query) => $query->where('objekta_veids', 'user')->where('objekta_id', $id)));
    }

    private function dateRules(): array
    {
        return ['from' => ['date_format:Y-m-d'], 'to' => ['date_format:Y-m-d']];
    }

    private function dateFilters(Builder $query, array $filters, string $column): void
    {
        if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => __('admin.invalid_date_range')]);
        }
        if (isset($filters['from'])) {
            $query->where($column, '>=', $filters['from'].' 00:00:00');
        }
        if (isset($filters['to'])) {
            $query->where($column, '<', \Carbon\CarbonImmutable::parse($filters['to'])->addDay()->startOfDay());
        }
    }
}
