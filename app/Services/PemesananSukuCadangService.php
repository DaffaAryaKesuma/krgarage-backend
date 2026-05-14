<?php

namespace App\Services;

use App\Models\Pemesanan;
use App\Models\ItemPemesanan;
use App\Models\SukuCadang;
use Illuminate\Database\Eloquent\Collection;

class PemesananSukuCadangService
{
    /**
     * Ambil data pemesanan beserta semua relasinya.
     */
    public function ambilPemesananDenganRelasi(Pemesanan $pemesanan): Pemesanan
    {
        return $pemesanan->load(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang']);
    }

    /**
     * Tambahkan suku cadang ke dalam pemesanan.
     */
    public function tambahSukuCadang(Pemesanan $pemesanan, int $idSukuCadang, int $jumlah): array
    {
        $sukuCadang = SukuCadang::findOrFail($idSukuCadang);

        // Cek apakah stok mencukupi
        if ($sukuCadang->jumlah_stok < $jumlah) {
            return [
                'success' => false,
                'message' => 'Stok suku cadang tidak mencukupi. Stok tersedia: ' . $sukuCadang->jumlah_stok
            ];
        }

        // Cek apakah suku cadang sudah pernah ditambahkan ke pemesanan ini
        $itemSudahAda = ItemPemesanan::where('id_pemesanan', $pemesanan->id)
            ->where('id_suku_cadang', $sukuCadang->id)
            ->first();

        if ($itemSudahAda) {
            // Perbarui jumlah jika sudah ada
            $itemSudahAda->jumlah += $jumlah;
            $itemSudahAda->save();
            $itemPemesanan = $itemSudahAda;
        } else {
            // Buat item pemesanan baru
            $itemPemesanan = ItemPemesanan::create([
                'id_pemesanan'  => $pemesanan->id,
                'id_suku_cadang' => $sukuCadang->id,
                'jumlah'        => $jumlah,
                'harga_saat_ini' => $sukuCadang->harga_jual,
            ]);
        }

        // Catatan: Stok akan dikurangi saat status pemesanan berubah menjadi 'Completed'

        // Hitung ulang total harga
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
        $itemPemesanan = $pemesanan->itemPemesanan()->find($idItemPemesanan);

        if (!$itemPemesanan) {
            return [
                'success'     => false,
                'message'     => 'Item pemesanan tidak ditemukan',
                'status_code' => 404,
            ];
        }

        // Tidak bisa hapus suku cadang jika pemesanan sudah selesai
        if ($pemesanan->status === Pemesanan::STATUS_SELESAI) {
            return [
                'success'     => false,
                'message'     => 'Tidak dapat menghapus suku cadang dari pemesanan yang sudah selesai',
                'status_code' => 400,
            ];
        }

        $itemPemesanan->delete();

        // Hitung ulang total harga
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
        return SukuCadang::tersedia()
            ->orderBy('nama_suku_cadang', 'asc')
            ->get();
    }

    /**
     * Kurangi stok suku cadang saat pemesanan selesai.
     */
    public function kurangiStokSukuCadang(Pemesanan $pemesanan): array
    {
        $daftarItem = $pemesanan->itemPemesanan()->with('sukuCadang')->get();
        $ringkasanPerubahanStok = [];

        foreach ($daftarItem as $item) {
            if ($item->sukuCadang) {
                $sukuCadang = $item->sukuCadang;
                $stokSebelum = (int) $sukuCadang->jumlah_stok;
                $stokSesudah = $stokSebelum - (int) $item->jumlah;

                // Mencegah stok menjadi negatif
                if ($stokSesudah < 0) {
                    $stokSesudah = 0;
                }

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

