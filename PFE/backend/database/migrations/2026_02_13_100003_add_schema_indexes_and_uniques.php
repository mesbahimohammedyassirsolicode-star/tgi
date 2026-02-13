<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add composite uniques and indexes for frequent queries (GIMS Phase 1).
     */
    public function up(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->unique(['year_start', 'year_end']);
            $table->index('is_current');
        });

        Schema::table('groupe_stagiaire', function (Blueprint $table) {
            $table->unique(['groupe_id', 'stagiaire_id']);
        });

        Schema::table('filieres', function (Blueprint $table) {
            $table->index(['niveau_id', 'code']);
        });

        Schema::table('groupes', function (Blueprint $table) {
            $table->index(['filiere_id', 'annee_scolaire_id']);
            $table->index('year_level');
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->index(['affectation_id', 'date']);
            $table->index('date');
            $table->index('status');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->unique(['evaluation_id', 'stagiaire_id']);
        });

        Schema::table('progressions', function (Blueprint $table) {
            $table->unique(['affectation_id', 'syllabus_item_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at']);
        });

        Schema::table('stagiaires', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('affectations', function (Blueprint $table) {
            $table->index(['formateur_id', 'annee_scolaire_id']);
            $table->index(['groupe_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->dropUnique(['year_start', 'year_end']);
            $table->dropIndex(['is_current']);
        });
        Schema::table('groupe_stagiaire', function (Blueprint $table) {
            $table->dropUnique(['groupe_id', 'stagiaire_id']);
        });
        Schema::table('filieres', function (Blueprint $table) {
            $table->dropIndex(['niveau_id', 'code']);
        });
        Schema::table('groupes', function (Blueprint $table) {
            $table->dropIndex(['filiere_id', 'annee_scolaire_id']);
            $table->dropIndex(['year_level']);
        });
        Schema::table('seances', function (Blueprint $table) {
            $table->dropIndex(['affectation_id', 'date']);
            $table->dropIndex(['date']);
            $table->dropIndex(['status']);
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique(['evaluation_id', 'stagiaire_id']);
        });
        Schema::table('progressions', function (Blueprint $table) {
            $table->dropUnique(['affectation_id', 'syllabus_item_id']);
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
        });
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('affectations', function (Blueprint $table) {
            $table->dropIndex(['formateur_id', 'annee_scolaire_id']);
            $table->dropIndex(['groupe_id', 'module_id']);
        });
    }
};
