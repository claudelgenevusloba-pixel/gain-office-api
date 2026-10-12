<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Historique;
use App\Models\Notification;
use App\Models\ParametreEntreprise;
use App\Models\RapportTache;
use App\Models\RapportValidation;
use App\Models\Tache;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RapportTacheController extends Controller
{
    /**
     * Liste des rapports accessibles à l'utilisateur connecté.
     *
     * Périmètre :
     * - Administrateur : tous les rapports
     * - RH : consultation globale (permissions réelles, sans décision)
     * - Auteur : ses propres rapports
     * - Responsable/Directeur : rapports reçus (destinataire),
     *   rapports dont il a décidé, rapports de ses subordonnés directs
     */
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $query = RapportTache::query()
            ->with([
                'tache:id_tache,titre,statut,priorite,id_createur,id_assigne',
                'auteur:id_utilisateur,nom,prenom,email',
                'destinataire:id_utilisateur,nom,prenom',
                'decideur:id_utilisateur,nom,prenom',
            ])
            ->orderByDesc('created_at');

        $this->appliquerPerimetre($query, $utilisateur);

        $rapports = $query->get();

        $this->genererRappels($rapports, $utilisateur);

        return response()->json([
            'success' => true,
            'message' => 'Liste des rapports récupérée avec succès.',
            'total' => $rapports->count(),
            'rapports' => $rapports->map(function ($rapport) {
                return $this->enrichirRapport($rapport);
            }),
        ]);
    }

    /**
     * Afficher un rapport (auteur, décideur, périmètre autorisé).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $rapport = RapportTache::with([
            'tache',
            'auteur:id_utilisateur,nom,prenom,email',
            'destinataire:id_utilisateur,nom,prenom,email',
            'decideur:id_utilisateur,nom,prenom,email',
            'validations.utilisateur:id_utilisateur,nom,prenom',
            'piecesJointes',
        ])->findOrFail($id);

        $this->verifierAccesRapport($utilisateur, $rapport);

        return response()->json([
            'success' => true,
            'message' => 'Rapport récupéré avec succès.',
            'rapport' => $this->enrichirRapport($rapport),
        ]);
    }

    /**
     * Soumettre un rapport.
     *
     * - Rapport autonome (période + contenu) ou lié à une tâche assignée.
     * - Destinataire calculé : supérieur hiérarchique direct.
     *   La Direction n'est concernée qu'en dernier recours,
     *   jamais par défaut si un responsable intermédiaire existe.
     * - Échéance calculée depuis les paramètres d'entreprise.
     */
    public function store(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $validated = $request->validate([
            'id_tache' => [
                'nullable',
                'integer',
                Rule::exists('taches', 'id_tache'),
            ],
            'travail_effectue' => ['required', 'string'],
            'difficultes' => ['nullable', 'string'],
            'temps_passe' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string'],

            /* Période couverte par le rapport */
            'date_debut_periode' => ['nullable', 'date'],
            'date_fin_periode' => [
                'nullable',
                'date',
                'after_or_equal:date_debut_periode',
            ],
        ]);

        $tache = null;

        if (!empty($validated['id_tache'])) {
            $tache = Tache::findOrFail($validated['id_tache']);

            if (
                (int) $tache->id_assigne !==
                (int) $utilisateur->id_utilisateur
            ) {
                abort(
                    403,
                    'Vous ne pouvez soumettre un rapport que pour une tâche qui vous est assignée.'
                );
            }

            if (
                in_array(
                    $tache->statut,
                    ['validee'],
                    true
                )
            ) {
                abort(422, 'Cette tâche est déjà validée.');
            }
        }

        /*
         * Supérieur hiérarchique direct.
         * Aucun envoi automatique à la Direction si un
         * responsable intermédiaire existe.
         */
        $destinataire = $this->superieurDirect($utilisateur);

        if (!$destinataire) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Aucun supérieur hiérarchique n\'est configuré pour vous. Contactez le RH pour rattacher votre compte à un responsable.',
            ], 422);
        }

        /* Échéance : périodicité configurée dans les paramètres */
        $dateLimite = $this->calculerDateLimite($validated);

        $rapport = DB::transaction(function () use (
            $validated,
            $utilisateur,
            $destinataire,
            $tache,
            $dateLimite
        ) {
            $rapport = RapportTache::create([
                'id_tache' => $tache?->id_tache,
                'id_auteur' => $utilisateur->id_utilisateur,
                'id_destinataire' =>
                    $destinataire->id_utilisateur,
                'travail_effectue' =>
                    $validated['travail_effectue'],
                'difficultes' =>
                    $validated['difficultes'] ?? null,
                'temps_passe' =>
                    $validated['temps_passe'] ?? null,
                'commentaire' =>
                    $validated['commentaire'] ?? null,
                'date_debut_periode' =>
                    $validated['date_debut_periode'] ?? null,
                'date_fin_periode' =>
                    $validated['date_fin_periode'] ?? null,
                'date_limite_soumission' => $dateLimite,
                'statut' => 'soumis',
                'niveau_validation' => 1,
                'date_soumission' => now(),
            ]);

            if ($tache) {
                $tache->update([
                    'statut' => 'en_attente_validation',
                ]);
            }

            RapportValidation::create([
                'id_rapport' => $rapport->id_rapport,
                'id_utilisateur' =>
                    $utilisateur->id_utilisateur,
                'action' => 'soumis',
                'niveau' => 1,
                'motif' =>
                    'Soumission au supérieur hiérarchique direct.',
            ]);

            Notification::create([
                'id_utilisateur' =>
                    $destinataire->id_utilisateur,
                'titre' => 'Rapport à examiner',
                'message' =>
                    "Un rapport de {$utilisateur->prenom} {$utilisateur->nom} vous est soumis.",
                'type' => 'rapport',
                'lien' => '/rapports',
                'lu' => false,
            ]);

            $this->historique(
                $utilisateur->id_utilisateur,
                'creation',
                'rapports_taches',
                "Soumission du rapport #{$rapport->id_rapport}" .
                    ($tache
                        ? " pour la tâche #{$tache->id_tache}"
                        : ' (rapport périodique)')
            );

            return $rapport;
        });

        return response()->json([
            'success' => true,
            'message' => 'Rapport soumis avec succès.',
            'rapport' => $this->enrichirRapport(
                $rapport->load([
                    'tache',
                    'auteur:id_utilisateur,nom,prenom,email',
                    'destinataire:id_utilisateur,nom,prenom',
                ])
            ),
        ], 201);
    }

    /**
     * Modifier un rapport ou prendre une décision.
     *
     * Auteur : corrige le contenu (notamment après une
     * demande de correction) et resoumet.
     * Décideur : accepte, refuse ou renvoie en correction
     * avec motif — uniquement dans son périmètre et avec
     * la permission VALIDER_RAPPORT.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $rapport = RapportTache::with('tache')->findOrFail($id);

        $this->verifierAccesRapport($utilisateur, $rapport);

        $estAuteur =
            (int) $rapport->id_auteur ===
            (int) $utilisateur->id_utilisateur;

        $estDestinataire =
            (int) $rapport->id_destinataire ===
            (int) $utilisateur->id_utilisateur;

        $permissions = $this->permissionsRole($utilisateur);
        $estAdmin = $this->role($utilisateur) === 'Administrateur';

        $validated = $request->validate([
            'travail_effectue' => ['sometimes', 'required', 'string'],
            'difficultes' => ['nullable', 'string'],
            'temps_passe' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string'],
            'date_debut_periode' => ['nullable', 'date'],
            'date_fin_periode' => [
                'nullable',
                'date',
                'after_or_equal:date_debut_periode',
            ],

            /* Décision du supérieur */
            'statut' => [
                'sometimes',
                Rule::in([
                    'soumis',
                    'en_cours_examen',
                    'valide',
                    'correction_demandee',
                    'rejete',
                ]),
            ],
            'motif' => ['nullable', 'string', 'max:1000'],
            'commentaire_validation' => ['nullable', 'string'],
        ]);

        $decision = isset($validated['statut'])
            ? $validated['statut']
            : null;

        $ancienStatut = $rapport->statut;

        /*
         * ----------------------------------------------------------------
         * ROUTAGE DES ACTIONS
         * ----------------------------------------------------------------
         */

        if ($decision !== null && $decision !== $ancienStatut) {
            /* Prise en charge : destinataire uniquement */
            if ($decision === 'en_cours_examen') {
                if (!$estDestinataire && !$estAdmin) {
                    abort(
                        403,
                        'Seul le supérieur destinataire peut prendre ce rapport en examen.'
                    );
                }
            } else {
                /* Validation / correction / rejet : permission réelle */
                if (
                    !in_array(
                        'valider_rapport',
                        $permissions,
                        true
                    )
                ) {
                    abort(
                        403,
                        'Vous n\'avez pas la permission de valider les rapports.'
                    );
                }

                if (!$estDestinataire && !$estAdmin) {
                    abort(
                        403,
                        'Ce rapport ne relève pas de votre périmètre : vous n\'en êtes pas le supérieur destinataire.'
                    );
                }

                if (
                    in_array(
                        $ancienStatut,
                        ['valide', 'rejete'],
                        true
                    )
                ) {
                    abort(
                        422,
                        'Ce rapport a déjà fait l\'objet d\'une décision finale.'
                    );
                }

                if (
                    in_array(
                        $decision,
                        ['correction_demandee', 'rejete'],
                        true
                    ) &&
                    empty($validated['motif'])
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Un motif est obligatoire pour une correction ou un rejet.',
                        'errors' => [
                            'motif' => ['Le motif est obligatoire.'],
                        ],
                    ], 422);
                }
            }
        } elseif ($decision !== null) {
            /* Même statut : simple modification de contenu */
            $decision = null;
        }

        /*
         * ----------------------------------------------------------------
         * ROUTES AUTEUR / DÉCIDEUR
         * ----------------------------------------------------------------
         */

        if (!$estAuteur) {
            /*
             * Un décideur ne modifie pas le contenu :
             * il ne traite que statut/motif/commentaire.
             */
            unset(
                $validated['travail_effectue'],
                $validated['difficultes'],
                $validated['temps_passe'],
                $validated['commentaire'],
                $validated['date_debut_periode'],
                $validated['date_fin_periode']
            );
        } else {
            /* L'auteur ne décide jamais du statut lui-même */
            unset($validated['statut'], $validated['motif']);

            /*
             * Resoumission après correction :
             * le rapport repart vers le destinataire.
             */
            if (
                $ancienStatut === 'correction_demandee' &&
                count($validated) > 0
            ) {
                $validated['statut'] = 'soumis';
            }
        }

        DB::transaction(function () use (
            $rapport,
            $validated,
            $utilisateur,
            $ancienStatut,
            $decision,
            $estAuteur
        ) {
            $rapport->fill($validated);

            if ($decision !== null) {
                $this->appliquerDecision(
                    $rapport,
                    $utilisateur,
                    $decision,
                    $validated,
                    $ancienStatut
                );
            } elseif (
                $estAuteur &&
                ($validated['statut'] ?? null) === 'soumis'
            ) {
                /* Resoumission après correction */
                $rapport->date_soumission = now();
                $rapport->date_traitement = null;

                RapportValidation::create([
                    'id_rapport' => $rapport->id_rapport,
                    'id_utilisateur' =>
                        $utilisateur->id_utilisateur,
                    'action' => 'soumis',
                    'niveau' => $rapport->niveau_validation ?? 1,
                    'motif' =>
                        'Corrections apportées, rapport resoumis.',
                ]);

                if ($rapport->id_destinataire) {
                    Notification::create([
                        'id_utilisateur' =>
                            $rapport->id_destinataire,
                        'titre' => 'Rapport corrigé et resoumis',
                        'message' =>
                            "Le rapport #{$rapport->id_rapport} a été corrigé et resoumis pour examen.",
                        'type' => 'rapport',
                        'lien' => '/rapports',
                        'lu' => false,
                    ]);
                }
            }

            $rapport->save();

            $this->historique(
                $utilisateur->id_utilisateur,
                'modification',
                'rapports_taches',
                "Mise à jour du rapport #{$rapport->id_rapport}" .
                    (
                        $decision !== null
                            ? " (décision : {$decision})"
                            : ''
                    )
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Rapport mis à jour avec succès.',
            'rapport' => $this->enrichirRapport(
                $rapport->fresh()->load([
                    'tache',
                    'auteur:id_utilisateur,nom,prenom,email',
                    'destinataire:id_utilisateur,nom,prenom',
                    'decideur:id_utilisateur,nom,prenom',
                    'validations.utilisateur:id_utilisateur,nom,prenom',
                ])
            ),
        ]);
    }

    /**
     * Supprimer un rapport.
     * Seul l'auteur ou un Administrateur peut supprimer.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $rapport = RapportTache::with('tache')->findOrFail($id);

        $role = $this->role($utilisateur);

        if (
            $role !== 'Administrateur' &&
            (int) $rapport->id_auteur !==
            (int) $utilisateur->id_utilisateur
        ) {
            abort(403, 'Vous ne pouvez pas supprimer ce rapport.');
        }

        DB::transaction(function () use ($rapport, $utilisateur) {
            $this->historique(
                $utilisateur->id_utilisateur,
                'suppression',
                'rapports_taches',
                "Suppression du rapport #{$rapport->id_rapport}"
            );

            $rapport->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Rapport supprimé avec succès.',
        ]);
    }

    private function role(Utilisateur $utilisateur): string
    {
        return $utilisateur->role?->nom ?? '';
    }

    /**
     * Vérifie que l'utilisateur peut consulter ce rapport :
     * auteur, supérieur destinataire, décideur, subordonné
     * direct — ou consultation globale (RH, Administrateur).
     */
    private function verifierAccesRapport(
        Utilisateur $utilisateur,
        RapportTache $rapport
    ): void {
        if ($this->peutConsulterRapport($utilisateur, $rapport)) {
            return;
        }

        abort(403, 'Vous n’avez pas accès à ce rapport.');
    }

    private function peutConsulterRapport(
        Utilisateur $utilisateur,
        RapportTache $rapport
    ): bool {
        $role = $this->role($utilisateur);

        /* Consultation globale : permissions réelles du rôle */
        if (
            in_array(
                $role,
                ['RH', 'Administrateur'],
                true
            )
        ) {
            return true;
        }

        $id = (int) $utilisateur->id_utilisateur;

        /* Auteur, destinataire, décideur */
        if (
            (int) $rapport->id_auteur === $id ||
            (int) $rapport->id_destinataire === $id ||
            (int) $rapport->id_decideur === $id
        ) {
            return true;
        }

        /* Rapport d'un subordonné direct */
        return Utilisateur::where(
            'id_utilisateur',
            $rapport->id_auteur
        )
            ->where('id_responsable', $id)
            ->exists();
    }

    /**
     * Applique le périmètre de visibilité à la requête liste.
     */
    private function appliquerPerimetre(
        $query,
        Utilisateur $utilisateur
    ): void {
        $role = $this->role($utilisateur);

        /* Consultation globale : permissions réelles */
        if (
            in_array(
                $role,
                ['RH', 'Administrateur'],
                true
            )
        ) {
            return;
        }

        $id = $utilisateur->id_utilisateur;

        $idsSubordonnes = Utilisateur::where(
            'id_responsable',
            $id
        )
            ->pluck('id_utilisateur')
            ->all();

        $query->where(function ($sous) use ($id, $idsSubordonnes) {
            $sous->where('id_auteur', $id)
                ->orWhere('id_destinataire', $id)
                ->orWhere('id_decideur', $id);

            if (!empty($idsSubordonnes)) {
                $sous->orWhereIn('id_auteur', $idsSubordonnes);
            }
        });
    }

    /**
     * Supérieur hiérarchique direct de l'utilisateur.
     *
     * 1. Rattachement explicite (id_responsable, désigné par le RH)
     * 2. À défaut : responsable (rôle Responsable) du même service
     * 3. En dernier recours : le Directeur — jamais par défaut
     *    si un responsable intermédiaire existe.
     *
     * Dans tous les cas, le supérieur doit être d'un niveau
     * hiérarchique STRICTEMENT supérieur (niveau_hierarchique
     * plus petit) — sinon pas de supérieur (le Directeur
     * au sommet n'en a pas).
     */
    private function superieurDirect(
        Utilisateur $utilisateur
    ): ?Utilisateur {
        $niveau = $utilisateur->role?->niveau_hierarchique ?? 99;

        $plusHautQueMoi = function ($query) use ($niveau) {
            $query->where(
                'niveau_hierarchique',
                '<',
                $niveau
            );
        };

        /* 1. Rattachement explicite */
        if ($utilisateur->id_responsable) {
            $superieur = Utilisateur::with('role')
                ->find($utilisateur->id_responsable);

            if (
                $superieur &&
                $superieur->statut === 'actif' &&
                (int) $superieur->id_utilisateur !==
                    (int) $utilisateur->id_utilisateur &&
                ($superieur->role?->niveau_hierarchique ?? 99) < $niveau
            ) {
                return $superieur;
            }
        }

        /* 2. Responsable du même service, niveau supérieur */
        $idService = $utilisateur->employe
            ?->poste
            ?->id_service;

        if ($idService) {
            $responsable = Utilisateur::with('role')
                ->whereHas('employe.poste', function ($q) use ($idService) {
                    $q->where('id_service', $idService);
                })
                ->whereHas('role', function ($q) {
                    $q->where('nom', 'Responsable');
                })
                ->whereHas('role', $plusHautQueMoi)
                ->where('statut', 'actif')
                ->where('id_utilisateur', '!=', $utilisateur->id_utilisateur)
                ->first();

            if ($responsable) {
                return $responsable;
            }
        }

        /* 3. Dernier recours : Directeur d'un niveau supérieur */
        return Utilisateur::with('role')
            ->whereHas('role', function ($q) {
                $q->where('nom', 'Directeur');
            })
            ->whereHas('role', $plusHautQueMoi)
            ->where('statut', 'actif')
            ->where('id_utilisateur', '!=', $utilisateur->id_utilisateur)
            ->orderBy('id_utilisateur')
            ->first();
    }

    /**
     * Date limite de soumission selon la configuration
     * d'entreprise (aucune valeur imposée si non configurée).
     */
    private function calculerDateLimite(array $validated): ?\DateTime
    {
        $parametres = ParametreEntreprise::query()
            ->where('statut', true)
            ->first();

        if (
            !$parametres ||
            !$parametres->delai_soumission_rapports ||
            !$parametres->periodicite_rapports ||
            empty($validated['date_fin_periode'])
        ) {
            return null;
        }

        return \Carbon\Carbon::parse(
            $validated['date_fin_periode']
        )->addDays($parametres->delai_soumission_rapports);
    }

    /**
     * Permissions du rôle — délégué à la source unique
     * App\Support\RolePermissions (alignée sur le frontend).
     */
    private function permissionsRole(Utilisateur $utilisateur): array
    {
        return \App\Support\RolePermissions::parRole(
            $this->role($utilisateur)
        );
    }

    /**
     * Champs dérivés renvoyés au frontend :
     * en_retard et libellé de statut.
     */
    private function enrichirRapport(RapportTache $rapport): array
    {
        $donnees = $rapport->toArray();

        $donnees['en_retard'] = (bool) (
            $rapport->date_limite_soumission &&
            $rapport->date_limite_soumission->isPast() &&
            !in_array(
                $rapport->statut,
                ['valide', 'rejete'],
                true
            )
        );

        $donnees['libelle_statut'] = match ($rapport->statut) {
            'soumis' => 'Soumis',
            'en_cours_examen' => 'En cours d\'examen',
            'valide' => 'Validé',
            'correction_demandee' => 'Correction demandée',
            'rejete' => 'Refusé',
            default => $rapport->statut,
        };

        return $donnees;
    }

    /**
     * Rappels d'échéance (une seule fois par rapport,
     * si les rappels sont activés dans les paramètres).
     */
    private function genererRappels(
        $rapports,
        Utilisateur $utilisateur
    ): void {
        $parametres = ParametreEntreprise::query()
            ->where('statut', true)
            ->first();

        if (
            !$parametres ||
            !$parametres->rappels_rapports
        ) {
            return;
        }

        $rapports
            ->filter(function ($rapport) use ($utilisateur) {
                return (int) $rapport->id_auteur ===
                        (int) $utilisateur->id_utilisateur &&
                    $rapport->rappel_le === null &&
                    $rapport->date_limite_soumission !== null &&
                    $rapport->date_limite_soumission->isPast() &&
                    !in_array(
                        $rapport->statut,
                        ['valide', 'rejete'],
                        true
                    );
            })
            ->each(function ($rapport) {
                Notification::create([
                    'id_utilisateur' => $rapport->id_auteur,
                    'titre' => 'Rapport en retard',
                    'message' =>
                        "Votre rapport #{$rapport->id_rapport} a dépassé sa date limite de soumission.",
                    'type' => 'rapport',
                    'lien' => '/rapports',
                    'lu' => false,
                ]);

                $rapport->rappel_le = now();
                $rapport->save();
            });
    }

    /**
     * Applique la décision du supérieur au rapport,
     * avec remontée multi-niveaux si configurée.
     */
    private function appliquerDecision(
        RapportTache $rapport,
        Utilisateur $decideur,
        string $decision,
        array $validated,
        string $ancienStatut
    ): void {
        $rapport->id_decideur = $decideur->id_utilisateur;
        $rapport->date_decision = now();
        $rapport->motif_decision =
            $validated['motif'] ?? null;
        $rapport->commentaire_validation =
            $validated['commentaire_validation'] ?? null;
        $rapport->date_traitement = now();
        $rapport->statut = $decision;

        $niveau = $rapport->niveau_validation ?? 1;

        /* ----------------------------------------------------------------
         * Validation : remontée au niveau suivant si configurée
         * ---------------------------------------------------------------- */
        if ($decision === 'valide') {
            $parametres = ParametreEntreprise::query()
                ->where('statut', true)
                ->first();

            $niveauxRequis = max(
                1,
                (int) ($parametres?->niveaux_validation_rapports ?? 1)
            );

            $superieur = $this->superieurDirect($decideur);

            if ($niveau < $niveauxRequis && $superieur) {
                /* Remontée au niveau hiérarchique suivant */
                $rapport->niveau_validation = $niveau + 1;
                $rapport->id_destinataire =
                    $superieur->id_utilisateur;
                $rapport->statut = 'soumis';

                RapportValidation::create([
                    'id_rapport' => $rapport->id_rapport,
                    'id_utilisateur' =>
                        $decideur->id_utilisateur,
                    'action' => 'remonte',
                    'niveau' => $niveau,
                    'motif' =>
                        $validated['motif'] ??
                        "Validation de niveau {$niveau}, remontée au niveau " .
                            ($niveau + 1) . '.',
                ]);

                Notification::create([
                    'id_utilisateur' =>
                        $superieur->id_utilisateur,
                    'titre' => 'Rapport à valider (niveau supérieur)',
                    'message' =>
                        "Le rapport #{$rapport->id_rapport} a été validé au niveau {$niveau} et requiert votre validation finale.",
                    'type' => 'rapport',
                    'lien' => '/rapports',
                    'lu' => false,
                ]);
            } else {
                /* Validation finale */
                if ($rapport->tache) {
                    $rapport->tache->update([
                        'statut' => 'validee',
                        'date_validation' => now(),
                        'commentaire_validation' =>
                            $validated['commentaire_validation'] ?? null,
                    ]);
                }
            }
        }

        /* Correction demandée : retour à l'auteur */
        if ($decision === 'correction_demandee') {
            if ($rapport->tache) {
                $rapport->tache->update([
                    'statut' => 'correction_demandee',
                    'commentaire_validation' =>
                        $validated['commentaire_validation'] ?? null,
                ]);
            }
        }

        /* Prise en charge : la tâche repasse en attente de validation */
        if (
            $decision === 'en_cours_examen' &&
            $rapport->tache &&
            $ancienStatut !== $decision
        ) {
            $rapport->tache->update([
                'statut' => 'en_attente_validation',
            ]);
        }

        /* Historique de la décision */
        RapportValidation::create([
            'id_rapport' => $rapport->id_rapport,
            'id_utilisateur' => $decideur->id_utilisateur,
            'action' => $decision,
            'niveau' => $rapport->niveau_validation ?? 1,
            'motif' =>
                $validated['motif'] ??
                $validated['commentaire_validation'] ??
                null,
        ]);

        /*
         * Notification à l'auteur (sauf si la remontée
         * multi-niveaux a déjà notifié le suivant).
         */
        if (
            !($decision === 'valide' && $rapport->statut === 'soumis')
        ) {
            $titre = match ($decision) {
                'valide' => 'Rapport validé',
                'correction_demandee' => 'Correction demandée',
                'rejete' => 'Rapport refusé',
                'en_cours_examen' => 'Rapport en examen',
                default => 'Rapport mis à jour',
            };

            $message = match ($decision) {
                'valide' =>
                    "Votre rapport #{$rapport->id_rapport} a été validé par votre supérieur.",
                'correction_demandee' =>
                    "Une correction est demandée pour votre rapport #{$rapport->id_rapport}. Motif : " .
                        ($validated['motif'] ?? 'voir détails.'),
                'rejete' =>
                    "Votre rapport #{$rapport->id_rapport} a été refusé. Motif : " .
                        ($validated['motif'] ?? 'voir détails.'),
                'en_cours_examen' =>
                    "Votre rapport #{$rapport->id_rapport} est en cours d'examen.",
                default =>
                    "Le statut de votre rapport #{$rapport->id_rapport} a été modifié.",
            };

            Notification::create([
                'id_utilisateur' => $rapport->id_auteur,
                'titre' => $titre,
                'message' => $message,
                'type' => 'rapport',
                'lien' => '/rapports',
                'lu' => false,
            ]);
        }
    }

    private function historique(
        int $idUtilisateur,
        string $action,
        string $module,
        string $description
    ): void {
        Historique::create([
            'id_utilisateur' => $idUtilisateur,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'adresse_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
