<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProgramasTable extends Migration
{
    /**
     * Run the migrations.
     * @table programas
     *
     * @return void
     */
    public function up()
    {
        Schema::create('programas', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->increments('id');
		    $table->string('servidor_id', 4);
		    $table->string('clave', 45);
		    $table->string('nombre', 255);
		    $table->boolean('status');
		    $table->string('usuario_id', 255);
		
		    $table->unique('clave','clave_UNIQUE');
		
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
       Schema::dropIfExists('programas');
     }
}
