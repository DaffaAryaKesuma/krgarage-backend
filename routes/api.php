<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\Customer\VespaController;
use App\Http\Controllers\Api\Customer\BookingController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\AdminServiceController;
use App\Http\Controllers\Api\Admin\AdminFinancialReportController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminSparepartController;
use App\Http\Controllers\Api\Mechanic\MechanicDashboardController;
use App\Http\Controllers\Api\Owner\OwnerController;

// Rute publik: registrasi dan login
Route::post('/register', [AuthController::class, 'daftar']);
Route::post('/login', [AuthController::class, 'masuk']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rute untuk vespa milik pelanggan
    Route::get('/my-vespas', [VespaController::class, 'index']);
    Route::post('/my-vespas', [VespaController::class, 'store']);
    Route::put('/my-vespas/{vespa}', [VespaController::class, 'update']);
    Route::delete('/my-vespas/{vespa}', [VespaController::class, 'destroy']);
    Route::get('/vespas/due-service', [VespaController::class, 'perluServis']);

    // Rute untuk pemesanan pelanggan
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/check-slots', [BookingController::class, 'cekSlot']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{pemesanan}/cancel', [BookingController::class, 'batalkan']);

    // Rute untuk notifikasi
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'tandaiDibaca']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'tandaiSemuaDibaca']);
});

// Rute untuk admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::get('/bookings/{pemesanan}', [AdminBookingController::class, 'show']);
    Route::patch('/bookings/{pemesanan}/status', [AdminBookingController::class, 'updateStatus']);

    Route::post('/services', [AdminServiceController::class, 'store']);
    Route::put('/services/{service}', [AdminServiceController::class, 'update']);
    Route::delete('/services/{service}', [AdminServiceController::class, 'destroy']);

    Route::get('/dashboard/stats', [AdminDashboardController::class, 'statistik']);
    Route::get('/dashboard/recent-bookings', [AdminDashboardController::class, 'pemesananTerbaru']);
    Route::get('/dashboard/overview', [AdminDashboardController::class, 'ringkasan']);

    // Rute untuk laporan keuangan
    Route::get('/reports/financial', [AdminFinancialReportController::class, 'index']);

    // Rute untuk manajemen inventori (suku cadang)
    Route::get('/inventory', [AdminSparepartController::class, 'index']);
    Route::get('/inventory/available', [AdminSparepartController::class, 'daftarSukuCadangTersedia']);
    Route::get('/inventory/low-stock', [AdminSparepartController::class, 'peringatanStokMenipis']);
    Route::post('/inventory', [AdminSparepartController::class, 'store']);
    Route::get('/inventory/{sukuCadang}', [AdminSparepartController::class, 'show']);
    Route::put('/inventory/{sukuCadang}', [AdminSparepartController::class, 'update']);
    Route::delete('/inventory/{sukuCadang}', [AdminSparepartController::class, 'destroy']);
    Route::post('/inventory/{sukuCadang}/restock', [AdminSparepartController::class, 'tambahStok']);

    // Rute untuk suku cadang pada pemesanan
    Route::post('/bookings/{pemesanan}/add-sparepart', [AdminBookingController::class, 'tambahSukuCadang']);
    Route::delete('/bookings/{pemesanan}/items/{idItemPemesanan}', [AdminBookingController::class, 'hapusSukuCadang']);

    // Rute untuk penugasan mekanik
    Route::get('/mechanics', [AdminBookingController::class, 'daftarMekanik']);
    Route::patch('/bookings/{pemesanan}/assign-mechanic', [AdminBookingController::class, 'tugaskanMekanik']);
});

// Rute untuk mekanik
Route::middleware(['auth:sanctum', 'role:mekanik'])->prefix('mechanic')->group(function () {
    Route::get('/bookings', [MechanicDashboardController::class, 'index']);
    Route::get('/bookings/{pemesanan}', [MechanicDashboardController::class, 'show']);
    Route::get('/dashboard', [MechanicDashboardController::class, 'index']);
    Route::get('/history', [MechanicDashboardController::class, 'riwayat']);
    Route::put('/bookings/{pemesanan}/status', [MechanicDashboardController::class, 'updateStatus']);
    Route::post('/bookings/{pemesanan}/add-sparepart', [MechanicDashboardController::class, 'tambahSukuCadang']);
    Route::delete('/bookings/{pemesanan}/items/{itemPemesanan}', [MechanicDashboardController::class, 'hapusSukuCadang']);
    Route::get('/spareparts', [MechanicDashboardController::class, 'daftarSukuCadangTersedia']);
});

// Rute untuk owner (monitoring)
Route::middleware(['auth:sanctum', 'role:owner'])->prefix('owner')->group(function () {
    Route::get('/stats', [OwnerController::class, 'statistik']);
    Route::get('/recent-bookings', [OwnerController::class, 'pemesananTerbaru']);
    Route::get('/revenue-trend', [OwnerController::class, 'trenPendapatan']);
    Route::get('/transactions', [OwnerController::class, 'transaksi']);
    Route::get('/top-services', [OwnerController::class, 'layananTerpopuler']);
    Route::get('/top-spareparts', [OwnerController::class, 'sukuCadangTerlaris']);
    Route::get('/low-stock', [OwnerController::class, 'stokMenipis']);
});