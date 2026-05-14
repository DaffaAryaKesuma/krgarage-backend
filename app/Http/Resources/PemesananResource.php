<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PemesananResource extends JsonResource {
    public function toArray(Request $request): array {
        $data = parent::toArray($request);
        
        // Pastikan field status_pembayaran selalu ada
        $data['status_pembayaran'] = $this->status_pembayaran ?? null;
        $data['status'] = $this->status ?? null;
        
        return $data;
    }
}
