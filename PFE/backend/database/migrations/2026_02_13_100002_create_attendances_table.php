<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Canonical attendance grid: one row per (seance, stagiaire).
     * Attendance rate = COUNT(status='present'|'retard') / COUNT(*) per stagiaire per module/year.
     * Business rule: < 80% ⇒ "À risque"; block from final exam.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->onDelete('cascade');
            $table->foreignId('stagiaire_id')->constrained('stagiaires')->onDelete('cascade');
            $table->enum('status', ['present', 'absent', 'retard'])->default('present');
            $table->unsignedSmallInteger('retard_minutes')->default(0);
            $table->boolean('justifie')->default(false);
            $table->string('motif', 255)->nullable();
            $table->string('justification_doc', 255)->nullable();
            $table->timestamps();
            $table->unique(['seance_id', 'stagiaire_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['stagiaire_id', 'seance_id']);
            $table->index(['stagiaire_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
