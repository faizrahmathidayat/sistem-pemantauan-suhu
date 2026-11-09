<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\StarterRoles;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        StarterRoles::install();
        $this->call(ZoneSeeder::class);

        $admin = config('monitoring.admin');
        if (filled($admin['email']) && filled($admin['password'])) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'] ?: 'Admin',
                    'password' => $admin['password'],
                    'role_id' => Role::administrator()->id,
                ],
            );
        }
    }
}
