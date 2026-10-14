<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvitationEmploye;
use App\Models\Employe;
use App\Models\Invitation;
use App\Models\Utilisateur;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationController extends Controller
{
    /**
     * Créer un employé et lui envoyer une invitation.
     */
    public function store(Request $request): JsonResponse
    {
        RolePermissions::exiger(
            $request->user(),
            'creer_employe'
        );

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'id_role' => [
                'required',
                'integer',
                Rule::exists('roles', 'id_role'),
            ],
            'id_poste' => [
                'required',
                'integer',
                Rule::exists('postes', 'id_poste'),
            ],
            'matricule' => ['nullable', 'string', 'max:50'],
            'date_embauche' => ['nullable', 'date'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
            'id_responsable' => [
                'nullable',
                'integer',
                Rule::exists('utilisateurs', 'id_utilisateur'),
            ],
        ]);

        $validated['email'] = strtolower(trim($validated['email']));

        if (Utilisateur::where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => [
                    'Cette adresse e-mail est déjà utilisée.',
                ],
            ]);
        }

        $token = Str::random(64);

        $resultat = DB::transaction(function () use (
            $validated,
            $token
        ) {
            $utilisateur = Utilisateur::create([
                'id_role' => $validated['id_role'],
                'id_responsable' => $validated['id_responsable'] ?? null,
                'nom' => trim($validated['nom']),
                'prenom' => trim($validated['prenom']),
                'email' => $validated['email'],
                'telephone' => $validated['telephone'] ?? null,
                'mot_de_passe' => Hash::make(Str::random(80)),
                'statut' => 'inactif',
            ]);

            $employe = Employe::create([
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'id_poste' => $validated['id_poste'],
                'matricule' => $validated['matricule'] ?? null,
                'date_embauche' => $validated['date_embauche'] ?? null,
                'date_naissance' => $validated['date_naissance'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'ville' => $validated['ville'] ?? null,
            ]);

            $invitation = Invitation::create([
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'email' => $utilisateur->email,
                'token' => hash('sha256', $token),
                'expires_at' => now()->addHours(48),
            ]);

            return [
                'utilisateur' => $utilisateur,
                'employe' => $employe,
                'invitation' => $invitation,
            ];
        });

        $activationUrl = rtrim(
            config('app.frontend_url', 'http://localhost:5173'),
            '/'
        ) . '/activation-compte?token=' . urlencode($token);

        try {
            Mail::to($resultat['utilisateur']->email)->send(
                new InvitationEmploye(
                    trim(
                        $resultat['utilisateur']->prenom . ' ' .
                        $resultat['utilisateur']->nom
                    ),
                    $activationUrl,
                    $resultat['invitation']->expires_at->format('d/m/Y à H:i')
                )
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => "L'employé a été créé, mais l'e-mail d'invitation n'a pas pu être envoyé. Réessaie avec l'action de renvoi d'invitation.",
                'data' => [
                    'id_utilisateur' => $resultat['utilisateur']->id_utilisateur,
                    'id_employe' => $resultat['employe']->id_employe,
                    'email' => $resultat['utilisateur']->email,
                ],
            ], 503);
        }

        return response()->json([
            'message' => 'Employé créé. L’invitation a été envoyée par e-mail.',
            'data' => [
                'id_utilisateur' => $resultat['utilisateur']->id_utilisateur,
                'id_employe' => $resultat['employe']->id_employe,
                'email' => $resultat['utilisateur']->email,
                'expires_at' => $resultat['invitation']->expires_at,
            ],
        ], 201);
    }

    /**
     * Renvoyer une invitation à un employé existant.
     */
    public function renvoyer(Request $request, int $id): JsonResponse
    {
        RolePermissions::exiger(
            $request->user(),
            'creer_employe'
        );

        $employe = Employe::with('utilisateur')->find($id);

        if (!$employe || !$employe->utilisateur) {
            return response()->json([
                'message' => 'Employé introuvable.',
            ], 404);
        }

        $utilisateur = $employe->utilisateur;

        if (strtolower((string) $utilisateur->statut) === 'actif') {
            return response()->json([
                'message' => 'Ce compte est déjà activé.',
            ], 422);
        }

        $token = Str::random(64);

        $invitation = Invitation::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'email' => $utilisateur->email,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHours(48),
        ]);

        $activationUrl = rtrim(
            config('app.frontend_url', 'http://localhost:5173'),
            '/'
        ) . '/activation-compte?token=' . urlencode($token);

        try {
            Mail::to($utilisateur->email)->send(
                new InvitationEmploye(
                    trim($utilisateur->prenom . ' ' . $utilisateur->nom),
                    $activationUrl,
                    $invitation->expires_at->format('d/m/Y à H:i')
                )
            );
        } catch (Throwable $e) {
            report($e);

            // En cas d'échec, invalider uniquement la nouvelle invitation.
            $invitation->used_at = now();
            $invitation->save();

            return response()->json([
                'message' => "L'e-mail n'a pas pu être envoyé. Les anciennes invitations n'ont pas été invalidées. Réessaie.",
            ], 503);
        }

        // L'e-mail a été envoyé : les anciennes invitations deviennent invalides.
        Invitation::where('id_utilisateur', $utilisateur->id_utilisateur)
            ->where('id', '!=', $invitation->getKey())
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        return response()->json([
            'message' => 'Une nouvelle invitation a été envoyée par e-mail.',
            'data' => [
                'id_employe' => $employe->id_employe,
                'email' => $utilisateur->email,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Message explicite selon la raison du refus du lien :
     * - compte déjà actif  => l'invitation a réellement été utilisée ;
     * - compte inactif     => le lien a été remplacé par un renvoi plus récent.
     */
    private function reponseInvitationUtilisee(Invitation $invitation): JsonResponse
    {
        $compteActif = strtolower((string) $invitation->utilisateur?->statut)
            === 'actif';

        return response()->json([
            'message' => $compteActif
                ? 'Cette invitation a déjà été utilisée. Le compte est actif : tu peux te connecter.'
                : 'Ce lien n’est plus valide : une invitation plus récente a été envoyée. Ouvre le dernier e-mail d’invitation reçu.',
        ], 410);
    }

    /**
     * Vérifier un lien d'activation.
     */
    /**
     * Vérifier un lien d'activation.
     */
    public function verify(string $token): JsonResponse
    {
        $invitation = Invitation::with([
            'utilisateur.employe',
        ])->where(
            'token',
            hash('sha256', $token)
        )->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Lien d’invitation invalide.',
            ], 404);
        }

        if ($invitation->used_at) {
            return $this->reponseInvitationUtilisee($invitation);
        }

        if ($invitation->expires_at->isPast()) {
            return response()->json([
                'message' => 'Cette invitation a expiré. Demande un nouveau lien.',
            ], 410);
        }

        $utilisateur = $invitation->utilisateur;
        $employe = $utilisateur?->employe;

        if (!$utilisateur) {
            return response()->json([
                'message' => 'Le compte associé à cette invitation est introuvable.',
            ], 404);
        }

        if (strtolower((string) $utilisateur->statut) === 'actif') {
            return response()->json([
                'message' => 'Ce compte est déjà activé.',
            ], 410);
        }

        $champsManquants = [];

        if (empty($utilisateur->email_personnel)) {
            $champsManquants[] = 'email_personnel';
        }

        if (empty($utilisateur->telephone)) {
            $champsManquants[] = 'telephone';
        }

        if ($employe && empty($employe->date_naissance)) {
            $champsManquants[] = 'date_naissance';
        }

        if ($employe && empty($employe->adresse)) {
            $champsManquants[] = 'adresse';
        }

        if ($employe && empty($employe->ville)) {
            $champsManquants[] = 'ville';
        }

        return response()->json([
            'message' => 'Invitation valide.',
            'data' => [
                'email' => $utilisateur->email,
                'nom' => $utilisateur->nom,
                'prenom' => $utilisateur->prenom,
                'expires_at' => $invitation->expires_at,
                'telephone' => $utilisateur->telephone,
                'email_personnel' => $utilisateur->email_personnel,
                'date_naissance' => $employe?->date_naissance,
                'adresse' => $employe?->adresse,
                'ville' => $employe?->ville,
                'champs_manquants' => $champsManquants,
            ],
        ]);
    }

    /**
     * Contrôle l'état du jeton avant la validation du formulaire,
     * afin que les erreurs de jeton (invalide, expiré, déjà utilisé)
     * priment sur les erreurs de champs (ex. e-mail personnel déjà pris).
     */
    private function controleTokenAvantValidation(?string $token): ?JsonResponse
    {
        if ($token === null || $token === '') {
            return null;
        }

        $invitation = Invitation::where(
            'token',
            hash('sha256', $token)
        )->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Lien d’invitation invalide.',
            ], 404);
        }

        if ($invitation->used_at) {
            return $this->reponseInvitationUtilisee($invitation);
        }

        if ($invitation->expires_at->isPast()) {
            return response()->json([
                'message' => 'Cette invitation a expiré. Demande un nouveau lien.',
            ], 410);
        }

        return null;
    }


    /**
     * Activer le compte de l'employé.
     */
    public function activate(Request $request): JsonResponse
    {
        $refus = $this->controleTokenAvantValidation(
            trim((string) $request->input('token', ''))
        );

        if ($refus) {
            return $refus;
        }


        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'email_personnel' => [
                'required',
                'email',
                'max:150',
                'unique:utilisateurs,email_personnel',
            ],
            'telephone' => ['nullable', 'string', 'max:30'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['email_personnel'] = strtolower(
            trim($validated['email_personnel'])
        );

        return DB::transaction(function () use ($validated) {
            $invitation = Invitation::with('utilisateur.employe')
                ->where('token', hash('sha256', $validated['token']))
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                return response()->json([
                    'message' => 'Lien d’invitation invalide.',
                ], 404);
            }

            if ($invitation->used_at) {
                return $this->reponseInvitationUtilisee($invitation);
            }

            if ($invitation->expires_at->isPast()) {
                return response()->json([
                    'message' => 'Cette invitation a expiré. Demande un nouveau lien.',
                ], 410);
            }

            $utilisateur = $invitation->utilisateur;

            if (!$utilisateur) {
                return response()->json([
                    'message' => 'Compte utilisateur introuvable.',
                ], 404);
            }

            if (strtolower((string) $utilisateur->statut) === 'actif') {
                return response()->json([
                    'message' => 'Ce compte est déjà activé.',
                ], 410);
            }

            $utilisateur->mot_de_passe = Hash::make($validated['password']);
            $utilisateur->email_personnel = $validated['email_personnel'];
            $utilisateur->telephone = $validated['telephone']
                ?? $utilisateur->telephone;
            $utilisateur->statut = 'actif';
            $utilisateur->save();

            $employe = $utilisateur->employe;

            if ($employe) {
                $employe->date_naissance = $validated['date_naissance']
                    ?? $employe->date_naissance;

                $employe->adresse = $validated['adresse']
                    ?? $employe->adresse;

                $employe->ville = $validated['ville']
                    ?? $employe->ville;

                $employe->save();
            }

            $invitation->used_at = now();
            $invitation->save();

            // Invalider également les autres invitations non utilisées.
            Invitation::where('id_utilisateur', $utilisateur->id_utilisateur)
                ->whereNull('used_at')
                ->where('id', '!=', $invitation->getKey())
                ->update([
                    'used_at' => now(),
                ]);

            return response()->json([
                'message' => 'Compte activé avec succès. Tu peux maintenant te connecter.',
                'data' => [
                    'id_utilisateur' => $utilisateur->id_utilisateur,
                    'email' => $utilisateur->email,
                    'statut' => $utilisateur->statut,
                ],
            ]);
        });
    }
}
