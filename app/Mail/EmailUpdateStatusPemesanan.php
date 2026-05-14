<?php

namespace App\Mail;

use App\Models\Pemesanan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailUpdateStatusPemesanan extends Mailable
{
    use Queueable, SerializesModels;

    public $pemesanan;
    public $judulEmail;
    public $pesanEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(Pemesanan $pemesanan, string $judulEmail, string $pesanEmail)
    {
        $this->pemesanan = $pemesanan;
        $this->judulEmail = $judulEmail;
        $this->pesanEmail = $pesanEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pembaruan Pesanan KRGarage: " . $this->judulEmail,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: "emails.booking.status_update",
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

