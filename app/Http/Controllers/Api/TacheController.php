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
        $role = $this->role($utilisateur);

        $query = Tache::query()
            ->with([
                'createur:id_utilisateur,nom,prenom,email',
                'assigne:id_utilisateur,nom,prenom,email',
            ])
            ->orderByDesc('created_at');

        if ($role === 'Employe') {
            $query->where(function ($q) use ($utilisateur) {
                $q->where('id_assigne', $utilisateur->id_utilisateur)
                  ->orWhere('id_createur', $utilisateur->id_utilisateur);
            });
        }

        $taches = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des tâches récupérée avec succès.',
            'total' => $taches->count(),
            'taches' => $taches,
        ]);
    }

    /**
     * Afficher une tâche.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

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
            'message' => 'Tâche récupérée avec succès.',
            'tache' => $tache,
        ]);
    }

    /**
     * Créer une tâche.
     */
    public function store(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $this->verifierGestionTaches($utilisateur);

        $validated = $request->validate([
            'id_assigne' => [
                'required',
                'integer',
                Rule::exists('utilisateurs', 'id_utilisateur')
                    ->where(fn ($query) => $query->where('statut', 'actif')),
            ],
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'priorite' => [
                'required',
                Rule::in(['basse', 'normale', 'haute', 'urgente']),
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
            'date_debut' => ['nullable', 'date'],
            'date_echeance' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $tache = DB::transaction(function () use ($validated, $utilisateur) {
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

            Notification::create([
                'id_utilisateur' => $tache->id_assigne,
                'titre' => 'Nouvelle tâche',
                'message' => "Une nouvelle tâche vous a été assignée : {$tache->titre}",
                'type' => 'tache',
                'lien' => "/taches/{$tache->id_tache}",
                'lu' => false,
            ]);

            $this->historique(
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
    public function update(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $tache = Tache::findOrFail($id);

        $this->verifierAccesTache($utilisateur, $tache);

        $role = $this->role($utilisateur);

        $regles = [
            'titre' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'priorite' => [
                'sometimes',
                Rule::in(['basse', 'normale', 'haute', 'urgente']),
            ],
            'date_debut' => ['nullable', 'date'],
            'date_echeance' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ];

        if (in_array($role, ['Directeur', 'Responsable'], true)) {
            $regles['id_assigne'] = [
                'sometimes',
                'required',
                'integer',
                Rule::exists('utilisateurs', 'id_utilisateur')
                    ->where(fn ($query) => $query->where('statut', 'actif')),
            ];

            $regles['statut'] = [
                'sometimes',
                Rule::in([
                    'a_faire',
                    'en_cours',
                    'terminee',
                    'en_attente_validation',
                    'validee',
                    'correction_demandee',
                ]),
            ];
        } else {
            $regles['statut'] = [
                'sometimes',
                Rule::in([
                    'en_cours',
                    'terminee',
                    'en_attente_validation',
                ]),
            ];
        }

        $validated = $request->validate($regles);

        $ancienStatut = $tache->statut;
        $ancienAssigne = $tache->id_assigne;

        DB::transaction(function () use (
            $tache,
            $validated,
            $utilisateur,
            $ancienStatut,
            $ancienAssigne
        ) {
            $tache->update($validated);

            if (
                isset($validated['id_assigne']) &&
                (int) $validated['id_assigne'] !== (int) $ancienAssigne
            ) {
                Notification::create([
                    'id_utilisateur' => $validated['id_assigne'],
                    'titre' => 'Tâche assignée',
                    'message' => "Une tâche vous a été assignée : {$tache->titre}",
                    'type' => 'tache',
                    'lien' => "/taches/{$tache->id_tache}",
                    'lu' => false,
                ]);
            }

            if (
                isset($validated['statut']) &&
                $validated['statut'] !== $ancienStatut
            ) {
                Notification::create([
                    'id_utilisateur' => $tache->id_createur,
                    'titre' => 'Mise à jour d’une tâche',
                    'message' => "Le statut de la tâche « {$tache->titre} » est maintenant « {$validated['statut']} ».",
                    'type' => 'tache',
                    'lien' => "/taches/{$tache->id_tache}",
                    'lu' => false,
                ]);
            }

            $this->historique(
                $utilisateur->id_utilisateur,
                'modification',
                'taches',
                "Modification de la tâche #{$tache->id_tache} : {$tache->titre}"
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Tâche modifiée avec succès.',
            'tache' => $tache->fresh()->load([
                'createur:id_utilisateur,nom,prenom,email',
                'assigne:id_utilisateur,nom,prenom,email',
            ]),
        ]);
    }

    /**
     * Supprimer une tâche.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $this->verifierGestionTaches($utilisateur);

        $tache = Tache::findOrFail($id);

        DB::transaction(function () use ($tache, $utilisateur) {
            $this->historique(
                $utilisateur->id_utilisateur,
                'suppression',
                'taches',
                "Suppression de la tâche #{$tache->id_tache} : {$tache->titre}"
            );

            $tache->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Tâche supprimée avec succès.',
        ]);
    }

    private function role(Utilisateur $utilisateur): string
    {
        return $utilisateur->role?->nom ?? '';
    }

    private function verifierGestionTaches(Utilisateur $utilisateur): void
    {
        if (!in_array($this->role($utilisateur), ['Directeur', 'Responsable'], true)) {
            abort(403, 'Vous n’avez pas l’autorisation de gérer les tâches.');
        }
    }

    private function verifierAccesTache(
        Utilisateur $utilisateur,
        Tache $tache
    ): void {
        $role = $this->role($utilisateur);

        if (
            in_array($role, ['Directeur', 'Responsable', 'RH'], true)
        ) {
            return;
        }

        if (
            (int) $tache->id_assigne !== (int) $utilisateur->id_utilisateur &&
            (int) $tache->id_createur !== (int) $utilisateur->id_utilisateur
        ) {
            abort(403, 'Vous n’avez pas accès à cette tâche.');
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
