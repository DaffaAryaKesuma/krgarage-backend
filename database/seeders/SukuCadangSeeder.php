<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SukuCadang;
use App\Models\KategoriSukuCadang;

class SukuCadangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Oli', 'Busi', 'Kampas Rem', 'Kopling', 'Kabel', 'Filter', 'Bearing', 'Aki', 'Karburator'
        ];

        $kategoriMap = [];
        foreach ($categories as $cat) {
            $kategoriModel = KategoriSukuCadang::firstOrCreate(['nama' => $cat]);
            $kategoriMap[$cat] = $kategoriModel->id;
        }

        $spareparts = [
            [
                'nama_suku_cadang' => 'Oli 2-Tak Castrol Power1',
                'id_kategori' => $kategoriMap['Oli'],
                'jumlah_stok' => 25,
                'harga_beli' => 35000,
                'harga_jual' => 50000,
                'batas_minimal_stok' => 10,
                'deskripsi' => 'Oli 2-tak premium untuk Vespa klasik 1 liter'
            ],
            [
                'nama_suku_cadang' => 'Busi NGK BR8ES',
                'id_kategori' => $kategoriMap['Busi'],
                'jumlah_stok' => 20,
                'harga_beli' => 18000,
                'harga_jual' => 28000,
                'batas_minimal_stok' => 10,
                'deskripsi' => 'Busi NGK standar untuk Vespa 2-tak'
            ],
            [
                'nama_suku_cadang' => 'Kampas Rem Depan',
                'id_kategori' => $kategoriMap['Kampas Rem'],
                'jumlah_stok' => 3,
                'harga_beli' => 45000,
                'harga_jual' => 70000,
                'batas_minimal_stok' => 5,
                'deskripsi' => 'Kampas rem depan Vespa klasik - Low Stock Alert!'
            ],
            [
                'nama_suku_cadang' => 'Kampas Kopling Set',
                'id_kategori' => $kategoriMap['Kopling'],
                'jumlah_stok' => 8,
                'harga_beli' => 85000,
                'harga_jual' => 130000,
                'batas_minimal_stok' => 5,
                'deskripsi' => 'Set kampas kopling lengkap untuk transmisi manual'
            ],
            [
                'nama_suku_cadang' => 'Kabel Gas Original',
                'id_kategori' => $kategoriMap['Kabel'],
                'jumlah_stok' => 2,
                'harga_beli' => 35000,
                'harga_jual' => 55000,
                'batas_minimal_stok' => 5,
                'deskripsi' => 'Kabel gas original Vespa - Low Stock Alert!'
            ],
            [
                'nama_suku_cadang' => 'Filter Udara Karburator',
                'id_kategori' => $kategoriMap['Filter'],
                'jumlah_stok' => 18,
                'harga_beli' => 25000,
                'harga_jual' => 40000,
                'batas_minimal_stok' => 8,
                'deskripsi' => 'Filter udara busa untuk karburator Vespa'
            ],
            [
                'nama_suku_cadang' => 'Kampas Rem Belakang',
                'id_kategori' => $kategoriMap['Kampas Rem'],
                'jumlah_stok' => 12,
                'harga_beli' => 40000,
                'harga_jual' => 65000,
                'batas_minimal_stok' => 5,
                'deskripsi' => 'Kampas rem belakang Vespa klasik'
            ],
            [
                'nama_suku_cadang' => 'Bearing Roda Depan',
                'id_kategori' => $kategoriMap['Bearing'],
                'jumlah_stok' => 15,
                'harga_beli' => 30000,
                'harga_jual' => 50000,
                'batas_minimal_stok' => 6,
                'deskripsi' => 'Bearing roda depan original'
            ],
            [
                'nama_suku_cadang' => 'Kabel Rem Depan',
                'id_kategori' => $kategoriMap['Kabel'],
                'jumlah_stok' => 6,
                'harga_beli' => 28000,
                'harga_jual' => 45000,
                'batas_minimal_stok' => 4,
                'deskripsi' => 'Kabel rem depan Vespa klasik'
            ],
            [
                'nama_suku_cadang' => 'Aki 6V 4Ah',
                'id_kategori' => $kategoriMap['Aki'],
                'jumlah_stok' => 4,
                'harga_beli' => 120000,
                'harga_jual' => 175000,
                'batas_minimal_stok' => 3,
                'deskripsi' => 'Aki 6 volt untuk Vespa klasik kering maintenance-free'
            ],
            [
                'nama_suku_cadang' => 'Karburator Dell\'Orto SI 20/20',
                'id_kategori' => $kategoriMap['Karburator'],
                'jumlah_stok' => 2,
                'harga_beli' => 450000,
                'harga_jual' => 650000,
                'batas_minimal_stok' => 2,
                'deskripsi' => 'Karburator Dell\'Orto original Vespa - Low Stock Alert!'
            ],
            [
                'nama_suku_cadang' => 'Kabel Kopling',
                'id_kategori' => $kategoriMap['Kabel'],
                'jumlah_stok' => 8,
                'harga_beli' => 32000,
                'harga_jual' => 50000,
                'batas_minimal_stok' => 5,
                'deskripsi' => 'Kabel kopling Vespa manual'
            ],
        ];

        foreach ($spareparts as $sparepart) {
            SukuCadang::create($sparepart);
        }

        $this->command->info('✅ SukuCadang seeder completed: 12 items created (Vespa 2-Tak)');
        $this->command->info('⚠️  Low stock items: Kampas Rem Depan (3), Kabel Gas (2), Karburator (2)');
    }
}


