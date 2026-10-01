<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les informations nécessaires à la hiérarchie des rôles.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedInteger('niveau_hierarchique')
                ->default(99)
                ->after('description');

            $table->enum('statut', [
                'actif',
                'inactif',
            ])
                ->default('actif')
                ->after('niveau_hierarchique');

            $table->index('niveau_hierarchique');
            $table->index('statut');
        });

        /*
         * Hiérarchie actuelle de GAIN OFFICE.
         *
         * 1 = niveau le plus élevé.
         * 4 = niveau le plus bas.
         */
        DB::table('roles')->where('id_role', 1)->update([
            'niveau_hierarchique' => 1,
            'statut' => 'actif',
        ]);

        DB::table('roles')->where('id_role', 2)->update([
            'niveau_hierarchique' => 2,
            'statut' => 'actif',
        ]);

        DB::table('roles')->where('id_role', 3)->update([
            'niveau_hierarchique' => 3,
            'statut' => 'actif',
        ]);

        DB::table('roles')->where('id_role', 4)->update([
            'niveau_hierarchique' => 4,
            'statut' => 'actif',
        ]);
    }

    /**
     * Annule la modification.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['niveau_hierarchique']);
            $table->dropIndex(['statut']);

            $table->dropColumn([
                'niveau_hierarchique',
                'statut',
            ]);
        });
    }
};
