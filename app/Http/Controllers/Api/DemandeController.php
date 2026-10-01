<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Historique;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class DemandeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        $role = $utilisateur->role?->nom;

        $query = Demande::with([
            'demandeur.role',
            'traiteur.role',
        ])->orderByDesc('id_demande');

        /*
        |--------------------------------------------------------------------------
        | EMPLOYÉ
        |--------------------------------------------------------------------------
        | Il ne voit que ses propres demandes.
        */
        if ($role === 'Employe') {
            $query->where('id_demandeur', $utilisateur->id_utilisateur);
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSABLE
        |--------------------------------------------------------------------------
        | Pour l'instant, le responsable peut consulter les demandes
        | qui lui sont adressées via le traitement global.
        */

        $demandes = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Demandes récupérées avec succès.',
            'total' => $demandes->count(),
            'demandes' => $demandes,
        ]);
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $utilisateur = $request->user();
        $role = $utilisateur->role?->nom;

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

        if (
            $role === 'Employe' &&
            $demande->id_demandeur !== $utilisateur->id_utilisateur
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Accès interdit à cette demande.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'demande' => $demande,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $validated = $request->validate([
            'type_demande' => [
                'required',
                'string',
                'max:50',
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
            $demande = DB::transaction(function () use (
                $validated,
                $utilisateur
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
                        'statut' => 'en_attente',
                        'objet' => $demande->objet,
                    ],

                    'adresse_ip' =>
                        request()->ip(),

                    'user_agent' =>
                        request()->userAgent(),

                    'created_at' =>
                        now(),
                ]);

                return $demande;
            });

            return response()->json([
                'success' => true,
                'message' => 'Demande créée avec succès.',
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

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $utilisateur = $request->user();
        $role = $utilisateur->role?->nom;

        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | TRAITEMENT
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $role,
                ['Directeur', 'RH', 'Responsable'],
                true
            )
        ) {
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

            if ($demande->statut !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cette demande a déjà été traitée.',
                ], 422);
            }

            try {
                DB::transaction(function () use (
                    $demande,
                    $validated,
                    $utilisateur
                ) {
                    $ancienStatut =
                        $demande->statut;

                    $demande->update([
                        'id_traiteur' =>
                            $utilisateur->id_utilisateur,

                        'statut' =>
                            $validated['statut'],

                        'date_traitement' =>
                            now(),

                        'commentaire_traitement' =>
                            $validated[
                                'commentaire_traitement'
                            ] ?? null,
                    ]);

                    Notification::create([
                        'id_utilisateur' =>
                            $demande->id_demandeur,

                        'titre' =>
                            $validated['statut'] === 'acceptee'
                                ? 'Demande acceptée'
                                : 'Demande refusée',

                        'message' =>
                            'Votre demande "'
                            . $demande->objet
                            . '" a été '
                            . (
                                $validated['statut'] === 'acceptee'
                                    ? 'acceptée.'
                                    : 'refusée.'
                            ),

                        'type' =>
                            'demande',

                        'lien' =>
                            '/demandes/'
                            . $demande->id_demande,

                        'lu' =>
                            false,
                    ]);

                    Historique::create([
                        'id_utilisateur' =>
                            $utilisateur->id_utilisateur,

                        'action' =>
                            'traitement',

                        'module' =>
                            'demandes',

                        'description' =>
                            'Traitement de la demande #'
                            . $demande->id_demande,

                        'ancienne_valeur' => [
                            'statut' => $ancienStatut,
                        ],

                        'nouvelle_valeur' => [
                            'statut' =>
                                $demande->statut,
                        ],

                        'adresse_ip' =>
                            request()->ip(),

                        'user_agent' =>
                            request()->userAgent(),

                        'created_at' =>
                            now(),
                    ]);
                });

                return response()->json([
                    'success' => true,
                    'message' =>
                        'Demande traitée avec succès.',

                    'demande' =>
                        $demande->fresh()->load([
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
        | EMPLOYÉ : ANNULATION
        |--------------------------------------------------------------------------
        */

        if (
            $role === 'Employe' &&
            $demande->id_demandeur ===
                $utilisateur->id_utilisateur
        ) {
            if ($demande->statut !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cette demande ne peut plus être annulée.',
                ], 422);
            }

            $demande->update([
                'statut' => 'annulee',
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Demande annulée avec succès.',
                'demande' => $demande,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Action non autorisée.',
        ], 403);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $utilisateur = $request->user();

        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable.',
            ], 404);
        }

        if (
            $demande->id_demandeur !==
            $utilisateur->id_utilisateur
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Vous ne pouvez pas supprimer cette demande.',
            ], 403);
        }

        if ($demande->statut !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Seules les demandes en attente peuvent être supprimées.',
            ], 422);
        }

        $demande->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée avec succès.',
        ]);
    }
}
