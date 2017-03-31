<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProveedoresTable extends Migration{
    /**
     * Run the migrations.
     * @table proveedores
     *
     * @return void
     */
    public function up(){
        Schema::create('proveedores', function(Blueprint $table) {
            $table->engine = 'InnoDB';
        
            $table->increments('id');
            $table->string('razon_social', 255);
            $table->string('rfc', 45);
            $table->string('direccion', 255);
            $table->string('colonia', 150);
            $table->string('codigo_postal', 10);
            $table->string('localidad', 150);
            $table->string('municipio', 150);
            $table->string('estado',45);
            $table->string('pais', 45);
            $table->string('email',255)->nullable();

            $table->boolean('activo');
            $table->string('usuario_id', 255);
        
            //$table->unique('rfc','rfc_UNIQUE');
        
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
       Schema::dropIfExists('proveedores');
     }
}
