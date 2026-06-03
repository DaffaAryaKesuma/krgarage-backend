<?php

namespace App\Services;

use App\Models\ItemPemesanan;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\RealtimeEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class RealtimeEventService
{
    public const EVENT_PEMESANAN_CHANGED = 'pemesanan.changed';
    public const EVENT_NOTIFIKASI_CHANGED = 'notifikasi.changed';

    public static function publishPemesananChanged(Pemesanan $pemesanan, string $action = 'changed'): void
    {
        self::publish(self::EVENT_PEMESANAN_CHANGED, $pemesanan, [
            'action' => $action,
            'id_pemesanan' => $pemesanan->id,
            'id_pengguna' => $pemesanan->id_pengguna,
            'id_mekanik' => $pemesanan->id_mekanik,
        ]);
    }

    public static function publishItemPemesananChanged(ItemPemesanan $itemPemesanan, string $action = 'changed'): void
    {
        self::publish(self::EVENT_PEMESANAN_CHANGED, $itemPemesanan, [
            'action' => $action,
            'id_pemesanan' => $itemPemesanan->id_pemesanan,
        ]);
    }

    public static function publishNotifikasiChanged(Notifikasi $notifikasi, string $action = 'changed'): void
    {
        self::publish(self::EVENT_NOTIFIKASI_CHANGED, $notifikasi, [
            'action' => $action,
            'id_pengguna' => $notifikasi->id_pengguna,
            'id_pemesanan' => $notifikasi->id_pemesanan,
        ]);
    }

    private static function publish(string $event, Model $model, array $payload): void
    {
        try {
            RealtimeEvent::create([
                'event' => $event,
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'payload' => $payload,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Gagal membuat realtime event: ' . $exception->getMessage(), [
                'event' => $event,
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
            ]);
        }
    }
}
