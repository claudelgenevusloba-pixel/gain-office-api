<?php

namespace App\Console\Commands;

use App\Models\Demande;
use App\Models\Historique;
use App\Models\Notification;
use App\Models\Tache;
use App\Models\Utilisateur;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class EnvoyerRappelsAutomatiques extends Command
{
    protected $signature = 'gain-office:send-reminders';
    protected $description = 'Envoie les rappels quotidiens des demandes et tâches en attente.';

    public function handle(): int
    {
        $this->rappelerTaches();
        $this->rappelerDemandes();
        $this->info('Rappels GAIN OFFICE traités.');
        return self::SUCCESS;
    }

    private function rappelerTaches(): void
    {
        Tache::query()
            ->whereNotNull('date_echeance')
            ->where('date_echeance', '<=', now()->addDay())
            ->whereNotIn('statut', [
                'terminee', 'Terminée', 'termine', 'Terminé', 'validee', 'Validée', 'annulee',
            ])
            ->whereNotNull('id_assigne')
            ->chunkById(100, function ($taches) {
                foreach ($taches as $tache) {
                    $date = $tache->date_echeance?->format('d/m/Y');
                    $this->notifierUneFoisParJour(
                        (int) $tache->id_assigne,
                        'rappel_tache',
                        '/taches/' . $tache->id_tache,
                        'Échéance de tâche',
                        'La tâche « ' . $tache->titre . ' » arrive à échéance le ' . $date . '.'
                    );
                }
            }, 'id_tache');
    }

    private function rappelerDemandes(): void
    {
        Demande::query()
            ->whereIn('statut', ['en_attente', 'En attente'])
            ->where('date_demande', '<=', now()->subDay())
            ->chunkById(100, function ($demandes) {
                foreach ($demandes as $demande) {
                    $workflow = $this->workflow($demande->type_demande);
                    $validations = Historique::query()
                        ->where('module', 'demandes')
                        ->where('description', 'like', 'Validation de la demande #' . $demande->id_demande . '%')
                        ->count();
                    $roleAttendu = $workflow[$validations] ?? null;
                    if (!$roleAttendu) continue;

                    foreach ($this->utilisateursRole($roleAttendu) as $idUtilisateur) {
                        $this->notifierUneFoisParJour(
                            $idUtilisateur,
                            'rappel_demande',
                            '/demandes/' . $demande->id_demande,
                            'Demande en attente',
                            'La demande « ' . $demande->objet . ' » attend toujours votre validation.'
                        );
                    }
                }
            }, 'id_demande');
    }

    private function utilisateursRole(string $role): Collection
    {
        $noms = match ($role) {
            'RH' => ['RH', 'Ressources humaines'],
            'Responsable' => ['Responsable', 'Sous-directeur'],
            'Employe' => ['Employe', 'Employé'],
            default => [$role],
        };

        return Utilisateur::query()
            ->where('statut', 'actif')
            ->whereHas('role', fn ($query) => $query->whereIn('nom', $noms))
            ->pluck('id_utilisateur');
    }

    private function notifierUneFoisParJour(
        int $idUtilisateur,
        string $type,
        string $lien,
        string $titre,
        string $message
    ): void {
        $dejaNotifie = Notification::query()
            ->where('id_utilisateur', $idUtilisateur)
            ->where('type', $type)
            ->where('lien', $lien)
            ->whereDate('created_at', today())
            ->exists();

        if ($dejaNotifie) return;

        Notification::create([
            'id_utilisateur' => $idUtilisateur,
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'lien' => $lien,
            'lu' => false,
        ]);
    }

    private function workflow(string $type): array
    {
        return match ($type) {
            'conge', 'permission', 'absence' => ['Responsable', 'RH'],
            'avance' => ['Responsable', 'RH', 'Directeur'],
            'achat', 'materiel', 'fournitures' => ['Responsable', 'Administration'],
            'support_informatique' => ['Informatique'],
            'mission' => ['Responsable', 'Directeur'],
            'document_administratif' => ['RH'],
            'recrutement' => ['RH', 'Directeur'],
            'autre' => ['Responsable', 'Directeur'],
            default => ['Responsable'],
        };
    }
}
