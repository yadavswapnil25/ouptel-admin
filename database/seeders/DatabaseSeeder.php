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
            BlogCategoriesSeeder::class,
            BlogsSeeder::class,
            GroupsSeeder::class,
            PagesSeeder::class,
            AlbumsSeeder::class,
            EventsSeeder::class,
            SettingsSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);
    }
}
