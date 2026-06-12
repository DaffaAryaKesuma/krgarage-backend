<?php

namespace App\Mail;

use App\Models\Vespa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailPengingatServisBerkala extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vespa $vespa,
        public string $tahapPengingat
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengingat Servis Berkala Vespa - KRGarage',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.service_reminder',
            with: [
                'vespa' => $this->vespa,
                'tahapPengingat' => $this->tahapPengingat,
                'pelanggan' => $this->vespa->pengguna,
                'urlPemesanan' => rtrim(config('app.frontend_url'), '/') . '/app/pemesanan',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
