<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlmacenesTable extends Migration
{
    /**
     * Run the migrations.
     * @table almacenes
     *
     * @return void
     */
    public function up()
    {
       Schema::create('almacenes', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->string('id', 255);
		    $table->integer('incremento');
		    $table->string('servidor_id', 4);
		    $table->string('tipo_almacen', 100)->comment('* ESTATAL\n* JURISDICCIONAL\n* UNIDAD_MEDICA\n* LABORATORIO\n* FARMACIA\n* CENDIS_UNIDOSIS');
		    $table->string('clues', 45);
		    $table->boolean('subrogado');
		    $table->integer('proveedor_id')->unsigned();
		    $table->boolean('unidosis');
		    $table->string('clave', 45);
		    $table->string('nombre', 255);
		    $table->string('usuario_id', 255);
		    
		    $table->primary('id');
		
		    $table->index('proveedor_id','fk_almacenes_proveedores1_idx');
		
		    $table->foreign('proveedor_id')->references('id')->on('proveedores');
		
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
       Schema::dropIfExists('almacenes');
     }
}
