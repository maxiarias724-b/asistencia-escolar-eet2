<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo Programa QR ("Quiero Regresar") - Actualización 1.1 EET N.º 2.
     * Seguimiento socioeducativo de trayectorias en riesgo y revinculación escolar.
     */
    public function up(): void
    {
        Schema::create('qr_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->date('detection_date')->index();
            $table->integer('consecutive_absences_flag')->default(0);
            $table->string('territorial_team_contact')->nullable();
            $table->date('home_visit_date')->nullable();
            $table->text('pedagogical_support_details')->nullable();
            $table->enum('status', ['evaluacion', 'intervencion_activa', 'revinculado', 'abandono'])
                ->default('evaluacion')
                ->index();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_interventions');
    }
};
