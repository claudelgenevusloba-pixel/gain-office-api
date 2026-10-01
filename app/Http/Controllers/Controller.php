<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Models\Role;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('id_role')
            ->get([
                'id_role',
                'nom',
                'description',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Liste des rôles récupérée avec succès.',
            'total' => $roles->count(),
            'roles' => $roles,
        ]);
    }

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
