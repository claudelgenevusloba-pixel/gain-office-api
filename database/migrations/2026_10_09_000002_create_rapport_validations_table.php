<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des décisions sur les rapports.
     *
     * Trace chaque étape du circuit hiérarchique :
     * soumission, validation, correction demandée, rejet,
     * remontée au niveau suivant — avec l'auteur de la
     * décision, son niveau, son motif et sa date.
     *
     * Table complémentaire (et non doublon) : la table
     * "historiques" reste l'audit technique général,
     * sans clé étrangère vers les rapports.
     */
    public function up(): void
    {
        if (Schema::hasTable('rapport_validations')) {
            return;
        }

        Schema::create('rapport_validations', function (Blueprint $table) {
            $table->id('id_validation');

            $table->unsignedBigInteger('id_rapport');
            $table->unsignedBigInteger('id_utilisateur');

            /* soumis | valide | correction_demandee | rejete | remonte */
            $table->string('action', 30);

            $table->unsignedTinyInteger('niveau')
                ->default(1);

            $table->text('motif')
                ->nullable();

            $table->timestamps();

            $table->foreign('id_rapport')
                ->references('id_rapport')
                ->on('rapports_taches')
                ->cascadeOnDelete();

            $table->foreign('id_utilisateur')
                ->references('id_utilisateur')
                ->on('utilisateurs')
                ->cascadeOnDelete();

            $table->index('id_rapport');
        });
    }

    /**
     * Aucune suppression : conserver les données existantes.
     */
    public function down(): void
    {
        //
    }
};
