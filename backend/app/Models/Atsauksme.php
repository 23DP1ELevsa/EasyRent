<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Atsauksme extends Model
{
    protected $table = 'atsauksme';
    protected $primaryKey = 'atsauksme_id';

    protected $fillable = [
        'klients_id',
        'transportlidzeklis_id',
        'vertejums',
        'komentars',
        'datums',
    ];

    protected $casts = [
        'datums' => 'date',
    ];

    public function klients(): BelongsTo
    {
        return $this->belongsTo(Klients::class, 'klients_id', 'klients_id');
    }

    public function transportlidzeklis(): BelongsTo
    {
        return $this->belongsTo(Transportlidzeklis::class, 'transportlidzeklis_id', 'transportlidzeklis_id');
    }
}