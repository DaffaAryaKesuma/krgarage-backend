<?php

namespace Tests\Feature;

use App\Models\ItemPemesanan;
use App\Models\KategoriSukuCadang;
use App\Models\Layanan;
use App\Models\Pemesanan;
use App\Models\SukuCadang;
use App\Models\User;
use App\Models\Vespa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SoftDeleteMasterDataHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_layanan_and_suku_cadang_remain_available_to_history_and_reports(): void
    {
        $admin = $this->buatPengguna('admin');
        $pemilik = $this->buatPengguna('pemilik');
        $pelanggan = $this->buatPengguna('pelanggan');

        $vespa = Vespa::create([
            'id_pengguna' => $pelanggan->id,
            'model' => 'Vespa Sprint',
            'tahun_produksi' => 2022,
            'plat_nomor' => 'B 1234 UJI',
        ]);

        $layanan = Layanan::create([
            'nama_layanan' => 'Servis Besar',
            'deskripsi' => 'Layanan untuk pengujian arsip',
            'harga' => 150000,
            'durasi_pengerjaan' => 120,
        ]);

        $kategori = KategoriSukuCadang::create(['nama' => 'Mesin']);
        $sukuCadang = SukuCadang::create([
            'nama_suku_cadang' => 'Busi',
            'id_kategori' => $kategori->id,
            'jumlah_stok' => 10,
            'harga_beli' => 15000,
            'harga_jual' => 25000,
            'batas_minimal_stok' => 2,
            'deskripsi' => 'Suku cadang untuk pengujian arsip',
        ]);

        $pemesanan = Pemesanan::create([
            'id_pengguna' => $pelanggan->id,
            'id_vespa' => $vespa->id,
            'tanggal_pemesanan' => now()->toDateString(),
            'jam_pemesanan' => '10:00:00',
            'status' => Pemesanan::STATUS_SELESAI,
            'status_pembayaran' => Pemesanan::PAYMENT_STATUS_PAID,
            'completed_at' => now(),
            'paid_at' => now(),
            'total_harga' => 175000,
        ]);

        $dataLayananPemesanan = [
            'id_pemesanan' => $pemesanan->id,
            'id_layanan' => $layanan->id,
            'nama_layanan_saat_ini' => $layanan->nama_layanan,
            'harga_saat_pesan' => $layanan->harga,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Kolom warisan masih ada pada skema SQLite pengujian.
        if (Schema::hasColumn('layanan_pemesanan', 'booking_id')) {
            $dataLayananPemesanan['booking_id'] = $pemesanan->id;
        }
        if (Schema::hasColumn('layanan_pemesanan', 'service_id')) {
            $dataLayananPemesanan['service_id'] = $layanan->id;
        }

        DB::table('layanan_pemesanan')->insert($dataLayananPemesanan);

        ItemPemesanan::create([
            'id_pemesanan' => $pemesanan->id,
            'id_suku_cadang' => $sukuCadang->id,
            'nama_suku_cadang_saat_ini' => $sukuCadang->nama_suku_cadang,
            'jumlah' => 1,
            'harga_saat_ini' => $sukuCadang->harga_jual,
        ]);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/layanan/{$layanan->id}")->assertOk();
        $this->deleteJson("/api/admin/inventori/{$sukuCadang->id}")->assertOk();

        $this->assertSoftDeleted('layanan', ['id' => $layanan->id]);
        $this->assertSoftDeleted('suku_cadang', ['id' => $sukuCadang->id]);
        $this->assertDatabaseHas('layanan_pemesanan', [
            'id_pemesanan' => $pemesanan->id,
            'id_layanan' => $layanan->id,
        ]);
        $this->assertDatabaseHas('item_pemesanan', [
            'id_pemesanan' => $pemesanan->id,
            'id_suku_cadang' => $sukuCadang->id,
        ]);

        $this->assertNull(Layanan::find($layanan->id));
        $this->assertNull(SukuCadang::find($sukuCadang->id));

        $riwayat = $pemesanan->fresh()->load(['layanan', 'itemPemesanan.sukuCadang']);
        $this->assertSame('Servis Besar', $riwayat->layanan->first()->pivot->nama_layanan_saat_ini);
        $this->assertSame('Busi', $riwayat->itemPemesanan->first()->sukuCadang->nama_suku_cadang);
        $this->assertSame(175000, (int) $riwayat->total_harga);

        Sanctum::actingAs($pemilik);
        $this->getJson('/api/pemilik/layanan-terpopuler?month='.now()->month.'&year='.now()->year)
            ->assertOk()
            ->assertJsonPath('data.0.nama_layanan', 'Servis Besar');
        $this->getJson('/api/pemilik/suku-cadang-terlaris?month='.now()->month.'&year='.now()->year)
            ->assertOk()
            ->assertJsonPath('data.0.nama_barang', 'Busi');

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/laporan/keuangan?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('ringkasan.total_pendapatan', 175000)
            ->assertJsonPath('pemesanan.0.layanan.0.pivot.nama_layanan_saat_ini', 'Servis Besar')
            ->assertJsonPath('pemesanan.0.item_pemesanan.0.nama_suku_cadang_saat_ini', 'Busi');
    }

    private function buatPengguna(string $role): User
    {
        $data = [
            'nama' => ucfirst($role).' Pengujian',
            'email' => $role.'-'.uniqid().'@example.test',
            'no_telepon' => '081234567890',
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
}
