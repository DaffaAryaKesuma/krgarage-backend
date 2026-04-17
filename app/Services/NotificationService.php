<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingStatusUpdateMail;

class NotificationService
{
    /**
     * Kirim email pembaruan status ke pelanggan
     */
    private function kirimEmailStatusUpdate(Booking $pemesanan, string $judul, string $pesan): void
    {
        try {
            // Pastikan relasi yang dibutuhkan untuk email dimuat
            $pemesanan->loadMissing('pengguna', 'vespa');
            
            if ($pemesanan->pengguna && $pemesanan->pengguna->email) {
                Mail::to($pemesanan->pengguna->email)->send(new BookingStatusUpdateMail($pemesanan, $judul, $pesan));
            }
        } catch (\Exception $e) {
            \Log::error('Gagal mengirim email status update: ' . $e->getMessage());
        }
    }

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
        $judul = 'Servis Selesai';
        $pesan = "Servis untuk pemesanan {$pemesanan->kode_pemesanan} telah selesai. Silakan datang ke bengkel untuk mengambil Vespa dan melakukan pembayaran.";
        
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_completed',
            $judul,
            $pesan,
            $pemesanan->id
        );

        $this->kirimEmailStatusUpdate($pemesanan, $judul, $pesan);
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan dikonfirmasi.
     */
    public function notifikasiPemesananDikonfirmasi(Booking $pemesanan): void
    {
        $judul = 'Pemesanan Dikonfirmasi';
        $pesan = "Pemesanan {$pemesanan->kode_pemesanan} telah dikonfirmasi. Silakan datang ke bengkel untuk mengantar Vespa Anda.";

        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_confirmed',
            $judul,
            $pesan,
            $pemesanan->id
        );

        $this->kirimEmailStatusUpdate($pemesanan, $judul, $pesan);
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan sedang diproses.
     */
    public function notifikasiPemesananDiproses(Booking $pemesanan): void
    {
        $judul = 'Servis Dimulai';
        $pesan = "Servis untuk pemesanan {$pemesanan->kode_pemesanan} telah dimulai dan sedang dikerjakan oleh mekanik kami.";

        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_in_progress',
            $judul,
            $pesan,
            $pemesanan->id
        );

        $this->kirimEmailStatusUpdate($pemesanan, $judul, $pesan);
    }

    /**
     * Notifikasi ke pelanggan saat pemesanan dibatalkan.
     */
    public function notifikasiPemesananDibatalkan(Booking $pemesanan): void
    {
        $judul = 'Pemesanan Dibatalkan';
        $pesan = "Pemesanan {$pemesanan->kode_pemesanan} telah dibatalkan.";

        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_cancelled',
            $judul,
            $pesan,
            $pemesanan->id
        );

        $this->kirimEmailStatusUpdate($pemesanan, $judul, $pesan);
    }

    /**
     * Notifikasi ke semua admin saat ada pemesanan baru.
     */
    public function notifikasiAdminPemesananBaru(Booking $pemesanan, User $pelanggan): void
    {
        $daftarAdmin = User::admin()->get();

        $namaPelanggan = $pelanggan->nama;
        // Opsional: Hilangkan kata "pelanggan" jika pengguna kebetulan menulis namanya "pelanggan daffa" dsb
        $namaPelanggan = trim(str_ireplace('pelanggan', '', $namaPelanggan));

        foreach ($daftarAdmin as $admin) {
            $this->buatNotifikasi(
                $admin->id,
                'booking_confirmed',
                'Pemesanan Baru',
                "Pemesanan baru #{$pemesanan->kode_pemesanan} dari {$namaPelanggan}.",
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