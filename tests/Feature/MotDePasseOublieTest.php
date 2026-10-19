<?php

namespace Tests\Feature;

use App\Mail\ReinitialisationMotDePasse;
use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MotDePasseOublieTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id('id_utilisateur');
            $table->unsignedBigInteger('id_role')->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('telephone', 30)->nullable();
            $table->string('mot_de_passe');
            $table->string('photo')->nullable();
            $table->string('statut', 20)->default('inactif');
            $table->timestamp('dernier_acces')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 191)->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function creerUtilisateurActif(
        string $email = 'reset.test@gain-office.test'
    ): Utilisateur {
        return Utilisateur::create([
            'nom' => 'Reset',
            'prenom' => 'Test',
            'email' => $email,
            'mot_de_passe' => bcrypt('AncienMot123!'),
            'statut' => 'actif',
        ]);
    }

    /**
     * Adresse inconnue : reponse generique200 et AUCUN e-mail envoye.
     */
    public function test_adresse_inconnue_reponse_generique(): void
    {
        Mail::fake();

        $this->postJson('/api/mot-de-passe-oublie', [
            'email' => 'inconnu@exemple.test',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        Mail::assertNothingSent();
    }

    /**
     * Parcours complet : demande -> e-mail -> jeton -> nouveau
     * mot de passe -> connexion -> rejeu du jeton refuse.
     */
    public function test_parcours_complet_reinitialisation(): void
    {
        Mail::fake();
        $this->creerUtilisateurActif();

        $this->postJson('/api/mot-de-passe-oublie', [
            'email' => 'reset.test@gain-office.test',
        ])->assertStatus(200);

        $token = null;

        Mail::assertSent(
            ReinitialisationMotDePasse::class,
            function ($mail) use (&$token) {
                $pos = strpos($mail->lienReset, 'token=');

                if ($pos === false) {
                    return false;
                }

                $token = urldecode(
                    explode(
                        '&',
                        substr($mail->lienReset, $pos + 6)
                    )[0]
                );

                return str_contains(
                    $mail->lienReset,
                    '/reinitialisation-mot-de-passe?'
                );
            }
        );

        $this->assertNotEmpty($token);

        $this->postJson('/api/mot-de-passe-reinitialise', [
            'email' => 'reset.test@gain-office.test',
            'token' => $token,
            'mot_de_passe' => 'NouveauMot2026!',
            'mot_de_passe_confirmation' => 'NouveauMot2026!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->postJson('/api/auth/login', [
            'email' => 'reset.test@gain-office.test',
            'mot_de_passe' => 'AncienMot123!',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'reset.test@gain-office.test',
            'mot_de_passe' => 'NouveauMot2026!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->postJson('/api/mot-de-passe-reinitialise', [
            'email' => 'reset.test@gain-office.test',
            'token' => $token,
            'mot_de_passe' => 'EncoreUn2026!',
            'mot_de_passe_confirmation' => 'EncoreUn2026!',
        ])->assertStatus(422);
    }

    /**
     * Jeton invalide puis jeton expire (2 h > expiration de60 min).
     */
    public function test_jeton_invalide_et_jeton_expire(): void
    {
        Mail::fake();
        $this->creerUtilisateurActif();

        $this->postJson('/api/mot-de-passe-reinitialise', [
            'email' => 'reset.test@gain-office.test',
            'token' => 'jeton-bidon',
            'mot_de_passe' => 'NouveauMot2026!',
            'mot_de_passe_confirmation' => 'NouveauMot2026!',
        ])->assertStatus(422);

        $this->postJson('/api/mot-de-passe-oublie', [
            'email' => 'reset.test@gain-office.test',
        ])->assertStatus(200);

        DB::table('password_reset_tokens')
            ->where('email', 'reset.test@gain-office.test')
            ->update(['created_at' => now()->subHours(2)]);

        $this->postJson('/api/mot-de-passe-reinitialise', [
            'email' => 'reset.test@gain-office.test',
            'token' => 'n-importe-quoi',
            'mot_de_passe' => 'NouveauMot2026!',
            'mot_de_passe_confirmation' => 'NouveauMot2026!',
        ])->assertStatus(422);
    }

    /**
     * Regles de validation du nouveau mot de passe.
     */
    public function test_validation_mot_de_passe(): void
    {
        Mail::fake();
        $this->creerUtilisateurActif();

        $this->postJson('/api/mot-de-passe-oublie', [
            'email' => 'reset.test@gain-office.test',
        ])->assertStatus(200);

        $this->postJson('/api/mot-de-passe-reinitialise', [
            'email' => 'reset.test@gain-office.test',
            'token' => 'x',
            'mot_de_passe' => 'court',
            'mot_de_passe_confirmation' => 'court',
        ])->assertStatus(422);

        $this->postJson('/api/mot-de-passe-reinitialise', [
            'email' => 'reset.test@gain-office.test',
            'token' => 'x',
            'mot_de_passe' => 'NouveauMot2026!',
            'mot_de_passe_confirmation' => 'Different2026!',
        ])->assertStatus(422);
    }

    /**
     * Limitation des demandes repetees (3 par adresse + IP).
     */
    public function test_limite_des_demandes_repetees(): void
    {
        Mail::fake();
        $this->creerUtilisateurActif();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/mot-de-passe-oublie', [
                'email' => 'reset.test@gain-office.test',
            ])->assertStatus(200);
        }

        $this->postJson('/api/mot-de-passe-oublie', [
            'email' => 'reset.test@gain-office.test',
        ])->assertStatus(429);
    }
}
