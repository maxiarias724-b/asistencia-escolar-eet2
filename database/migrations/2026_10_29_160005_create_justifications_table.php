<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->unique()->constrained()->cascadeOnDelete(); // 1 justificación por asistencia
            $table->foreignId('justified_by')->constrained('users')->restrictOnDelete();
            $table->text('reason_text')->nullable();
            $table->string('certificate_path')->nullable();   // ruta del PNG/JPG/JPEG/PDF
            $table->timestamp('justified_at');
            $table->timestamps();
        });

        // Regla crítica a nivel BD: no se puede justificar "en vacío".
        // Debe existir al menos el texto o el certificado adjunto.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE justifications ADD CONSTRAINT justifications_requires_support_check '
                .'CHECK (reason_text IS NOT NULL OR certificate_path IS NOT NULL)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('justifications');
    }
};
