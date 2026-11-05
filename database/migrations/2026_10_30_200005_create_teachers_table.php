<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 15)->unique();
            $table->string('cuil', 15)->nullable()->unique();
            $table->string('file_number', 50)->nullable()->index();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('specialty_area')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
