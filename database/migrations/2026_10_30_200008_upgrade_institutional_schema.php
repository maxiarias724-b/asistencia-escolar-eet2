<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extiende las tablas existentes hacia la arquitectura configurable de la EET N.º 2:
     *  - users: first_name, last_name y rol abierto a string(20) (director, operador, docente).
     *  - courses: specialty_id, shift_id, active y shift dinámico (string).
     *  - course_user: scope_id, shift_id y activity_type dinámico (string) sin ENUM rígido.
     *  - students: file_number (legajo) y active (borrado lógico).
     *  - attendances: scope_id, activity_id, shift_id y activity_type dinámico (string).
     */
    public function up(): void
    {
        // 1. users
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('operador')->change();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // 2. courses
        Schema::table('courses', function (Blueprint $table) {
            $table->string('shift', 40)->nullable()->change();
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('specialty_id')->nullable()->after('cycle')->constrained('specialties')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->after('shift')->constrained('shifts')->nullOnDelete();
            $table->boolean('active')->default(true)->after('shift_id')->index();
        });

        // 3. course_user (asignaciones delegadas por Ámbito + Curso + Turno opcional)
        Schema::table('course_user', function (Blueprint $table) {
            $table->string('activity_type', 50)->nullable()->change();
        });
        Schema::table('course_user', function (Blueprint $table) {
            $table->foreignId('scope_id')->nullable()->after('course_id')->constrained('scopes')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->after('scope_id')->constrained('shifts')->nullOnDelete();
        });

        // 4. students (legajo y estado lógico active)
        Schema::table('students', function (Blueprint $table) {
            $table->string('file_number', 50)->nullable()->after('cuil')->index();
            $table->boolean('active')->default(true)->after('status')->index();
        });

        // 5. attendances (ámbito, actividad y turno configurables)
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('activity_type', 50)->change();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('scope_id')->nullable()->after('course_id')->constrained('scopes')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->after('scope_id')->constrained('activities')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->after('activity_id')->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('activity_id');
            $table->dropConstrainedForeignId('scope_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['file_number', 'active']);
        });

        Schema::table('course_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('scope_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('specialty_id');
            $table->dropColumn('active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
