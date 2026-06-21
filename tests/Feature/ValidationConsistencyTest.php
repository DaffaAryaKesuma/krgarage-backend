<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vespa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_values_that_frontend_considers_invalid(): void
    {
        $invalidPayloads = [
            'nama terlalu pendek' => [
                'nama' => 'A',
                'email' => 'nama-pendek@example.test',
                'no_telepon' => '081234560001',
                'password' => 'Password123',
            ],
            'email kosong' => [
                'nama' => 'Tanpa Email',
                'no_telepon' => '081234560002',
                'password' => 'Password123',
            ],
            'nomor telepon tidak valid' => [
                'nama' => 'Nomor Tidak Valid',
                'email' => 'nomor-invalid@example.test',
                'no_telepon' => '12345',
                'password' => 'Password123',
            ],
            'password tanpa huruf besar dan angka' => [
                'nama' => 'Password Lemah',
                'email' => 'password-lemah@example.test',
                'no_telepon' => '081234560003',
                'password' => 'abcdefgh',
            ],
        ];

        foreach ($invalidPayloads as $skenario => $payload) {
            $this->postJson('/api/daftar', $payload)
                ->assertStatus(422, "Skenario gagal ditolak: {$skenario}");
        }

        $this->assertDatabaseCount('pengguna', 0);
    }

    public function test_registration_normalizes_indonesian_phone_number_and_prevents_duplicate_format(): void
    {
        $this->postJson('/api/daftar', [
            'nama' => 'Pelanggan Pertama',
            'email' => 'pelanggan-pertama@example.test',
            'no_telepon' => '+6281234567890',
            'password' => 'Password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.no_telepon', '081234567890');

        $this->postJson('/api/daftar', [
            'nama' => 'Pelanggan Kedua',
            'email' => 'pelanggan-kedua@example.test',
            'no_telepon' => '081234567890',
            'password' => 'Password123',
        ])->assertStatus(422);

        $this->assertDatabaseCount('pengguna', 1);
    }

    public function test_create_vespa_rejects_values_that_frontend_considers_invalid(): void
    {
        $pelanggan = $this->buatPelanggan();
        Sanctum::actingAs($pelanggan);

        $invalidPayloads = [
            'model terlalu pendek' => [
                'model' => 'P',
                'tahun_produksi' => 2020,
                'plat_nomor' => 'BM 1001 UJI',
            ],
            'tahun di bawah batas' => [
                'model' => 'Super',
                'tahun_produksi' => 1940,
                'plat_nomor' => 'BM 1940 UJI',
            ],
            'tahun di atas batas' => [
                'model' => 'Super',
                'tahun_produksi' => 9999,
                'plat_nomor' => 'BM 9999 UJI',
            ],
            'plat mengandung karakter khusus' => [
                'model' => 'Super',
                'tahun_produksi' => 2020,
                'plat_nomor' => 'BM 1234@ABC',
            ],
            'plat terlalu pendek' => [
                'model' => 'Super',
                'tahun_produksi' => 2020,
                'plat_nomor' => 'BM',
            ],
            'plat terlalu panjang' => [
                'model' => 'Super',
                'tahun_produksi' => 2020,
                'plat_nomor' => 'BM1234567890123456',
            ],
        ];

        foreach ($invalidPayloads as $skenario => $payload) {
            $this->postJson('/api/vespa-saya', $payload)
                ->assertStatus(422, "Skenario gagal ditolak: {$skenario}");
        }

        $this->assertDatabaseCount('vespa', 0);
    }

    public function test_create_vespa_accepts_valid_data_and_normalizes_plate_spacing(): void
    {
        $pelanggan = $this->buatPelanggan();
        Sanctum::actingAs($pelanggan);

        $this->postJson('/api/vespa-saya', [
            'model' => 'Vespa Super',
            'tahun_produksi' => 1987,
            'plat_nomor' => ' bm   1987   uji ',
        ])
            ->assertCreated()
            ->assertJsonPath('data.plat_nomor', 'BM 1987 UJI');

        $this->assertDatabaseHas('vespa', [
            'id_pengguna' => $pelanggan->id,
            'plat_nomor' => 'BM 1987 UJI',
        ]);
    }

    public function test_update_vespa_uses_the_same_validation_rules_as_create(): void
    {
        $pelanggan = $this->buatPelanggan();
        $vespa = Vespa::create([
            'id_pengguna' => $pelanggan->id,
            'model' => 'Vespa Super',
            'tahun_produksi' => 1987,
            'plat_nomor' => 'BM 1987 UJI',
        ]);

        Sanctum::actingAs($pelanggan);

        $this->putJson("/api/vespa-saya/{$vespa->id}", [
            'model' => 'P',
            'tahun_produksi' => 9999,
            'plat_nomor' => 'BM@',
        ])->assertStatus(422);

        $vespa->refresh();
        $this->assertSame('Vespa Super', $vespa->model);
        $this->assertSame(1987, $vespa->tahun_produksi);
        $this->assertSame('BM 1987 UJI', $vespa->plat_nomor);
    }

    private function buatPelanggan(): User
    {
        $data = [
            'nama' => 'Pelanggan Pengujian',
            'email' => 'pelanggan-' . uniqid() . '@example.test',
            'no_telepon' => '08' . random_int(1000000000, 9999999999),
            'password' => Hash::make('Password123'),
            'role' => 'pelanggan',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pengguna', 'name')) {
            $data['name'] = $data['nama'];
        }

        return User::findOrFail(DB::table('pengguna')->insertGetId($data));
    }
}
