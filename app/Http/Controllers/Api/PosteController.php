<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poste;
use App\Models\Service;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PosteController extends Controller
{
    /**
     * Liste de tous les postes.
     */
    public function index(): JsonResponse
    {
        $postes = Poste::query()
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

    /**
     * Créer un poste.
     */
    public function store(Request $request): JsonResponse
    {
        RolePermissions::exiger(
            $request->user(),
            'gerer_postes'
        );

        $validator = Validator::make(
            $request->all(),
            [
                'id_service' => 'required|integer|exists:services,id_service',
                'libelle' => 'required|string|max:150',
                'description' => 'nullable|string',
                'statut' => 'nullable|in:actif,inactif',
            ],
            [
                'id_service.required' => 'Le service est obligatoire.',
                'id_service.integer' => 'Le service sélectionné est invalide.',
                'id_service.exists' => 'Le service sélectionné n’existe pas.',
                'libelle.required' => 'Le libellé du poste est obligatoire.',
                'libelle.string' => 'Le libellé du poste doit être une chaîne de caractères.',
                'libelle.max' => 'Le libellé du poste ne doit pas dépasser 150 caractères.',
                'statut.in' => 'Le statut doit être actif ou inactif.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = Service::find($request->id_service);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Le service sélectionné est introuvable.',
            ], 404);
        }

        if ($service->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de créer un poste dans un service inactif.',
            ], 422);
        }

        $posteExiste = Poste::query()
            ->where('id_service', $request->id_service)
            ->whereRaw(
                'LOWER(libelle) = ?',
                [mb_strtolower($request->libelle)]
            )
            ->exists();

        if ($posteExiste) {
            return response()->json([
                'success' => false,
                'message' => 'Ce poste existe déjà dans ce service.',
            ], 409);
        }

        $poste = Poste::create([
            'id_service' => $request->id_service,
            'libelle' => $request->libelle,
            'description' => $request->description,
            'statut' => $request->statut ?? 'actif',
        ]);

        $poste->load('service');

        return response()->json([
            'success' => true,
            'message' => 'Poste créé avec succès.',
            'poste' => $poste,
        ], 201);
    }

    /**
     * Afficher un poste.
     */
    public function show(int $id): JsonResponse
    {
        $poste = Poste::query()
            ->with('service')
            ->find($id);

        if (!$poste) {
            return response()->json([
                'success' => false,
                'message' => 'Poste introuvable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poste récupéré avec succès.',
            'poste' => $poste,
        ]);
    }

    /**
     * Modifier un poste.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        RolePermissions::exiger(
            $request->user(),
            'gerer_postes'
        );

        $poste = Poste::find($id);

        if (!$poste) {
            return response()->json([
                'success' => false,
                'message' => 'Poste introuvable.',
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'id_service' => 'sometimes|required|integer|exists:services,id_service',
                'libelle' => 'sometimes|required|string|max:150',
                'description' => 'nullable|string',
                'statut' => 'sometimes|required|in:actif,inactif',
            ],
            [
                'id_service.exists' => 'Le service sélectionné n’existe pas.',
                'libelle.required' => 'Le libellé du poste est obligatoire.',
                'libelle.max' => 'Le libellé du poste ne doit pas dépasser 150 caractères.',
                'statut.in' => 'Le statut doit être actif ou inactif.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $idService = $request->has('id_service')
            ? $request->id_service
            : $poste->id_service;

        $service = Service::find($idService);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Le service sélectionné est introuvable.',
            ], 404);
        }

        if ($service->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d’associer le poste à un service inactif.',
            ], 422);
        }

        $libelle = $request->has('libelle')
            ? $request->libelle
            : $poste->libelle;

        $posteExiste = Poste::query()
            ->where('id_service', $idService)
            ->whereRaw(
                'LOWER(libelle) = ?',
                [mb_strtolower($libelle)]
            )
            ->where('id_poste', '!=', $poste->id_poste)
            ->exists();

        if ($posteExiste) {
            return response()->json([
                'success' => false,
                'message' => 'Ce poste existe déjà dans ce service.',
            ], 409);
        }

        $poste->update(
            $request->only([
                'id_service',
                'libelle',
                'description',
                'statut',
            ])
        );

        $poste->load('service');

        return response()->json([
            'success' => true,
            'message' => 'Poste modifié avec succès.',
            'poste' => $poste,
        ]);
    }

    /**
     * Désactiver un poste.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        RolePermissions::exiger(
            $request->user(),
            'gerer_postes'
        );

        $poste = Poste::find($id);

        if (!$poste) {
            return response()->json([
                'success' => false,
                'message' => 'Poste introuvable.',
            ], 404);
        }

        $poste->update([
            'statut' => 'inactif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Poste désactivé avec succès.',
            'poste' => $poste->fresh('service'),
        ]);
    }
}
