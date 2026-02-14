<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make CIN mandatory and unique for stagiaires (Moroccan school management).
     * Assigns placeholder CINs to existing null records before enforcing constraints.
     */
    public function up(): void
    {
        // Assign unique placeholder CINs for existing null records
        $stagiaires = DB::table('stagiaires')
            ->whereNull('cin')
            ->orWhere('cin', '')
            ->get();

        foreach ($stagiaires as $i => $s) {
            DB::table('stagiaires')
                ->where('id', $s->id)
                ->update(['cin' => 'XX' . str_pad((string) ($s->id + 100000), 6, '0', STR_PAD_LEFT)]);
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stagiaires MODIFY cin VARCHAR(20) NOT NULL');
        }
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->unique('cin');
        });
    }

    public function down(): void
    {
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->dropUnique(['cin']);
        });
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stagiaires MODIFY cin VARCHAR(20) NULL');
        }
    }
};
