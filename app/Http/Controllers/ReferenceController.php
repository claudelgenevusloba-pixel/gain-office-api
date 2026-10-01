<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Models\Role;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    /**
     * Récupérer tous les rôles actifs.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->where('statut', 'actif')
            ->orderBy('niveau_hierarchique')
            ->orderBy('nom')
            ->get([
                'id_role',
                'nom',
                'description',
                'niveau_hierarchique',
                'statut',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Liste des rôles récupérée avec succès.',
            'total' => $roles->count(),
            'roles' => $roles,
        ]);
    }

    /**
     * Récupérer uniquement les rôles qu'un utilisateur
     * connecté est autorisé à attribuer.
     *
     * Règle :
     * un utilisateur peut attribuer uniquement un rôle
     * situé à un niveau hiérarchique inférieur au sien.
     */
    public function rolesDisponibles(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        if (!$utilisateur) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $roleUtilisateur = $utilisateur->role;

        if (!$roleUtilisateur) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun rôle associé à cet utilisateur.',
                'roles' => [],
            ], 403);
        }

        $roles = Role::query()
            ->where('statut', 'actif')
            ->where(
                'niveau_hierarchique',
                '>',
                $roleUtilisateur->niveau_hierarchique
            )
            ->orderBy('niveau_hierarchique')
            ->orderBy('nom')
            ->get([
                'id_role',
                'nom',
                'description',
                'niveau_hierarchique',
                'statut',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Liste des rôles disponibles récupérée avec succès.',
            'role_connecte' => [
                'id_role' => $roleUtilisateur->id_role,
                'nom' => $roleUtilisateur->nom,
                'niveau_hierarchique' => $roleUtilisateur->niveau_hierarchique,
            ],
            'total' => $roles->count(),
            'roles' => $roles,
        ]);
    }

    /**
     * Récupérer les services actifs avec leurs postes actifs.
     */
    public function services(): JsonResponse
    {
        $services = Service::query()
            ->where('statut', 'actif')
            ->with([
                'postes' => function ($query) {
                    $query
                        ->where('statut', 'actif')
                        ->orderBy('libelle');
                },
            ])
            ->orderBy('nom')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des services récupérée avec succès.',
            'total' => $services->count(),
            'services' => $services,
        ]);
    }

    /**
     * Récupérer tous les postes actifs.
     */
    public function postes(): JsonResponse
    {
        $postes = Poste::query()
            ->where('statut', 'actif')
            ->with('service')
            ->orderBy('libelle')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des postes récupérée avec succès.',
            'total' => $postes->count(),
            'postes' => $postes,
        ]);
    }
}
