<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::firstOrCreate(
            ['email' => 'mesum@worldoftech.company'],
            [
                'name' => 'Admin',
                'password' => bcrypt('admin123'), // Helper to set default password
            ]
        );

        $this->call([
            CategorySeeder::class,
        ]);
    }
}
