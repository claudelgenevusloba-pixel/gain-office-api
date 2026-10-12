<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Employe;
use App\Models\Notification;
use App\Models\RapportTache;
use App\Models\Tache;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    /**
     * Dashboard principal GAIN OFFICE.
     *
     * Toutes les données proviennent de la base de données.
     * Aucune donnée de démonstration n'est utilisée.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var Utilisateur|null $utilisateur */
            $utilisateur = $request->user();

            if (!$utilisateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié.',
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | UTILISATEUR CONNECTÉ
            |--------------------------------------------------------------------------
            */

            $utilisateur->load([
                'role',
                'employe.poste',
                'employe.poste.service',
            ]);

            $role = $this->normaliser(
                $utilisateur->role?->nom
            );

            $nomComplet = trim(
                ($utilisateur->prenom ?? '') . ' ' .
                ($utilisateur->nom ?? '')
            );

            /*
            |--------------------------------------------------------------------------
            | VISIBILITÉ
            |--------------------------------------------------------------------------
            |
            | Pour le moment :
            |
            | - Employé : ses propres tâches + ses propres demandes
            | - Direction/RH/Responsable/Admin : données globales
            |
            | Les règles fines pourront ensuite être raccordées exactement
            | à access.js / permissions.php.
            |
            */

            $estEmploye = in_array($role, [
                'employe',
            ], true);

            /*
            |--------------------------------------------------------------------------
            | EMPLOYÉS
            |--------------------------------------------------------------------------
            */

            $employesTotal = Employe::query()->count();

            $employesActifs = Employe::query()
                ->whereHas('utilisateur', function ($query) {
                    $query->where('statut', 'actif');
                })
                ->count();

            $employesInactifs = max(
                0,
                $employesTotal - $employesActifs
            );

            /*
            |--------------------------------------------------------------------------
            | REQUÊTES TÂCHES
            |--------------------------------------------------------------------------
            */

            $tachesQuery = Tache::query();

            if ($estEmploye) {
                $tachesQuery->where(
                    'id_assigne',
                    $utilisateur->id_utilisateur
                );
            }

            /*
            |--------------------------------------------------------------------------
            | STATISTIQUES TÂCHES
            |--------------------------------------------------------------------------
            */

            $tachesTotal = (clone $tachesQuery)->count();

            $tachesEnCours = (clone $tachesQuery)
                ->whereIn('statut', [
                    'en_cours',
                    'En cours',
                ])
                ->count();

            $tachesEnAttente = (clone $tachesQuery)
                ->whereIn('statut', [
                    'en_attente',
                    'En attente',
                    'a_faire',
                    'À faire',
                ])
                ->count();

            $tachesValidation = (clone $tachesQuery)
                ->whereIn('statut', [
                    'en_attente_validation',
                    'En attente de validation',
                    'a_valider',
                ])
                ->count();

            $tachesTerminees = (clone $tachesQuery)
                ->whereIn('statut', [
                    'terminee',
                    'Terminée',
                    'termine',
                    'Terminé',
                    'validee',
                    'Validée',
                ])
                ->count();

            /*
            |--------------------------------------------------------------------------
            | TÂCHES EN RETARD
            |--------------------------------------------------------------------------
            */

            $tachesEnRetard = (clone $tachesQuery)
                ->whereNotNull('date_echeance')
                ->where('date_echeance', '<', now())
                ->whereNotIn('statut', [
                    'terminee',
                    'Terminée',
                    'termine',
                    'Terminé',
                    'validee',
                    'Validée',
                ])
                ->count();

            /*
            |--------------------------------------------------------------------------
            | TAUX D'AVANCEMENT
            |--------------------------------------------------------------------------
            */

            $tauxAvancement = $tachesTotal > 0
                ? round(($tachesTerminees / $tachesTotal) * 100, 2)
                : 0;

            /*
            |--------------------------------------------------------------------------
            | DEMANDES
            |--------------------------------------------------------------------------
            */

            $demandesQuery = Demande::query();

            if ($estEmploye) {
                $demandesQuery->where(
                    'id_demandeur',
                    $utilisateur->id_utilisateur
                );
            }

            $demandesTotal = (clone $demandesQuery)->count();

            $demandesEnAttente = (clone $demandesQuery)
                ->whereIn('statut', [
                    'en_attente',
                    'En attente',
                ])
                ->count();

            $demandesAcceptees = (clone $demandesQuery)
                ->whereIn('statut', [
                    'acceptee',
                    'Acceptée',
                    'acceptee',
                ])
                ->count();

            $demandesRefusees = (clone $demandesQuery)
                ->whereIn('statut', [
                    'refusee',
                    'Refusée',
                ])
                ->count();

            $demandesAnnulees = (clone $demandesQuery)
                ->whereIn('statut', [
                    'annulee',
                    'Annulée',
                ])
                ->count();

            /*
            |--------------------------------------------------------------------------
            | TÂCHES RÉCENTES
            |--------------------------------------------------------------------------
            */

            $tachesRecentes = (clone $tachesQuery)
                ->with([
                    'createur:id_utilisateur,nom,prenom',
                    'assigne:id_utilisateur,nom,prenom',
                ])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(function (Tache $tache) {
                    return [
                        'id' => $tache->id_tache,
                        'titre' => $tache->titre,
                        'description' => $tache->description,
                        'priorite' => $this->libellePriorite(
                            $tache->priorite
                        ),
                        'priorite_code' => $tache->priorite,
                        'statut' => $this->libelleTache(
                            $tache->statut
                        ),
                        'statut_code' => $tache->statut,

                        'date_debut' => $this->dateFormat(
                            $tache->date_debut
                        ),

                        'date_echeance' => $this->dateFormat(
                            $tache->date_echeance
                        ),

                        'date_terminaison' => $this->dateFormat(
                            $tache->date_terminaison
                        ),

                        'createur' => $this->nomUtilisateur(
                            $tache->createur
                        ),

                        'assigne_a' => $this->nomUtilisateur(
                            $tache->assigne
                        ),

                        'en_retard' => $tache->date_echeance
                            && Carbon::parse($tache->date_echeance)->isPast()
                            && !$this->estTacheTerminee($tache->statut),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | DEMANDES RÉCENTES
            |--------------------------------------------------------------------------
            */

            $demandesRecentes = (clone $demandesQuery)
                ->with([
                    'demandeur:id_utilisateur,nom,prenom',
                    'traiteur:id_utilisateur,nom,prenom',
                ])
                ->orderByDesc('date_demande')
                ->limit(8)
                ->get()
                ->map(function (Demande $demande) {
                    return [
                        'id' => $demande->id_demande,

                        'type' => $demande->type_libelle,

                        'type_code' => $demande->type_demande,

                        'objet' => $demande->objet,

                        'description' => $demande->description,

                        'statut' => $demande->statut_libelle,

                        'statut_code' => $demande->statut,

                        'date' => $this->dateFormat(
                            $demande->date_demande
                        ),

                        'date_traitement' => $this->dateFormat(
                            $demande->date_traitement
                        ),

                        'demandeur' => $this->nomUtilisateur(
                            $demande->demandeur
                        ),

                        'traiteur' => $this->nomUtilisateur(
                            $demande->traiteur
                        ),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATIONS DE L'UTILISATEUR
            |--------------------------------------------------------------------------
            */

            $notifications = Notification::query()
                ->where(
                    'id_utilisateur',
                    $utilisateur->id_utilisateur
                )
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(function (Notification $notification) {
                    return [
                        'id' => $notification->id_notification,

                        'titre' => $notification->titre,

                        'message' => $notification->message,

                        'type' => $notification->type,

                        'lien' => $notification->lien,

                        'lu' => (bool) $notification->lu,

                        'date_lecture' => $this->dateFormat(
                            $notification->date_lecture
                        ),

                        'date' => $this->dateFormat(
                            $notification->created_at
                        ),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | COMPTEUR NOTIFICATIONS NON LUES
            |--------------------------------------------------------------------------
            */

            $notificationsNonLues = Notification::query()
                ->where(
                    'id_utilisateur',
                    $utilisateur->id_utilisateur
                )
                ->where('lu', false)
                ->count();

            /*
            |--------------------------------------------------------------------------
            | ACTIVITÉS RÉCENTES
            |--------------------------------------------------------------------------
            |
            | On ne fabrique aucune activité.
            | On reconstruit uniquement des événements à partir
            | des vraies tâches et demandes présentes en base.
            |
            */

            $activites = $this->construireActivites(
                $tachesQuery,
                $demandesQuery
            );

            /*
            |--------------------------------------------------------------------------
            | STATISTIQUES SUPPLÉMENTAIRES
            |--------------------------------------------------------------------------
            */

            $tauxDemandesAcceptees = $demandesTotal > 0
                ? round(
                    ($demandesAcceptees / $demandesTotal) * 100,
                    2
                )
                : 0;

            $tauxDemandesRefusees = $demandesTotal > 0
                ? round(
                    ($demandesRefusees / $demandesTotal) * 100,
                    2
                )
                : 0;

            /*
            |--------------------------------------------------------------------------
            | RAPPORTS HIÉRARCHIQUES
            |--------------------------------------------------------------------
            | Périmètre identique à celui de RapportTacheController :
            | auteur, destinataire, subordonnés directs — global
            | pour RH et Administrateur.
            */

            $rapportsQuery = RapportTache::query();

            $roleNom = $utilisateur->role?->nom ?? '';

            if (
                in_array(
                    $roleNom,
                    ['RH', 'Administrateur'],
                    true
                )
            ) {
                /* Consultation globale */
            } elseif ($estEmploye) {
                $rapportsQuery->where(
                    'id_auteur',
                    $utilisateur->id_utilisateur
                );
            } else {
                $idsSubordonnes = Utilisateur::where(
                    'id_responsable',
                    $utilisateur->id_utilisateur
                )
                    ->pluck('id_utilisateur')
                    ->all();

                $idConnecte = $utilisateur->id_utilisateur;

                $rapportsQuery->where(function ($sous) use (
                    $idConnecte,
                    $idsSubordonnes
                ) {
                    $sous->where('id_auteur', $idConnecte)
                        ->orWhere('id_destinataire', $idConnecte)
                        ->orWhere('id_decideur', $idConnecte);

                    if (!empty($idsSubordonnes)) {
                        $sous->orWhereIn('id_auteur', $idsSubordonnes);
                    }
                });
            }

            $rapportsTotal = (clone $rapportsQuery)->count();

            $rapportsATraiter = (clone $rapportsQuery)
                ->where(
                    'id_destinataire',
                    $utilisateur->id_utilisateur
                )
                ->whereIn('statut', [
                    'soumis',
                    'en_cours_examen',
                ])
                ->count();

            $rapportsEnRetard = (clone $rapportsQuery)
                ->whereNotNull('date_limite_soumission')
                ->where('date_limite_soumission', '<', now())
                ->whereIn('statut', [
                    'soumis',
                    'en_cours_examen',
                    'correction_demandee',
                ])
                ->count();

            $rapportsCorrections = (clone $rapportsQuery)
                ->where(
                    'id_auteur',
                    $utilisateur->id_utilisateur
                )
                ->where('statut', 'correction_demandee')
                ->count();

            $rapportsValides = (clone $rapportsQuery)
                ->where('statut', 'valide')
                ->count();

            /*
            |--------------------------------------------------------------------
            | RÉPONSE FINALE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' => 'Dashboard chargé avec succès.',

                'utilisateur' => [
                    'id' => $utilisateur->id_utilisateur,

                    'nom' => $nomComplet,

                    'prenom' => $utilisateur->prenom,

                    'nom_famille' => $utilisateur->nom,

                    'email' => $utilisateur->email,

                    'telephone' => $utilisateur->telephone,

                    'photo' => $utilisateur->photo,

                    'statut' => $utilisateur->statut,

                    'role' => $utilisateur->role?->nom,

                    'poste' => $utilisateur
                        ->employe
                        ?->poste
                        ?->nom,

                    'service' => $utilisateur
                        ->employe
                        ?->poste
                        ?->service
                        ?->nom,
                ],

                'stats' => [
                    /*
                     * EMPLOYÉS
                     */
                    'employes_total' => $employesTotal,
                    'employes_actifs' => $employesActifs,
                    'employes_inactifs' => $employesInactifs,

                    /*
                     * TÂCHES
                     */
                    'taches_total' => $tachesTotal,
                    'taches_en_cours' => $tachesEnCours,
                    'taches_en_attente' => $tachesEnAttente,
                    'taches_validation' => $tachesValidation,
                    'taches_terminees' => $tachesTerminees,
                    'taches_en_retard' => $tachesEnRetard,

                    /*
                     * PERFORMANCE
                     */
                    'taux_avancement' => $tauxAvancement,

                    /*
                     * DEMANDES
                     */
                    'demandes_total' => $demandesTotal,
                    'demandes_en_attente' => $demandesEnAttente,
                    'demandes_acceptees' => $demandesAcceptees,
                    'demandes_refusees' => $demandesRefusees,
                    'demandes_annulees' => $demandesAnnulees,

                    /*
                     * RAPPORTS
                     */
                    'rapports_total' => $rapportsTotal,
                    'rapports_a_traiter' => $rapportsATraiter,
                    'rapports_en_retard' => $rapportsEnRetard,
                    'rapports_corrections' => $rapportsCorrections,
                    'rapports_valides' => $rapportsValides,

                    'taux_demandes_acceptees' =>
                        $tauxDemandesAcceptees,

                    'taux_demandes_refusees' =>
                        $tauxDemandesRefusees,

                    /*
                     * NOTIFICATIONS
                     */
                    'notifications_non_lues' =>
                        $notificationsNonLues,
                ],

                'taches' => $tachesRecentes,

                'demandes' => $demandesRecentes,

                'activites' => $activites,

                'notifications' => $notifications,

                'meta' => [
                    'genere_le' => now()->toIso8601String(),

                    'timezone' => config(
                        'app.timezone'
                    ),

                    'role' => $utilisateur->role?->nom,

                    'portee' => $estEmploye
                        ? 'utilisateur'
                        : 'globale',
                ],
            ]);

        } catch (Throwable $e) {

            Log::error(
                'Erreur Dashboard GAIN OFFICE',
                [
                    'utilisateur_id' =>
                        $request->user()?->id_utilisateur,

                    'message' => $e->getMessage(),

                    'fichier' => $e->getFile(),

                    'ligne' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Impossible de charger les données du dashboard.',

                'error' => app()->environment('local')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /**
     * Construit les activités réelles à partir des tâches
     * et des demandes.
     */
    private function construireActivites(
        $tachesQuery,
        $demandesQuery
    ): array {
        $activites = collect();

        /*
        |--------------------------------------------------------------------------
        | ACTIVITÉS TÂCHES
        |--------------------------------------------------------------------------
        */

        $taches = (clone $tachesQuery)
            ->with([
                'assigne:id_utilisateur,nom,prenom',
            ])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        foreach ($taches as $tache) {
            $nom = $this->nomUtilisateur(
                $tache->assigne
            );

            $activites->push([
                'id' => 'tache_' . $tache->id_tache,

                'type' => 'task',

                'titre' => 'Tâche mise à jour',

                'description' => $nom
                    ? $nom . ' — ' . $tache->titre
                    : $tache->titre,

                'statut' => $this->libelleTache(
                    $tache->statut
                ),

                'date' => $tache->updated_at
                    ? $tache->updated_at->toIso8601String()
                    : null,

                'date_relative' => $tache->updated_at
                    ? $tache->updated_at->diffForHumans()
                    : null,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | ACTIVITÉS DEMANDES
        |--------------------------------------------------------------------------
        */

        $demandes = (clone $demandesQuery)
            ->with([
                'demandeur:id_utilisateur,nom,prenom',
            ])
            ->orderByDesc('date_demande')
            ->limit(6)
            ->get();

        foreach ($demandes as $demande) {
            $nom = $this->nomUtilisateur(
                $demande->demandeur
            );

            $activites->push([
                'id' => 'demande_' . $demande->id_demande,

                'type' => 'request',

                'titre' => 'Demande ' .
                    $demande->type_libelle,

                'description' => $nom
                    ? $nom . ' a effectué une demande.'
                    : 'Nouvelle demande enregistrée.',

                'statut' => $demande->statut_libelle,

                'date' => $demande->date_demande
                    ? $demande->date_demande->toIso8601String()
                    : null,

                'date_relative' => $demande->date_demande
                    ? $demande->date_demande->diffForHumans()
                    : null,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRI FINAL
        |--------------------------------------------------------------------------
        */

        return $activites
            ->sortByDesc(function ($activite) {
                return $activite['date'] ?? '';
            })
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Retourne le nom complet d'un utilisateur.
     */
    private function nomUtilisateur(
        ?Utilisateur $utilisateur
    ): ?string {
        if (!$utilisateur) {
            return null;
        }

        $nom = trim(
            ($utilisateur->prenom ?? '') . ' ' .
            ($utilisateur->nom ?? '')
        );

        return $nom !== ''
            ? $nom
            : null;
    }

    /**
     * Format date pour le frontend.
     */
    private function dateFormat($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date)
                ->format('d/m/Y H:i');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Normalisation légère des valeurs.
     */
    private function normaliser(?string $valeur): string
    {
        return mb_strtolower(
            trim($valeur ?? ''),
            'UTF-8'
        );
    }

    /**
     * Vérifie si une tâche est terminée.
     */
    private function estTacheTerminee(
        ?string $statut
    ): bool {
        return in_array($statut, [
            'terminee',
            'Terminée',
            'termine',
            'Terminé',
            'validee',
            'Validée',
        ], true);
    }

    /**
     * Libellé professionnel des tâches.
     */
    private function libelleTache(
        ?string $statut
    ): string {
        return match ($statut) {
            'en_attente',
            'a_faire',
            'En attente',
            'À faire'
                => 'À faire',

            'en_cours',
            'En cours'
                => 'En cours',

            'en_attente_validation',
            'a_valider',
            'En attente de validation'
                => 'En attente de validation',

            'terminee',
            'termine',
            'Terminée',
            'Terminé'
                => 'Terminée',

            'validee',
            'Validée'
                => 'Validée',

            default => ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $statut ?? 'Inconnu'
                )
            ),
        };
    }

    /**
     * Libellé priorité.
     */
    private function libellePriorite(
        ?string $priorite
    ): string {
        return match ($priorite) {
            'haute',
            'Haute'
                => 'Haute',

            'moyenne',
            'Moyenne'
                => 'Moyenne',

            'basse',
            'Basse'
                => 'Basse',

            default => ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $priorite ?? 'Normale'
                )
            ),
        };
    }
}
