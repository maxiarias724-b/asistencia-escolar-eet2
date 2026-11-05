<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();   // quién tomó / modificó
            $table->date('date')->index();
            $table->enum('activity_type', ['clase', 'educacion_fisica', 'taller']);
            $table->enum('status', ['presente', 'ausente', 'tardanza', 'justificada']);
            $table->text('observation')->nullable();
            $table->timestamps();

            // Un solo registro por alumno, día y actividad.
            $table->unique(['student_id', 'date', 'activity_type'], 'attendances_student_date_activity_unique');
            // Consulta principal de la grilla mensual: curso + actividad + rango de fechas.
            $table->index(['course_id', 'activity_type', 'date'], 'attendances_grid_index');
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
