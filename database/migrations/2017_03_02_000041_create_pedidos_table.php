<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosTable extends Migration{
    /**
     * Run the migrations.
     * @table pedidos
     *
     * @return void
     */
    public function up(){
        Schema::create('pedidos', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->string('id', 255);
		    $table->integer('incremento');
		    $table->string('servidor_id', 4);
		    $table->integer('tipo_insumo_id')->unsigned()->nullable();
		    $table->integer('tipo_pedido_id')->unsigned();
			$table->string('descripcion', 255);
		    $table->string('pedido_padre', 255)->nullable();
		    $table->string('folio', 45);
		    $table->string('almacen_solicitante', 255);
		    $table->string('almacen_proveedor', 255);
		    $table->integer('organismo_dirigido')->unsigned()->nullable();
		    $table->string('acta_id', 255)->nullable();
		    $table->string('status', 45)->comment('AB ABIERTO\n ES EN ESPERA\nPE PENDIENTE\nFI FINALIZADO\nAP APROBADO\nNE NEGADO\n');
			$table->text('observaciones')->nullable();
		    $table->integer('usuario_validacion')->nullable();
		    $table->integer('proveedor_id')->unsigned()->nullable();
		    $table->string('usuario_id', 255);
		    
		    $table->primary('id');
		/*
		    $table->index('almacen_solicitante','fk_pedidos_almacenes1_idx');
		    $table->index('almacen_proveedor','fk_pedidos_almacenes2_idx');
		    $table->index('organismo_dirigido','fk_pedidos_organismos1_idx');
		    $table->index('acta_id','fk_pedidos_actas1_idx');
		    $table->index('pedido_padre','fk_pedidos_pedidos1_idx');
		    $table->index('tipo_insumo_id','fk_pedidos_tipos_insumos1_idx');
		    $table->index('tipo_pedido_id','fk_pedidos_tipos_pedidos1_idx');
		    $table->index('proveedor_id','fk_pedidos_proveedores1_idx');
		*/
		    $table->foreign('almacen_solicitante')->references('id')->on('almacenes');
		
		    $table->foreign('almacen_proveedor')->references('id')->on('almacenes');
		
		    $table->foreign('organismo_dirigido')->references('id')->on('organismos');
		
		    $table->foreign('acta_id')->references('id')->on('actas');
		
		    $table->foreign('pedido_padre')->references('id')->on('pedidos');
		
		    $table->foreign('tipo_insumo_id')->references('id')->on('tipos_insumos');
		
		    $table->foreign('tipo_pedido_id')->references('id')->on('tipos_pedidos');
		
		    $table->foreign('proveedor_id')->references('id')->on('proveedores');
		
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
       Schema::dropIfExists('pedidos');
     }
}
