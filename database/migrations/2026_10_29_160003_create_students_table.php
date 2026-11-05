<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nómina de alumnos con la estructura de datos de SINIDE. */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 15)->unique();
            $table->string('cuil', 15)->nullable()->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->date('birth_date')->nullable();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('sinide_code', 50)->nullable()->index();
            $table->enum('status', ['activo', 'libre', 'pase', 'baja'])->default('activo'); // estado de matrícula
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
