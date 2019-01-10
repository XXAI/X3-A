<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusPedidosOrdinariosUnidadesMedicas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table) {
            $table->string('status',3)->default("S/P")->comment("S/P = Sin pedido / EX = Expirado / CA = Cancelado / EP = En proceso / FI = Finalizado");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table) {
            $table->dropColumn('status');        
        });
    }
}
