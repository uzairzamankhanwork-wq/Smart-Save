<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * First-time setup. Runs only from the server's command line, so there is no
 * web route anyone could use to become an administrator.
 */
class CreateAdmin extends Command
{
    protected $signature = 'smartsave:create-admin {--name=} {--email=}';

    protected $description = 'Create the first SmartSave administrator (only works while no admin exists)';

    public function handle(): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->error('An administrator already exists. Manage accounts from the Admin › Users page.');

            return self::FAILURE;
        }
        if (User::count() >= config('smartsave.max_users')) {
            $this->error('The maximum number of accounts has been reached.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->secret('Password (min 10 chars, upper+lower case and a number)');
        $confirm = $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = User::ROLE_ADMIN;
        $user->email_verified_at = now();
        $user->save();

        ActivityLog::record('admin.first_admin_created', $user, ['email' => $email], $user->id);
        $this->info("Administrator {$email} created. You can now sign in.");

        return self::SUCCESS;
    }
}
