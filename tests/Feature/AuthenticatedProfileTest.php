<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticatedProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_uses_identity_from_active_token(): void
    {
        $data = [
            'nama' => 'Pemilik Test',
            'email' => 'pemilik.profile@example.test',
            'no_telepon' => '081234567890',
            'password' => Hash::make('password'),
            'role' => 'pemilik',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pengguna', 'name')) {
            $data['name'] = 'Pemilik Test';
        }

        $pemilik = User::findOrFail(DB::table('pengguna')->insertGetId($data));

        Sanctum::actingAs($pemilik);

        $this->getJson('/api/profil')
            ->assertOk()
            ->assertJsonPath('data.id', $pemilik->id)
            ->assertJsonPath('data.role', 'pemilik');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profil')->assertUnauthorized();
    }
}
