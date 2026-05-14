<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\RoleNormalizer;

class UserResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'email' => $this->email,
            'role' => RoleNormalizer::normalizeOrNull($this->role) ?? $this->role,
            'no_telepon' => $this->no_telepon,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
