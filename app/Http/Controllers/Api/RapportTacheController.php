<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Historique;
use App\Models\Notification;
use App\Models\RapportTache;
use App\Models\Tache;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RapportTacheController extends Controller
{
    /**
     * Liste des rapports accessibles.
     */
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        $role = $this->role($utilisateur);

        $query = RapportTache::query()
            ->with([
                'tache:id_tache,titre,statut,priorite,id_createur,id_assigne',
                'auteur:id_utilisateur,nom,prenom,email',
            ])
            ->orderByDesc('created_at');

        if ($role === 'Employe') {
            $query->where('id_auteur', $utilisateur->id_utilisateur);
        }

        $rapports = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des rapports récupérée avec succès.',
            'total' => $rapports->count(),
            'rapports' => $rapports,
        ]);
    }

    /**
     * Afficher un rapport.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $rapport = RapportTache::with([
            'tache',
            'auteur:id_utilisateur,nom,prenom,email',
            'piecesJointes',
        ])->findOrFail($id);

        $this->verifierAccesRapport($utilisateur, $rapport);

        return response()->json([
            'success' => true,
            'message' => 'Rapport récupéré avec succès.',
            'rapport' => $rapport,
        ]);
    }

    /**
     * Soumettre un rapport de tâche.
     */
    public function store(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $validated = $request->validate([
            'id_tache' => [
                'required',
                'integer',
                Rule::exists('taches', 'id_tache'),
            ],
            'travail_effectue' => ['required', 'string'],
            'difficultes' => ['nullable', 'string'],
            'temps_passe' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string'],
        ]);

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

        $rapport = DB::transaction(function () use (
            $validated,
            $utilisateur,
            $tache
        ) {
            $rapport = RapportTache::create([
                'id_tache' => $tache->id_tache,
                'id_auteur' => $utilisateur->id_utilisateur,
                'travail_effectue' => $validated['travail_effectue'],
                'difficultes' => $validated['difficultes'] ?? null,
                'temps_passe' => $validated['temps_passe'] ?? null,
                'commentaire' => $validated['commentaire'] ?? null,
                'statut' => 'soumis',
                'date_soumission' => now(),
            ]);

            $tache->update([
                'statut' => 'en_attente_validation',
            ]);

            Notification::create([
                'id_utilisateur' => $tache->id_createur,
                'titre' => 'Rapport de tâche reçu',
                'message' => "Un rapport a été soumis pour la tâche « {$tache->titre} ».",
                'type' => 'rapport',
                'lien' => "/rapports-taches/{$rapport->id_rapport}",
                'lu' => false,
            ]);

            $this->historique(
                $utilisateur->id_utilisateur,
                'creation',
                'rapports_taches',
                "Soumission du rapport #{$rapport->id_rapport} pour la tâche #{$tache->id_tache}"
            );

            return $rapport;
        });

        return response()->json([
            'success' => true,
            'message' => 'Rapport soumis avec succès.',
            'rapport' => $rapport->load([
                'tache',
                'auteur:id_utilisateur,nom,prenom,email',
            ]),
        ], 201);
    }

    /**
     * Modifier un rapport.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $rapport = RapportTache::with('tache')->findOrFail($id);

        $this->verifierAccesRapport($utilisateur, $rapport);

        $role = $this->role($utilisateur);

        if (
            $role === 'Employe' &&
            (int) $rapport->id_auteur !==
            (int) $utilisateur->id_utilisateur
        ) {
            abort(403, 'Vous ne pouvez pas modifier ce rapport.');
        }

        $validated = $request->validate([
            'travail_effectue' => ['sometimes', 'required', 'string'],
            'difficultes' => ['nullable', 'string'],
            'temps_passe' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string'],
            'statut' => [
                'sometimes',
                Rule::in([
                    'soumis',
                    'en_cours_examen',
                    'valide',
                    'correction_demandee',
                ]),
            ],
            'commentaire_validation' => ['nullable', 'string'],
        ]);

        if (
            $role === 'Employe' &&
            isset($validated['statut'])
        ) {
            unset($validated['statut']);
            unset($validated['commentaire_validation']);
        }

        $ancienStatut = $rapport->statut;

        DB::transaction(function () use (
            $rapport,
            $validated,
            $utilisateur,
            $ancienStatut,
            $role
        ) {
            $rapport->update($validated);

            if (
                isset($validated['statut']) &&
                $validated['statut'] !== $ancienStatut &&
                in_array($role, ['Directeur', 'Responsable', 'RH'], true)
            ) {
                $nouveauStatut = $validated['statut'];

                $notificationTitre = match ($nouveauStatut) {
                    'valide' => 'Rapport validé',
                    'correction_demandee' => 'Correction demandée',
                    'en_cours_examen' => 'Rapport en examen',
                    default => 'Rapport mis à jour',
                };

                $notificationMessage = match ($nouveauStatut) {
                    'valide' =>
                        "Votre rapport pour « {$rapport->tache->titre} » a été validé.",
                    'correction_demandee' =>
                        "Une correction est demandée pour votre rapport concernant « {$rapport->tache->titre} ».",
                    'en_cours_examen' =>
                        "Votre rapport concernant « {$rapport->tache->titre} » est en cours d’examen.",
                    default =>
                        "Le statut de votre rapport a été modifié.",
                };

                Notification::create([
                    'id_utilisateur' => $rapport->id_auteur,
                    'titre' => $notificationTitre,
                    'message' => $notificationMessage,
                    'type' => 'rapport',
                    'lien' => "/rapports-taches/{$rapport->id_rapport}",
                    'lu' => false,
                ]);

                if ($nouveauStatut === 'valide') {
                    $rapport->tache->update([
                        'statut' => 'validee',
                        'date_validation' => now(),
                        'commentaire_validation' =>
                            $validated['commentaire_validation'] ?? null,
                    ]);
                }

                if ($nouveauStatut === 'correction_demandee') {
                    $rapport->tache->update([
                        'statut' => 'correction_demandee',
                        'commentaire_validation' =>
                            $validated['commentaire_validation'] ?? null,
                    ]);
                }

                if ($nouveauStatut === 'en_cours_examen') {
                    $rapport->tache->update([
                        'statut' => 'en_attente_validation',
                    ]);
                }

                $rapport->date_traitement = now();
                $rapport->save();
            }

            $this->historique(
                $utilisateur->id_utilisateur,
                'modification',
                'rapports_taches',
                "Modification du rapport #{$rapport->id_rapport}"
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Rapport modifié avec succès.',
            'rapport' => $rapport->fresh()->load([
                'tache',
                'auteur:id_utilisateur,nom,prenom,email',
            ]),
        ]);
    }

    /**
     * Supprimer un rapport.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user();

        $rapport = RapportTache::with('tache')->findOrFail($id);

        $role = $this->role($utilisateur);

        if (
            !in_array($role, ['Directeur', 'Responsable'], true) &&
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

    private function verifierAccesRapport(
        Utilisateur $utilisateur,
        RapportTache $rapport
    ): void {
        $role = $this->role($utilisateur);

        if (
            in_array($role, ['Directeur', 'Responsable', 'RH'], true)
        ) {
            return;
        }

        if (
            (int) $rapport->id_auteur !==
            (int) $utilisateur->id_utilisateur
        ) {
            abort(403, 'Vous n’avez pas accès à ce rapport.');
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
