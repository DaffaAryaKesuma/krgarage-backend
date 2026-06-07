<?php

namespace App\Services;

// Model notifikasi, pemesanan, suku cadang, dan user.
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\SukuCadang;
use App\Models\User;
// Mail dipakai untuk email update status ke pelanggan.
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailUpdateStatusPemesanan;

// Service ini memusatkan pembuatan notifikasi agar controller tidak terlalu ramai.
class NotifikasiService
{
    /**
     * Ambil daftar akun pemilik (termasuk alias role legacy).
     */
    private function ambilDaftarPemilik()
    {
        // Role owner tetap didukung sebagai alias legacy.
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
            // Pastikan relasi yang dibutuhkan untuk email dimuat.
            $pemesanan->loadMissing('pengguna', 'vespa');
            
            // Email hanya dikirim jika pelanggan punya email.
            if ($pemesanan->pengguna && $pemesanan->pengguna->email) {
                $emailPelanggan = $pemesanan->pengguna->email;
                $pemesananUntukEmail = $pemesanan;

                app()->terminating(function () use ($emailPelanggan, $pemesananUntukEmail, $judul, $pesan) {
                    try {
                        Mail::to($emailPelanggan)->send(new EmailUpdateStatusPemesanan($pemesananUntukEmail, $judul, $pesan));
                    } catch (\Throwable $e) {
                        \Log::error('Gagal mengirim email status update: ' . $e->getMessage());
                    }
                });
            }
        } catch (\Exception $e) {
            // Gagal email tidak boleh menggagalkan proses utama.
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
        // Semua notifikasi disimpan ke tabel notifikasi.
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
        // Notifikasi utama untuk pelanggan.
        $judul = 'Servis Selesai';
        $pesan = "Servis untuk pemesanan {$pemesanan->kode_pemesanan} telah selesai. Silakan datang ke bengkel untuk mengambil Vespa dan melakukan pembayaran.";
        
        $this->buatNotifikasi(
            $pemesanan->id_pengguna,
            Notifikasi::TIPE_PEMESANAN_SELESAI,
            $judul,
            $pesan,
            $pemesanan->id
        );

        $this->kirimEmailStatusUpdate($pemesanan, $judul, $pesan);

        // Kirim notifikasi ke semua admin bahwa servis telah diselesaikan.
        $this->notifikasiAdminServisSelesai($pemesanan);
    }

    /**
     * Notifikasi ke semua admin saat servis selesai dikerjakan oleh mekanik/admin.
     */
    public function notifikasiAdminServisSelesai(Pemesanan $pemesanan): void
    {
        // Ambil semua admin untuk diberi notifikasi.
        $daftarAdmin = User::admin()->get();
        // Relasi mekanik dan pelanggan dibutuhkan untuk isi pesan.
        $pemesanan->loadMissing(['mekanik', 'pengguna']);
        $namaMekanik = isset($pemesanan->mekanik->nama) ? ucwords(strtolower($pemesanan->mekanik->nama)) : 'Mekanik';
        $namaPelanggan = isset($pemesanan->pengguna->nama) ? ucwords(strtolower($pemesanan->pengguna->nama)) : 'Pelanggan';

        // Buat satu notifikasi untuk setiap admin.
        foreach ($daftarAdmin as $admin) {
            $this->buatNotifikasi(
                $admin->id,
                Notifikasi::TIPE_PEMESANAN_SELESAI,
                'Servis Selesai Dikerjakan',
                "Servis pemesanan #{$pemesanan->kode_pemesanan} atas nama {$namaPelanggan} telah selesai dikerjakan oleh {$namaMekanik}. Siapkan invoice & pembayaran.",
                $pemesanan->id,
                false
            );
        }
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
            Notifikasi::TIPE_PEMESANAN_DIKONFIRMASI,
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
            Notifikasi::TIPE_PEMESANAN_DIPROSES,
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
            Notifikasi::TIPE_PEMESANAN_DIBATALKAN,
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
        // Semua admin perlu tahu ada pemesanan baru.
        $daftarAdmin = User::admin()->get();

        $namaPelanggan = $pelanggan->nama;
        // Opsional: Hilangkan kata "pelanggan" jika pengguna kebetulan menulis namanya "pelanggan daffa" dsb
        $namaPelanggan = trim(str_ireplace('pelanggan', '', $namaPelanggan));
        $namaPelanggan = ucwords(strtolower($namaPelanggan));

        // Buat notifikasi untuk masing-masing admin.
        foreach ($daftarAdmin as $admin) {
            $this->buatNotifikasi(
                $admin->id,
                Notifikasi::TIPE_PEMESANAN_DIKONFIRMASI,
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
            Notifikasi::TIPE_PEMESANAN_DITUGASKAN,
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
            Notifikasi::TIPE_PEMESANAN_DIHAPUS,
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
            Notifikasi::TIPE_PEMESANAN_DIPERBARUI,
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
        // Guard: hanya kirim jika status benar-benar Lunas.
        if ($pemesanan->status_pembayaran !== Pemesanan::PAYMENT_STATUS_PAID) {
            return;
        }

        // Ambil semua pemilik/owner.
        $daftarPemilik = $this->ambilDaftarPemilik();
        if ($daftarPemilik->isEmpty()) {
            return;
        }

        // Data pelanggan dipakai dalam isi pesan.
        $pemesanan->loadMissing('pengguna');

        $namaPelanggan = isset($pemesanan->pengguna->nama) ? ucwords(strtolower($pemesanan->pengguna->nama)) : 'Pelanggan';
        $totalPembayaran = 'Rp' . number_format((float) ($pemesanan->total_harga ?? 0), 0, ',', '.');
        $judul = 'Pembayaran Diterima';
        $pesan = "Pemesanan {$pemesanan->kode_pemesanan} dari {$namaPelanggan} telah lunas ({$totalPembayaran}).";

        foreach ($daftarPemilik as $pemilik) {
            // Cegah notifikasi pembayaran dobel untuk pemesanan yang sama.
            $sudahPernahTerkirimKePemilik = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TIPE_PEMBAYARAN_DITERIMA)
                ->where('id_pemesanan', $pemesanan->id)
                ->exists();

            if ($sudahPernahTerkirimKePemilik) {
                continue;
            }

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TIPE_PEMBAYARAN_DITERIMA,
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
        // Jika stok masih di atas batas minimal, tidak perlu notifikasi.
        if ((int) $sukuCadang->jumlah_stok > (int) $sukuCadang->batas_minimal_stok) {
            return;
        }

        // Jika sebelumnya sudah menipis, jangan spam notifikasi saat tetap di bawah batas.
        if ($stokSebelum !== null && $stokSebelum <= (int) $sukuCadang->batas_minimal_stok) {
            return;
        }

        // Ambil daftar pemilik yang menerima notifikasi.
        $daftarPemilik = $this->ambilDaftarPemilik();
        if ($daftarPemilik->isEmpty()) {
            return;
        }

        $judul = 'Stok Menipis';
        $pesan = "Stok {$sukuCadang->nama_suku_cadang} menipis ({$sukuCadang->jumlah_stok} tersisa, batas minimal {$sukuCadang->batas_minimal_stok}).";

        foreach ($daftarPemilik as $pemilik) {
            // Cegah notifikasi stok serupa berulang dalam 12 jam.
            $sudahAdaNotifikasiSerupa = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TIPE_STOK_MENIPIS)
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
                    Notifikasi::TIPE_STOK_MENIPIS,
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
        // Sinkronisasi hanya untuk role pemilik/owner.
        $role = strtolower((string) ($pemilik->role ?? ''));
        if (!in_array($role, ['pemilik', 'owner'], true)) {
            return;
        }

        // Batasi pemesanan lama agar proses sinkronisasi tidak terlalu berat.
        $batasWaktu = now()->subDays(max(1, $batasHari));

        // Ambil pemesanan selesai dan lunas dalam batas waktu.
        $daftarPemesananLunas = Pemesanan::query()
            ->where('status', Pemesanan::STATUS_SELESAI)
            ->where('status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
            ->where('updated_at', '>=', $batasWaktu)
            ->with('pengguna:id,nama')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        foreach ($daftarPemesananLunas as $pemesanan) {
            // Lewati jika notifikasi pembayaran untuk pemesanan ini sudah ada.
            $sudahAda = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TIPE_PEMBAYARAN_DITERIMA)
                ->where('id_pemesanan', $pemesanan->id)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            $namaPelanggan = isset($pemesanan->pengguna->nama) ? ucwords(strtolower($pemesanan->pengguna->nama)) : 'Pelanggan';
            $totalPembayaran = 'Rp' . number_format((float) ($pemesanan->total_harga ?? 0), 0, ',', '.');
            $judul = 'Pembayaran Diterima';
            $pesan = "Pemesanan {$pemesanan->kode_pemesanan} dari {$namaPelanggan} telah lunas ({$totalPembayaran}).";

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TIPE_PEMBAYARAN_DITERIMA,
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
        // Sinkronisasi hanya untuk role pemilik/owner.
        $role = strtolower((string) ($pemilik->role ?? ''));
        if (!in_array($role, ['pemilik', 'owner'], true)) {
            return;
        }

        // Ambil suku cadang yang saat ini sudah di bawah/sama dengan batas minimal.
        $daftarSukuCadangStokMenipis = SukuCadang::query()
            ->whereRaw('jumlah_stok <= batas_minimal_stok')
            ->orderBy('jumlah_stok')
            ->limit(100)
            ->get();

        foreach ($daftarSukuCadangStokMenipis as $sukuCadang) {
            $judul = 'Stok Menipis';
            $pesan = "Stok {$sukuCadang->nama_suku_cadang} menipis ({$sukuCadang->jumlah_stok} tersisa, batas minimal {$sukuCadang->batas_minimal_stok}).";

            // Hindari membuat notifikasi stok yang sama dua kali.
            $sudahAda = Notifikasi::query()
                ->where('id_pengguna', $pemilik->id)
                ->where('tipe', Notifikasi::TIPE_STOK_MENIPIS)
                ->where('judul', $judul)
                ->where('pesan', $pesan)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $this->buatNotifikasi(
                    $pemilik->id,
                    Notifikasi::TIPE_STOK_MENIPIS,
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
