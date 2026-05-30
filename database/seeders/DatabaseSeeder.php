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

        User::updateOrCreate(
            ['email' => 'owner@gmail.com'],
            [
                'nama' => 'Owner',
                'no_telepon' => '081234567890',
                'password' => bcrypt('Password123'),
                'role' => 'pemilik',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'nama' => 'Admin',
                'no_telepon' => '081234567891',
                'password' => bcrypt('Password123'),
                'role' => 'admin',
            ]
        );

        $this->call(SukuCadangSeeder::class);
    }
}

