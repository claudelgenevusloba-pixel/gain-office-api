<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajouts non destructifs pour la hiérarchie des rapports :
     *
     * - utilisateurs.id_responsable : supérieur direct (désigné par le RH)
     * - rapports_taches : rapports autonomes (id_tache nullable),
     *   circuit hiérarchique et échéances
     * - parametre_entreprises : périodicité et règles configurables
     *
     * Aucune colonne ni donnée existante n'est supprimée.
     */
    public function up(): void
    {
        /* 1. Supérieur direct de l'utilisateur */
        if (!Schema::hasColumn('utilisateurs', 'id_responsable')) {
            Schema::table('utilisateurs', function (Blueprint $table) {
                $table->unsignedBigInteger('id_responsable')
                    ->nullable()
                    ->after('id_role');

                $table->foreign('id_responsable')
                    ->references('id_utilisateur')
                    ->on('utilisateurs')
                    ->nullOnDelete();

                $table->index('id_responsable');
            });
        }

        /* 2. Rapports : autonomie, destinataire, échéances, décisions */
        if (!Schema::hasColumn('rapports_taches', 'id_destinataire')) {
            Schema::table('rapports_taches', function (Blueprint $table) {
                /* Rendre la tâche optionnelle : rapport périodique autonome */
                $table->unsignedBigInteger('id_tache')
                    ->nullable()
                    ->change();

                /*
                 * Élargir le workflow : ajout de "rejete" (refus).
                 * Les statuts existants sont conservés à l'identique.
                 */
                $table->enum('statut', [
                    'soumis',
                    'en_cours_examen',
                    'valide',
                    'correction_demandee',
                    'rejete',
                ])->change();

                $table->unsignedBigInteger('id_destinataire')
                    ->nullable()
                    ->after('id_auteur');

                $table->date('date_debut_periode')
                    ->nullable()
                    ->after('commentaire');

                $table->date('date_fin_periode')
                    ->nullable()
                    ->after('date_debut_periode');

                $table->timestamp('date_limite_soumission')
                    ->nullable()
                    ->after('date_fin_periode');

                $table->unsignedTinyInteger('niveau_validation')
                    ->default(1)
                    ->after('statut');

                $table->unsignedBigInteger('id_decideur')
                    ->nullable()
                    ->after('niveau_validation');

                $table->timestamp('date_decision')
                    ->nullable()
                    ->after('id_decideur');

                $table->text('motif_decision')
                    ->nullable()
                    ->after('date_decision');

                $table->timestamp('rappel_le')
                    ->nullable()
                    ->after('motif_decision');

                $table->index('id_destinataire');
                $table->index('id_decideur');
            });
        }

        /* 3. Paramètres entreprise : périodicité configurable */
        if (!Schema::hasColumn('parametre_entreprises', 'periodicite_rapports')) {
            Schema::table('parametre_entreprises', function (Blueprint $table) {
                /* null = non définie (aucune périodicité imposée) */
                $table->string('periodicite_rapports', 20)
                    ->nullable()
                    ->after('notifications_email');

                /* Jours après la fin de période avant échéance (null = aucune) */
                $table->unsignedSmallInteger('delai_soumission_rapports')
                    ->nullable()
                    ->after('periodicite_rapports');

                $table->boolean('rappels_rapports')
                    ->default(true)
                    ->after('delai_soumission_rapports');

                /* Nombre de niveaux de validation du circuit (1 = direct) */
                $table->unsignedTinyInteger('niveaux_validation_rapports')
                    ->default(1)
                    ->after('rappels_rapports');
            });
        }
    }

    /**
     * Aucune suppression : conserver les données existantes.
     */
    public function down(): void
    {
        //
    }
};
