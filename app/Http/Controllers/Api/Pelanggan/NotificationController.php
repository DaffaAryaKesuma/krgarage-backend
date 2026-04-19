<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Menampilkan semua notifikasi milik pengguna yang sedang login.
     */
    public function index()
    {
        $daftarNotifikasi = Notification::where('id_pengguna', Auth::id())
            ->with('pemesanan')
            ->orderBy('created_at', 'desc')
            ->get();

        $jumlahBelumDibaca = Notification::where('id_pengguna', Auth::id())
            ->belumDibaca()
            ->count();

        return response()->json([
            'notifikasi'          => $daftarNotifikasi,
            'jumlah_belum_dibaca' => $jumlahBelumDibaca,
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function tandaiDibaca($id)
    {
        $notifikasi = Notification::where('id_pengguna', Auth::id())
            ->findOrFail($id);

        $notifikasi->tandaiDibaca();

        return response()->json(['message' => 'Notifikasi berhasil ditandai sebagai sudah dibaca']);
    }

    /**
     * Tandai semua notifikasi sebagai sudah dibaca.
     */
    public function tandaiSemuaDibaca()
    {
        Notification::where('id_pengguna', Auth::id())
            ->belumDibaca()
            ->update(['sudah_dibaca' => true]);

        return response()->json(['message' => 'Semua notifikasi berhasil ditandai sebagai sudah dibaca']);
    }
}