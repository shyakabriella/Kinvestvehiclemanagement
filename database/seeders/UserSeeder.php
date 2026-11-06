<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $name = 'Aime Fabrice';
        $email = 'admin@kinvest.rw';

        DB::transaction(function () use ($name, $email): void {
            $admin = User::where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($admin) {
                if (! $admin->hasRole('system_admin')) {
                    throw new RuntimeException(
                        'The configured admin email belongs to a ' .
                        'non-administrator account.'
                    );
                }

                $this->command?->info(
                    'System Administrator already exists. Password unchanged.'
                );

                return;
            }

            $password = Str::random(24);

            $admin = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
            ]);

            $admin->assignRole('system_admin');

            $directory = storage_path('app/private');

            if (! is_dir($directory) &&
                ! mkdir($directory, 0700, true) &&
                ! is_dir($directory)) {
                throw new RuntimeException(
                    'Unable to create private credentials directory.'
                );
            }

            $file = $directory . '/kfms-initial-admin.txt';

            $credentials = implode(PHP_EOL, [
                'KFMS INITIAL ADMINISTRATOR',
                'Name: ' . $name,
                'Email: ' . $email,
                'Password: ' . $password,
                '',
                'Change this password after first login.',
                '',
            ]);

            if (file_put_contents(
                $file,
                $credentials,
                LOCK_EX
            ) === false) {
                throw new RuntimeException(
                    'Unable to save administrator credentials.'
                );
            }

            chmod($file, 0600);

            $this->command?->info(
                'Initial System Administrator created.'
            );

            $this->command?->info(
                'Credentials saved privately at: ' . $file
            );
        });
    }
}
