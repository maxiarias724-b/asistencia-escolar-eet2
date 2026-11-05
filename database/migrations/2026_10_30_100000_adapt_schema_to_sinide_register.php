<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes tras analizar el registro mensual real de SINIDE:
     *  - attendances.status pasa de ENUM a string(20) (se agrega "media_falta"; el enum PHP valida los valores).
     *  - attendances.absence_value: valor de inasistencia (C=1, M=0.5, Q=0.25). Permite sumar con SQL
     *    y conservar el valor original de una falta cuando pasa a "justificada".
     *  - students.dni pasa a ser opcional (la nómina de SINIDE muestra solo "Apellido, Nombre").
     *  - non_working_days: feriados / recesos / días sin actividad (los sábados y domingos no cuentan nunca).
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status', 20)->change();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('absence_value', 4, 2)->default(0)->after('status');
        });

        DB::table('attendances')->where('status', 'ausente')->update(['absence_value' => 1]);
        DB::table('attendances')->where('status', 'justificada')->update(['absence_value' => 1]);
        DB::table('attendances')->where('status', 'tardanza')->update(['absence_value' => 0.25]);

        Schema::table('students', function (Blueprint $table) {
            $table->string('dni', 15)->nullable()->change();
        });

        Schema::create('non_working_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_working_days');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('absence_value');
        });
    }
};
