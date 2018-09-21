<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterInsumosMedicosAtencionMedicaSaludPublica extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('insumos_medicos', function (Blueprint $table) {
            $table->boolean('atencion_medica')->default(false)->nullable()->after('generico_id');
            $table->boolean('salud_publica')->default(false)->nullable()->after('atencion_medica');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('insumos_medicos', function (Blueprint $table) {
            $table->dropColumn('atencion_medica');
            $table->dropColumn('salud_publica');
        });
    }
}
