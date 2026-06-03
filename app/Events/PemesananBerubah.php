<?php

namespace App\Events;

use App\Models\Pemesanan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PemesananBerubah implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $aksi,
        public ?int $idPemesanan = null,
        public ?int $idPengguna = null,
        public ?int $idMekanik = null,
    ) {
    }

    public static function dariPemesanan(Pemesanan $pemesanan, string $aksi): self
    {
        return new self(
            $aksi,
            $pemesanan->id,
            $pemesanan->id_pengguna,
            $pemesanan->id_mekanik,
        );
    }

    public function broadcastOn(): Channel
    {
        return new Channel('krgarage-status');
    }

    public function broadcastAs(): string
    {
        return 'pemesanan.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'aksi' => $this->aksi,
            'id_pemesanan' => $this->idPemesanan,
            'id_pengguna' => $this->idPengguna,
            'id_mekanik' => $this->idMekanik,
        ];
    }
}
