<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeController extends Controller
{
    /**
     * Liste de tous les employés.
     */
    public function index(): JsonResponse
    {
        $employes = Employe::with([
            'utilisateur.role',
            'poste.service',
        ])
        ->orderByDesc('id_employe')
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des employés récupérée avec succès.',
            'total' => $employes->count(),
            'employes' => $employes,
        ]);
    }

    /**
     * Afficher un employé.
     */
    public function show(int $id): JsonResponse
    {
        $employe = Employe::with([
            'utilisateur.role',
            'poste.service',
        ])->find($id);

        if (!$employe) {
            return response()->json([
                'success' => false,
                'message' => 'Employé introuvable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employé récupéré avec succès.',
            'employe' => $employe,
        ]);
    }

    /**
     * Créer un employé avec son compte utilisateur.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_role' => ['required', 'integer', 'exists:roles,id_role'],
            'id_poste' => ['required', 'integer', 'exists:postes,id_poste'],

            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:utilisateurs,email'],
            'telephone' => ['nullable', 'string', 'max:30'],

            'matricule' => ['required', 'string', 'max:50', 'unique:employes,matricule'],

            'date_embauche' => ['nullable', 'date'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string'],
            'ville' => ['nullable', 'string', 'max:100'],

            'mot_de_passe' => ['required', 'string', 'min:6'],
        ]);

        try {
            $employe = DB::transaction(function () use ($validated) {

                $utilisateur = Utilisateur::create([
                    'id_role' => $validated['id_role'],
                    'nom' => $validated['nom'],
                    'prenom' => $validated['prenom'],
                    'email' => $validated['email'],
                    'telephone' => $validated['telephone'] ?? null,
                    'mot_de_passe' => Hash::make($validated['mot_de_passe']),
                    'statut' => 'actif',
                ]);

                return Employe::create([
                    'id_utilisateur' => $utilisateur->id_utilisateur,
                    'id_poste' => $validated['id_poste'],
                    'matricule' => $validated['matricule'],
                    'date_embauche' => $validated['date_embauche'] ?? null,
                    'date_naissance' => $validated['date_naissance'] ?? null,
                    'adresse' => $validated['adresse'] ?? null,
                    'ville' => $validated['ville'] ?? null,
                ]);
            });

            $employe->load([
                'utilisateur.role',
                'poste.service',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employé créé avec succès.',
                'employe' => $employe,
            ], 201);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Impossible de créer l’employé.',
            ], 500);
        }
    }

    /**
     * Modifier un employé.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employe = Employe::with('utilisateur')->find($id);

        if (!$employe) {
            return response()->json([
                'success' => false,
                'message' => 'Employé introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'id_role' => ['sometimes', 'integer', 'exists:roles,id_role'],
            'id_poste' => ['sometimes', 'integer', 'exists:postes,id_poste'],

            'nom' => ['sometimes', 'string', 'max:100'],
            'prenom' => ['sometimes', 'string', 'max:100'],

            'email' => [
                'sometimes',
                'email',
                'max:150',
                Rule::unique('utilisateurs', 'email')
                    ->ignore($employe->id_utilisateur, 'id_utilisateur'),
            ],

            'telephone' => ['nullable', 'string', 'max:30'],
            'matricule' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('employes', 'matricule')
                    ->ignore($employe->id_employe, 'id_employe'),
            ],

            'date_embauche' => ['nullable', 'date'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string'],
            'ville' => ['nullable', 'string', 'max:100'],

            'statut' => ['sometimes', Rule::in([
                'actif',
                'inactif',
                'bloque',
            ])],

            'mot_de_passe' => ['nullable', 'string', 'min:6'],
        ]);

        try {
            DB::transaction(function () use ($employe, $validated) {

                $utilisateurData = [];

                foreach ([
                    'id_role',
                    'nom',
                    'prenom',
                    'email',
                    'telephone',
                    'statut',
                ] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $utilisateurData[$field] = $validated[$field];
                    }
                }

                if (!empty($validated['mot_de_passe'])) {
                    $utilisateurData['mot_de_passe'] =
                        Hash::make($validated['mot_de_passe']);
                }

                if (!empty($utilisateurData)) {
                    $employe->utilisateur->update($utilisateurData);
                }

                $employeData = [];

                foreach ([
                    'id_poste',
                    'matricule',
                    'date_embauche',
                    'date_naissance',
                    'adresse',
                    'ville',
                ] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $employeData[$field] = $validated[$field];
                    }
                }

                if (!empty($employeData)) {
                    $employe->update($employeData);
                }
            });

            $employe->refresh()->load([
                'utilisateur.role',
                'poste.service',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employé modifié avec succès.',
                'employe' => $employe,
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier l’employé.',
            ], 500);
        }
    }

    /**
     * Désactiver un employé.
     *
     * On évite la suppression physique afin de conserver
     * l'historique de GAIN OFFICE.
     */
    public function destroy(int $id): JsonResponse
    {
        $employe = Employe::with('utilisateur')->find($id);

        if (!$employe) {
            return response()->json([
                'success' => false,
                'message' => 'Employé introuvable.',
            ], 404);
        }

        $employe->utilisateur->update([
            'statut' => 'inactif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employé désactivé avec succès.',
        ]);
    }
}
