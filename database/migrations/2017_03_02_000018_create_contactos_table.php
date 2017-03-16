<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContactosTable extends Migration{
    /**
     * Run the migrations.
     * @table contactos
     *
     * @return void
     */
    public function up(){
        Schema::create('contactos', function(Blueprint $table) {
          $table->engine = 'InnoDB';
      
          $table->increments('id');
          $table->integer('incremento');
          $table->string('servidor_id', 4);
          $table->string('nombre', 45);
          $table->integer('proveedor_id')->unsigned();
          $table->string('direccion', 45);
          $table->string('puesto', 45);
          $table->string('usuario_id', 255);
          
  //$table->primary('id');
      
          //$table->index('proveedor_id','fk_contactos_proveedores1_idx');
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
       Schema::dropIfExists('contactos');
     }
}
