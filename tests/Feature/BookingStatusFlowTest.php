<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Notification;
use App\Models\Sparepart;
use App\Models\User;
use App\Models\Vespa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_move_booking_to_in_progress_then_mechanic_completes_and_stock_only_deducted_once(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_PENDING);

        $admin = $skenario['admin'];
        $mekanik = $skenario['mekanik'];
        $pemesanan = $skenario['pemesanan'];
        $sukuCadang = $skenario['suku_cadang'];

        $stokAwal = $sukuCadang->jumlah_stok;

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_IN_PROGRESS,
        ])
            ->assertOk()
            ->assertJsonPath('pemesanan.status', Booking::STATUS_IN_PROGRESS);

        Sanctum::actingAs($mekanik);
        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_COMPLETED,
            'catatan_mekanik' => 'Pekerjaan selesai, keluhan utama sudah ditangani.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_COMPLETED)
            ->assertJsonPath('data.catatan_mekanik', 'Pekerjaan selesai, keluhan utama sudah ditangani.');

        $sukuCadang->refresh();
        $this->assertSame($stokAwal - 1, $sukuCadang->jumlah_stok);

        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_COMPLETED,
            'catatan_mekanik' => 'Percobaan update ulang setelah selesai.',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Status pemesanan yang sudah selesai atau dibatalkan tidak dapat diubah lagi.');

        $sukuCadang->refresh();
        $this->assertSame($stokAwal - 1, $sukuCadang->jumlah_stok);
    }

    public function test_mechanic_cannot_update_status_to_non_completed_values(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_IN_PROGRESS);

        $mekanik = $skenario['mekanik'];
        $pemesanan = $skenario['pemesanan'];

        Sanctum::actingAs($mekanik);

        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_CONFIRMED,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'The selected status is invalid.');

        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_IN_PROGRESS,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'The selected status is invalid.');
    }

    public function test_mechanic_can_only_complete_booking_when_status_is_in_progress(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_CONFIRMED);

        $mekanik = $skenario['mekanik'];
        $pemesanan = $skenario['pemesanan'];

        Sanctum::actingAs($mekanik);
        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_COMPLETED,
            'catatan_mekanik' => 'Sudah dicek, namun status belum in progress.',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Mekanik hanya dapat menyelesaikan pemesanan yang berstatus sedang dikerjakan.');
    }

    public function test_mechanic_must_fill_note_when_completing_booking(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_IN_PROGRESS);

        $mekanik = $skenario['mekanik'];
        $pemesanan = $skenario['pemesanan'];

        Sanctum::actingAs($mekanik);
        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_COMPLETED,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.catatan_mekanik.0', 'The catatan mekanik field is required.');
    }

    public function test_admin_can_cancel_booking_and_mechanic_cannot_modify_cancelled_booking(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_IN_PROGRESS);

        $admin = $skenario['admin'];
        $mekanik = $skenario['mekanik'];
        $pemesanan = $skenario['pemesanan'];
        $sukuCadang = $skenario['suku_cadang'];
        $itemPemesanan = $skenario['item_pemesanan'];

        $stokAwal = $sukuCadang->jumlah_stok;

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_CANCELLED,
        ])
            ->assertOk()
            ->assertJsonPath('pemesanan.status', Booking::STATUS_CANCELLED);

        Sanctum::actingAs($mekanik);
        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_COMPLETED,
            'catatan_mekanik' => 'Mencoba ubah status booking yang sudah dibatalkan.',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Status pemesanan yang sudah selesai atau dibatalkan tidak dapat diubah lagi.');

        $this->postJson("/api/mekanik/pemesanan/{$pemesanan->id}/tambah-suku-cadang", [
            'id_suku_cadang' => $sukuCadang->id,
            'jumlah' => 1,
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Pemesanan yang sudah selesai atau dibatalkan tidak dapat dimodifikasi.');

        $this->deleteJson("/api/mekanik/pemesanan/{$pemesanan->id}/item/{$itemPemesanan->id}")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Pemesanan yang sudah selesai atau dibatalkan tidak dapat dimodifikasi.');

        $sukuCadang->refresh();
        $this->assertSame($stokAwal, $sukuCadang->jumlah_stok);
        $this->assertDatabaseHas('item_pemesanan', ['id' => $itemPemesanan->id]);
    }

    public function test_owner_receives_low_stock_notification_when_completion_crosses_minimum_threshold(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_IN_PROGRESS);

        $owner = $this->buatPengguna('pemilik');
        $mekanik = $skenario['mekanik'];
        $pemesanan = $skenario['pemesanan'];
        $sukuCadang = $skenario['suku_cadang'];
        $itemPemesanan = $skenario['item_pemesanan'];

        $itemPemesanan->update(['jumlah' => 9]);

        Sanctum::actingAs($mekanik);
        $this->putJson("/api/mekanik/pemesanan/{$pemesanan->id}/status", [
            'status' => Booking::STATUS_COMPLETED,
            'catatan_mekanik' => 'Pekerjaan selesai dan stok terpakai besar.',
        ])->assertOk();

        $sukuCadang->refresh();
        $this->assertSame(1, $sukuCadang->jumlah_stok);

        $this->assertDatabaseHas('notifikasi', [
            'id_pengguna' => $owner->id,
            'tipe' => Notification::TYPE_LOW_STOCK,
        ]);
    }

    public function test_owner_receives_payment_notification_when_admin_marks_booking_as_paid(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_COMPLETED);

        $owner = $this->buatPengguna('pemilik');
        $admin = $skenario['admin'];
        $pemesanan = $skenario['pemesanan'];

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/pemesanan/{$pemesanan->id}/status-pembayaran", [
            'status_pembayaran' => Booking::PAYMENT_STATUS_PAID,
        ])
            ->assertOk()
            ->assertJsonPath('pemesanan.status_pembayaran', Booking::PAYMENT_STATUS_PAID);

        $this->assertDatabaseHas('notifikasi', [
            'id_pengguna' => $owner->id,
            'tipe' => Notification::TYPE_PAYMENT_RECEIVED,
            'id_pemesanan' => $pemesanan->id,
        ]);
    }

    public function test_owner_notification_index_backfills_missed_paid_booking_notifications(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_COMPLETED);

        $owner = $this->buatPengguna('pemilik');
        $pemesanan = $skenario['pemesanan'];

        $pemesanan->status_pembayaran = Booking::PAYMENT_STATUS_PAID;
        $pemesanan->save();

        $this->assertDatabaseMissing('notifikasi', [
            'id_pengguna' => $owner->id,
            'tipe' => Notification::TYPE_PAYMENT_RECEIVED,
            'id_pemesanan' => $pemesanan->id,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/notifikasi')->assertOk();

        $this->assertDatabaseHas('notifikasi', [
            'id_pengguna' => $owner->id,
            'tipe' => Notification::TYPE_PAYMENT_RECEIVED,
            'id_pemesanan' => $pemesanan->id,
        ]);
    }

    public function test_owner_notification_index_backfills_current_low_stock_spareparts(): void
    {
        $skenario = $this->siapkanSkenarioPemesanan(Booking::STATUS_COMPLETED);

        $owner = $this->buatPengguna('pemilik');
        $sukuCadang = $skenario['suku_cadang'];

        $sukuCadang->jumlah_stok = 1;
        $sukuCadang->batas_minimal_stok = 2;
        $sukuCadang->save();

        $pesan = "Stok {$sukuCadang->nama_suku_cadang} menipis ({$sukuCadang->jumlah_stok} tersisa, batas minimal {$sukuCadang->batas_minimal_stok}).";

        $this->assertDatabaseMissing('notifikasi', [
            'id_pengguna' => $owner->id,
            'tipe' => Notification::TYPE_LOW_STOCK,
            'judul' => 'Stok Menipis',
            'pesan' => $pesan,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/notifikasi')->assertOk();

        $this->assertDatabaseHas('notifikasi', [
            'id_pengguna' => $owner->id,
            'tipe' => Notification::TYPE_LOW_STOCK,
            'judul' => 'Stok Menipis',
            'pesan' => $pesan,
        ]);
    }

    /**
     * @return array{admin: User, mekanik: User, pemesanan: Booking, suku_cadang: Sparepart, item_pemesanan: BookingItem}
     */
    private function siapkanSkenarioPemesanan(string $statusAwal): array
    {
        $admin = $this->buatPengguna('admin');
        $mekanik = $this->buatPengguna('mekanik');
        $pelanggan = $this->buatPengguna('pelanggan');

        $vespa = Vespa::create([
            'id_pengguna' => $pelanggan->id,
            'model' => 'Vespa Sprint',
            'tahun_produksi' => 2022,
            'plat_nomor' => 'BTEST' . strtoupper(substr(uniqid(), -4)),
            'jeda_hari_servis' => 30,
        ]);

        $pemesanan = Booking::create([
            'id_pengguna' => $pelanggan->id,
            'id_mekanik' => $mekanik->id,
            'id_vespa' => $vespa->id,
            'tanggal_pemesanan' => now()->toDateString(),
            'jam_pemesanan' => '10:00:00',
            'status' => $statusAwal,
            'catatan_pelanggan' => 'Catatan pengujian',
            'total_harga' => 0,
        ]);

        $sukuCadang = Sparepart::create([
            'nama_suku_cadang' => 'Suku Cadang Test',
            'kategori' => 'Oli',
            'jumlah_stok' => 10,
            'harga_beli' => 50000,
            'harga_jual' => 75000,
            'batas_minimal_stok' => 2,
            'deskripsi' => 'Data dummy untuk feature test.',
        ]);

        $itemPemesanan = BookingItem::create([
            'id_pemesanan' => $pemesanan->id,
            'id_suku_cadang' => $sukuCadang->id,
            'jumlah' => 1,
            'harga_saat_ini' => $sukuCadang->harga_jual,
        ]);

        return [
            'admin' => $admin,
            'mekanik' => $mekanik,
            'pemesanan' => $pemesanan,
            'suku_cadang' => $sukuCadang,
            'item_pemesanan' => $itemPemesanan,
        ];
    }

    private function buatPengguna(string $role): User
    {
        $nama = ucfirst($role) . ' Test ' . strtoupper(substr(uniqid(), -4));

        $dataPengguna = [
            'nama' => $nama,
            'email' => $role . '.' . strtolower(substr(uniqid(), -6)) . '@example.test',
            'no_telepon' => '08' . str_pad((string) random_int(1, 9999999999), 10, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'role' => $role,
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pengguna', 'name')) {
            $dataPengguna['name'] = $nama;
        }

        $idPengguna = DB::table('pengguna')->insertGetId($dataPengguna);

        return User::findOrFail($idPengguna);
    }
}
