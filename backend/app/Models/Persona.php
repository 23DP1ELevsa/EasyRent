<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model
{
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

    public function klients(): HasOne
    {
        return $this->hasOne(Klients::class, 'persona_id', 'persona_id');
    }

    public function pakalpojumuSniedzejs(): HasOne
    {
        return $this->hasOne(PakalpojumuSniedzejs::class, 'persona_id', 'persona_id');
    }
}