<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\RoleNormalizer;

// Resource ini mengatur data user yang aman untuk dikirim ke frontend.
class UserResource extends JsonResource {
    // toArray() menentukan field apa saja yang muncul di response JSON.
    public function toArray(Request $request): array {
        return [
            // ID dipakai frontend sebagai identifier user.
            'id' => $this->id,
            // Nama dan email ditampilkan di profil/navbar/dashboard.
            'nama' => $this->nama,
            'email' => $this->email,
            // Role dinormalisasi agar variasi penulisan role tetap konsisten.
            'role' => RoleNormalizer::normalizeOrNull($this->role) ?? $this->role,
            // Nomor telepon dikirim untuk kebutuhan profil dan kontak.
            'no_telepon' => $this->no_telepon,
            // Timestamp berguna untuk audit sederhana kapan data dibuat/diubah.
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
