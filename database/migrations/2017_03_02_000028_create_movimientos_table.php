<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMovimientosTable extends Migration
{
    /**
     * Run the migrations.
     * @table movimientos
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movimientos', function(Blueprint $table) 
		{
		    $table->engine = 'InnoDB';

		    $table->string('id', 255);
		    $table->string('servidor_id', 4);
		    $table->integer('incremento');
		    $table->string('almacenes_id', 255);
		    $table->string('folio', 55);
		    $table->integer('tipo_movimiento_id')->unsigned();
		    $table->string('almacen_origen', 255)->nullable();
		    $table->string('almacen_destino', 255)->nullable();
		    $table->integer('programas_id')->unsigned();
		    $table->string('folio_pedido', 45)->nullable()->default(null);
		    $table->string('factura', 45)->nullable()->default(null);
		    $table->integer('proveedores_id')->unsigned();
		    $table->date('fecha_factura')->nullable()->default(null);
		    $table->string('referencia', 45)->nullable()->default(null);
		    $table->date('fecha_referencia')->nullable()->default(null);
		    $table->date('fecha_movimiento');
		    $table->string('observaciones', 255)->nullable()->default(null);
		    $table->boolean('cancelado');
		    $table->string('observaciones_cancelacion', 255)->nullable();
		    $table->string('usuario_id', 255);
		    
		    $table->primary('id');
		    $table->unique('folio_pedido','numero_pedido_UNIQUE');
	/*	
		    $table->index('programas_id','fk_movimientos_programas1_idx');
		    $table->index('almacenes_id','fk_movimientos_almacenes1_idx');
		    $table->index('almacen_origen','fk_movimientos_almacenes2_idx');
		    $table->index('almacen_destino','fk_movimientos_almacenes3_idx');
		    $table->index('proveedores_id','fk_movimientos_proveedores1_idx');
		    $table->index('tipo_movimiento_id','fk_movimientos_tipos_movimientos1_idx');
	*/	
		    $table->foreign('programas_id')->references('id')->on('programas');
		    $table->foreign('almacenes_id')->references('id')->on('almacenes');
		    $table->foreign('almacen_origen')->references('id')->on('almacenes');
		    $table->foreign('almacen_destino')->references('id')->on('almacenes');
		    $table->foreign('proveedores_id')->references('id')->on('proveedores');
		    $table->foreign('tipo_movimiento_id')->references('id')->on('tipos_movimientos');

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
       Schema::dropIfExists('movimientos');
     }
}
