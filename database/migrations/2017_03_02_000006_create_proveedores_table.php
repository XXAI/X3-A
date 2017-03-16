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
            $table->string('servidor_id', 4);
            $table->string('razon_social', 255);
            $table->string('rfc', 45);
            $table->string('direccion', 255);
            $table->boolean('activo');
            $table->string('usuario_id', 255);
        
            $table->unique('rfc','rfc_UNIQUE');
            $table->unique('razon_social','nombre_UNIQUE');
        
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
