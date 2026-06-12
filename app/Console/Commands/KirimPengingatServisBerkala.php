<?php

namespace App\Console\Commands;

use App\Mail\EmailPengingatServisBerkala;
use App\Models\Pemesanan;
use App\Models\Vespa;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class KirimPengingatServisBerkala extends Command
{
    protected $signature = 'servis:kirim-pengingat-berkala';

    protected $description = 'Mengirim email pengingat servis berkala Vespa pada H-3, hari-H, dan H+7.';

    public function handle(): int
    {
        if (!$this->kolomReminderTersedia()) {
            $this->warn('Kolom reminder email servis belum tersedia. Jalankan migration terlebih dahulu.');
            return self::SUCCESS;
        }

        $hariIni = Carbon::today(config('app.timezone'));
        $tanggalTarget = [
            $hariIni->copy()->addDays(3)->toDateString(),
            $hariIni->toDateString(),
            $hariIni->copy()->subDays(7)->toDateString(),
        ];

        $jumlahTerkirim = 0;

        Vespa::query()
            ->with('pengguna')
            ->whereNotNull('tanggal_servis_selanjutnya')
            ->whereIn('tanggal_servis_selanjutnya', $tanggalTarget)
            ->chunkById(100, function ($daftarVespa) use ($hariIni, &$jumlahTerkirim) {
                foreach ($daftarVespa as $vespa) {
                    $tahap = $this->tentukanTahapPengingat($vespa, $hariIni);

                    if ($tahap === null || $vespa->{$tahap['kolom']} !== null) {
                        continue;
                    }

                    if (!$vespa->pengguna || empty($vespa->pengguna->email)) {
                        continue;
                    }

                    if ($this->vespaMemilikiPemesananAktif($vespa)) {
                        continue;
                    }

                    try {
                        Mail::to($vespa->pengguna->email)->send(
                            new EmailPengingatServisBerkala($vespa, $tahap['label'])
                        );

                        $vespa->{$tahap['kolom']} = now();
                        $vespa->save();
                        $jumlahTerkirim++;
                    } catch (\Throwable $e) {
                        Log::error('Gagal mengirim email pengingat servis berkala', [
                            'id_vespa' => $vespa->id,
                            'id_pengguna' => $vespa->id_pengguna,
                            'tahap' => $tahap['label'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Email pengingat servis terkirim: {$jumlahTerkirim}");

        return self::SUCCESS;
    }

    private function kolomReminderTersedia(): bool
    {
        return Schema::hasTable('vespa')
            && Schema::hasColumn('vespa', 'reminder_h_minus_3_sent_at')
            && Schema::hasColumn('vespa', 'reminder_due_date_sent_at')
            && Schema::hasColumn('vespa', 'reminder_h_plus_7_sent_at');
    }

    /**
     * @return array{label: string, kolom: string}|null
     */
    private function tentukanTahapPengingat(Vespa $vespa, Carbon $hariIni): ?array
    {
        $tanggalServis = Carbon::parse($vespa->tanggal_servis_selanjutnya, config('app.timezone'))->startOfDay();

        if ($hariIni->isSameDay($tanggalServis->copy()->subDays(3))) {
            return [
                'label' => 'H-3 sebelum jadwal servis',
                'kolom' => 'reminder_h_minus_3_sent_at',
            ];
        }

        if ($hariIni->isSameDay($tanggalServis)) {
            return [
                'label' => 'hari-H jadwal servis',
                'kolom' => 'reminder_due_date_sent_at',
            ];
        }

        if ($hariIni->isSameDay($tanggalServis->copy()->addDays(7))) {
            return [
                'label' => 'H+7 setelah jadwal servis',
                'kolom' => 'reminder_h_plus_7_sent_at',
            ];
        }

        return null;
    }

    private function vespaMemilikiPemesananAktif(Vespa $vespa): bool
    {
        return Pemesanan::query()
            ->where('id_vespa', $vespa->id)
            ->whereIn('status', [
                Pemesanan::STATUS_MENUNGGU,
                Pemesanan::STATUS_DIKONFIRMASI,
                Pemesanan::STATUS_DIKERJAKAN,
            ])
            ->exists();
    }
}
