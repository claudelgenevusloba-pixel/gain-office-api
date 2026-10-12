<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correction du moteur de rapport_validations.
     *
     * Le serveur utilise MyISAM par défaut : la table a été
     * créée en MyISAM, ce qui a rendu les clés étrangères
     * silencieusement inopérantes (les tables métier existantes
     * sont en InnoDB).
     *
     * Effet : conversion MyISAM -> InnoDB en place (table vide
     * ou quasi vide, conversion non destructive), purge des
     * orphelines, puis ajout effectif des 2 clés étrangères.
     */
    public function up(): void
    {
        if (!Schema::hasTable('rapport_validations')) {
            return;
        }

        /* 1. Conversion de moteur (non destructif) */
        DB::statement(
            'ALTER TABLE rapport_validations ENGINE = InnoDB'
        );

        /* 2. Purge des orphelines avant contrainte */
        DB::table('rapport_validations')
            ->whereNotIn(
                'id_rapport',
                DB::table('rapports_taches')->select('id_rapport')
            )
            ->delete();

        DB::table('rapport_validations')
            ->whereNotIn(
                'id_utilisateur',
                DB::table('utilisateurs')->select('id_utilisateur')
            )
            ->delete();

        /* 3. Clés étrangères (si absentes) */
        $colonnes = array_map(
            fn ($fk) => $fk['columns'][0] ?? '',
            Schema::getForeignKeys('rapport_validations')
        );

        if (!in_array('id_rapport', $colonnes, true)) {
            DB::statement('
                ALTER TABLE rapport_validations
                ADD CONSTRAINT fk_rapport_validations_rapport
                FOREIGN KEY (id_rapport)
                REFERENCES rapports_taches (id_rapport)
                ON DELETE CASCADE
            ');
        }

        if (!in_array('id_utilisateur', $colonnes, true)) {
            DB::statement('
                ALTER TABLE rapport_validations
                ADD CONSTRAINT fk_rapport_validations_utilisateur
                FOREIGN KEY (id_utilisateur)
                REFERENCES utilisateurs (id_utilisateur)
                ON DELETE CASCADE
            ');
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
