<?php

namespace App\Console\Commands;

use App\Models\Persona;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'admin:create';
    protected $description = 'Interactively create a new verified EasyRent administrator';

    public function handle(): int
    {
        if (! $this->input->isInteractive() || ! defined('STDIN') || ! stream_isatty(STDIN)) {
            $this->error(__('admin.interactive_required'));

            return self::FAILURE;
        }

        $name = trim((string) $this->ask(__('admin.command_name')));
        $email = Persona::normalizeEmail((string) $this->ask(__('admin.command_email')));
        $password = $this->secret(__('admin.command_password'), false);
        $confirmation = $this->secret(__('admin.command_confirmation'), false);
        $validator = Validator::make(compact('name', 'email', 'password') + ['password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails() || strlen((string) $password) > 72 || str_contains((string) $password, "\0")) {
            $this->error($validator->errors()->first() ?: __('admin.invalid_password'));

            return self::FAILURE;
        }
        if (Persona::withTrashed()->forEmail($email)->exists()) {
            $this->error(__('app.emailTaken'));

            return self::FAILURE;
        }

        try {
            (new Persona([
                'vards' => $name,
                'epasts' => $email,
                'parole' => Hash::make($password),
                'loma' => 'administrators',
            ]))->forceFill(['email_verified_at' => now(), 'preferred_locale' => app()->getLocale()])->save();
        } catch (UniqueConstraintViolationException) {
            $this->error(__('app.emailTaken'));

            return self::FAILURE;
        }

        $this->info(__('admin.administrator_created'));

        return self::SUCCESS;
    }
}
