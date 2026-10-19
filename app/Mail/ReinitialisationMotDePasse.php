<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReinitialisationMotDePasse extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomUtilisateur,
        public string $lienReset
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reinitialisation de votre mot de passe - GAIN OFFICE',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reinitialisation-mot-de-passe',
            with: [
                'nomUtilisateur' => $this->nomUtilisateur,
                'lienReset' => $this->lienReset,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
