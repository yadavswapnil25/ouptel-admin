<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            UsersGenderSeeder::class,
            PageCategoriesSeeder::class,
            GroupCategoriesSeeder::class,
            SettingsSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);
    }
}
