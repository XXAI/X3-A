<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableReporteSalidas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reporte_salidas', function (Blueprint $table) {
            // ...
            $table->string('tipo', 2)->after('clave');
            $table->smallInteger('es_causes')->after('tipo'); 
            $table->date('fecha_realizado')->after('negado'); 
            $table->decimal('surtido_unidosis', 15,2)->after('fecha_realizado')->default(0); 
            $table->decimal('negado_unidosis', 15,2)->after('surtido_unidosis')->default(0);  
            //$table->foreign('clues')->references('clues')->on('unidades_medicas');
            //$table->foreign('clave')->references('clave')->on('insumos_medicos');
            // ...
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reporte_salidas', function (Blueprint $table) {
            // ...
            //$table->dropForeign('reporte_salidas_clave_foreign');
            //$table->dropForeign('reporte_salidas_clues_foreign');
            $table->dropColumn('tipo');
            $table->dropColumn('es_causes');
            $table->dropColumn('fecha_realizado');
            $table->dropColumn('surtido_unidosis');
            $table->dropColumn('negado_unidosis');
            // ...
        });
    }
}
