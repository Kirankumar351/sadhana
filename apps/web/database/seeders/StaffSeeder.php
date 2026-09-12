<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The first staff account, so the admin portal can be opened at all.
 *
 * Password auth is used here rather than phone OTP because the admin panel is a desktop
 * tool for a named team, and Vol 2 requires it to sit behind a separate subdomain with an
 * IP allowlist and mandatory 2FA before it is exposed publicly.
 *
 * LOCAL AND STAGING ONLY. The guard below is what stops a seeded password reaching
 * production, and the credentials are deliberately obvious so nobody mistakes them for
 * something that should survive a deploy.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('StaffSeeder skipped: never seed credentials in production.');

            return;
        }

        $user = User::updateOrCreate(
            ['phone' => '9999900001'],
            [
                'name' => 'Content Lead',
                'email' => 'admin@sadhana.test',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
                'is_staff' => true,
                'preferred_locale' => 'te',
            ],
        );

        $this->command?->info("Admin: {$user->email} / password  ->  /admin");
    }
}
