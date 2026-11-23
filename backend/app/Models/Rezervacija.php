<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rezervacija extends Model
{
    protected $table = 'rezervacija';
    protected $primaryKey = 'rezervacija_id';

    protected $fillable = [
        'klients_id',
        'transportlidzeklis_id',
        'sakuma_laiks',
        'beigu_laiks',
        'rezervacijas_datums',
        'izveides_datums',
        'kopa_summa',
        'maksajuma_datums',
        'apmaksas_statuss',
        'transporta_snapshot',
    ];

    protected $casts = [
        'sakuma_laiks' => 'datetime',
        'beigu_laiks' => 'datetime',
        'rezervacijas_datums' => 'date',
        'izveides_datums' => 'datetime',
        'maksajuma_datums' => 'date',
        'kopa_summa' => 'decimal:2',
        'transporta_snapshot' => 'array',
    ];

    public function scopeExpiredUnpaid(Builder $query): void
    {
        $query->where('apmaksas_statuss', 'neapmaksata')
            ->where('sakuma_laiks', '<=', now())
            ->whereDoesntHave('maksajumi');
    }

    public static function cleanupAvailability(): void
    {
        static::expiredUnpaid()->delete();

        Transportlidzeklis::whereNull('archived_at')->where('statuss', 'aiznemts')
            ->whereHas('rezervacijas')
            ->whereDoesntHave('rezervacijas', fn ($query) => $query
                ->where('apmaksas_statuss', 'apmaksata')->where('beigu_laiks', '>', now()))
            ->update(['statuss' => 'pieejams']);

        Transportlidzeklis::whereNull('archived_at')->where('statuss', 'pieejams')
            ->whereHas('rezervacijas', fn ($query) => $query
                ->where('apmaksas_statuss', 'apmaksata')->where('beigu_laiks', '>', now()))
            ->update(['statuss' => 'aiznemts']);
    }

    public function klients(): BelongsTo
    {
        return $this->belongsTo(Klients::class, 'klients_id', 'klients_id');
    }

    public function transportlidzeklis(): BelongsTo
    {
        return $this->belongsTo(Transportlidzeklis::class, 'transportlidzeklis_id', 'transportlidzeklis_id');
    }

    public function maksajumi(): HasMany
    {
        return $this->hasMany(Maksajums::class, 'rezervacija_id', 'rezervacija_id');
    }
}
