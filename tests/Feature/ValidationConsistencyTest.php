<?php

namespace Tests\Feature;

use App\Models\KategoriSukuCadang;
use App\Models\Layanan;
use App\Models\Pemesanan;
use App\Models\SukuCadang;
use App\Models\User;
use App\Models\Vespa;
use Carbon\Carbon;
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

    public function test_employee_create_and_update_reject_values_blocked_by_frontend(): void
    {
        $admin = $this->buatPengguna('admin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/karyawan', [
            'nama' => 'A1',
            'email' => 'karyawan-invalid@example.test',
            'no_telepon' => '123',
            'password' => '123456',
            'role' => 'mekanik',
        ])->assertStatus(422);

        $mekanik = $this->buatPengguna('mekanik');

        $this->putJson("/api/admin/karyawan/{$mekanik->id}", [
            'nama' => 'B2',
            'email' => $mekanik->email,
            'no_telepon' => '456',
            'password' => 'abcdef',
            'role' => 'mekanik',
        ])->assertStatus(422);

        $mekanik->refresh();
        $this->assertNotSame('B2', $mekanik->nama);
    }

    public function test_employee_phone_is_normalized_and_must_be_unique(): void
    {
        $admin = $this->buatPengguna('admin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/karyawan', [
            'nama' => 'Mekanik Baru',
            'email' => 'mekanik-baru@example.test',
            'no_telepon' => '+6281234567801',
            'password' => 'Password123',
            'role' => 'mekanik',
        ])
            ->assertCreated()
            ->assertJsonPath('data.no_telepon', '081234567801');

        $this->postJson('/api/admin/karyawan', [
            'nama' => 'Admin Baru',
            'email' => 'admin-baru@example.test',
            'no_telepon' => '081234567801',
            'password' => 'Password123',
            'role' => 'admin',
        ])->assertStatus(422);
    }

    public function test_profile_rejects_short_name_invalid_phone_and_duplicate_phone(): void
    {
        $pengguna = $this->buatPengguna('pelanggan');
        $penggunaLain = $this->buatPengguna('pelanggan');
        Sanctum::actingAs($pengguna);

        $this->putJson('/api/profil', [
            'nama' => 'A',
            'email' => $pengguna->email,
            'no_telepon' => 'abc12345',
        ])->assertStatus(422);

        $this->putJson('/api/profil', [
            'nama' => 'Pelanggan Valid',
            'email' => $pengguna->email,
            'no_telepon' => $penggunaLain->no_telepon,
        ])->assertStatus(422);

        $pengguna->refresh();
        $this->assertNotSame('A', $pengguna->nama);
        $this->assertNotSame($penggunaLain->no_telepon, $pengguna->no_telepon);
    }

    public function test_profile_accepts_valid_phone_and_normalizes_plus_62_format(): void
    {
        $pengguna = $this->buatPengguna('pelanggan');
        Sanctum::actingAs($pengguna);

        $this->putJson('/api/profil', [
            'nama' => 'Pelanggan Diperbarui',
            'email' => 'profil-baru@example.test',
            'no_telepon' => '+6281234567802',
        ])
            ->assertOk()
            ->assertJsonPath('data.no_telepon', '081234567802');
    }

    public function test_booking_rejects_outside_business_hours_and_duplicate_services(): void
    {
        $pelanggan = $this->buatPengguna('pelanggan');
        $this->buatPengguna('mekanik');
        $vespa = Vespa::create([
            'id_pengguna' => $pelanggan->id,
            'model' => 'Vespa Super',
            'tahun_produksi' => 1987,
            'plat_nomor' => 'BM 7001 UJI',
        ]);
        $layanan = Layanan::create([
            'nama_layanan' => 'Servis Ringan',
            'deskripsi' => 'Layanan pengujian',
            'harga' => 100000,
            'durasi_pengerjaan' => 60,
        ]);
        $tanggal = $this->tanggalPemesananValid();

        Sanctum::actingAs($pelanggan);

        $payload = [
            'id_vespa' => $vespa->id,
            'id_layanan' => [$layanan->id],
            'tanggal_pemesanan' => $tanggal,
            'jam_pemesanan' => '23:00',
        ];

        $this->postJson('/api/pemesanan', $payload)->assertStatus(422);

        $payload['jam_pemesanan'] = '10:00';
        $payload['id_layanan'] = [$layanan->id, $layanan->id];
        $this->postJson('/api/pemesanan', $payload)->assertStatus(422);

        $this->assertDatabaseCount('pemesanan', 0);
    }

    public function test_inventory_update_rejects_empty_required_fields(): void
    {
        $admin = $this->buatPengguna('admin');
        $kategori = KategoriSukuCadang::create(['nama' => 'Kelistrikan']);
        $sukuCadang = SukuCadang::create([
            'nama_suku_cadang' => 'Aki',
            'id_kategori' => $kategori->id,
            'jumlah_stok' => 5,
            'harga_beli' => 100000,
            'harga_jual' => 125000,
            'batas_minimal_stok' => 1,
            'deskripsi' => 'Aki pengujian',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/inventori/{$sukuCadang->id}", [
            'nama_suku_cadang' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.nama_suku_cadang.0', 'Nama suku cadang wajib diisi.');

        $sukuCadang->refresh();
        $this->assertSame('Aki', $sukuCadang->nama_suku_cadang);
    }

    public function test_mechanic_assignment_requires_a_mechanic_id(): void
    {
        $admin = $this->buatPengguna('admin');
        $pelanggan = $this->buatPengguna('pelanggan');
        $vespa = Vespa::create([
            'id_pengguna' => $pelanggan->id,
            'model' => 'Vespa Sprint',
            'tahun_produksi' => 2022,
            'plat_nomor' => 'BM 7002 UJI',
        ]);
        $pemesanan = Pemesanan::create([
            'id_pengguna' => $pelanggan->id,
            'id_vespa' => $vespa->id,
            'tanggal_pemesanan' => $this->tanggalPemesananValid(),
            'jam_pemesanan' => '10:00:00',
            'status' => Pemesanan::STATUS_DIKONFIRMASI,
            'total_harga' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/pemesanan/{$pemesanan->id}/tugaskan-mekanik", [
            'id_mekanik' => null,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.id_mekanik.0', 'Mekanik wajib diisi.');

        $this->assertNull($pemesanan->fresh()->id_mekanik);
    }

    private function buatPelanggan(): User
    {
        return $this->buatPengguna('pelanggan');
    }

    private function buatPengguna(string $role): User
    {
        $data = [
            'nama' => ucfirst($role) . ' Pengujian',
            'email' => $role . '-' . uniqid() . '@example.test',
            'no_telepon' => '08' . random_int(1000000000, 9999999999),
            'password' => Hash::make('Password123'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pengguna', 'name')) {
            $data['name'] = $data['nama'];
        }

        return User::findOrFail(DB::table('pengguna')->insertGetId($data));
    }

    private function tanggalPemesananValid(): string
    {
        $tanggal = Carbon::now(config('app.timezone'))->addDays(2)->startOfDay();

        while ($tanggal->isFriday()) {
            $tanggal->addDay();
        }

        return $tanggal->toDateString();
    }
}
