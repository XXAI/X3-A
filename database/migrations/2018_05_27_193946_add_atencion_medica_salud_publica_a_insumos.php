<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAtencionMedicaSaludPublicaAInsumos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('insumos_medicos', function (Blueprint $table) {
            if(!Schema::hasColumn('insumos_medicos','salud_publica')){
                //$table->dropColumn('salud_publica');
                $table->boolean('salud_publica')->default(0)->after('generico_id');
            }
            
            if(!Schema::hasColumn('insumos_medicos','atencion_medica')){
                //$table->dropColumn('atencion_medica');
                $table->boolean('atencion_medica')->default(0)->after('generico_id');
            }
            
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
            $table->dropColumn('salud_publica');
            $table->dropColumn('atencion_medica');
        });
    }
}
