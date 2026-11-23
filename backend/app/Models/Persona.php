<?php

namespace App\Models;

use App\Notifications\ResetPersonaPassword;
use App\Support\AccountMail;
use App\Support\EmailVerification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\TransientToken;

class Persona extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use Notifiable;
    use SoftDeletes;

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

    protected $casts = [
        'profile_completed' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'parole', 'google_id', 'profile_completed', 'deleted_at', 'email_verified_at', 'email_verification_password_reset_at',
    ];

    /** Acquire participant locks by primary key, in the same order for every booking write. */
    public static function lockActiveAccounts(int ...$ids): void
    {
        $ids = array_unique($ids);
        sort($ids, SORT_NUMERIC);

        foreach ($ids as $id) {
            abort_unless(static::whereKey($id)->lockForUpdate()->first(), 409, __('app.inactiveParticipant'));
        }
    }

    public function getAuthPassword(): string
    {
        return $this->parole ?? '';
    }

    public function getAuthPasswordName(): string
    {
        return 'parole';
    }

    public function getEmailForPasswordReset(): ?string
    {
        return $this->epasts;
    }

    public function getEmailForVerification(): ?string
    {
        return $this->epasts;
    }

    public function sendEmailVerificationNotification(): void
    {
        if ($this->epasts !== null) {
            app(EmailVerification::class)->send($this->epasts, app()->getLocale(), $this->getKey());
        }
    }

    public function mayCompleteGoogleProfile(): bool
    {
        return $this->google_id !== null && ! $this->profile_completed && $this->epasts === null
            && $this->currentAccessToken() instanceof TransientToken;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->epasts;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token, ?string $locale = null, ?string $requestId = null): void
    {
        app(AccountMail::class)->send('password_reset', $this, new ResetPersonaPassword($token), $locale ?? app()->getLocale(), $requestId);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function purgeDatabaseSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $store = DB::connection(config('session.connection'));
        $purge = fn () => $store->table(config('session.table'))->where('user_id', $this->getKey())->delete();
        if ($store === $this->getConnection()) {
            $purge();

            return;
        }

        $this->getConnection()->afterCommit(function () use ($purge) {
            try {
                $purge();
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
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
