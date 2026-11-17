<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** php artisan iibsom:staff owner@iibsom.com --role=super_admin --name="Hashim" */
class CreateStaff extends Command
{
    protected $signature = 'iibsom:staff {email} {--role=super_admin} {--name=}';

    protected $description = 'Create (or update) a staff account for the Super Admin panel';

    public function handle(): int
    {
        $role = $this->option('role');
        if (! is_string($role) || ! array_key_exists($role, config()->array('admin.roles'))) {
            $this->error('Role must be one of: '.implode(', ', array_keys(config()->array('admin.roles'))));

            return self::FAILURE;
        }

        $email = Str::lower($this->argument('email'));
        $password = $this->secret('Password (12+ characters, letters and numbers)');

        $check = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(12)->letters()->numbers()],
        ]);
        if ($check->fails()) {
            foreach ($check->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?: ($user->name ?: Str::before($email, '@'));
        $user->email = $email;
        $user->forceFill(['role' => $role, 'password' => $password, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->info("Staff account ready: {$email} ({$role}). Open /admin and set up 2FA on first sign-in.");

        return self::SUCCESS;
    }
}
