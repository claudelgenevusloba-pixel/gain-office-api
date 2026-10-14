<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationEmploye extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomEmploye,
        public string $lienActivation,
        public string $dateExpiration
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre invitation à GAIN OFFICE',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation-employe',
            with: [
                'nomEmploye' => $this->nomEmploye,
                'lienActivation' => $this->lienActivation,
                'dateExpiration' => $this->dateExpiration,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
