<?php

namespace Tests\Feature;

use App\Mail\InvitationEmploye;
use App\Models\Invitation;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Parcours complet invitation -> activation en base SQLite isolée.
 *
 * Le schéma minimal est créé ici car la base de développement provient
 * d'un import SQL (les migrations ne couvrent que les ajouts).
 * Aucune table de la base MySQL de développement n'est touchée.
 */
class InvitationActivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->creerSchemaDeTest();
    }

    private function creerSchemaDeTest(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('employes');
        Schema::dropIfExists('postes');
        Schema::dropIfExists('services');
        Schema::dropIfExists('utilisateurs');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('roles', function ($table) {
            $table->increments('id_role');
            $table->string('nom', 100)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('utilisateurs', function ($table) {
            $table->increments('id_utilisateur');
            $table->unsignedInteger('id_role');
            $table->unsignedInteger('id_responsable')->nullable();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 150)->unique();
            $table->string('email_personnel', 150)->nullable()->unique();
            $table->string('telephone', 30)->nullable();
            $table->string('mot_de_passe');
            $table->string('photo')->nullable();
            $table->string('statut', 20)->default('inactif');
            $table->timestamp('dernier_acces')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('services', function ($table) {
            $table->increments('id_service');
            $table->string('nom', 100);
            $table->string('description')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->timestamps();
        });

        Schema::create('postes', function ($table) {
            $table->increments('id_poste');
            $table->unsignedInteger('id_service');
            $table->string('libelle', 100);
            $table->string('description')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->timestamps();
        });

        Schema::create('employes', function ($table) {
            $table->increments('id_employe');
            $table->unsignedInteger('id_utilisateur');
            $table->unsignedInteger('id_poste');
            $table->string('matricule', 50)->nullable();
            $table->date('date_embauche')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('photo_identite')->nullable();
            $table->timestamps();
        });

        Schema::create('invitations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('id_utilisateur');
            $table->string('email', 150);
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Crée un utilisateur du rôle RH (possède "creer_employe").
     */
    private function creerUtilisateurRH(): Utilisateur
    {
        $role = \App\Models\Role::create([
            'nom' => 'RH',
            'description' => 'Ressources humaines',
        ]);

        return Utilisateur::create([
            'id_role' => $role->id_role,
            'id_responsable' => null,
            'nom' => 'TestRH',
            'prenom' => 'Scenario',
            'email' => 'scenario.rh@gain-office.test',
            'mot_de_passe' => bcrypt('MotDePasse123!'),
            'statut' => 'actif',
        ]);
    }

    /**
     * POST /api/invitations : l'e-mail doit contenir une URL
     * d'activation construite avec FRONTEND_URL et un jeton
     * dont le hash correspond à l'invitation enregistrée.
     */
    public function test_creation_invitation_genere_une_url_d_activation_valide(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->creerUtilisateurRH());

        \App\Models\Service::create(['nom' => 'Informatique']);
        $poste = \App\Models\Poste::create([
            'id_service' => 1,
            'libelle' => 'Developpeur',
        ]);

        $reponse = $this->postJson('/api/invitations', [
            'nom' => 'Activation',
            'prenom' => 'Scenario',
            'email' => 'scenario.employe@gain-office.test',
            'id_role' => 1,
            'id_poste' => $poste->id_poste,
        ]);

        $reponse->assertStatus(201);

        Mail::assertSent(InvitationEmploye::class, function ($mail) {
            $attendu = rtrim(config('app.frontend_url'), '/')
                . '/activation-compte?token=';

            if (!str_starts_with($mail->lienActivation, $attendu)) {
                return false;
            }

            $token = substr($mail->lienActivation, strlen($attendu));

            // Le jeton de l'e-mail doit correspondre au hash stocké.
            return Invitation::where('token', hash('sha256', $token))
                ->where('email', 'scenario.employe@gain-office.test')
                ->exists();
        });
    }

    /**
     * Parcours complet : verification du jeton, activation,
     * reutilisation refusee puis connexion avec le nouveau mot de passe.
     */
    public function test_activation_complete_puis_connexion(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->creerUtilisateurRH());

        \App\Models\Service::create(['nom' => 'Informatique']);
        $poste = \App\Models\Poste::create([
            'id_service' => 1,
            'libelle' => 'Developpeur',
        ]);

        $this->postJson('/api/invitations', [
            'nom' => 'Activation',
            'prenom' => 'Scenario',
            'email' => 'scenario.employe@gain-office.test',
            'id_role' => 1,
            'id_poste' => $poste->id_poste,
        ])->assertStatus(201);

        $invitation = Invitation::first();
        $token = null;
        Mail::assertSent(InvitationEmploye::class, function ($mail) use (&$token) {
            $token = substr(
                $mail->lienActivation,
                strpos($mail->lienActivation, 'token=') + 6
            );

            return true;
        });

        // 1. Verification du jeton par la page React.
        $this->getJson('/api/invitations/' . $token)
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'scenario.employe@gain-office.test');

        // 2. Compte inactif avant activation.
        $this->assertSame(
            'inactif',
            Utilisateur::where('email', 'scenario.employe@gain-office.test')
                ->value('statut')
        );

        // 3. Activation.
        $this->postJson('/api/invitations/activate', [
            'token' => $token,
            'password' => 'Activation123!',
            'password_confirmation' => 'Activation123!',
            'email_personnel' => 'scenario.personnel@example.com',
        ])->assertStatus(200);

        $this->assertSame(
            'actif',
            Utilisateur::where('email', 'scenario.employe@gain-office.test')
                ->value('statut')
        );
        $this->assertNotNull($invitation->fresh()->used_at);

        // 4. Rejet de la reutilisation.
        $this->postJson('/api/invitations/activate', [
            'token' => $token,
            'password' => 'Activation123!',
            'password_confirmation' => 'Activation123!',
            'email_personnel' => 'scenario.autre@example.com',
        ])->assertStatus(410);

        // 5. Connexion avec les nouveaux identifiants.
        $this->postJson('/api/auth/login', [
            'email' => 'scenario.employe@gain-office.test',
            'mot_de_passe' => 'Activation123!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);
    }


    /**
     * Les jetons invalides, expirés ou inconnus produisent
     * des messages JSON explicites.
     */
    public function test_jetons_invalides_ou_expires_produisent_des_erreurs_explicites(): void
    {
        // Jeton totalement inconnu : verification.
        $this->getJson('/api/invitations/jetonabsolumentinconnu12345678901234567890')
            ->assertStatus(404)
            ->assertJsonFragment(['message' => 'Lien d’invitation invalide.']);

        // Jeton totalement inconnu : activation.
        $this->postJson('/api/invitations/activate', [
            'token' => 'jetonabsolumentinconnu12345678901234567890',
            'password' => 'Activation123!',
            'password_confirmation' => 'Activation123!',
            'email_personnel' => 'inconnu@example.com',
        ])->assertStatus(404)
            ->assertJsonFragment(['message' => 'Lien d’invitation invalide.']);

        // Invitation expiree : verification.
        $role = \App\Models\Role::create(['nom' => 'Employe']);
        $utilisateur = Utilisateur::create([
            'id_role' => $role->id_role,
            'id_responsable' => null,
            'nom' => 'Expire',
            'prenom' => 'Scenario',
            'email' => 'scenario.expire@gain-office.test',
            'mot_de_passe' => bcrypt('MotDePasse123!'),
            'statut' => 'inactif',
        ]);

        $jetonExpire = Str::random(64);
        Invitation::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'email' => $utilisateur->email,
            'token' => hash('sha256', $jetonExpire),
            'expires_at' => now()->subHour(),
        ]);

        $this->getJson('/api/invitations/' . $jetonExpire)
            ->assertStatus(410)
            ->assertJsonFragment([
                'message' => 'Cette invitation a expiré. Demande un nouveau lien.',
            ]);
    }

    /**
     * Un lien remplacé par un renvoi n'est pas "utilisé" :
     * le message doit inviter à ouvrir le dernier e-mail reçu,
     * et non affirmer à tort que le lien a déjà servi.
     */
    public function test_lien_remplace_par_un_renvoi_renvoie_un_message_explicite(): void
    {
        $role = \App\Models\Role::create(['nom' => 'Employe']);
        $utilisateur = Utilisateur::create([
            'id_role' => $role->id_role,
            'id_responsable' => null,
            'nom' => 'Remplace',
            'prenom' => 'Scenario',
            'email' => 'scenario.remplace@gain-office.test',
            'mot_de_passe' => bcrypt('MotDePasse123!'),
            'statut' => 'inactif',
        ]);

        $jeton = Str::random(64);
        Invitation::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'email' => $utilisateur->email,
            'token' => hash('sha256', $jeton),
            'expires_at' => now()->addDay(),
            'used_at' => now(),
        ]);

        // Compte toujours inactif : le lien a été remplacé par un renvoi.
        $this->getJson('/api/invitations/' . $jeton)
            ->assertStatus(410)
            ->assertJsonFragment([
                'message' => 'Ce lien n’est plus valide : une invitation plus récente a été envoyée. Ouvre le dernier e-mail d’invitation reçu.',
            ]);

        // Une fois le compte activé, ce lien devient "déjà utilisée".
        $utilisateur->statut = 'actif';
        $utilisateur->save();

        $this->getJson('/api/invitations/' . $jeton)
            ->assertStatus(410)
            ->assertJsonFragment([
                'message' => 'Cette invitation a déjà été utilisée. Le compte est actif : tu peux te connecter.',
            ]);
    }
}

