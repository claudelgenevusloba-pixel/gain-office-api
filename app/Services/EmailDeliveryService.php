<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailDeliveryService
{
    /**
     * Envoie un message seulement avec un transport qui peut le livrer.
     * Les transports log, array et null ne livrent rien au destinataire.
     */
    public function envoyer(string $destinataire, Mailable $courriel): void
    {
        $nomMailer = (string) config('mail.default', '');
        $configuration = config('mail.mailers.' . $nomMailer, []);

        if (!$this->transportPeutLivrer($nomMailer, $configuration)) {
            throw new RuntimeException(
                'Le transport e-mail est configuré en mode journalisation ou simulation. '
                . 'Configurez un fournisseur SMTP réel pour livrer les messages.'
            );
        }

        Mail::mailer($nomMailer)->to($destinataire)->send($courriel);
    }

    private function transportPeutLivrer(
        string $nomMailer,
        array $configuration,
        array $visites = []
    ): bool {
        if ($nomMailer === '' || isset($visites[$nomMailer])) {
            return false;
        }

        $visites[$nomMailer] = true;
        $transport = $configuration['transport'] ?? null;

        if (in_array($transport, ['log', 'array', 'null'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $mailers = $configuration['mailers'] ?? [];

            if (!is_array($mailers) || $mailers === []) {
                return false;
            }

            foreach ($mailers as $enfant) {
                $configurationEnfant = config('mail.mailers.' . $enfant, []);

                if (!$this->transportPeutLivrer(
                    (string) $enfant,
                    $configurationEnfant,
                    $visites
                )) {
                    return false;
                }
            }
        }

        return is_string($transport) && $transport !== '';
    }
}