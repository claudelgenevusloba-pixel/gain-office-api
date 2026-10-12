<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\Invitation;
use App\Models\Utilisateur;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    /**
     * Créer un nouvel employé et générer son invitation.
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

            /*
             * Supérieur hiérarchique direct —
             * désigné par le RH à la création.
             */
            'id_responsable' => [
                'nullable',
                'integer',
                Rule::exists('utilisateurs', 'id_utilisateur'),
            ],
        ]);

        $email = strtolower(trim($validated['email']));

        /*
        |--------------------------------------------------------------------------
        | Vérifier si l'adresse existe déjà
        |--------------------------------------------------------------------------
        */

        if (Utilisateur::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'Un utilisateur existe déjà avec cette adresse email.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Générer le token d'invitation
        |--------------------------------------------------------------------------
        */

        $token = Str::random(64);

        /*
        |--------------------------------------------------------------------------
        | Création transactionnelle
        |--------------------------------------------------------------------------
        */

        $resultat = DB::transaction(function () use (
            $validated,
            $email,
            $token
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1. Création de l'utilisateur
            |--------------------------------------------------------------------------
            */

            $utilisateur = Utilisateur::create([
                'id_role' => $validated['id_role'],
                'id_responsable' =>
                    $validated['id_responsable'] ?? null,
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'email' => $email,
                'telephone' => $validated['telephone'] ?? null,

                /*
                 * Mot de passe temporaire.
                 * L'employé choisira son vrai mot de passe
                 * lors de l'activation.
                 */
                'mot_de_passe' => Hash::make(Str::random(80)),

               'statut' => 'inactif',
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2. Création de l'employé
            |--------------------------------------------------------------------------
            */

            $employe = Employe::create([
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'id_poste' => $validated['id_poste'],
                'matricule' => $validated['matricule'] ?? null,
                'date_embauche' => $validated['date_embauche'] ?? null,
                'date_naissance' => $validated['date_naissance'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'ville' => $validated['ville'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3. Création de l'invitation
            |--------------------------------------------------------------------------
            */

            $invitation = Invitation::create([
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'email' => $email,

                /*
                 * On ne stocke jamais le token brut.
                 */
                'token' => hash('sha256', $token),

                /*
                 * QR code valable 15 minutes (règle métier validée).
                 */
                'expires_at' => now()->addMinutes(15),
            ]);

            return [
                'utilisateur' => $utilisateur,
                'employe' => $employe,
                'invitation' => $invitation,
                'token' => $token,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Génération du lien React
        |--------------------------------------------------------------------------
        */

        $frontendUrl = config(
            'app.frontend_url',
            'http://localhost:5173'
        );

        $activationUrl =
            rtrim($frontendUrl, '/') .
            '/activation-compte?token=' .
            urlencode($resultat['token']);

        return response()->json([
            'message' => 'Invitation créée avec succès.',

            'data' => [
                'id_utilisateur' =>
                    $resultat['utilisateur']->id_utilisateur,

                'id_employe' =>
                    $resultat['employe']->id_employe,

                'email' =>
                    $resultat['utilisateur']->email,

                'activation_url' =>
                    $activationUrl,

                'expires_at' =>
                    $resultat['invitation']->expires_at,
            ],
        ], 201);
    }


    /**
     * Renvoyer une invitation à un employé dont le compte est inactif.
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

        if (strtolower($utilisateur->statut) === 'actif') {
            return response()->json([
                'message' => 'Ce compte est déjà activé.',
            ], 422);
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use (
            $utilisateur,
            $token
        ) {
            // Invalider les anciennes invitations non utilisées.
            Invitation::where(
                'id_utilisateur',
                $utilisateur->id_utilisateur
            )
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            // Créer une nouvelle invitation valable 15 minutes.
            return Invitation::create([
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'email' => $utilisateur->email,
                'token' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(15),
            ]);
        });

        $frontendUrl = config(
            'app.frontend_url',
            'http://localhost:5173'
        );

        $activationUrl = rtrim($frontendUrl, '/')
            . '/activation-compte?token='
            . urlencode($token);

        return response()->json([
            'message' => 'Nouvelle invitation générée avec succès.',
            'data' => [
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'id_employe' => $employe->id_employe,
                'email' => $utilisateur->email,
                'activation_url' => $activationUrl,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Vérifier une invitation.
     */
    public function verify(string $token): JsonResponse
    {
        $invitation = Invitation::with([
            'utilisateur.employe',
        ])
            ->where(
                'token',
                hash('sha256', $token)
            )
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation invalide.'
            ], 404);
        }

        if ($invitation->used_at !== null) {
            return response()->json([
                'message' => 'Cette invitation a déjà été utilisée.'
            ], 410);
        }

        if ($invitation->expires_at->isPast()) {
            return response()->json([
                'message' => 'Cette invitation a expiré. Veuillez demander un nouveau QR code au RH.'
            ], 410);
        }

        $utilisateur = $invitation->utilisateur;
        $employe = $utilisateur->employe;

        /*
        |--------------------------------------------------------------------
        | Informations de pré-remplissage + champs manquants
        |--------------------------------------------------------------------
        | L'employé complète uniquement ce qui manque. Le rôle, le service,
        | le poste et le matricule sont réservés au RH et ne sont pas
        | renvoyés pour modification.
        */

        $champsManquants = array_values(array_filter([
            !$utilisateur->email_personnel ? 'email_personnel' : null,
            !$utilisateur->telephone ? 'telephone' : null,
            !$employe?->date_naissance ? 'date_naissance' : null,
            !$employe?->adresse ? 'adresse' : null,
            !$employe?->ville ? 'ville' : null,
        ]));

        return response()->json([
            'message' => 'Invitation valide.',

            'data' => [
                'email' => $invitation->email,
                'nom' => $utilisateur->nom,
                'prenom' => $utilisateur->prenom,
                'expires_at' => $invitation->expires_at,

                /* Champs déjà renseignés (lecture seule) */
                'telephone' => $utilisateur->telephone,

                /* Pré-remplissage des infos personnelles */
                'email_personnel' =>
                    $utilisateur->email_personnel,
                'date_naissance' =>
                    $employe?->date_naissance,
                'adresse' => $employe?->adresse,
                'ville' => $employe?->ville,

                'champs_manquants' => $champsManquants,
            ],
        ]);
    }


    /**
     * Activer le compte.
     *
     * L'employé renseigne son e-mail personnel, son téléphone et les
     * informations personnelles manquantes, puis choisit son mot de passe.
     * Son rôle, service, poste et matricule ne peuvent pas être modifiés
     * ici (réservés au RH).
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            /* Identifiant de connexion inchangé : email pro. */
            'email_personnel' => [
                'required',
                'email',
                'max:150',
                'unique:utilisateurs,email_personnel',
            ],

            'telephone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'date_naissance' => ['nullable', 'date'],

            'adresse' => [
                'nullable',
                'string',
                'max:255',
            ],

            'ville' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        /*
        |--------------------------------------------------------------------
        | Activation transactionnelle et verrouillée
        |--------------------------------------------------------------------
        | lockForUpdate empêche deux soumissions concurrentes d'activer
        | le même compte (double utilisation du QR code).
        */

        return DB::transaction(function () use ($validated) {
            $invitation = Invitation::with('utilisateur')
                ->where(
                    'token',
                    hash('sha256', $validated['token'])
                )
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                return response()->json([
                    'message' => 'Invitation invalide.'
                ], 404);
            }

            if ($invitation->used_at !== null) {
                return response()->json([
                    'message' => 'Cette invitation a déjà été utilisée.'
                ], 410);
            }

            if ($invitation->expires_at->isPast()) {
                return response()->json([
                    'message' => 'Cette invitation a expiré. Veuillez demander un nouveau QR code au RH.'
                ], 410);
            }

        /*
        |--------------------------------------------------------------------------
        | Activation du compte
        |--------------------------------------------------------------------------
        */

            $utilisateur = $invitation->utilisateur;
            $employe = $utilisateur->employe;

            $utilisateur->mot_de_passe =
                Hash::make($validated['password']);

            $utilisateur->email_personnel =
                strtolower(trim($validated['email_personnel']));

            $utilisateur->telephone =
                $validated['telephone']
                    ?? $utilisateur->telephone;

            $utilisateur->statut = 'actif';

            $utilisateur->save();

            /*
             * Informations personnelles de l'employé.
             * Seuls les champs personnels sont complétés.
             * Rôle, service, poste et matricule inchangés.
             */
            if ($employe) {
                $employe->date_naissance =
                    $validated['date_naissance']
                        ?? $employe->date_naissance;

                $employe->adresse =
                    $validated['adresse']
                        ?? $employe->adresse;

                $employe->ville =
                    $validated['ville']
                        ?? $employe->ville;

                $employe->save();
            }

            $invitation->used_at = now();
            $invitation->save();

            return response()->json([
                'message' =>
                    'Compte activé avec succès. Vous pouvez maintenant vous connecter.',
                'data' => [
                    'email' => $utilisateur->email,
                ],
            ]);
        });
    }
}
