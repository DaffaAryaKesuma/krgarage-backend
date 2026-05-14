<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Uncomment baris di bawah untuk generate dummy data saat development
        // User::factory(10)->create();

        User::create([
            'nama' => 'Owner',
            'email' => 'owner@gmail.com',
            'no_telepon' => '081234567890',
            'password' => bcrypt('Password123'),
            'role' => 'pemilik',
        ]);

        User::create([
            'nama' => 'Admin',
            'email' => 'admin@gmail.com',
            'no_telepon' => '081234567891',
            'password' => bcrypt('Password123'),
            'role' => 'admin',
        ]);
    }
}

