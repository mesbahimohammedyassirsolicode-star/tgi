<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->foreignId('filiere_id')->nullable()->after('user_id')->constrained('filieres')->onDelete('restrict');
        });

        $firstFiliereId = DB::table('filieres')->orderBy('id')->value('id');
        if ($firstFiliereId) {
            DB::table('stagiaires')->whereNull('filiere_id')->update(['filiere_id' => $firstFiliereId]);
        }

        if ($firstFiliereId) {
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE stagiaires MODIFY filiere_id BIGINT UNSIGNED NOT NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->dropForeign(['filiere_id']);
        });
    }
};
