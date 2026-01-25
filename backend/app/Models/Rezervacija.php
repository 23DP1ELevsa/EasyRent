<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'sakuma_laiks' => 'datetime',
        'beigu_laiks' => 'datetime',
        'rezervacijas_datums' => 'date',
        'izveides_datums' => 'datetime',
        'maksajuma_datums' => 'date',
        'kopa_summa' => 'decimal:2',
    ];

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