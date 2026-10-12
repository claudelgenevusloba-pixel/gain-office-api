<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Création de la table des rôles.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_role');

            $table->string('nom', 100);

            $table->text('description')->nullable();


            $table->enum('statut', [
                'actif',
                'inactif'
            ])->default('actif');

            $table->timestamps();

            $table->unique('nom');
            $table->index('niveau_hierarchique');
            $table->index('statut');
        });
    }

    /**
     * Suppression de la table des rôles.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
