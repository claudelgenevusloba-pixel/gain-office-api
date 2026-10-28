<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('nom', 'Sous-directeur')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'nom' => 'Sous-directeur',
            'description' => 'Sous-directeur',
            'niveau_hierarchique' => 2,
            'statut' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Ne supprime pas un rôle pouvant déjà être lié à des utilisateurs.
    }
};
