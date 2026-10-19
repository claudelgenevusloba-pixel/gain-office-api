<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\PasswordReset;
use App\Mail\ReinitialisationMotDePasse;
use Illuminate\Validation\Rule;
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
     * Mettre à jour le profil de l'utilisateur connecté (nom, prénom, email, téléphone).
     */
    public function mettreAJourProfil(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('utilisateurs', 'email')->ignore($utilisateur->id_utilisateur, 'id_utilisateur'),
            ],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]);

        $utilisateur->update($validated);

        $utilisateur->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Votre profil a été mis à jour avec succès.',
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

    /**
     * Demande de réinitialisation de mot de passe (« mot de passe oublié »).
     *
     * - Message générique : ne révèle jamais si l'adresse existe.
     * - Limitation : 3 demandes maximum par adresse + IP (fenêtre de 60 s).
     * - Envoi réel d'un e-mail uniquement pour un compte admissible et actif.
     * - Si l'envoi échoue, une erreur explicite est retournée (aucun faux succès).
     */
    public function motDePasseOublie(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $cleLimite = 'mot-de-passe-oublie:' . md5($email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($cleLimite, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de demandes effectuées. Réessayez dans '
                    . RateLimiter::availableIn($cleLimite) . ' secondes.',
            ], 429);
        }

        $messageGenerique = 'Si une adresse correspond à un compte admissible, '
            . 'les instructions de réinitialisation seront envoyées par e-mail.';

        $utilisateur = Utilisateur::where('email', $email)->first();

        if ($utilisateur && $utilisateur->statut === 'actif') {
            RateLimiter::hit($cleLimite, 60);

            $token = Password::broker()->createToken($utilisateur);

            $lienReset = rtrim(config('app.frontend_url'), '/')
                . '/reinitialisation-mot-de-passe?token=' . urlencode($token)
                . '&email=' . urlencode($utilisateur->email);

            try {
                Mail::to($utilisateur->email)->send(
                    new ReinitialisationMotDePasse(
                        trim($utilisateur->prenom . ' ' . $utilisateur->nom),
                        $lienReset
                    )
                );
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'success' => false,
                    'message' => "Le service d'envoi d'e-mail n'est pas disponible "
                        . 'pour le moment. Veuillez réessayer plus tard.',
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $messageGenerique,
        ]);
    }

    /**
     * Réinitialisation effective du mot de passe avec un jeton valide.
     *
     * Le broker Laravel valide l'existence, l'expiration (60 min) et
     * l'utilisation unique du jeton. Le mot de passe est haché avec Hash::make.
     */
    public function reinitialiserMotDePasse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $statut = Password::broker()->reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['mot_de_passe'],
            ],
            function ($utilisateur, string $motDePasse): void {
                $utilisateur->forceFill([
                    'mot_de_passe' => Hash::make($motDePasse),
                ])->save();

                event(new PasswordReset($utilisateur));
            }
        );

        if ($statut === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe a été réinitialisé avec succès. '
                    . 'Vous pouvez maintenant vous connecter.',
            ]);
        }

        $messages = [
            Password::INVALID_USER =>
                'Cette adresse ne peut pas réinitialiser de mot de passe.',
            Password::INVALID_TOKEN =>
                'Le lien de réinitialisation est invalide ou a expiré.',
            Password::RESET_THROTTLED =>
                'Trop de tentatives. Veuillez refaire une demande dans quelques instants.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$statut]
                ?? 'Le lien de réinitialisation est invalide ou a expiré.',
        ], 422);
    }
}
