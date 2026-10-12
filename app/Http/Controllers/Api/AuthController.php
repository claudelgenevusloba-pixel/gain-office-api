<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion d'un utilisateur.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $utilisateur = Utilisateur::with('role')
            ->where('email', $validated['email'])
            ->first();

        if (!$utilisateur) {
            throw ValidationException::withMessages([
                'email' => ['Adresse e-mail ou mot de passe incorrect.'],
            ]);
        }

        if (!Hash::check(
            $validated['mot_de_passe'],
            $utilisateur->mot_de_passe
        )) {
            throw ValidationException::withMessages([
                'email' => ['Adresse e-mail ou mot de passe incorrect.'],
            ]);
        }

        if ($utilisateur->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte n’est pas actif.',
            ], 403);
        }

        // Supprime les anciens tokens avant d'en créer un nouveau.
        $utilisateur->tokens()->delete();

        $token = $utilisateur->createToken(
            'gain-office-token'
        )->plainTextToken;

        $utilisateur->update([
            'dernier_acces' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'token' => $token,
            'utilisateur' => [
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'nom' => $utilisateur->nom,
                'prenom' => $utilisateur->prenom,
                'email' => $utilisateur->email,
                'telephone' => $utilisateur->telephone,
                'statut' => $utilisateur->statut,
                'role' => $utilisateur->role ? [
                    'id_role' => $utilisateur->role->id_role,
                    'nom' => $utilisateur->role->nom,
                    'description' => $utilisateur->role->description,
                ] : null,
            ],
        ]);
    }

    /**
     * Retourne l'utilisateur actuellement connecté.
     */
    public function me(Request $request): JsonResponse
    {
        $utilisateur = $request->user()->load('role', 'employe');

        return response()->json([
            'success' => true,
            'utilisateur' => $utilisateur,
        ]);
    }

    /**
     * Changer le mot de passe de l'utilisateur connecté.
     */
    public function changerMotDePasse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ancien_mot_de_passe' => ['required', 'string'],
            'nouveau_mot_de_passe' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $utilisateur = $request->user();

        if (
            !Hash::check(
                $validated['ancien_mot_de_passe'],
                $utilisateur->mot_de_passe
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Votre mot de passe actuel est incorrect.',
                'errors' => [
                    'ancien_mot_de_passe' => [
                        'Votre mot de passe actuel est incorrect.',
                    ],
                ],
            ], 422);
        }

        $utilisateur->mot_de_passe = Hash::make(
            $validated['nouveau_mot_de_passe']
        );

        $utilisateur->save();

        return response()->json([
            'success' => true,
            'message' => 'Votre mot de passe a été modifié avec succès.',
        ]);
    }

    /**
     * Déconnexion de l'utilisateur.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
