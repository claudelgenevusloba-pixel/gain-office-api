<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Historique;
use App\Models\Notification;
use App\Models\Tache;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TacheController extends Controller
{
    /**
     * Liste des tâches accessibles à l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        if (!$this->permission($utilisateur, 'voir_taches')) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n’avez pas la permission de consulter les tâches.',
            ], 403);
        }

        $role = $this->role($utilisateur);

        $query = Tache::query()
            ->with([
                'createur:id_utilisateur,nom,prenom,email',
                'assigne:id_utilisateur,nom,prenom,email',
            ])
            ->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | Employé
        |--------------------------------------------------------------------------
        */

        if ($this->normaliserRole($role) === 'employe') {
            $query->where(function ($q) use ($utilisateur) {
                $q->where(
                    'id_assigne',
                    $utilisateur->id_utilisateur
                )->orWhere(
                    'id_createur',
                    $utilisateur->id_utilisateur
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Autres utilisateurs
        |--------------------------------------------------------------------------
        |
        | Le filtrage détaillé par permissions est déjà assuré par le système
        | global. On ne limite donc pas artificiellement Directeur/RH/
        | Responsable/Admin ici.
        |
        |--------------------------------------------------------------------------
        */

        $taches = $query->get();

        return response()->json([
            'success' => true,
            'taches' => $taches,
        ]);
    }

    /**
     * Afficher une tâche.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        if (!$this->permission($utilisateur, 'voir_taches')) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n’avez pas la permission de consulter les tâches.',
            ], 403);
        }

        $tache = Tache::with([
            'createur:id_utilisateur,nom,prenom,email',
            'assigne:id_utilisateur,nom,prenom,email',
            'rapports',
            'messages',
            'piecesJointes',
        ])->findOrFail($id);

        $this->verifierAccesTache($utilisateur, $tache);

        return response()->json([
            'success' => true,
            'tache' => $tache,
        ]);
    }

    /**
     * Créer une tâche.
     */
    public function store(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        if (!$this->permission($utilisateur, 'creer_tache')) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n’avez pas la permission de créer une tâche.',
            ], 403);
        }

        $validated = $request->validate([
            'id_assigne' => [
                'required',
                'integer',
                Rule::exists('utilisateurs', 'id_utilisateur')
                    ->where(function ($query) {
                        $query->where('statut', 'actif');
                    }),
            ],

            'titre' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'priorite' => [
                'required',
                Rule::in([
                    'basse',
                    'normale',
                    'haute',
                    'urgente',
                ]),
            ],

            'statut' => [
                'nullable',
                Rule::in([
                    'a_faire',
                    'en_cours',
                    'terminee',
                    'en_attente_validation',
                    'validee',
                    'correction_demandee',
                ]),
            ],

            'date_debut' => [
                'nullable',
                'date',
            ],

            'date_echeance' => [
                'nullable',
                'date',
                'after_or_equal:date_debut',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Vérification de l'assignation
        |--------------------------------------------------------------------------
        */

        if (!$this->permission($utilisateur, 'assigner_tache')) {
            $idAssigne = (int) $validated['id_assigne'];

            /*
             * Un utilisateur qui ne possède pas la permission
             * d'assigner une tâche ne peut pas choisir quelqu'un
             * d'autre que lui-même.
             */

            if ($idAssigne !== (int) $utilisateur->id_utilisateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n’avez pas la permission d’assigner cette tâche à un autre utilisateur.',
                ], 403);
            }
        }

        $tache = DB::transaction(function () use (
            $validated,
            $utilisateur
        ) {
            $tache = Tache::create([
                'id_createur' => $utilisateur->id_utilisateur,
                'id_assigne' => $validated['id_assigne'],
                'titre' => $validated['titre'],
                'description' => $validated['description'] ?? null,
                'priorite' => $validated['priorite'],
                'statut' => $validated['statut'] ?? 'a_faire',
                'date_debut' => $validated['date_debut'] ?? null,
                'date_echeance' => $validated['date_echeance'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Notification de l'utilisateur assigné
            |--------------------------------------------------------------------------
            */

            if (
                (int) $tache->id_assigne !==
                (int) $utilisateur->id_utilisateur
            ) {
                Notification::create([
                    'id_utilisateur' => $tache->id_assigne,
                    'titre' => 'Nouvelle tâche',
                    'message' =>
                        "Une nouvelle tâche vous a été assignée : {$tache->titre}",
                    'type' => 'tache',
                    'lien' => "/taches/{$tache->id_tache}",
                    'lu' => false,
                ]);
            }

            $this->enregistrerHistorique(
                $utilisateur->id_utilisateur,
                'creation',
                'taches',
                "Création de la tâche #{$tache->id_tache} : {$tache->titre}"
            );

            return $tache;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tâche créée avec succès.',
            'tache' => $tache->load([
                'createur:id_utilisateur,nom,prenom,email',
                'assigne:id_utilisateur,nom,prenom,email',
            ]),
        ], 201);
    }

    /**
     * Modifier une tâche.
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $utilisateur = $request->user();

        if (!$this->permission($utilisateur, 'modifier_tache')) {
            /*
             * Un employé peut néanmoins terminer sa propre tâche
             * s'il possède terminer_tache.
             */
            if (
                !$this->permission(
                    $utilisateur,
                    'terminer_tache'
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n’avez pas la permission de modifier cette tâche.',
                ], 403);
            }
        }

        $tache = Tache::findOrFail($id);

        $this->verifierAccesTache(
            $utilisateur,
            $tache
        );

        $role = $this->normaliserRole(
            $this->role($utilisateur)
        );

        $validated = $request->validate([
            'id_assigne' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists(
                    'utilisateurs',
                    'id_utilisateur'
                )->where(function ($query) {
                    $query->where('statut', 'actif');
                }),
            ],

            'titre' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'priorite' => [
                'sometimes',
                Rule::in([
                    'basse',
                    'normale',
                    'haute',
                    'urgente',
                ]),
            ],

            'statut' => [
                'sometimes',
                Rule::in([
                    'a_faire',
                    'en_cours',
                    'terminee',
                    'en_attente_validation',
                    'validee',
                    'correction_demandee',
                ]),
            ],

            'date_debut' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'date_echeance' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:date_debut',
            ],

            'commentaire_validation' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assignation
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists('id_assigne', $validated) &&
            !$this->permission(
                $utilisateur,
                'assigner_tache'
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n’avez pas la permission de réassigner cette tâche.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Gestion du statut
        |--------------------------------------------------------------------------
        */

        if (array_key_exists('statut', $validated)) {
            $nouveauStatut = $validated['statut'];

            /*
             * Terminer une tâche.
             */

            if (
                $nouveauStatut === 'terminee' &&
                !$this->permission(
                    $utilisateur,
                    'terminer_tache'
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n’avez pas la permission de terminer cette tâche.',
                ], 403);
            }

            /*
             * Valider une tâche.
             */

            if (
                $nouveauStatut === 'validee' &&
                !$this->permission(
                    $utilisateur,
                    'valider_tache'
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n’avez pas la permission de valider cette tâche.',
                ], 403);
            }

            /*
             * Les autres changements de statut nécessitent
             * la permission de modification.
             */

            $statutsSpeciaux = [
                'terminee',
                'validee',
            ];

            if (
                !in_array(
                    $nouveauStatut,
                    $statutsSpeciaux,
                    true
                ) &&
                !$this->permission(
                    $utilisateur,
                    'modifier_tache'
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n’avez pas la permission de modifier le statut de cette tâche.',
                ], 403);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Employé : limitation des champs
        |--------------------------------------------------------------------------
        */

        if (
            $role === 'employe' &&
            !$this->permission(
                $utilisateur,
                'modifier_tache'
            )
        ) {
            $champsAutorises = [
                'statut',
                'commentaire_validation',
            ];

            foreach (array_keys($validated) as $champ) {
                if (!in_array(
                    $champ,
                    $champsAutorises,
                    true
                )) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Vous ne pouvez modifier que le statut de votre tâche.',
                    ], 403);
                }
            }
        }

        $ancienStatut = $tache->statut;
        $ancienneAssignation = $tache->id_assigne;

        $tache->fill($validated);

        /*
        |--------------------------------------------------------------------------
        | Date de terminaison
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['statut']) &&
            $validated['statut'] === 'terminee' &&
            $ancienStatut !== 'terminee'
        ) {
            $tache->date_terminaison = now();
        }

        /*
        |--------------------------------------------------------------------------
        | Date de validation
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['statut']) &&
            $validated['statut'] === 'validee' &&
            $ancienStatut !== 'validee'
        ) {
            $tache->date_validation = now();
        }

        $tache->save();

        /*
        |--------------------------------------------------------------------------
        | Historique
        |--------------------------------------------------------------------------
        */

        $this->enregistrerHistorique(
            $utilisateur->id_utilisateur,
            'modification',
            'taches',
            "Modification de la tâche #{$tache->id_tache} : {$tache->titre}"
        );

        /*
        |--------------------------------------------------------------------------
        | Notification de réassignation
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'id_assigne',
                $validated
            ) &&
            (int) $ancienneAssignation !==
            (int) $tache->id_assigne
        ) {
            Notification::create([
                'id_utilisateur' => $tache->id_assigne,
                'titre' => 'Tâche réassignée',
                'message' =>
                    "La tâche « {$tache->titre} » vous a été assignée.",
                'type' => 'tache',
                'lien' => "/taches/{$tache->id_tache}",
                'lu' => false,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Notification changement de statut
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists('statut', $validated) &&
            $ancienStatut !== $tache->statut
        ) {
            $destinataire = null;

            /*
             * Lorsque l'assigné termine la tâche,
             * le créateur est averti.
             */
            if (
                $tache->statut === 'terminee' ||
                $tache->statut === 'en_attente_validation'
            ) {
                $destinataire = $tache->id_createur;
            }

            /*
             * Lorsqu'une tâche est validée,
             * l'assigné est informé.
             */
            if (
                $tache->statut === 'validee'
            ) {
                $destinataire = $tache->id_assigne;
            }

            if (
                $destinataire !== null &&
                (int) $destinataire !==
                (int) $utilisateur->id_utilisateur
            ) {
                Notification::create([
                    'id_utilisateur' => $destinataire,
                    'titre' => 'Mise à jour de tâche',
                    'message' =>
                        "La tâche « {$tache->titre} » est maintenant : {$tache->statut}.",
                    'type' => 'tache',
                    'lien' => "/taches/{$tache->id_tache}",
                    'lu' => false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Tâche modifiée avec succès.',
            'tache' => $tache->load([
                'createur:id_utilisateur,nom,prenom,email',
                'assigne:id_utilisateur,nom,prenom,email',
            ]),
        ]);
    }/**
 * Supprimer une tâche.
 */
public function destroy(
    Request $request,
    int $id
): JsonResponse {
    $utilisateur = $request->user();

    if (!$this->permission(
        $utilisateur,
        'supprimer_tache'
    )) {
        return response()->json([
            'success' => false,
            'message' => 'Vous n’avez pas la permission de supprimer cette tâche.',
        ], 403);
    }

    $tache = Tache::findOrFail($id);

    $this->verifierAccesTache(
        $utilisateur,
        $tache
    );

    $titre = $tache->titre;
    $idTache = $tache->id_tache;

    DB::transaction(function () use (
        $tache,
        $utilisateur,
        $titre,
        $idTache
    ) {
        $this->enregistrerHistorique(
            $utilisateur->id_utilisateur,
            'suppression',
            'taches',
            "Suppression de la tâche #{$idTache} : {$titre}"
        );

        $tache->delete();
    });

    return response()->json([
        'success' => true,
        'message' => 'Tâche supprimée avec succès.',
    ]);
}


/**
 * Historique des actions liées aux tâches.
 */
public function historique(
    Request $request,
    int $id
): JsonResponse {
    $utilisateur = $request->user();

    if (!$this->permission(
        $utilisateur,
        'suivre_tache'
    )) {
        return response()->json([
            'success' => false,
            'message' => 'Vous n’avez pas la permission de consulter le suivi des tâches.',
        ], 403);
    }

    $tache = Tache::findOrFail($id);

    $this->verifierAccesTache(
        $utilisateur,
        $tache
    );

    $historique = Historique::query()
        ->where('module', 'taches')
        ->where(function ($query) use ($id) {
            $query
                ->where(
                    'description',
                    'like',
                    "%tâche #{$id}%"
                )
                ->orWhere(
                    'description',
                    'like',
                    "%tache #{$id}%"
                );
        })
        ->with([
            'utilisateur:id_utilisateur,nom,prenom,email',
        ])
        ->orderByDesc('created_at')
        ->get();

    return response()->json([
        'success' => true,
        'historique' => $historique,
    ]);
}


/**
 * Vérifier l'accès général à une tâche.
 */
private function verifierAccesTache(
    Utilisateur $utilisateur,
    Tache $tache
): void {

    $rolesGlobaux = [
        'directeur',
        'rh',
        'responsable',
        'administrateur',
    ];

    $role = $this->normaliserRole(
        $this->role($utilisateur)
    );

    if (
        in_array(
            $role,
            $rolesGlobaux,
            true
        )
    ) {
        return;
    }

    if (
        (int) $tache->id_assigne ===
        (int) $utilisateur->id_utilisateur
    ) {
        return;
    }

    if (
        (int) $tache->id_createur ===
        (int) $utilisateur->id_utilisateur
    ) {
        return;
    }

    abort(
        403,
        'Vous n’avez pas accès à cette tâche.'
    );
}/**
 * Vérifier une permission.
 *
 * IMPORTANT :
 * On utilise ici le système de permissions existant.
 * Aucun PermissionService supplémentaire n'est créé.
 */
private function permission(
    ?Utilisateur $utilisateur,
    string $permission
): bool {
    if (!$utilisateur) {
        return false;
    }

    $role = $this->normaliserRole(
        $this->role($utilisateur)
    );

    if ($role === 'administrateur') {
        return true;
    }

    if (
        method_exists(
            $utilisateur,
            'aPermission'
        )
    ) {
        return (bool) $utilisateur->aPermission(
            $permission
        );
    }

    $permissions = [
        'directeur' => [
            'voir_taches',
            'creer_tache',
            'modifier_tache',
            'supprimer_tache',
            'assigner_tache',
            'suivre_tache',
            'terminer_tache',
            'valider_tache',
        ],

        'responsable' => [
            'voir_taches',
            'creer_tache',
            'modifier_tache',
            'assigner_tache',
            'suivre_tache',
            'terminer_tache',
        ],

        'rh' => [
            'voir_taches',
            'suivre_tache',
        ],

        'employe' => [
            'voir_taches',
            'suivre_tache',
            'terminer_tache',
        ],

        'administrateur' => [
            '*',
        ],
    ];

    if (
        in_array(
            '*',
            $permissions[$role] ?? [],
            true
        )
    ) {
        return true;
    }

    return in_array(
        $permission,
        $permissions[$role] ?? [],
        true
    );
}


/**
 * Récupérer le rôle de l'utilisateur.
 */
private function role(
    Utilisateur $utilisateur
): string {
    return $utilisateur->role?->nom ?? '';
}


/**
 * Normaliser un rôle.
 */
private function normaliserRole(
    ?string $role
): string {
    $role = trim(
        mb_strtolower(
            $role ?? '',
            'UTF-8'
        )
    );

    $role = str_replace(
        [
            'é',
            'è',
            'ê',
            'ë',
            'à',
            'â',
            'ä',
            'î',
            'ï',
            'ô',
            'ö',
            'ù',
            'û',
            'ü',
            'ç',
        ],
        [
            'e',
            'e',
            'e',
            'e',
            'a',
            'a',
            'a',
            'i',
            'i',
            'o',
            'o',
            'u',
            'u',
            'u',
            'c',
        ],
        $role
    );

    return $role;
}


/**
 * Enregistrer une action dans l'historique.
 */
private function enregistrerHistorique(
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
