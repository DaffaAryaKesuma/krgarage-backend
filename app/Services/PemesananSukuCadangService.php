<?php

namespace App\Services;

// Model yang berhubungan dengan suku cadang pada pemesanan.
use App\Models\Pemesanan;
use App\Models\ItemPemesanan;
use App\Models\SukuCadang;
// Collection dipakai sebagai tipe return daftar suku cadang.
use Illuminate\Database\Eloquent\Collection;

// Service ini menyatukan logika suku cadang pemesanan agar tidak duplikat di controller admin/mekanik.
class PemesananSukuCadangService
{
    /**
     * Ambil data pemesanan beserta semua relasinya.
     */
    public function ambilPemesananDenganRelasi(Pemesanan $pemesanan): Pemesanan
    {
        // Relasi ini dibutuhkan halaman detail pemesanan.
        return $pemesanan->load(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang']);
    }

    /**
     * Tambahkan suku cadang ke dalam pemesanan.
     */
    public function tambahSukuCadang(Pemesanan $pemesanan, int $idSukuCadang, int $jumlah): array
    {
        // Cari suku cadang, throw 404 jika tidak ditemukan.
        $sukuCadang = SukuCadang::findOrFail($idSukuCadang);

        // Cek apakah stok mencukupi.
        if ($sukuCadang->jumlah_stok < $jumlah) {
            return [
                'success' => false,
                'message' => 'Stok suku cadang tidak mencukupi. Stok tersedia: ' . $sukuCadang->jumlah_stok
            ];
        }

        // Cek apakah suku cadang sudah pernah ditambahkan ke pemesanan ini.
        $itemSudahAda = ItemPemesanan::where('id_pemesanan', $pemesanan->id)
            ->where('id_suku_cadang', $sukuCadang->id)
            ->first();

        if ($itemSudahAda) {
            // Perbarui jumlah jika item sudah ada.
            $itemSudahAda->jumlah += $jumlah;
            $itemSudahAda->save();
            $itemPemesanan = $itemSudahAda;
        } else {
            // Buat item pemesanan baru.
            $itemPemesanan = ItemPemesanan::create([
                'id_pemesanan'  => $pemesanan->id,
                'id_suku_cadang' => $sukuCadang->id,
                // Snapshot nama dan harga agar riwayat tetap konsisten meski master berubah.
                'nama_suku_cadang_saat_ini' => $sukuCadang->nama_suku_cadang,
                'jumlah'        => $jumlah,
                'harga_saat_ini' => $sukuCadang->harga_jual,
            ]);
        }

        // Catatan: stok baru dikurangi saat pemesanan berubah menjadi Selesai.

        // Hitung ulang total harga layanan + suku cadang.
        $pemesanan->recalculateTotalHarga();

        return [
            'success' => true,
            'message' => 'Suku cadang berhasil ditambahkan',
            'data'    => $itemPemesanan->load('sukuCadang'),
        ];
    }

    /**
     * Hapus suku cadang dari pemesanan.
     */
    public function hapusSukuCadang(Pemesanan $pemesanan, int $idItemPemesanan): array
    {
        // Cari item melalui relasi pemesanan agar tidak bisa menghapus item milik pemesanan lain.
        $itemPemesanan = $pemesanan->itemPemesanan()->find($idItemPemesanan);

        if (!$itemPemesanan) {
            return [
                'success'     => false,
                'message'     => 'Item pemesanan tidak ditemukan',
                'status_code' => 404,
            ];
        }

        // Tidak bisa hapus suku cadang jika pemesanan sudah selesai.
        if ($pemesanan->status === Pemesanan::STATUS_SELESAI) {
            return [
                'success'     => false,
                'message'     => 'Tidak dapat menghapus suku cadang dari pemesanan yang sudah selesai',
                'status_code' => 400,
            ];
        }

        // Hapus item suku cadang dari pemesanan.
        $itemPemesanan->delete();

        // Hitung ulang total harga setelah item dihapus.
        $pemesanan->recalculateTotalHarga();

        return [
            'success' => true,
            'message' => 'Suku cadang berhasil dihapus',
        ];
    }

    /**
     * Ambil daftar suku cadang yang tersedia (stok > 0).
     */
    public function ambilSukuCadangTersedia(): Collection
    {
        // Scope tersedia biasanya berarti stok lebih dari 0.
        return SukuCadang::tersedia()
            ->orderBy('nama_suku_cadang', 'asc')
            ->get();
    }

    /**
     * Kurangi stok suku cadang saat pemesanan selesai.
     */
    public function kurangiStokSukuCadang(Pemesanan $pemesanan): array
    {
        // Ambil semua item suku cadang pada pemesanan.
        $daftarItem = $pemesanan->itemPemesanan()->with('sukuCadang')->get();
        // Ringkasan perubahan stok dikembalikan untuk kebutuhan notifikasi stok menipis.
        $ringkasanPerubahanStok = [];

        foreach ($daftarItem as $item) {
            if ($item->sukuCadang) {
                $sukuCadang = $item->sukuCadang;
                $stokSebelum = (int) $sukuCadang->jumlah_stok;
                // Stok sesudah dikurangi jumlah item yang dipakai.
                $stokSesudah = $stokSebelum - (int) $item->jumlah;

                // Mencegah stok menjadi negatif.
                if ($stokSesudah < 0) {
                    $stokSesudah = 0;
                }

                // Simpan stok baru ke master suku cadang.
                $sukuCadang->jumlah_stok = $stokSesudah;
                $sukuCadang->save();

                $ringkasanPerubahanStok[] = [
                    'suku_cadang' => $sukuCadang->fresh(),
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                ];
            }
        }

        return $ringkasanPerubahanStok;
    }
}
