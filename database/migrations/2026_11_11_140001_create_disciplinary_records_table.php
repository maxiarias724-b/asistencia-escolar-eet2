<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de Amonestaciones (Disciplina) - Actualización 1.1 EET N.º 2.
     * Baja lógica mediante el campo `active` para preservar la trazabilidad institucional.
     */
    public function up(): void
    {
        Schema::create('disciplinary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('date')->index();
            $table->integer('amount');
            $table->text('reason');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['student_id', 'active', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_records');
    }
};
