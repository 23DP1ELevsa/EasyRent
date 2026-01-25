<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportliedzieklsVeids extends Model
{
    protected $table = 'transportlidzekla_veids';
    protected $primaryKey = 'veids_id';

    protected $fillable = ['nosaukums'];

    public function transportlidzekli(): HasMany
    {
        return $this->hasMany(Transportlidzeklis::class, 'veids_id', 'veids_id');
    }
}