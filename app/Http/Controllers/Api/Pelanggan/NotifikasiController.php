<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Services\NotifikasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends Controller
{
    protected $layananNotifikasi;

    public function __construct(NotifikasiService $layananNotifikasi)
    {
        $this->layananNotifikasi = $layananNotifikasi;
    }

    /**
     * Menampilkan semua notifikasi milik pengguna yang sedang login.
     */
    public function index()
    {
        $penggunaLogin = Auth::user();

        if ($penggunaLogin) {
            $role = strtolower((string) ($penggunaLogin->role ?? ''));

            if (in_array($role, ['pemilik', 'owner'], true)) {
                $this->layananNotifikasi->sinkronkanNotifikasiPembayaranPemilik($penggunaLogin);
                $this->layananNotifikasi->sinkronkanNotifikasiStokMenipisPemilik($penggunaLogin);
            }
        }

        $daftarNotifikasi = Notifikasi::where('id_pengguna', Auth::id())
            ->with('pemesanan')
            ->orderBy('created_at', 'desc')
            ->get();

        $jumlahBelumDibaca = Notifikasi::where('id_pengguna', Auth::id())
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
        $notifikasi = Notifikasi::where('id_pengguna', Auth::id())
            ->findOrFail($id);

        $notifikasi->tandaiDibaca();

        return response()->json(['message' => 'Notifikasi berhasil ditandai sebagai sudah dibaca']);
    }

    /**
     * Tandai semua notifikasi sebagai sudah dibaca.
     */
    public function tandaiSemuaDibaca()
    {
        Notifikasi::where('id_pengguna', Auth::id())
            ->belumDibaca()
            ->update(['sudah_dibaca' => true]);

        return response()->json(['message' => 'Semua notifikasi berhasil ditandai sebagai sudah dibaca']);
    }
}

