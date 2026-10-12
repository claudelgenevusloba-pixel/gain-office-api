<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Historique;
use App\Models\Notification;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class DemandeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TYPES DE DEMANDES
    |--------------------------------------------------------------------------
    */

    private function typesDemandes(): array
    {
        return [
            'conge',
            'permission',
            'absence',
            'avance',
            'achat',
            'materiel',
            'support_informatique',
            'mission',
            'document_administratif',
            'fournitures',
            'recrutement',
            'autre',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW
    |--------------------------------------------------------------------------
    |
    | Chaque type de demande possède son propre circuit de validation.
    |
    */

    private function workflow(string $type): array
    {
        return match ($type) {

            'conge',
            'permission',
            'absence' => [
                'Responsable',
                'RH',
            ],

            'avance' => [
                'Responsable',
                'RH',
                'Directeur',
            ],

            'achat',
            'materiel',
            'fournitures' => [
                'Responsable',
                'Administration',
            ],

            'support_informatique' => [
                'Informatique',
            ],

            'mission' => [
                'Responsable',
                'Directeur',
            ],

            'document_administratif' => [
                'RH',
            ],

            'recrutement' => [
                'RH',
                'Directeur',
            ],

            'autre' => [
                'Responsable',
                'Directeur',
            ],

            default => [
                'Responsable',
            ],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSIONS
    |--------------------------------------------------------------------------
    |
    | Les permissions correspondent exactement aux permissions définies
    | dans le frontend React.
    |
    | Il n'existe pas de table permissions dans la base actuelle.
    | On applique donc ici les mêmes droits que roles.js.
    |
    */

    private function permissionsParRole(): array
    {
        return [

            'Directeur' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
                'supprimer_demande',
                'traiter_demande',
                'accepter_demande',
                'refuser_demande',
            ],

            'Ressources humaines' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
                'traiter_demande',
                'accepter_demande',
                'refuser_demande',
            ],

            'RH' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
                'traiter_demande',
                'accepter_demande',
                'refuser_demande',
            ],

            'Responsable' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
                'traiter_demande',
                'accepter_demande',
                'refuser_demande',
            ],

            'Employé' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
            ],

            'Employe' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
            ],

            'Administrateur' => [
                'voir_demandes',
                'creer_demande',
                'modifier_demande',
                'supprimer_demande',
                'traiter_demande',
                'accepter_demande',
                'refuser_demande',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RÔLE DE L'UTILISATEUR
    |--------------------------------------------------------------------------
    */

    private function role(Utilisateur $utilisateur): string
    {
        return trim((string) ($utilisateur->role?->nom ?? ''));
    }

    /*
    |--------------------------------------------------------------------------
    | VÉRIFICATION D'UNE PERMISSION
    |--------------------------------------------------------------------------
    */

    private function verifierPermission(
        Utilisateur $utilisateur,
        string $permission
    ): void {
        $role = $this->role($utilisateur);

        $permissions = $this->permissionsParRole();

        if (
            !isset($permissions[$role]) ||
            !in_array($permission, $permissions[$role], true)
        ) {
            abort(
                403,
                "Vous n’avez pas la permission : {$permission}."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALISATION DES RÔLES
    |--------------------------------------------------------------------------
    |
    | Permet de considérer "RH" et "Ressources humaines" comme le même
    | rôle dans le workflow.
    |
    */

    private function memeRole(
        ?string $roleUtilisateur,
        ?string $roleWorkflow
    ): bool {
        $roleUtilisateur = trim((string) $roleUtilisateur);
        $roleWorkflow = trim((string) $roleWorkflow);

        if (
            $roleUtilisateur === 'RH' &&
            $roleWorkflow === 'Ressources humaines'
        ) {
            return true;
        }

        if (
            $roleUtilisateur === 'Ressources humaines' &&
            $roleWorkflow === 'RH'
        ) {
            return true;
        }

        if (
            $roleUtilisateur === 'Employé' &&
            $roleWorkflow === 'Employe'
        ) {
            return true;
        }

        if (
            $roleUtilisateur === 'Employe' &&
            $roleWorkflow === 'Employé'
        ) {
            return true;
        }

        return $roleUtilisateur === $roleWorkflow;
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORIQUE DES VALIDATIONS
    |--------------------------------------------------------------------------
    */

    private function validationsEffectuees(Demande $demande): array
    {
        return Historique::query()
            ->where('module', 'demandes')
            ->where(
                'description',
                'like',
                'Validation de la demande #' . $demande->id_demande . '%'
            )
            ->orderBy('id_historique')
            ->get()
            ->map(function ($historique) {
                return [
                    'id_utilisateur' => $historique->id_utilisateur,
                    'action' => $historique->action,
                    'nouvelle_valeur' => $historique->nouvelle_valeur,
                    'created_at' => $historique->created_at,
                ];
            })
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | ÉTAPE ACTUELLE DU WORKFLOW
    |--------------------------------------------------------------------------
    */

    private function etapeActuelle(Demande $demande): array
    {
        $workflow = $this->workflow($demande->type_demande);

        $validations = $this->validationsEffectuees($demande);

        $etape = count($validations);

        if (!isset($workflow[$etape])) {
            return [
                'terminee' => true,
                'role' => null,
                'etape' => $etape,
                'total' => count($workflow),
            ];
        }

        return [
            'terminee' => false,
            'role' => $workflow[$etape],
            'etape' => $etape + 1,
            'total' => count($workflow),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UTILISATEURS D'UN RÔLE
    |--------------------------------------------------------------------------
    */

    private function utilisateursRole(string $role)
    {
        return Utilisateur::query()
            ->whereHas('role', function ($query) use ($role) {

                if ($role === 'RH') {
                    $query->whereIn('nom', [
                        'RH',
                        'Ressources humaines',
                    ]);
                    return;
                }

                if ($role === 'Employe') {
                    $query->whereIn('nom', [
                        'Employe',
                        'Employé',
                    ]);
                    return;
                }

                $query->where('nom', $role);
            })
            ->where('statut', 'actif')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $this->verifierPermission(
            $utilisateur,
            'voir_demandes'
        );

        $role = $this->role($utilisateur);

        $query = Demande::with([
            'demandeur.role',
            'traiteur.role',
        ])
            ->orderByDesc('id_demande');

        /*
        |--------------------------------------------------------------------------
        | EMPLOYÉ
        |--------------------------------------------------------------------------
        */

        if (
            $role === 'Employe' ||
            $role === 'Employé'
        ) {
            $query->where(
                'id_demandeur',
                $utilisateur->id_utilisateur
            );
        }

        $demandes = $query->get();

        $demandes = $demandes
            ->map(function ($demande) use ($utilisateur) {

                $etape = $this->etapeActuelle($demande);

                $peutTraiter = false;

                if (
                    $demande->statut === 'en_attente' &&
                    !$etape['terminee'] &&
                    $this->memeRole(
                        $this->role($utilisateur),
                        $etape['role']
                    )
                ) {
                    $peutTraiter =
                        in_array(
                            'traiter_demande',
                            $this->permissionsParRole()[
                                $this->role($utilisateur)
                            ] ?? [],
                            true
                        );
                }

                $demande->setAttribute(
                    'type_libelle',
                    $demande->type_libelle
                );

                $demande->setAttribute(
                    'statut_libelle',
                    $demande->statut_libelle
                );

                $demande->setAttribute(
                    'workflow',
                    $etape
                );

                $demande->setAttribute(
                    'peut_traiter',
                    $peutTraiter
                );

                return $demande;
            });

        /*
        |--------------------------------------------------------------------------
        | FILTRE POUR LES RÔLES DE TRAITEMENT
        |--------------------------------------------------------------------------
        */

        if (
            $role !== 'Employe' &&
            $role !== 'Employé'
        ) {
            $demandes = $demandes
                ->filter(function ($demande) use ($utilisateur) {

                    return
                        $demande->peut_traiter === true
                        ||
                        $demande->id_traiteur ===
                            $utilisateur->id_utilisateur
                        ||
                        $demande->id_demandeur ===
                            $utilisateur->id_utilisateur;
                })
                ->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Demandes récupérées avec succès.',
            'total' => $demandes->count(),
            'demandes' => $demandes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        int $id
    ): JsonResponse {

        $utilisateur = $request->user();

        $this->verifierPermission(
            $utilisateur,
            'voir_demandes'
        );

        $demande = Demande::with([
            'demandeur.role',
            'traiteur.role',
        ])->find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable.',
            ], 404);
        }

        $role = $this->role($utilisateur);

        /*
        |--------------------------------------------------------------------------
        | EMPLOYÉ : UNIQUEMENT SES DEMANDES
        |--------------------------------------------------------------------------
        */

        if (
            ($role === 'Employe' || $role === 'Employé') &&
            $demande->id_demandeur !==
                $utilisateur->id_utilisateur
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Accès interdit à cette demande.',
            ], 403);
        }

        $etape = $this->etapeActuelle($demande);

        $peutTraiter = false;

        if (
            $demande->statut === 'en_attente' &&
            !$etape['terminee'] &&
            $this->memeRole($role, $etape['role'])
        ) {
            $peutTraiter =
                in_array(
                    'traiter_demande',
                    $this->permissionsParRole()[$role] ?? [],
                    true
                );
        }

        $demande->setAttribute(
            'type_libelle',
            $demande->type_libelle
        );

        $demande->setAttribute(
            'statut_libelle',
            $demande->statut_libelle
        );

        $demande->setAttribute(
            'workflow',
            $etape
        );

        $demande->setAttribute(
            'peut_traiter',
            $peutTraiter
        );

        $demande->setAttribute(
            'historique_validation',
            $this->validationsEffectuees($demande)
        );

        return response()->json([
            'success' => true,
            'demande' => $demande,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $this->verifierPermission(
            $utilisateur,
            'creer_demande'
        );

        $validated = $request->validate([

            'type_demande' => [
                'required',
                'string',
                Rule::in($this->typesDemandes()),
            ],

            'objet' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'required',
                'string',
            ],
        ]);

        try {

            $demande = DB::transaction(
                function () use (
                    $validated,
                    $utilisateur,
                    $request
                ) {

                    $demande = Demande::create([

                        'id_demandeur' =>
                            $utilisateur->id_utilisateur,

                        'type_demande' =>
                            $validated['type_demande'],

                        'objet' =>
                            $validated['objet'],

                        'description' =>
                            $validated['description'],

                        'statut' =>
                            'en_attente',

                        'date_demande' =>
                            now(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | HISTORIQUE CRÉATION
                    |--------------------------------------------------------------------------
                    */

                    Historique::create([

                        'id_utilisateur' =>
                            $utilisateur->id_utilisateur,

                        'action' =>
                            'creation',

                        'module' =>
                            'demandes',

                        'description' =>
                            'Création de la demande #'
                            . $demande->id_demande,

                        'nouvelle_valeur' => [
                            'statut' =>
                                'en_attente',

                            'type_demande' =>
                                $demande->type_demande,

                            'objet' =>
                                $demande->objet,
                        ],

                        'adresse_ip' =>
                            $request->ip(),

                        'user_agent' =>
                            $request->userAgent(),

                        'created_at' =>
                            now(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | NOTIFICATION PREMIER VALIDATEUR
                    |--------------------------------------------------------------------------
                    */

                    $workflow = $this->workflow(
                        $demande->type_demande
                    );

                    $premierRole =
                        $workflow[0] ?? null;

                    if ($premierRole) {

                        $validateurs =
                            $this->utilisateursRole(
                                $premierRole
                            );

                        foreach ($validateurs as $validateur) {

                            Notification::create([

                                'id_utilisateur' =>
                                    $validateur->id_utilisateur,

                                'titre' =>
                                    'Nouvelle demande à traiter',

                                'message' =>
                                    'La demande "'
                                    . $demande->objet
                                    . '" nécessite votre validation.',

                                'type' =>
                                    'demande',

                                'lien' =>
                                    '/demandes/'
                                    . $demande->id_demande,

                                'lu' =>
                                    false,
                            ]);
                        }
                    }

                    return $demande;
                }
            );

            return response()->json([

                'success' => true,

                'message' =>
                    'Demande créée avec succès.',

                'demande' =>
                    $demande->load([
                        'demandeur.role',
                    ]),

            ], 201);

        } catch (Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Impossible de créer la demande.',

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TRAITEMENT / VALIDATION
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $id
    ): JsonResponse {

        $utilisateur = $request->user();

        $role = $this->role($utilisateur);

        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | ANNULATION / MODIFICATION PAR LE DEMANDEUR
        |--------------------------------------------------------------------------
        |
        | La permission modifier_demande est nécessaire.
        |
        */

        if (
            (
                $role === 'Employe' ||
                $role === 'Employé'
            ) &&
            $demande->id_demandeur ===
                $utilisateur->id_utilisateur
        ) {

            $this->verifierPermission(
                $utilisateur,
                'modifier_demande'
            );

            $validated = $request->validate([
                'statut' => [
                    'required',
                    Rule::in([
                        'annulee',
                    ]),
                ],
            ]);

            if ($demande->statut !== 'en_attente') {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cette demande ne peut plus être annulée.',
                ], 422);
            }

            $ancienStatut = $demande->statut;

            $demande->update([
                'statut' => $validated['statut'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | HISTORIQUE ANNULATION
            |--------------------------------------------------------------------------
            */

            Historique::create([

                'id_utilisateur' =>
                    $utilisateur->id_utilisateur,

                'action' =>
                    'annulation',

                'module' =>
                    'demandes',

                'description' =>
                    'Annulation de la demande #'
                    . $demande->id_demande,

                'ancienne_valeur' => [
                    'statut' => $ancienStatut,
                ],

                'nouvelle_valeur' => [
                    'statut' => 'annulee',
                ],

                'adresse_ip' =>
                    $request->ip(),

                'user_agent' =>
                    $request->userAgent(),

                'created_at' =>
                    now(),
            ]);

            return response()->json([

                'success' => true,

                'message' =>
                    'Demande annulée avec succès.',

                'demande' =>
                    $demande,

            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | LE VALIDATEUR DOIT AVOIR LA PERMISSION DE TRAITER
        |--------------------------------------------------------------------------
        */

        $this->verifierPermission(
            $utilisateur,
            'traiter_demande'
        );

        /*
        |--------------------------------------------------------------------------
        | VÉRIFICATION DU WORKFLOW
        |--------------------------------------------------------------------------
        */

        $etape = $this->etapeActuelle($demande);

        if (
            $demande->statut !== 'en_attente' ||
            $etape['terminee'] ||
            !$this->memeRole(
                $role,
                $etape['role']
            )
        ) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Vous n’êtes pas autorisé à traiter cette demande à cette étape.',

                'etape' =>
                    $etape,

            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'statut' => [
                'required',
                Rule::in([
                    'acceptee',
                    'refusee',
                ]),
            ],

            'commentaire_traitement' => [
                'nullable',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | ACCEPTER / REFUSER
        |--------------------------------------------------------------------------
        */

        if ($validated['statut'] === 'acceptee') {

            $this->verifierPermission(
                $utilisateur,
                'accepter_demande'
            );
        }

        if ($validated['statut'] === 'refusee') {

            $this->verifierPermission(
                $utilisateur,
                'refuser_demande'
            );
        }

        try {

            $resultat = DB::transaction(
                function () use (
                    $demande,
                    $validated,
                    $utilisateur,
                    $request,
                    $etape,
                    $role
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | REFUS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $validated['statut'] === 'refusee'
                    ) {

                        $demande->update([

                            'id_traiteur' =>
                                $utilisateur->id_utilisateur,

                            'statut' =>
                                'refusee',

                            'date_traitement' =>
                                now(),

                            'commentaire_traitement' =>
                                $validated[
                                    'commentaire_traitement'
                                ] ?? null,
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | HISTORIQUE REFUS
                        |--------------------------------------------------------------------------
                        */

                        Historique::create([

                            'id_utilisateur' =>
                                $utilisateur->id_utilisateur,

                            'action' =>
                                'validation_refusee',

                            'module' =>
                                'demandes',

                            'description' =>
                                'Validation de la demande #'
                                . $demande->id_demande
                                . ' - refusée',

                            'ancienne_valeur' => [
                                'statut' =>
                                    'en_attente',
                            ],

                            'nouvelle_valeur' => [

                                'statut' =>
                                    'refusee',

                                'role' =>
                                    $role,

                                'commentaire' =>
                                    $validated[
                                        'commentaire_traitement'
                                    ] ?? null,
                            ],

                            'adresse_ip' =>
                                $request->ip(),

                            'user_agent' =>
                                $request->userAgent(),

                            'created_at' =>
                                now(),
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | NOTIFICATION DEMANDEUR
                        |--------------------------------------------------------------------------
                        */

                        Notification::create([

                            'id_utilisateur' =>
                                $demande->id_demandeur,

                            'titre' =>
                                'Demande refusée',

                            'message' =>
                                'Votre demande "'
                                . $demande->objet
                                . '" a été refusée.',

                            'type' =>
                                'demande',

                            'lien' =>
                                '/demandes/'
                                . $demande->id_demande,

                            'lu' =>
                                false,
                        ]);

                        return $demande;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ACCEPTATION DE L'ÉTAPE
                    |--------------------------------------------------------------------------
                    */

                    $demande->update([

                        'id_traiteur' =>
                            $utilisateur->id_utilisateur,

                        'commentaire_traitement' =>
                            $validated[
                                'commentaire_traitement'
                            ] ?? null,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | HISTORIQUE VALIDATION
                    |--------------------------------------------------------------------------
                    */

                    Historique::create([

                        'id_utilisateur' =>
                            $utilisateur->id_utilisateur,

                        'action' =>
                            'validation',

                        'module' =>
                            'demandes',

                        'description' =>
                            'Validation de la demande #'
                            . $demande->id_demande
                            . ' - étape '
                            . $etape['etape'],

                        'ancienne_valeur' => [
                            'statut' =>
                                'en_attente',
                        ],

                        'nouvelle_valeur' => [

                            'statut' =>
                                'validee_etape',

                            'role' =>
                                $role,

                            'etape' =>
                                $etape['etape'],
                        ],

                        'adresse_ip' =>
                            $request->ip(),

                        'user_agent' =>
                            $request->userAgent(),

                        'created_at' =>
                            now(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | CALCUL DE L'ÉTAPE SUIVANTE
                    |--------------------------------------------------------------------------
                    */

                    $nouvelleEtape =
                        $this->etapeActuelle(
                            $demande
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | DERNIÈRE ÉTAPE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $nouvelleEtape['terminee']
                    ) {

                        $demande->update([

                            'statut' =>
                                'acceptee',

                            'date_traitement' =>
                                now(),
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | NOTIFICATION FINALE
                        |--------------------------------------------------------------------------
                        */

                        Notification::create([

                            'id_utilisateur' =>
                                $demande->id_demandeur,

                            'titre' =>
                                'Demande acceptée',

                            'message' =>
                                'Votre demande "'
                                . $demande->objet
                                . '" a été définitivement acceptée.',

                            'type' =>
                                'demande',

                            'lien' =>
                                '/demandes/'
                                . $demande->id_demande,

                            'lu' =>
                                false,
                        ]);

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | NOTIFICATION PROCHAIN VALIDATEUR
                        |--------------------------------------------------------------------------
                        */

                        $prochainRole =
                            $nouvelleEtape['role'];

                        $validateurs =
                            $this->utilisateursRole(
                                $prochainRole
                            );

                        foreach (
                            $validateurs
                            as $validateur
                        ) {

                            Notification::create([

                                'id_utilisateur' =>
                                    $validateur
                                        ->id_utilisateur,

                                'titre' =>
                                    'Demande à valider',

                                'message' =>
                                    'La demande "'
                                    . $demande->objet
                                    . '" nécessite votre validation.',

                                'type' =>
                                    'demande',

                                'lien' =>
                                    '/demandes/'
                                    . $demande->id_demande,

                                'lu' =>
                                    false,
                            ]);
                        }
                    }

                    return $demande;
                }
            );

            return response()->json([

                'success' => true,

                'message' =>
                    $resultat->statut === 'acceptee'
                        ? 'Demande définitivement acceptée.'
                        : 'Validation enregistrée avec succès.',

                'demande' =>
                    $resultat
                        ->fresh()
                        ->load([
                            'demandeur.role',
                            'traiteur.role',
                        ]),

            ]);

        } catch (Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Impossible de traiter la demande.',

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUPPRESSION
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {

        $utilisateur = $request->user();

        /*
        |--------------------------------------------------------------------------
        | PERMISSION DE SUPPRESSION
        |--------------------------------------------------------------------------
        */

        $this->verifierPermission(
            $utilisateur,
            'supprimer_demande'
        );

        $demande = Demande::find($id);

        if (!$demande) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Demande introuvable.',

            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | SEULES LES DEMANDES EN ATTENTE PEUVENT ÊTRE SUPPRIMÉES
        |--------------------------------------------------------------------------
        */

        if (
            $demande->statut !== 'en_attente'
        ) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Seules les demandes en attente peuvent être supprimées.',

            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | HISTORIQUE AVANT SUPPRESSION
        |--------------------------------------------------------------------------
        */

        Historique::create([

            'id_utilisateur' =>
                $utilisateur->id_utilisateur,

            'action' =>
                'suppression',

            'module' =>
                'demandes',

            'description' =>
                'Suppression de la demande #'
                . $demande->id_demande,

            'ancienne_valeur' => [

                'type_demande' =>
                    $demande->type_demande,

                'objet' =>
                    $demande->objet,

                'statut' =>
                    $demande->statut,
            ],

            'nouvelle_valeur' => [
                'statut' =>
                    'supprimee',
            ],

            'adresse_ip' =>
                $request->ip(),

            'user_agent' =>
                $request->userAgent(),

            'created_at' =>
                now(),
        ]);

        $demande->delete();

        return response()->json([

            'success' => true,

            'message' =>
                'Demande supprimée avec succès.',

        ]);
    }
}
