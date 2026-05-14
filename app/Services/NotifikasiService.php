<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\SukuCadang;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailUpdateStatusPemesanan;

class NotifikasiService
{
    /**
     * Ambil daftar akun pemilik (termasuk alias role legacy).
     */
    private function ambilDaftarPemilik()
    {
        return User::query()
            ->whereIn('role', ['pemilik', 'owner'])
            ->get(['id']);
    }

    /**
     * Kirim email pembaruan status ke pelanggan
     */
    private function kirimEmailStatusUpdate(Pemesanan $pemesanan, string $judul, string $pesan): void
    {
        try {
            // Pastikan relasi yang dibutuhkan untuk email dimuat
            $pemesanan->loadMissing('pengguna', 'vespa');
            
            if ($pemesanan->pengguna && $pemesanan->pengguna->email) {
                Mail::to($pemesanan->pengguna->email)->send(new EmailUpdateStatusPemesanan($pemesanan, $judul, $pesan));
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
    ): Notifikasi {
        return Notifikasi::create([
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
    public function notifikasiPemesananSelesai(Pemesanan $pemesanan): void
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
    public function notifikasiPemesananDikonfirmasi(Pemesanan $pemesanan): void
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
    public function notifikasiPemesananDiproses(Pemesanan $pemesanan): void
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
    public function notifikasiPemesananDibatalkan(Pemesanan $pemesanan): void
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
    public function notifikasiAdminPemesananBaru(Pemesanan $pemesanan, User $pelanggan): void
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
    public function notifikasiMekanikDitugaskan(Pemesanan $pemesanan, User $mekanik): void
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
    public function notifikasiPemesananDiperbarui(Pemesanan $pemesanan, string $pesan): void
    {
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            'booking_updated',
            'Pemesanan Diperbarui',
            $pesan,
            $pemesanan->id
        );
    }

    /**
     * Notifikasi ke pemilik saat pembayaran sebuah pemesanan diterima.
     */
    public function notifikasiPemilikPembayaranDiterima(Pemesanan $pemesanan): void
    {
        if ($pemesanan->status_pembayaran !== Pemesanan::PAYMENT_STATUS_PAID) {
            return;
        }

        $daftarPemilik = $this->ambilDaftarPemilik();
        if ($daftarPemilik->isEmpty()) {
            return;
        }

        $pemesanan->loadMissing('pengguna');

        $namaPelanggan = $pemesanan->pengguna->nama ?? 'Pelanggan';
        $totalPembayaran = 'Rp' . number_format((float) ($pemesanan->total_harga ?? 0), 0, ',', '.');
        $judul = 'Pembayaran Diterima';
        $pesan = "Pemesanan {$pemesanan->kode_pemesanan} dari {$namaPelanggan} telah lunas ({$totalPembayaran}).";

        foreach ($daftarPemilik as $pemilik) {
            $sudahPernahTerkirimKePemilik = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TYPE_PAYMENT_RECEIVED)
                ->where('id_pemesanan', $pemesanan->id)
                ->exists();

            if ($sudahPernahTerkirimKePemilik) {
                continue;
            }

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TYPE_PAYMENT_RECEIVED,
                    $judul,
                    $pesan,
                    $pemesanan->id
                );
            } catch (\Throwable $e) {
                \Log::error('Gagal membuat notifikasi pembayaran owner: ' . $e->getMessage(), [
                    'id_pemesanan' => $pemesanan->id,
                    'id_pemilik' => $pemilik->id,
                ]);
            }
        }
    }

    /**
     * Notifikasi ke pemilik saat stok suku cadang melewati batas minimal.
     */
    public function notifikasiPemilikStokMenipis(SukuCadang $sukuCadang, ?int $stokSebelum = null): void
    {
        if ((int) $sukuCadang->jumlah_stok > (int) $sukuCadang->batas_minimal_stok) {
            return;
        }

        if ($stokSebelum !== null && $stokSebelum <= (int) $sukuCadang->batas_minimal_stok) {
            return;
        }

        $daftarPemilik = $this->ambilDaftarPemilik();
        if ($daftarPemilik->isEmpty()) {
            return;
        }

        $judul = 'Stok Menipis';
        $pesan = "Stok {$sukuCadang->nama_suku_cadang} menipis ({$sukuCadang->jumlah_stok} tersisa, batas minimal {$sukuCadang->batas_minimal_stok}).";

        foreach ($daftarPemilik as $pemilik) {
            $sudahAdaNotifikasiSerupa = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TYPE_LOW_STOCK)
                ->where('judul', $judul)
                ->where('pesan', $pesan)
                ->where('created_at', '>=', now()->subHours(12))
                ->exists();

            if ($sudahAdaNotifikasiSerupa) {
                continue;
            }

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TYPE_LOW_STOCK,
                    $judul,
                    $pesan,
                    null
                );
            } catch (\Throwable $e) {
                \Log::error('Gagal membuat notifikasi stok menipis owner: ' . $e->getMessage(), [
                    'id_suku_cadang' => $sukuCadang->id,
                    'id_pemilik' => $pemilik->id,
                ]);
            }
        }
    }

    /**
     * Sinkronisasi notifikasi pembayaran owner untuk pemesanan lunas yang belum sempat terkirim.
     */
    public function sinkronkanNotifikasiPembayaranPemilik(User $pemilik, int $batasHari = 30): void
    {
        $role = strtolower((string) ($pemilik->role ?? ''));
        if (!in_array($role, ['pemilik', 'owner'], true)) {
            return;
        }

        $batasWaktu = now()->subDays(max(1, $batasHari));

        $daftarPemesananLunas = Pemesanan::query()
            ->where('status', Pemesanan::STATUS_SELESAI)
            ->where('status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
            ->where('updated_at', '>=', $batasWaktu)
            ->with('pengguna:id,nama')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        foreach ($daftarPemesananLunas as $pemesanan) {
            $sudahAda = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TYPE_PAYMENT_RECEIVED)
                ->where('id_pemesanan', $pemesanan->id)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            $namaPelanggan = $pemesanan->pengguna->nama ?? 'Pelanggan';
            $totalPembayaran = 'Rp' . number_format((float) ($pemesanan->total_harga ?? 0), 0, ',', '.');
            $judul = 'Pembayaran Diterima';
            $pesan = "Pemesanan {$pemesanan->kode_pemesanan} dari {$namaPelanggan} telah lunas ({$totalPembayaran}).";

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TYPE_PAYMENT_RECEIVED,
                    $judul,
                    $pesan,
                    $pemesanan->id
                );
            } catch (\Throwable $e) {
                \Log::error('Gagal sinkronisasi notifikasi pembayaran owner: ' . $e->getMessage(), [
                    'id_pemesanan' => $pemesanan->id,
                    'id_pemilik' => $pemilik->id,
                ]);
            }
        }
    }

    /**
     * Sinkronisasi notifikasi stok menipis owner untuk suku cadang yang saat ini sudah di bawah batas.
     */
    public function sinkronkanNotifikasiStokMenipisPemilik(User $pemilik): void
    {
        $role = strtolower((string) ($pemilik->role ?? ''));
        if (!in_array($role, ['pemilik', 'owner'], true)) {
            return;
        }

        $daftarSukuCadangStokMenipis = SukuCadang::query()
            ->whereRaw('jumlah_stok <= batas_minimal_stok')
            ->orderBy('jumlah_stok')
            ->limit(100)
            ->get();

        foreach ($daftarSukuCadangStokMenipis as $sukuCadang) {
            $judul = 'Stok Menipis';
            $pesan = "Stok {$sukuCadang->nama_suku_cadang} menipis ({$sukuCadang->jumlah_stok} tersisa, batas minimal {$sukuCadang->batas_minimal_stok}).";

            $sudahAda = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TYPE_LOW_STOCK)
                ->where('judul', $judul)
                ->where('pesan', $pesan)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TYPE_LOW_STOCK,
                    $judul,
                    $pesan,
                    null
                );
            } catch (\Throwable $e) {
                \Log::error('Gagal sinkronisasi notifikasi stok menipis owner: ' . $e->getMessage(), [
                    'id_suku_cadang' => $sukuCadang->id,
                    'id_pemilik' => $pemilik->id,
                ]);
            }
        }
    }
}

