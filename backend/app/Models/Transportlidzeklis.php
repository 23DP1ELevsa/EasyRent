<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transportlidzeklis extends Model
{
    protected $table = 'transportlidzeklis';
    protected $primaryKey = 'transportlidzeklis_id';

    protected $fillable = [
        'sniedzejs_id',
        'veids_id',
        'marka',
        'modelis',
        'atrumkarba',
        'degvielas_veids',
        'dienas_nomas_cena',
        'adrese',
        'statuss',
        'registracijas_numurs',
    ];

    protected $casts = [
        'dienas_nomas_cena' => 'decimal:2',
    ];

    public function sniedzejs(): BelongsTo
    {
        return $this->belongsTo(PakalpojumuSniedzejs::class, 'sniedzejs_id', 'sniedzejs_id');
    }

    public function veids(): BelongsTo
    {
        return $this->belongsTo(TransportliedzieklsVeids::class, 'veids_id', 'veids_id');
    }

    public function rezervacijas(): HasMany
    {
        return $this->hasMany(Rezervacija::class, 'transportlidzeklis_id', 'transportlidzeklis_id');
    }

    public function atsauksmes(): HasMany
    {
        return $this->hasMany(Atsauksme::class, 'transportlidzeklis_id', 'transportlidzeklis_id');
    }
}