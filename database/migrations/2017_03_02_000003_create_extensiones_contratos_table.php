<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExtensionesContratosTable extends Migration{
    /**
     * Run the migrations.
     * @table extensiones_contratos
     *
     * @return void
     */
    public function up(){
        Schema::create('extensiones_contratos', function(Blueprint $table) {
            $table->engine = 'InnoDB';

		    $table->increments('id');
		    $table->string('servidor_id', 4);
		    $table->date('fecha_fin');
		    $table->string('usuario_id', 255);

		
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
       Schema::dropIfExists('extensiones_contratos');
     }
}
