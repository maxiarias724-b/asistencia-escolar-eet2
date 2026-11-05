<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('year');                         // 1 a 6
            $table->string('division', 10);                              // "1", "2", "3"...
            $table->enum('cycle', ['primer_ciclo', 'segundo_ciclo']);    // 1°-2° / 3°-6°
            $table->enum('shift', ['manana', 'tarde', 'vespertino']);
            $table->timestamps();

            $table->unique(['year', 'division', 'shift']);
            $table->index(['cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
