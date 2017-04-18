<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLicitacionPedidosTable extends Migration{
    /**
     * Run the migrations.
     * @table licitacion_pedidos
     *
     * @return void
     */
    public function up(){
       Schema::create('licitacion_pedidos', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->increments('id');
		    $table->integer('contrato_id')->unsigned();
		    $table->string('clave_partida', 45);
		    $table->string('clave_pedido', 45);
		    $table->string('numero_requisision', 45);
		    $table->string('nombre', 255)->nullable();
		    $table->string('usuario_id', 255);
		    
 		
		    //$table->index('contratos_id','fk_licitacion_pedidos_contratos1_idx');
		
		    $table->foreign('contrato_id')->references('id')->on('contratos');
		
		    $table->timestamps();
        $table->softDeletes();
		  });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
     public function down(){
       Schema::dropIfExists('licitacion_pedidos');
     }
}
