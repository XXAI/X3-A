<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosOrdinarios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidos_ordinarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('descripcion', 255);
			$table->date('fecha');
            $table->datetime('fecha_expiracion');
            $table->string('usuario_id', 255);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidos_ordinarios');
    }
}
