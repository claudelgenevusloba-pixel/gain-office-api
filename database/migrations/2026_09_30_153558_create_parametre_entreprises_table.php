<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametre_entreprises', function (Blueprint $table) {
            $table->id('id_parametre');

            $table->string('nom_entreprise', 150);
            $table->string('sigle', 50)->nullable();

            $table->text('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('pays', 100)->default("Côte d'Ivoire");

            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('site_web', 255)->nullable();

            $table->string('logo', 255)->nullable();

            $table->string('devise', 20)->default('FCFA');
            $table->string('langue', 10)->default('fr');
            $table->string('fuseau_horaire', 100)
                ->default('Africa/Abidjan');

            $table->string('format_date', 30)
                ->default('DD/MM/YYYY');

            $table->unsignedInteger('duree_session')
                ->default(120);

            $table->boolean('notifications_systeme')
                ->default(true);

            $table->boolean('notifications_email')
                ->default(true);

            $table->boolean('statut')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'parametre_entreprises'
        );
    }
};
