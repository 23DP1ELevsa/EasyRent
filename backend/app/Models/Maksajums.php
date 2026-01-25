<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maksajums extends Model
{
    protected $table = 'maksajums';
    protected $primaryKey = 'maksajums_id';

    protected $fillable = [
        'rezervacija_id',
        'summa',
        'statuss',
        'tranzakcijas_numurs',
        'rekins',
    ];

    protected $casts = [
        'summa' => 'decimal:2',
    ];

    public function rezervacija(): BelongsTo
    {
        return $this->belongsTo(Rezervacija::class, 'rezervacija_id', 'rezervacija_id');
    }
}