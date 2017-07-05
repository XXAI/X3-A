<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMovimientoInsumosTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('movimiento_insumos', function (Blueprint $table)
        {
                $table->string('modo_salida', 1)->after('clave_insumo_medico');
                $table->string('cantidad_unidosis', 11)->after('cantidad');

         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('movimiento_insumos', function (Blueprint $table)
        {
                  $table->dropColumn('modo_salida');
                  $table->dropColumn('cantidad_unidosis');  
        });
    }
}
