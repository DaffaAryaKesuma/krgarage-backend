<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LayananController;
use App\Http\Controllers\Api\Pelanggan\VespaController;
use App\Http\Controllers\Api\Pelanggan\PemesananController;
use App\Http\Controllers\Api\Pelanggan\NotifikasiController;
use App\Http\Controllers\Api\Admin\AdminPemesananController;
use App\Http\Controllers\Api\Admin\AdminLayananController;
use App\Http\Controllers\Api\Admin\AdminLaporanKeuanganController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminKategoriInventarisController;
use App\Http\Controllers\Api\Admin\AdminSukuCadangController;
use App\Http\Controllers\Api\Admin\KaryawanController;
use App\Http\Controllers\Api\Mekanik\MekanikDashboardController;
use App\Http\Controllers\Api\Pemilik\PemilikController;

// Rute publik: registrasi dan login
Route::post('/daftar', [AuthController::class, 'daftar']);
Route::post('/masuk', [AuthController::class, 'masuk']);
Route::get('/layanan', [LayananController::class, 'index']);

// Rute untuk semua pengguna terautentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/keluar', [AuthController::class, 'keluar']);
    
    // Ping endpoint untuk update last_seen user
    Route::post('/ping', function (Request $request) {
        if ($user = $request->user()) {
            try {
                $user->timestamps = false;
                $user->last_seen = \Carbon\Carbon::now();
                $user->save();
            } catch (\Exception $e) {
                \Log::warning('Ping error: ' . $e->getMessage());
            }
        }
        return response()->json(['status' => 'ok']);
    });

    // Rute notifikasi untuk semua pengguna login (data tetap dipagari per user di controller)
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::post('/notifikasi/{id}/tandai-dibaca', [NotifikasiController::class, 'tandaiDibaca']);
    Route::post('/notifikasi/tandai-semua-dibaca', [NotifikasiController::class, 'tandaiSemuaDibaca']);
});

// Rute untuk pelanggan
Route::middleware(['auth:sanctum', 'role:pelanggan'])->group(function () {
    // Rute untuk vespa milik pelanggan
    Route::get('/vespa-saya', [VespaController::class, 'index']);
    Route::post('/vespa-saya', [VespaController::class, 'store']);
    Route::put('/vespa-saya/{vespa}', [VespaController::class, 'update']);
    Route::delete('/vespa-saya/{vespa}', [VespaController::class, 'destroy']);
    Route::get('/vespa-saya/perlu-servis', [VespaController::class, 'perluServis']);

    // Rute untuk pemesanan pelanggan
    Route::get('/pemesanan', [PemesananController::class, 'index']);
    Route::get('/pemesanan/cek-slot', [PemesananController::class, 'cekSlot']);
    Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show']);
    Route::post('/pemesanan', [PemesananController::class, 'store']);
    Route::post('/pemesanan/{pemesanan}/batalkan', [PemesananController::class, 'batalkan']);

});

// Rute untuk admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/pemesanan', [AdminPemesananController::class, 'index']);
    Route::get('/pemesanan/{pemesanan}', [AdminPemesananController::class, 'show']);
    Route::patch('/pemesanan/{pemesanan}/status', [AdminPemesananController::class, 'updateStatus']);
    Route::patch('/pemesanan/{pemesanan}/status-pembayaran', [AdminPemesananController::class, 'updatePaymentStatus']);

    Route::post('/layanan', [AdminLayananController::class, 'store']);
    Route::put('/layanan/{service}', [AdminLayananController::class, 'update']);
    Route::delete('/layanan/{service}', [AdminLayananController::class, 'destroy']);

    Route::get('/dashboard/statistik', [AdminDashboardController::class, 'statistik']);
    Route::get('/dashboard/pemesanan-terbaru', [AdminDashboardController::class, 'pemesananTerbaru']);

    // Rute untuk laporan keuangan
    Route::get('/laporan/keuangan', [AdminLaporanKeuanganController::class, 'index']);

    // Rute untuk manajemen inventori (suku cadang)
    Route::get('/inventori/kategori', [AdminKategoriInventarisController::class, 'index']);
    Route::post('/inventori/kategori', [AdminKategoriInventarisController::class, 'store']);
    Route::get('/inventori', [AdminSukuCadangController::class, 'index']);
    Route::get('/inventori/stok-menipis', [AdminSukuCadangController::class, 'peringatanStokMenipis']);
    Route::post('/inventori', [AdminSukuCadangController::class, 'store']);
    Route::get('/inventori/{sukuCadang}', [AdminSukuCadangController::class, 'show']);
    Route::put('/inventori/{sukuCadang}', [AdminSukuCadangController::class, 'update']);
    Route::delete('/inventori/{sukuCadang}', [AdminSukuCadangController::class, 'destroy']);
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

// Rute untuk mekanik
Route::middleware(['auth:sanctum', 'role:mekanik'])->prefix('mekanik')->group(function () {
    Route::get('/pemesanan', [MekanikDashboardController::class, 'index']);
    Route::put('/pemesanan/{pemesanan}/status', [MekanikDashboardController::class, 'updateStatus']);
    Route::post('/pemesanan/{pemesanan}/tambah-suku-cadang', [MekanikDashboardController::class, 'tambahSukuCadang']);
    Route::delete('/pemesanan/{pemesanan}/item/{itemPemesanan}', [MekanikDashboardController::class, 'hapusSukuCadang']);
    Route::get('/suku-cadang', [MekanikDashboardController::class, 'daftarSukuCadangTersedia']);
});

// Rute untuk pemilik (monitoring)
Route::middleware(['auth:sanctum', 'role:pemilik'])->prefix('pemilik')->group(function () {
    Route::get('/statistik', [PemilikController::class, 'statistik']);
    Route::get('/pemesanan-terbaru', [PemilikController::class, 'pemesananTerbaru']);
    Route::get('/tren-pendapatan', [PemilikController::class, 'trenPendapatan']);
    Route::get('/transaksi', [PemilikController::class, 'transaksi']);
    Route::get('/layanan-terpopuler', [PemilikController::class, 'layananTerpopuler']);
    Route::get('/suku-cadang-terlaris', [PemilikController::class, 'sukuCadangTerlaris']);
    Route::get('/stok-menipis', [PemilikController::class, 'stokMenipis']);
    Route::get('/mekanik-online', [PemilikController::class, 'getOnlineMechanicsCount']);
});

