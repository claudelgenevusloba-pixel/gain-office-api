<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Historique;
use App\Models\Utilisateur;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoriqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        RolePermissions::exiger($utilisateur, 'voir_historique');

        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Historique::query()
            ->with(['utilisateur:id_utilisateur,nom,prenom'])
            ->when($validated['module'] ?? null, function ($builder, $module) {
                $builder->where('module', $module);
            });

        $role = mb_strtolower(trim($utilisateur->role?->nom ?? ''), 'UTF-8');
        if ($role === 'employe' || $role === 'employé') {
            $query->where('id_utilisateur', $utilisateur->id_utilisateur);
        } elseif (in_array($role, ['responsable', 'sous-directeur'], true)) {
            $idsEquipe = Utilisateur::query()
                ->where('id_responsable', $utilisateur->id_utilisateur)
                ->pluck('id_utilisateur')
                ->push($utilisateur->id_utilisateur)
                ->unique()
                ->values();
            $query->whereIn('id_utilisateur', $idsEquipe);
        }

        $historique = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id_historique')
            ->limit($validated['limit'] ?? 100)
            ->get([
                'id_historique',
                'id_utilisateur',
                'action',
                'module',
                'description',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'historique' => $historique,
        ]);
    }
}
