<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Persona extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'persona';
    protected $primaryKey = 'persona_id';

    protected $fillable = [
        'vards',
        'uzvards',
        'epasts',
        'parole',
        'kontakttalrunis',
        'loma',
        'bankas_konts',
    ];

    protected $hidden = ['parole'];

    public function getAuthPassword(): string
    {
        return $this->parole;
    }

    public function klients(): HasOne
    {
        return $this->hasOne(Klients::class, 'persona_id', 'persona_id');
    }

    public function pakalpojumuSniedzejs(): HasOne
    {
        return $this->hasOne(PakalpojumuSniedzejs::class, 'persona_id', 'persona_id');
    }
}