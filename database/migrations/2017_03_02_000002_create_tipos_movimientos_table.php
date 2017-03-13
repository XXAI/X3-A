<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTiposMovimientosTable extends Migration
{
    /**
     * Run the migrations.
     * @table tipos_movimientos
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipos_movimientos', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->increments('id');
		    $table->string('servicio_id', 4);
		    $table->string('tipo_movimento', 45)->comment('* ENTRADA\n* SALIDA\n* AJUSTE\n\n(AJUSTE-> es la merma) ');
		    $table->string('nombre', 255);
		    $table->string('usuario_id', 255);
		
		    $table->timestamps();
		
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
     public function down()
     {
       Schema::dropIfExists('tipos_movimientos');
     }
}
