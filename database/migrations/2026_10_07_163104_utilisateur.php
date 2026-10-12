<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute l'adresse e-mail personnelle à l'utilisateur.
     *
     * L'e-mail professionnel (colonne "email") reste l'identifiant
     * de connexion. Cette colonne stocke uniquement l'adresse
     * personnelle renseignée par l'employé lors de l'activation.
     *
     * Effet non destructif : simple colonne nullable ajoutée.
     */
    public function up(): void
    {
        if (Schema::hasColumn('utilisateurs', 'email_personnel')) {
            return;
        }

        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->string('email_personnel', 150)
                ->nullable()
                ->unique()
                ->after('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Aucune suppression : la colonne est conservée pour préserver
     * les données existantes.
     */
    public function down(): void
    {
        //
    }
};
