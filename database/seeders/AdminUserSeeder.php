<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AdminRole;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@ouptel.com'],
            [
                // Wo_Users fields used by current auth/panel checks
                'username' => 'ouptel_admin',
                'first_name' => 'Ouptel',
                'last_name' => 'Admin',
                'email' => 'admin@ouptel.com',
                'password' => Hash::make('admin123'),
                'gender' => 'male',
                'admin' => '1',    // super admin flag used in panel access
                'active' => '1',   // active account
                'verified' => '1', // verified account
            ]
        );

        // Ensure a Super Admin role exists and is attached to this user.
        // (admin='1' already grants full access, role attach keeps RBAC consistent.)
        $superAdminRole = AdminRole::firstOrCreate(
            ['name' => 'Super Admin'],
            [
                'description' => 'Has access to everything. Can manage other admins.',
                'is_super_admin' => true,
            ]
        );
        $admin->adminRoles()->syncWithoutDetaching([$superAdminRole->id]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@ouptel.com');
        $this->command->info('Password: admin123');
    }
}
