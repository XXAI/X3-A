<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePartidasTable extends Migration
{
    /**
     * Run the migrations.
     * @table partidas
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partidas', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->increments('id');
		    $table->string('servidor_id', 4);
		    $table->string('clave', 45);
		    $table->string('nombre', 45);
		    $table->string('usuario_id', 255);
		
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
       Schema::dropIfExists('partidas');
     }
}
