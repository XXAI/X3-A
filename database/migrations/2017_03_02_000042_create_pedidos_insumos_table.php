<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosInsumosTable extends Migration
{
    /**
     * Run the migrations.
     * @table pedidos_insumos
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidos_insumos', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->string('id', 255);
		    $table->integer('incremento');
		    $table->string('servidor_id', 4);
		    $table->string('pedido_id', 255);
		    $table->string('insumo_medico_clave', 45);
		    $table->decimal('cantidad_calculada_sistema', 15, 2);
		    $table->decimal('cantidad_solicitada_um', 15, 2)->comment('1Cuando sea un pedido standar de surtimeinto :\ncantidad_solicitada --> ingresó manual y se toma en cuenta');
		    $table->string('cantidad_ajustada_js', 45)->nullable();
		    $table->string('cantidad_ajustada_ca', 45)->nullable();
		    $table->string('usuario_id', 255);
		    
		    $table->primary('id');
		
		   // $table->index('pedido_id','fk_pedidos_articulos_pedidos1_idx');
		
		    $table->foreign('pedido_id')->references('id')->on('pedidos');
		
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
       Schema::dropIfExists('pedidos_insumos');
     }
}
