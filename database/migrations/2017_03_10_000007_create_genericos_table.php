<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGenericosTable extends Migration{
    /**
     * Run the migrations.
     * @table genericos
     *
     * @return void
     */
    public function up(){
       Schema::create('genericos', function(Blueprint $table) {
          $table->engine = 'InnoDB';
      
          $table->increments('id');
          $table->string('tipo', 2)->nullable()->default('ME')->comment('ME = Medicamentos\nMC = Material de curación\nAD = Auxiliares de Diagnostico');
          $table->string('nombre', 255)->nullable()->default(null);
          $table->integer('grupo_insumo_id')->unsigned();
          $table->boolean('es_cuadro_basico');
      
        // $table->index('grupos_insumos_id','fk_genericos_grupos_insumos1_idx');
          $table->foreign('grupo_insumo_id')->references('id')->on('grupos_insumos');
      
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
       Schema::dropIfExists('genericos');
     }
}
