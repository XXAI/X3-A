<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMovimientoInsumosTable extends Migration
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
                $table->string('stock_id', 255)->nullable()->change();
                $table->string('clave_insumo_medico', 45)->after('stock_id');
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
                $table->string('stock_id', 255)->change();
                $table->dropColumn('clave_insumo_medico');        
        });
    }
}
