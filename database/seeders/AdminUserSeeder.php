<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@ouptel.com'],
            [
                'name' => 'Ouptel Admin',
                'email' => 'admin@ouptel.com',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'status' => 'active',
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@ouptel.com');
        $this->command->info('Password: admin123');
    }
}