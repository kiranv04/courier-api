<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Creates the initial super-admin ONLY if it does not exist yet; re-seeding never
     * resets an existing password. Set SUPERADMIN_INITIAL_PASSWORD in .env to choose the
     * initial password, otherwise a random one is generated and printed once.
     */
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@vkenterprises.com');
        $user  = User::where('email', $email)->first();

        if (! $user) {
            $password = env('SUPERADMIN_INITIAL_PASSWORD') ?: Str::random(20);

            $user = User::create([
                'email'                => $email,
                'owner_id'             => 0,
                'owner_type'           => 'system',
                'name'                 => 'Super Admin',
                'password'             => Hash::make($password),
                'must_change_password' => true,
            ]);

            if (! env('SUPERADMIN_INITIAL_PASSWORD')) {
                $this->command?->warn("Super admin created: {$email} / {$password}  (shown once - change it on first login)");
            }
        }

        $user->assignRole('super-admin');
    }
}
