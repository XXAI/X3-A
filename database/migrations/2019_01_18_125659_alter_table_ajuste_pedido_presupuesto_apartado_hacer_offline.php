<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAjustePedidoPresupuestoApartadoHacerOffline extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ajuste_pedido_presupuesto_apartado', function (Blueprint $table) {
            $table->dropPrimary();

            $table->string('id',255)->change();
            $table->integer('incremento')->after('id');
            $table->string('servidor_id', 4)->after('incremento');

            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ajuste_pedido_presupuesto_apartado', function (Blueprint $table) {
            $table->unsignedInteger('id')->change();
            $table->dropColumn('incremento');
            $table->dropColumn('servidor_id');
        });
    }
}
