<?php

namespace Database\Seeders;

use App\Models\KategoriSukuCadang;
use App\Models\SukuCadang;
use Illuminate\Database\Seeder;

class SukuCadangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Baut & Mur',
            'Bearing',
            'Engkol',
            'Kabel',
            'Kampas Kopling',
            'Kampas Rem',
            'Karburator',
            'Karet',
            'Kopling',
            'Kruk As',
            'Oli',
            'Paking',
            'Pengapian',
            'Piston',
            'Seal',
            'Transmisi',
        ];

        $kategoriMap = [];
        foreach ($categories as $category) {
            $kategoriMap[$category] = KategoriSukuCadang::firstOrCreate([
                'nama' => $category,
            ])->id;
        }

        $spareparts = [
            ['nama_suku_cadang' => 'Mur kopling', 'id_kategori' => $kategoriMap['Baut & Mur'], 'jumlah_stok' => 43, 'harga_beli' => 15000, 'harga_jual' => 20000, 'batas_minimal_stok' => 25, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Mur magnit', 'id_kategori' => $kategoriMap['Baut & Mur'], 'jumlah_stok' => 43, 'harga_beli' => 15000, 'harga_jual' => 20000, 'batas_minimal_stok' => 25, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Bearing kruk as besar', 'id_kategori' => $kategoriMap['Bearing'], 'jumlah_stok' => 9, 'harga_beli' => 250000, 'harga_jual' => 300000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Bearing kruk as kecil', 'id_kategori' => $kategoriMap['Bearing'], 'jumlah_stok' => 12, 'harga_beli' => 250000, 'harga_jual' => 300000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'As engkol px', 'id_kategori' => $kategoriMap['Engkol'], 'jumlah_stok' => 17, 'harga_beli' => 250000, 'harga_jual' => 300000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'As engkol sprint/super', 'id_kategori' => $kategoriMap['Engkol'], 'jumlah_stok' => 15, 'harga_beli' => 250000, 'harga_jual' => 300000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Gigi engkol px', 'id_kategori' => $kategoriMap['Engkol'], 'jumlah_stok' => 18, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Gigi engkol super', 'id_kategori' => $kategoriMap['Engkol'], 'jumlah_stok' => 17, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Gigi engkol XL', 'id_kategori' => $kategoriMap['Engkol'], 'jumlah_stok' => 20, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Sarung tali', 'id_kategori' => $kategoriMap['Kabel'], 'jumlah_stok' => 93, 'harga_beli' => 15000, 'harga_jual' => 20000, 'batas_minimal_stok' => 50, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Tali gas', 'id_kategori' => $kategoriMap['Kabel'], 'jumlah_stok' => 51, 'harga_beli' => 10000, 'harga_jual' => 15000, 'batas_minimal_stok' => 30, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Tali gigi', 'id_kategori' => $kategoriMap['Kabel'], 'jumlah_stok' => 51, 'harga_beli' => 10000, 'harga_jual' => 15000, 'batas_minimal_stok' => 30, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Tali kopling', 'id_kategori' => $kategoriMap['Kabel'], 'jumlah_stok' => 51, 'harga_beli' => 10000, 'harga_jual' => 15000, 'batas_minimal_stok' => 30, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Kampas kopling Excel', 'id_kategori' => $kategoriMap['Kampas Kopling'], 'jumlah_stok' => 19, 'harga_beli' => 80000, 'harga_jual' => 100000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Kampas kopling px', 'id_kategori' => $kategoriMap['Kampas Kopling'], 'jumlah_stok' => 17, 'harga_beli' => 75000, 'harga_jual' => 95000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Kampas rem belakang px', 'id_kategori' => $kategoriMap['Kampas Rem'], 'jumlah_stok' => 17, 'harga_beli' => 75000, 'harga_jual' => 95000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Kampas rem belakang super', 'id_kategori' => $kategoriMap['Kampas Rem'], 'jumlah_stok' => 17, 'harga_beli' => 75000, 'harga_jual' => 95000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Karburator 20/20', 'id_kategori' => $kategoriMap['Karburator'], 'jumlah_stok' => 9, 'harga_beli' => 700000, 'harga_jual' => 850000, 'batas_minimal_stok' => 5, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Karburator 24/24', 'id_kategori' => $kategoriMap['Karburator'], 'jumlah_stok' => 9, 'harga_beli' => 785000, 'harga_jual' => 950000, 'batas_minimal_stok' => 5, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Karburator 26/26', 'id_kategori' => $kategoriMap['Karburator'], 'jumlah_stok' => 6, 'harga_beli' => 815000, 'harga_jual' => 980000, 'batas_minimal_stok' => 5, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Karet bantalan engkol', 'id_kategori' => $kategoriMap['Karet'], 'jumlah_stok' => 14, 'harga_beli' => 5000, 'harga_jual' => 10000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Karet hawa karburator', 'id_kategori' => $kategoriMap['Karet'], 'jumlah_stok' => 14, 'harga_beli' => 35000, 'harga_jual' => 45000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Gobangan Excel', 'id_kategori' => $kategoriMap['Kopling'], 'jumlah_stok' => 19, 'harga_beli' => 60000, 'harga_jual' => 75000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Gobangan px', 'id_kategori' => $kategoriMap['Kopling'], 'jumlah_stok' => 17, 'harga_beli' => 60000, 'harga_jual' => 75000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Tiang Sokar', 'id_kategori' => $kategoriMap['Kruk As'], 'jumlah_stok' => 5, 'harga_beli' => 180000, 'harga_jual' => 220000, 'batas_minimal_stok' => 5, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Oli Motul 2T', 'id_kategori' => $kategoriMap['Oli'], 'jumlah_stok' => 4, 'harga_beli' => 150000, 'harga_jual' => 170000, 'batas_minimal_stok' => 5, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Paking set Excel', 'id_kategori' => $kategoriMap['Paking'], 'jumlah_stok' => 19, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Paking set px', 'id_kategori' => $kategoriMap['Paking'], 'jumlah_stok' => 18, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Paking set super', 'id_kategori' => $kategoriMap['Paking'], 'jumlah_stok' => 17, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Platina px', 'id_kategori' => $kategoriMap['Pengapian'], 'jumlah_stok' => 18, 'harga_beli' => 80000, 'harga_jual' => 100000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Platina super', 'id_kategori' => $kategoriMap['Pengapian'], 'jumlah_stok' => 17, 'harga_beli' => 80000, 'harga_jual' => 100000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Pulser', 'id_kategori' => $kategoriMap['Pengapian'], 'jumlah_stok' => 15, 'harga_beli' => 130000, 'harga_jual' => 160000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Spul api CDI', 'id_kategori' => $kategoriMap['Pengapian'], 'jumlah_stok' => 18, 'harga_beli' => 150000, 'harga_jual' => 185000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Spul api platina', 'id_kategori' => $kategoriMap['Pengapian'], 'jumlah_stok' => 16, 'harga_beli' => 100000, 'harga_jual' => 125000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Piston', 'id_kategori' => $kategoriMap['Piston'], 'jumlah_stok' => 5, 'harga_beli' => 150000, 'harga_jual' => 185000, 'batas_minimal_stok' => 5, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Seal engkol', 'id_kategori' => $kategoriMap['Seal'], 'jumlah_stok' => 14, 'harga_beli' => 10000, 'harga_jual' => 15000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Seal kruk as besar', 'id_kategori' => $kategoriMap['Seal'], 'jumlah_stok' => 15, 'harga_beli' => 100000, 'harga_jual' => 125000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Seal kruk as kecil', 'id_kategori' => $kategoriMap['Seal'], 'jumlah_stok' => 15, 'harga_beli' => 100000, 'harga_jual' => 125000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Seal tuas kopling', 'id_kategori' => $kategoriMap['Seal'], 'jumlah_stok' => 14, 'harga_beli' => 5000, 'harga_jual' => 10000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Seal tutup kopling', 'id_kategori' => $kategoriMap['Seal'], 'jumlah_stok' => 14, 'harga_beli' => 8000, 'harga_jual' => 15000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Simpang 4 Excel', 'id_kategori' => $kategoriMap['Transmisi'], 'jumlah_stok' => 19, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Simpang 4 px', 'id_kategori' => $kategoriMap['Transmisi'], 'jumlah_stok' => 18, 'harga_beli' => 50000, 'harga_jual' => 65000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
            ['nama_suku_cadang' => 'Simpang 4 super lama', 'id_kategori' => $kategoriMap['Transmisi'], 'jumlah_stok' => 17, 'harga_beli' => 60000, 'harga_jual' => 75000, 'batas_minimal_stok' => 10, 'deskripsi' => null],
        ];

        foreach ($spareparts as $sparepart) {
            SukuCadang::updateOrCreate(
                ['nama_suku_cadang' => $sparepart['nama_suku_cadang']],
                $sparepart
            );
        }

        $this->command->info('SukuCadang seeder completed: ' . count($spareparts) . ' items synced.');
    }
}
