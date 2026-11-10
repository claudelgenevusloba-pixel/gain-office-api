<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pieces_jointes')) {
            return;
        }

        $hasTaches = Schema::hasTable('taches') && Schema::hasColumn('taches', 'id_tache');
        $hasRapports = Schema::hasTable('rapports_taches') && Schema::hasColumn('rapports_taches', 'id_rapport');
        $hasUtilisateurs = Schema::hasTable('utilisateurs') && Schema::hasColumn('utilisateurs', 'id_utilisateur');

        Schema::create('pieces_jointes', function (Blueprint $table) use ($hasTaches, $hasRapports, $hasUtilisateurs) {
            $table->bigIncrements('id_piece');
            $table->unsignedBigInteger('id_tache')->nullable()->index();
            $table->unsignedBigInteger('id_rapport')->nullable()->index();
            $table->unsignedBigInteger('id_uploader')->nullable()->index();
            $table->string('nom_original', 255);
            $table->string('nom_stockage', 255);
            $table->string('chemin', 500);
            $table->string('extension', 20)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('taille')->default(0);
            $table->timestamps();

            if ($hasTaches) {
                $table->foreign('id_tache')->references('id_tache')->on('taches')->nullOnDelete();
            }
            if ($hasRapports) {
                $table->foreign('id_rapport')->references('id_rapport')->on('rapports_taches')->cascadeOnDelete();
            }
            if ($hasUtilisateurs) {
                $table->foreign('id_uploader')->references('id_utilisateur')->on('utilisateurs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};
