<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@vkenterprises.com'],
            [
                'owner_id' => 0,
                'owner_type' => 'system',
                'name' => 'Super Admin',
                'password' => bcrypt('SuperAdmin@123'), // change anytime
                'must_change_password' => false,
            ]
        );

        $user->assignRole('super-admin');
    }
}
