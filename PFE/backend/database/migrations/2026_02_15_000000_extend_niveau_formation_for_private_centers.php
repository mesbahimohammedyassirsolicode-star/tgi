<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend niveau_formation to support Bachelor and Master (private training centers).
     * Values: Q, T, TS, BACHELOR, MASTER
     */
    public function up(): void
    {
        if (! Schema::hasColumn('stagiaires', 'niveau_formation')) {
            return;
        }
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stagiaires MODIFY niveau_formation VARCHAR(20) NOT NULL DEFAULT "TS"');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stagiaires', 'niveau_formation')) {
            return;
        }
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stagiaires MODIFY niveau_formation VARCHAR(5) NOT NULL DEFAULT "TS"');
        }
    }
};
