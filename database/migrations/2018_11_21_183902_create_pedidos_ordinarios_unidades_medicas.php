<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosOrdinariosUnidadesMedicas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidos_ordinarios_unidades_medicas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pedido_ordinario_id')->unsigned();
            $table->string('pedido_id', 255)->nullable();
            $table->string('clues', 45);
            $table->decimal('causes_autorizado',15,2)->default(0);
            $table->decimal('causes_modificado',15,2)->default(0);
            $table->decimal('no_causes_autorizado',15,2)->default(0);
            $table->decimal('no_causes_modificado',15,2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pedido_ordinario_id')->references('id')->on('pedidos_ordinarios');
            $table->foreign('pedido_id')->references('id')->on('pedidos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidos_ordinarios_unidades_medicas');
    }
}
