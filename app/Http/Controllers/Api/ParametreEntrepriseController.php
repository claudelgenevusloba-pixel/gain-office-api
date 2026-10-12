<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParametreEntreprise;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ParametreEntrepriseController extends Controller
{
    /**
     * Récupérer la configuration actuelle
     */
    public function index(): JsonResponse
    {
        $parametres =
            ParametreEntreprise::query()
                ->where('statut', true)
                ->first();

        if (!$parametres) {
            return response()->json([
                'success' => true,
                'message' =>
                    'Aucun paramètre configuré.',
                'parametres' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Paramètres récupérés avec succès.',
            'parametres' => $parametres,
        ]);
    }

    /**
     * Créer ou mettre à jour la configuration
     */
    public function store(
        Request $request
    ): JsonResponse {
        RolePermissions::exiger(
            $request->user(),
            'modifier_parametres'
        );

        $validator = Validator::make(
            $request->all(),
            [
                'nom_entreprise' =>
                    'required|string|max:150',

                'sigle' =>
                    'nullable|string|max:50',

                'adresse' =>
                    'nullable|string',

                'ville' =>
                    'nullable|string|max:100',

                'pays' =>
                    'nullable|string|max:100',

                'telephone' =>
                    'nullable|string|max:30',

                'email' =>
                    'nullable|email|max:150',

                'site_web' =>
                    'nullable|string|max:255',

                'logo' =>
                    'nullable|string|max:255',

                'devise' =>
                    'nullable|string|max:20',

                'langue' =>
                    'nullable|string|max:10',

                'fuseau_horaire' =>
                    'nullable|string|max:100',

                'format_date' =>
                    'nullable|string|max:30',

                'duree_session' =>
                    'nullable|integer|min:15|max:1440',

                'notifications_systeme' =>
                    'nullable|boolean',

                'notifications_email' =>
                    'nullable|boolean',

                /*
                 * Rapports périodiques — configuration
                 * optionnelle : null = non définie.
                 */
                'periodicite_rapports' =>
                    'nullable|in:quotidienne,hebdomadaire,mensuelle',

                'delai_soumission_rapports' =>
                    'nullable|integer|min:1|max:365',

                'rappels_rapports' =>
                    'nullable|boolean',

                'niveaux_validation_rapports' =>
                    'nullable|integer|min:1|max:5',

                'statut' =>
                    'nullable|boolean',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Les données fournies sont invalides.',
                'errors' =>
                    $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $parametres =
                ParametreEntreprise::query()
                    ->where('statut', true)
                    ->first();

            if ($parametres) {
                $parametres->update(
                    $validator->validated()
                );
            } else {
                $parametres =
                    ParametreEntreprise::create(
                        $validator->validated()
                    );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' =>
                    'Paramètres enregistrés avec succès.',
                'parametres' =>
                    $parametres->fresh(),
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer les paramètres.',
                'error' =>
                    $exception->getMessage(),
            ], 500);
        }
    }
}
