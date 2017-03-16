<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLicitacionPedidosPreciosTable extends Migration{
    /**
     * Run the migrations.
     * @table licitacion_pedidos_precios
     *
     * @return void
     */
    public function up(){
        Schema::create('licitacion_pedidos_precios', function(Blueprint $table) {
            $table->engine = 'InnoDB';
        
            $table->increments('id');
            $table->integer('incremento');
            $table->string('servidor_id', 4);
            $table->integer('licitacion_pedido_id')->unsigned();
            $table->string('lote', 45);
            $table->string('insumo_medico_clave', 45);
            $table->string('marca', 100);
            $table->decimal('precio', 15, 2);
            $table->string('usuario_id', 255);
        
            //$table->index('licitacion_pedido_id','fk_licitacion_pedidos_precios_licitacion_pedidos1_idx');
        
            $table->foreign('licitacion_pedido_id')->references('id')->on('licitacion_pedidos');
        
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
       Schema::dropIfExists('licitacion_pedidos_precios');
     }
}
