<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosOrdinariosAddTipoPedidoId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos_ordinarios', function (Blueprint $table) {
            //
            $table->string('tipo_pedido_id', 4)->nullable()->after('presupuesto_ejercicio_id')->comment('PO Pedido Ordinario\n PXT Pedido extraordinario\n');
            $table->foreign('tipo_pedido_id')->references('id')->on('tipos_pedidos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos_ordinarios', function (Blueprint $table) {
            $table->dropForeing(['tipo_pedido_id']);
            $table->dropColumn('tipo_pedido_id');
        });
    }
}
