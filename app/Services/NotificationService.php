<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Booking;
use App\Models\User;

class NotificationService
{
    /**
     * Buat notifikasi untuk pengguna tertentu.
     */
    public function buatNotifikasi(
        int $idPengguna,
        string $tipe,
        string $judul,
        string $pesan,
        ?int $idPemesanan = null,
        bool $sudahDibaca = false
    ): Notification {
        return Notification::create([
            'id_pengguna'  => $idPengguna,
            'tipe'         => $tipe,
            'judul'        => $judul,
            'pesan'        => $pesan,
            'id_pemesanan' => $idPemesanan,
            'sudah_dibaca' => $sudahDibaca,
        ]);
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan selesai.
     */
    public function notifikasiPemesananSelesai(Booking $pemesanan): void
    {
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_completed',
            'Servis Selesai',
            "Servis untuk pemesanan {$pemesanan->kode_pemesanan} telah selesai. Terima kasih telah menggunakan layanan KRGarage!",
            $pemesanan->id
        );
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan dikonfirmasi.
     */
    public function notifikasiPemesananDikonfirmasi(Booking $pemesanan): void
    {
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_confirmed',
            'Pemesanan Dikonfirmasi',
            "Pemesanan {$pemesanan->kode_pemesanan} telah dikonfirmasi. Silakan datang ke bengkel untuk mengantar Vespa Anda.",
            $pemesanan->id
        );
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan sedang diproses.
     */
    public function notifikasiPemesananDiproses(Booking $pemesanan): void
    {
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_in_progress',
            'Servis Dimulai',
            "Servis untuk pemesanan {$pemesanan->kode_pemesanan} telah dimulai.",
            $pemesanan->id
        );
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan dibatalkan.
     */
    public function notifikasiPemesananDibatalkan(Booking $pemesanan): void
    {
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_cancelled',
            'Pemesanan Dibatalkan',
            "Pemesanan {$pemesanan->kode_pemesanan} telah dibatalkan.",
            $pemesanan->id
        );
    }

    /**
     * Notifikasi ke semua admin saat ada pemesanan baru.
     */
    public function notifikasiAdminPemesananBaru(Booking $pemesanan, User $pelanggan): void
    {
        $daftarAdmin = User::admin()->get();

        foreach ($daftarAdmin as $admin) {
            $this->buatNotifikasi(
                $admin->id,
                'booking_confirmed',
                'Pemesanan Baru',
                "Pemesanan baru #{$pemesanan->kode_pemesanan} dari {$pelanggan->nama}.",
                $pemesanan->id,
                false
            );
        }
    }

    /**
     * Notifikasi ke mekanik saat ditugaskan ke sebuah pemesanan.
     */
    public function notifikasiMekanikDitugaskan(Booking $pemesanan, User $mekanik): void
    {
        $this->buatNotifikasi(
            $mekanik->id,
            'booking_assigned',
            'Pemesanan Baru Ditugaskan',
            "Anda telah ditugaskan untuk menangani pemesanan {$pemesanan->kode_pemesanan}.",
            $pemesanan->id
        );
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan dihapus.
     */
    public function notifikasiPemesananDihapus(int $idPengguna, string $kodePemesanan): void
    {
        $this->buatNotifikasi(
            $idPengguna,
            'booking_deleted',
            'Pemesanan Dihapus',
            "Pemesanan {$kodePemesanan} telah dihapus oleh admin.",
            null
        );
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan diperbarui.
     */
    public function notifikasiPemesananDiperbarui(Booking $pemesanan, string $pesan): void
    {
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_updated',
            'Pemesanan Diperbarui',
            $pesan,
            $pemesanan->id
        );
    }
}