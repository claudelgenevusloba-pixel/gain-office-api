<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correction : les clés étrangères de rapport_validations
     * n'ont pas été appliquées par la migration précédente.
     *
     * Effet : ajout de 2 contraintes d'intégrité (après purge
     * des éventuelles lignes orphelines). Non destructif.
     */
    public function up(): void
    {
        if (!Schema::hasTable('rapport_validations')) {
            return;
        }

        /* Purge des orphelines avant contrainte */
        DB::table('rapport_validations')
            ->whereNotIn(
                'id_rapport',
                DB::table('rapports_taches')->select('id_rapport')
            )
            ->delete();

        $existantes = array_column(
            Schema::getForeignKeys('rapport_validations'),
            'columns'
        );

        if (!in_array(['id_rapport'], array_map(fn ($c) => $c, $existantes), true)) {
            $fk = Schema::getConnection()->getSchemaBuilder();

            DB::statement('
                ALTER TABLE rapport_validations
                ADD CONSTRAINT fk_rapport_validations_rapport
                FOREIGN KEY (id_rapport)
                REFERENCES rapports_taches (id_rapport)
                ON DELETE CASCADE
            ');
        }

        $existantes = array_column(
            Schema::getForeignKeys('rapport_validations'),
            'columns'
        );

        if (!in_array(['id_utilisateur'], array_map(fn ($c) => $c, $existantes), true)) {
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
