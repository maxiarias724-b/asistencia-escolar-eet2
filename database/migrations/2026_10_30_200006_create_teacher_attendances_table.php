<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('date')->index();
            $table->string('status', 20);
            $table->decimal('absence_value', 4, 2)->default(0);
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'date', 'shift_id'], 'teacher_attendances_teacher_date_shift_unique');
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
