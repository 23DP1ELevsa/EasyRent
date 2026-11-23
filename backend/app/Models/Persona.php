<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
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

    protected $attributes = ['profile_completed' => true];

    protected $casts = ['profile_completed' => 'boolean'];

    protected $hidden = ['parole', 'google_id', 'profile_completed'];

    public function getAuthPassword(): string
    {
        return $this->parole ?? '';
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? $query->whereRaw('epasts COLLATE NOCASE = ?', [$email])
            : $query->where('epasts', $email);
    }

    public function profileData(): array
    {
        $relation = match ($this->loma) {
            'klients' => 'klients',
            'pakalpojumu_sniedzejs' => 'pakalpojumuSniedzejs',
            default => null,
        };

        if ($relation) {
            $this->loadMissing($relation);
        }

        return $this->toArray() + [
            'profile_completed' => $this->profile_completed,
            'profile_missing_fields' => $this->profile_completed ? [] : $this->missingProfileFields($relation),
        ];
    }

    public function requiredProfileFields(): array
    {
        return [
            'vards', 'epasts', 'kontakttalrunis', 'bankas_konts',
            ...match ($this->loma) {
                'klients' => ['lietotajvards'],
                'pakalpojumu_sniedzejs' => ['registracijas_numurs', 'iela', 'majas_numurs', 'pilseta', 'pasta_indekss'],
                default => [],
            },
        ];
    }

    private function missingProfileFields(?string $relation): array
    {
        $fields = $this->getAttributes() + ($relation ? ($this->$relation?->getAttributes() ?? []) : []);

        return array_values(array_filter(
            $this->requiredProfileFields(),
            fn (string $field) => blank($fields[$field] ?? null),
        ));
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
