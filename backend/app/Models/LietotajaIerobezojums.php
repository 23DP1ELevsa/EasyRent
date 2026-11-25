<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LietotajaIerobezojums extends Model
{
    public const WARNING = 'bridinajums';
    public const BOOKING = 'rezervaciju_aizliegums';
    public const LISTING = 'publikaciju_aizliegums';
    public const REVIEW = 'atsauksmju_aizliegums';
    public const BLOCK = 'konta_blokesana';
    public const TYPES = [self::WARNING, self::BOOKING, self::LISTING, self::REVIEW, self::BLOCK];

    protected $table = 'lietotaja_ierobezojums';
    protected $primaryKey = 'ierobezojums_id';
    protected $fillable = ['veids', 'iemesls', 'iemesla_kods', 'sakuma_laiks', 'beigu_laiks', 'administrators_id'];
    protected $casts = ['sakuma_laiks' => 'datetime', 'beigu_laiks' => 'datetime', 'atcelts_at' => 'datetime'];

    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->whereNull('atcelts_at')->where('sakuma_laiks', '<=', $now)
            ->where(fn ($query) => $query->whereNull('beigu_laiks')->orWhere('beigu_laiks', '>', $now));
    }

    public function scopeBlocking(Builder $query): void
    {
        $query->active()->where('veids', self::BLOCK);
    }
}
