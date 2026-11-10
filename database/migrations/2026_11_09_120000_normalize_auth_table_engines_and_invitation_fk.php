<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invitations', 'personal_access_tokens', 'password_reset_tokens'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $engine = DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->value('ENGINE');

            if ($engine && strtoupper($engine) !== 'INNODB') {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }

        if (!Schema::hasTable('invitations') || !Schema::hasTable('utilisateurs')) {
            return;
        }

        DB::statement("DELETE i FROM invitations AS i LEFT JOIN utilisateurs AS u ON u.id_utilisateur = i.id_utilisateur WHERE u.id_utilisateur IS NULL AND (LOWER(i.email) LIKE '%local%' OR LOWER(i.email) LIKE '%test%')");

        $remainingOrphans = DB::table('invitations as i')
            ->leftJoin('utilisateurs as u', 'u.id_utilisateur', '=', 'i.id_utilisateur')
            ->whereNull('u.id_utilisateur')
            ->count();

        if ($remainingOrphans > 0) {
            throw new RuntimeException('Non-test orphan invitations must be resolved before adding the user foreign key.');
        }

        $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'invitations')
            ->where('COLUMN_NAME', 'id_utilisateur')
            ->where('REFERENCED_TABLE_NAME', 'utilisateurs')
            ->where('REFERENCED_COLUMN_NAME', 'id_utilisateur')
            ->exists();

        if (!$foreignKeyExists) {
            Schema::table('invitations', function (Blueprint $table): void {
                $table->foreign('id_utilisateur', 'invitations_id_utilisateur_foreign')
                    ->references('id_utilisateur')
                    ->on('utilisateurs')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invitations')) {
            $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'invitations')
                ->where('COLUMN_NAME', 'id_utilisateur')
                ->where('REFERENCED_TABLE_NAME', 'utilisateurs')
                ->exists();

            if ($foreignKeyExists) {
                Schema::table('invitations', function (Blueprint $table): void {
                    $table->dropForeign('invitations_id_utilisateur_foreign');
                });
            }
        }
    }
};