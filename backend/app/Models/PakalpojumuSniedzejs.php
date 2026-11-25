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

    public function publicStreet(): string
    {
        $street = trim((string) $this->iela);
        $houseNumber = trim((string) $this->majas_numurs);
        $apartmentNumber = trim((string) $this->dzivokla_numurs);
        if ($houseNumber !== '' && $apartmentNumber !== '') {
            // Some older records repeat the house and apartment in the street field.
            $street = preg_replace(
                '/(?<!\S)('.preg_quote($houseNumber, '/').')\s*[-\/]\s*'.preg_quote($apartmentNumber, '/').'$/iu',
                '$1',
                $street,
            );
        }

        return $street;
    }

    public function publicPickupLocation(): array
    {
        $street = $this->publicStreet();
        $houseNumber = trim((string) $this->majas_numurs);
        if (!preg_match('/[\p{L}\p{N}]/u', $street)) {
            $street = '';
        } elseif ($houseNumber !== '' && !preg_match('/(?<!\S)'.preg_quote($houseNumber, '/').'$/iu', $street)) {
            $street .= ' '.$houseNumber;
        }

        return [
            'address' => $street,
            'city' => trim((string) $this->pilseta),
            'postalCode' => trim((string) $this->pasta_indekss),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'persona_id');
    }

    public function transportlidzekli(): HasMany
    {
        return $this->hasMany(Transportlidzeklis::class, 'sniedzejs_id', 'sniedzejs_id');
    }
}
