<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Resource ini mengatur bentuk JSON data Vespa.
class VespaResource extends JsonResource {
    // Saat ini semua atribut Vespa dikirim apa adanya dari model.
    public function toArray(Request $request): array {
        // parent::toArray() mengambil field model, relasi yang sudah diload, dan appends.
        return parent::toArray($request);
    }
}
