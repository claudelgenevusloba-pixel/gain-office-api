<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    protected $signature = 'mail:test {destinataire}';

    protected $description = 'Teste l’envoi d’un e-mail via Gmail SMTP';

    public function handle(): int
    {
        $destinataire = $this->argument('destinataire');

        try {
            Mail::raw('Ceci est un e-mail de test de GAIN OFFICE.', function ($message) use ($destinataire) {
                $message->to($destinataire)
                    ->subject('Test e-mail - GAIN OFFICE');
            });

            $this->info('E-mail envoyé à : ' . $destinataire);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Échec de l’envoi : ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
