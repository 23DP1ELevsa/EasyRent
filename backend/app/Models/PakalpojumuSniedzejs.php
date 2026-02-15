<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PakalpojumuSniedzejs extends Model
{
    protected $table = 'pakalpojumu_sniedzejs';
    protected $primaryKey = 'sniedzejs_id';

    protected $fillable = [
        'persona_id',
        'registracijas_numurs',
        'iela',
        'majas_numurs',
        'dzivokla_numurs',
        'pilseta',
        'pasta_indekss',
        'latitude',
        'longitude',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'persona_id');
    }

    public function transportlidzekli(): HasMany
    {
        return $this->hasMany(Transportlidzeklis::class, 'sniedzejs_id', 'sniedzejs_id');
    }
}