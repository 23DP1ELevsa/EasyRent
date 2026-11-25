<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkapumaZinojums extends Model
{
    public const TARGETS = ['user', 'vehicle', 'review'];
    public const REASONS = ['fraud', 'misinformation', 'abuse', 'rental_terms', 'suspicious', 'other'];
    public const STATUSES = ['jauns', 'izskatisana', 'atrisinats', 'noraidits'];
    public const OPEN = ['jauns', 'izskatisana'];

    protected $table = 'parkapuma_zinojums';
    protected $primaryKey = 'zinojums_id';
    protected $fillable = ['persona_id', 'objekta_veids', 'objekta_id', 'iemesls', 'apraksts'];
    protected $casts = ['izskatits_at' => 'datetime'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id')->withTrashed();
    }
}
