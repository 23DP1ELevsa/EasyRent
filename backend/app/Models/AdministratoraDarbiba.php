<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministratoraDarbiba extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'administratora_darbiba';
    protected $primaryKey = 'darbiba_id';
    protected $fillable = [
        'administrators_id', 'darbiba', 'objekta_veids', 'objekta_id', 'iemesls',
        'iepriekseja_vertiba', 'jauna_vertiba',
    ];
    protected $casts = ['iepriekseja_vertiba' => 'array', 'jauna_vertiba' => 'array'];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'administrators_id')->withTrashed();
    }
}
