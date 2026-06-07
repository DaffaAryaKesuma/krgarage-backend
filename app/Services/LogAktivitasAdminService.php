<?php

namespace App\Services;

use App\Models\LogAktivitasAdmin;
use App\Models\User;

class LogAktivitasAdminService
{
    /**
     * Catat aktivitas penting pengguna untuk audit pemilik.
     *
     * @param array<string, mixed>|null $dataSebelum
     * @param array<string, mixed>|null $dataSesudah
     */
    public function catat(
        ?User $aktor,
        string $aksi,
        string $modul,
        ?string $targetTipe = null,
        ?int $targetId = null,
        ?string $targetLabel = null,
        ?string $deskripsi = null,
        ?array $dataSebelum = null,
        ?array $dataSesudah = null,
    ): LogAktivitasAdmin {
        return LogAktivitasAdmin::create([
            'id_admin' => $aktor?->role === 'admin' ? $aktor->id : null,
            'id_pengguna' => $aktor?->id,
            'role_pengguna' => $aktor?->role,
            'aksi' => $aksi,
            'modul' => $modul,
            'target_tipe' => $targetTipe,
            'target_id' => $targetId,
            'target_label' => $targetLabel,
            'deskripsi' => $deskripsi,
            'data_sebelum' => $dataSebelum,
            'data_sesudah' => $dataSesudah,
        ]);
    }

    /**
     * Ambil hanya field yang berubah agar log mudah dibaca.
     *
     * @param array<string, mixed> $sebelum
     * @param array<string, mixed> $sesudah
     * @return array{sebelum: array<string, mixed>, sesudah: array<string, mixed>}
     */
    public function perubahan(array $sebelum, array $sesudah): array
    {
        $dataSebelum = [];
        $dataSesudah = [];

        foreach ($sesudah as $field => $nilaiSesudah) {
            $nilaiSebelum = $sebelum[$field] ?? null;

            if ((string) $nilaiSebelum === (string) $nilaiSesudah) {
                continue;
            }

            $dataSebelum[$field] = $nilaiSebelum;
            $dataSesudah[$field] = $nilaiSesudah;
        }

        return [
            'sebelum' => $dataSebelum,
            'sesudah' => $dataSesudah,
        ];
    }
}
