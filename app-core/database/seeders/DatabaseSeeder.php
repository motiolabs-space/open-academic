<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles and permissions are part of the application, not of the demo
        // data — a production install runs this seeder too.
        $this->call(RolePermissionSeeder::class);

        if (!app()->environment('production')) {
            $this->call(DemoCampusSeeder::class);
        }
    }
}
