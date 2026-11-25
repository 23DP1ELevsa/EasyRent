<?php

namespace App\Models;

use App\Support\DrivingLicence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        DrivingLicence::FIELD,
    ];

    protected $casts = [
        'dienas_nomas_cena' => 'decimal:2',
        'archived_at' => 'datetime',
        'moderation_hidden_at' => 'datetime',
    ];

    public function scopeListed(Builder $query): void
    {
        $query->whereNull('archived_at')->whereNull('moderation_hidden_at')
            ->whereHas('sniedzejs.persona', fn ($persona) => $persona->whereDoesntHave('restrictions', fn ($restrictions) => $restrictions->blocking()));
    }

    public function scopeWithPhotoSummary(Builder $query): void
    {
        $query->withCount('photos')->with([
            'primaryPhoto' => fn ($photo) => $photo->select(
                'transportlidzekla_foto.foto_id', 'transportlidzekla_foto.transportlidzeklis_id', 'transportlidzekla_foto.content_hash'
            ),
        ]);
    }

    public function bookingSnapshot(): array
    {
        return $this->only(['marka', 'modelis', 'atrumkarba', 'degvielas_veids'])
            + ['veids' => $this->veids?->nosaukums];
    }

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

    public function photos(): HasMany
    {
        return $this->hasMany(TransportlidzeklaFoto::class, 'transportlidzeklis_id');
    }

    public function primaryPhoto(): HasOne
    {
        return $this->photos()->one()->ofMany(['position' => 'min', 'foto_id' => 'min']);
    }

    public function atsauksmes(): HasMany
    {
        return $this->hasMany(Atsauksme::class, 'transportlidzeklis_id', 'transportlidzeklis_id');
    }
}
