<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Klients extends Model
{
    protected $table = 'klients';
    protected $primaryKey = 'klients_id';

    protected $fillable = [
        'persona_id',
        'lietotajvards',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'persona_id');
    }

    public function rezervacijas(): HasMany
    {
        return $this->hasMany(Rezervacija::class, 'klients_id', 'klients_id');
    }

    public function atsauksmes(): HasMany
    {
        return $this->hasMany(Atsauksme::class, 'klients_id', 'klients_id');
    }
}