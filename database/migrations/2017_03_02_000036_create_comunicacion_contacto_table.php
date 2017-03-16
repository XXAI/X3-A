<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComunicacionContactoTable extends Migration{
    /**
     * Run the migrations.
     * @table comunicacion_contacto
     *
     * @return void
     */
    public function up(){
        Schema::create('comunicacion_contacto', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->increments('id');
		    $table->integer('incremento');
		    $table->string('servidor_id',255);
		    $table->integer('contacto_id')->unsigned();
		    $table->integer('medio_contacto_id');
		    $table->string('valor', 255);
		    $table->string('usuario_id', 255);
		/*
		    $table->index('medio_contacto_id','fk_datos_contacto_medios_contacto1_idx');
		    $table->index('contacto_id','fk_comunicacion_contacto_contactos1_idx');
		*/
		    $table->foreign('medio_contacto_id')->references('id')->on('medios_contacto');
		    $table->foreign('contacto_id')->references('id')->on('contactos');
		
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
       Schema::dropIfExists('comunicacion_contacto');
     }
}
