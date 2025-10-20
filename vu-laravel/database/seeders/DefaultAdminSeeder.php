<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if any users exist
        $userCount = DB::table('users')->count();
        
        if ($userCount === 0) {
            // Create default admin user
            DB::table('users')->insert([
                'username' => 'admin',
                'email' => 'admin@vuproject.com',
                'password' => Hash::make('admin123'),
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => 'admin',
                'is_active' => true,
                'phone' => null,
                'department' => 'Administration',
                'two_factor_enabled' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'email_verified_at' => now(),
                'last_login_at' => null,
                'last_login_ip' => null,
                'password_changed_at' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            echo "✅ Default admin user created successfully!\n";
            echo "   Username: admin\n";
            echo "   Password: admin123\n";
        } else {
            echo "⚠️  Users already exist in database. Skipping default admin creation.\n";
        }
    }
}

