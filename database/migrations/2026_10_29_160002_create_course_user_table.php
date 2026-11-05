<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot de permisos: qué curso + qué actividad puede gestionar cada operador.
     * Un mismo usuario puede tener varias actividades sobre el mismo curso (filas distintas).
     */
    public function up(): void
    {
        Schema::create('course_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->enum('activity_type', ['clase', 'educacion_fisica', 'taller']);
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'activity_type']);
            $table->index(['course_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_user');
    }
};
