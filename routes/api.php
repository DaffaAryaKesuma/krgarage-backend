<?php

// Request dipakai pada endpoint ping untuk mengambil user yang sedang login.
use Illuminate\Http\Request;
// Route adalah facade Laravel untuk mendefinisikan endpoint API.
use Illuminate\Support\Facades\Route;
// Controller publik/auth.
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LayananController;
// Controller pelanggan.
use App\Http\Controllers\Api\Pelanggan\VespaController;
use App\Http\Controllers\Api\Pelanggan\PemesananController;
use App\Http\Controllers\Api\Pelanggan\NotifikasiController;
// Controller admin.
use App\Http\Controllers\Api\Admin\AdminPemesananController;
use App\Http\Controllers\Api\Admin\AdminLayananController;
use App\Http\Controllers\Api\Admin\AdminLaporanKeuanganController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminKategoriSukuCadangController;
use App\Http\Controllers\Api\Admin\AdminSukuCadangController;
use App\Http\Controllers\Api\Admin\KaryawanController;
// Controller mekanik dan pemilik.
use App\Http\Controllers\Api\Mekanik\MekanikDashboardController;
use App\Http\Controllers\Api\Pemilik\PemilikController;

// Rute publik: bisa diakses tanpa token login.
Route::post('/daftar', [AuthController::class, 'daftar']);
Route::post('/masuk', [AuthController::class, 'masuk']);
// Layanan publik ditampilkan di landing page dan form pemesanan.
Route::get('/layanan', [LayananController::class, 'index']);

// Rute untuk semua pengguna terautentikasi, role apa pun.
Route::middleware('auth:sanctum')->group(function () {
    // Logout menghapus token aktif milik user.
    Route::post('/keluar', [AuthController::class, 'keluar']);
    
    // Ping endpoint untuk update last_seen user.
    Route::post('/ping', function (Request $request) {
        if ($user = $request->user()) {
            try {
                // timestamps dimatikan agar updated_at tidak ikut berubah hanya karena ping.
                $user->timestamps = false;
                $user->last_seen = \Carbon\Carbon::now();
                $user->save();
            } catch (\Exception $e) {
                \Log::warning('Ping error: ' . $e->getMessage());
            }
        }
        return response()->json(['status' => 'ok']);
    });

    // Rute notifikasi untuk semua pengguna login, data tetap dipagari per user di controller.
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::post('/notifikasi/{id}/tandai-dibaca', [NotifikasiController::class, 'tandaiDibaca']);
    Route::post('/notifikasi/tandai-semua-dibaca', [NotifikasiController::class, 'tandaiSemuaDibaca']);

    // Rute Profil
    Route::put('/profil', [AuthController::class, 'perbaruiProfil']);
    Route::put('/profil/password', [AuthController::class, 'gantiPassword']);
});

// Rute untuk pelanggan: wajib login dan role harus pelanggan.
Route::middleware(['auth:sanctum', 'role:pelanggan'])->group(function () {
    // Rute untuk vespa milik pelanggan
    Route::get('/vespa-saya', [VespaController::class, 'index']);
    Route::post('/vespa-saya', [VespaController::class, 'store']);
    Route::put('/vespa-saya/{vespa}', [VespaController::class, 'update']);
    Route::delete('/vespa-saya/{vespa}', [VespaController::class, 'destroy']);
    Route::get('/vespa-saya/perlu-servis', [VespaController::class, 'perluServis']);

    // Rute untuk pemesanan pelanggan
    // index/show/store/batalkan dipakai halaman riwayat, detail, dan form pemesanan pelanggan.
    Route::get('/pemesanan', [PemesananController::class, 'index']);
    // cek-slot dipakai frontend untuk menandai jam yang penuh.
    Route::get('/pemesanan/cek-slot', [PemesananController::class, 'cekSlot']);
    Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show']);
    Route::post('/pemesanan', [PemesananController::class, 'store']);
    Route::post('/pemesanan/{pemesanan}/batalkan', [PemesananController::class, 'batalkan']);

});

// Rute untuk admin: semua endpoint di dalam group punya prefix /admin.
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // CRUD dan aksi pemesanan admin.
    Route::get('/pemesanan', [AdminPemesananController::class, 'index']);
    Route::get('/pemesanan/{pemesanan}', [AdminPemesananController::class, 'show']);
    Route::patch('/pemesanan/{pemesanan}/status', [AdminPemesananController::class, 'updateStatus']);
    Route::patch('/pemesanan/{pemesanan}/status-pembayaran', [AdminPemesananController::class, 'updatePaymentStatus']);

    // CRUD layanan servis.
    Route::post('/layanan', [AdminLayananController::class, 'store']);
    Route::put('/layanan/{service}', [AdminLayananController::class, 'update']);
    Route::delete('/layanan/{service}', [AdminLayananController::class, 'destroy']);

    // Data dashboard admin.
    Route::get('/dashboard/statistik', [AdminDashboardController::class, 'statistik']);
    Route::get('/dashboard/pemesanan-terbaru', [AdminDashboardController::class, 'pemesananTerbaru']);

    // Rute untuk laporan keuangan
    Route::get('/laporan/keuangan', [AdminLaporanKeuanganController::class, 'index']);

    // Rute untuk manajemen inventori (suku cadang)
    Route::get('/inventori/kategori', [AdminKategoriSukuCadangController::class, 'index']);
    Route::post('/inventori/kategori', [AdminKategoriSukuCadangController::class, 'store']);
    Route::get('/inventori', [AdminSukuCadangController::class, 'index']);
    Route::get('/inventori/stok-menipis', [AdminSukuCadangController::class, 'peringatanStokMenipis']);
    Route::post('/inventori', [AdminSukuCadangController::class, 'store']);
    Route::get('/inventori/{sukuCadang}', [AdminSukuCadangController::class, 'show']);
    Route::put('/inventori/{sukuCadang}', [AdminSukuCadangController::class, 'update']);
    Route::delete('/inventori/{sukuCadang}', [AdminSukuCadangController::class, 'destroy']);
    // tambah-stok juga mencatat riwayat restok untuk laporan pengeluaran.
    Route::post('/inventori/{sukuCadang}/tambah-stok', [AdminSukuCadangController::class, 'tambahStok']);

    // Rute untuk suku cadang pada pemesanan
    Route::post('/pemesanan/{pemesanan}/tambah-suku-cadang', [AdminPemesananController::class, 'tambahSukuCadang']);
    Route::delete('/pemesanan/{pemesanan}/item/{idItemPemesanan}', [AdminPemesananController::class, 'hapusSukuCadang']);

    // Rute untuk penugasan mekanik
    Route::get('/mekanik', [AdminPemesananController::class, 'daftarMekanik']);
    Route::patch('/pemesanan/{pemesanan}/tugaskan-mekanik', [AdminPemesananController::class, 'tugaskanMekanik']);

    // Rute untuk manajemen karyawan (admin & mekanik)
    Route::get('/karyawan', [KaryawanController::class, 'index']);
    Route::post('/karyawan', [KaryawanController::class, 'store']);
    Route::put('/karyawan/{id}', [KaryawanController::class, 'update']);
    Route::delete('/karyawan/{id}', [KaryawanController::class, 'destroy']);
});

// Rute untuk mekanik: dipakai dashboard mekanik.
Route::middleware(['auth:sanctum', 'role:mekanik'])->prefix('mekanik')->group(function () {
    // Daftar pekerjaan aktif/riwayat mekanik.
    Route::get('/pemesanan', [MekanikDashboardController::class, 'index']);
    // Mekanik dapat update status pekerjaan.
    Route::put('/pemesanan/{pemesanan}/status', [MekanikDashboardController::class, 'updateStatus']);
    // Mekanik dapat menambah/menghapus suku cadang pada pemesanan yang sedang dikerjakan.
    Route::post('/pemesanan/{pemesanan}/tambah-suku-cadang', [MekanikDashboardController::class, 'tambahSukuCadang']);
    Route::delete('/pemesanan/{pemesanan}/item/{itemPemesanan}', [MekanikDashboardController::class, 'hapusSukuCadang']);
    Route::get('/suku-cadang', [MekanikDashboardController::class, 'daftarSukuCadangTersedia']);
});

// Rute untuk pemilik: monitoring dan laporan tanpa mengubah data operasional.
Route::middleware(['auth:sanctum', 'role:pemilik'])->prefix('pemilik')->group(function () {
    // Statistik dan aktivitas dashboard pemilik.
    Route::get('/statistik', [PemilikController::class, 'statistik']);
    Route::get('/pemesanan-terbaru', [PemilikController::class, 'pemesananTerbaru']);
    // Laporan keuangan pemilik.
    Route::get('/tren-pendapatan', [PemilikController::class, 'trenPendapatan']);
    Route::get('/transaksi', [PemilikController::class, 'transaksi']);
    Route::get('/pengeluaran-restok', [PemilikController::class, 'pengeluaranRestok']);
    // Analisa layanan, suku cadang, stok, dan performa.
    Route::get('/layanan-terpopuler', [PemilikController::class, 'layananTerpopuler']);
    Route::get('/suku-cadang-terlaris', [PemilikController::class, 'sukuCadangTerlaris']);
    Route::get('/stok-menipis', [PemilikController::class, 'stokMenipis']);
    Route::get('/log-aktivitas', [PemilikController::class, 'logAktivitasAdmin']);
    Route::get('/log-aktivitas-admin', [PemilikController::class, 'logAktivitasAdmin']);
    Route::get('/mekanik-online', [PemilikController::class, 'getOnlineMechanicsCount']);
    Route::get('/ringkasan', [PemilikController::class, 'ringkasan']);
    Route::get('/metrik-keuangan', [PemilikController::class, 'metrikKeuangan']);
});
