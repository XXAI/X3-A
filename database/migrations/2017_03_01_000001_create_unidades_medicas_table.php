<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUnidadesMedicasTable extends Migration{
    /**
     * Run the migrations.
     * @table unidades_medicas
     *
     * @return void
     */
    public function up(){
        Schema::create('unidades_medicas', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->string('clues', 12);
            $table->string('nombre', 255);

            $table->primary('clues');

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
       Schema::dropIfExists('unidades_medicas');
     }
}
