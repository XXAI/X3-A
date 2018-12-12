<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosAddPresupuestoEjercicioId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->integer('presupuesto_ejercicio_id')->unsigned()->nullable()->after('presupuesto_id');
            $table->foreign('presupuesto_ejercicio_id')->references('id')->on('presupuesto_ejercicio');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign('pedidos_presupuesto_ejercicio_foreign');
            $table->dropColumn('presupuesto_ejercicio_id');
        });
    }
}
