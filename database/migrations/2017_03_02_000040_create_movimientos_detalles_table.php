<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMovimientosDetallesTable extends Migration
{
    /**
     * Run the migrations.
     * @table movimientos_detalles
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movimientos_detalles', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->string('id', 255);
		    $table->integer('incremento');
		    $table->string('servidor_id', 255);
		    $table->string('movimientos_id', 255);
		    $table->string('stock_id', 255);
		    $table->decimal('cantidad', 15, 2);
		    $table->decimal('precio_unitario', 16, 2);
		    $table->decimal('iva', 5, 2);
		    $table->decimal('precio_total', 16, 2);
		    $table->string('usuario_id', 255);
		    
		    $table->primary('id');
		
		    //$table->index('movimientos_id','fk_movimientos_articulos_movimientos1_idx');
		    //$table->index('stock_id','fk_movimientos_detalles_stock1_idx');
		
		    $table->foreign('movimientos_id')->references('id')->on('movimientos');
		    $table->foreign('stock_id')->references('id')->on('stock');
		
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
       Schema::dropIfExists('movimientos_detalles');
     }
}
