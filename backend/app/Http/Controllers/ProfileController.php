<?php

namespace App\Http\Controllers;

use App\Models\Klients;
use App\Models\Persona;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request, string $personaId): JsonResponse
    {
        $this->authorizeProfile($request, $personaId);

        return response()->json(['persona' => $request->user()->profileData()])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $personaId): JsonResponse
    {
        $this->authorizeProfile($request, $personaId);

        try {
            $persona = DB::transaction(function () use ($request, $personaId) {
                $persona = Persona::whereKey($personaId)->lockForUpdate()->firstOrFail();
                if ($request->exists('loma')) {
                    throw ValidationException::withMessages(['loma' => __('app.profileRoleFixed')]);
                }
                if ($request->exists('email') || ($request->exists('epasts') && $persona->epasts !== null)) {
                    throw ValidationException::withMessages(['epasts' => __('app.profileEmailFixed')]);
                }

                return $this->saveProfile($request->all(), $persona);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($request->filled('epasts') && Persona::forEmail($request->string('epasts')->value())->exists()) {
                throw ValidationException::withMessages(['epasts' => __('app.emailTaken')]);
            }
            if ($request->filled('lietotajvards') && Klients::where('lietotajvards', $request->input('lietotajvards'))->exists()) {
                throw ValidationException::withMessages(['lietotajvards' => __('app.usernameTaken')]);
            }
            throw $exception;
        }

        // Keep Sanctum's authenticated session user in sync when the password changes.
        $request->user()->setRawAttributes($persona->getAttributes(), true);

        return response()->json([
            'persona' => $persona->profileData(),
            'message' => __('app.profileUpdated'),
        ])->header('Cache-Control', 'no-store');
    }

    private function saveProfile(array $input, Persona $persona): Persona
    {
        $onboarding = ! $persona->profile_completed;
        $google = $persona->google_id !== null;
        $relation = match ($persona->loma) {
            'klients' => $persona->klients(),
            'pakalpojumu_sniedzejs' => $persona->pakalpojumuSniedzejs(),
            default => null,
        };
        $roleProfile = $relation?->first();
        $rules = [
            'vards' => $google ? ['string', 'max:50'] : ['string', 'min:2', 'max:50'],
            'uzvards' => ['nullable', 'string', 'max:50'],
            'kontakttalrunis' => ['string', 'min:6', 'max:20'],
            'bankas_konts' => ['string', 'min:15', 'max:34'],
            'password' => ['string', Password::defaults()],
        ];

        if ($google) {
            $rules['kontakttalrunis'][] = 'regex:/^\+?[0-9 \-()]+$/D';
            $rules['bankas_konts'][] = function (string $field, mixed $value, \Closure $fail) {
                if (is_string($value) && ! $this->validIban($value)) {
                    $fail(__('app.invalidIban'));
                }
            };
        } else {
            $rules['kontakttalrunis'][] = 'nullable';
            $rules['bankas_konts'][] = 'nullable';
        }

        if ($persona->epasts === null && $onboarding) {
            if (isset($input['epasts']) && is_string($input['epasts'])) {
                $input['epasts'] = strtolower($input['epasts']);
            }
            $rules['epasts'] = ['email', 'max:255', function (string $field, mixed $value, \Closure $fail) {
                if (is_string($value) && Persona::forEmail($value)->exists()) {
                    $fail(__('app.emailTaken'));
                }
            }];
        }

        if ($persona->loma === 'klients') {
            $rules['lietotajvards'] = [
                'string', 'max:30',
                Rule::unique('klients', 'lietotajvards')->ignore($roleProfile?->getKey(), 'klients_id'),
            ];
            if ($google) {
                $rules['lietotajvards'][] = 'min:3';
            }
        } elseif ($persona->loma === 'pakalpojumu_sniedzejs') {
            $rules += [
                'registracijas_numurs' => ['string', 'max:20'],
                'iela' => ['string', 'max:150'],
                'majas_numurs' => ['string', 'max:20'],
                'dzivokla_numurs' => ['nullable', 'string', 'max:20'],
                'pilseta' => ['string', 'max:100'],
                'pasta_indekss' => ['string', 'max:20'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            ];
        }

        $required = $persona->requiredProfileFields();
        if ($onboarding) {
            $input += Arr::only($persona->getAttributes() + ($roleProfile?->getAttributes() ?? []), $required);
        }
        foreach ($rules as $field => &$fieldRules) {
            if ($onboarding && in_array($field, $required, true)) {
                array_unshift($fieldRules, 'bail', 'required');
            } elseif ($google && in_array($field, $required, true)) {
                array_unshift($fieldRules, 'sometimes', 'bail', 'required');
            } else {
                array_unshift($fieldRules, 'sometimes', 'bail');
            }
        }
        unset($fieldRules);

        $data = Validator::make($input, $rules, [
            'lietotajvards.unique' => __('app.usernameTaken'),
            'kontakttalrunis.regex' => __('app.invalidPhone'),
        ])->validate();

        $personalFields = ['vards', 'uzvards', 'epasts', 'kontakttalrunis', 'bankas_konts', 'password'];
        $persona->fill(array_filter(Arr::only($data, $personalFields), fn ($value) => $value !== null));
        if (isset($data['password'])) {
            $persona->parole = Hash::make($data['password']);
        }

        $roleFields = Arr::except($data, $personalFields);
        if ($roleProfile) {
            $roleProfile->update($roleFields);
        } elseif ($onboarding) {
            $relation->create($roleFields);
        }
        if ($onboarding) {
            $persona->profile_completed = true;
        }
        $persona->save();

        return $persona;
    }

    private function validIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^[A-Z0-9]{15,34}$/D', $iban)) {
            return false;
        }

        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $remainder = ctype_digit($character)
                ? ($remainder * 10 + (int) $character) % 97
                : ($remainder * 100 + ord($character) - 55) % 97;
        }

        return $remainder === 1;
    }

    private function authorizeProfile(Request $request, string $personaId): void
    {
        abort_unless((string) $request->user()->getKey() === ltrim($personaId, '0'), 403, __('app.profileForbidden'));
    }
}
