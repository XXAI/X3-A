<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMedicamentosTable extends Migration{
    /**
     * Run the migrations.
     * @table medicamentos
     *
     * @return void
     */
    public function up(){
        Schema::create('medicamentos', function(Blueprint $table) {
		    $table->engine = 'InnoDB';
		
		    $table->increments('id');
		    $table->string('insumo_medico_clave', 25);
		    $table->integer('presentacion_id')->unsigned();
		    $table->boolean('es_controlado')->nullable()->default(null);
		    $table->boolean('es_surfactante')->nullable()->default(null);
		    $table->boolean('es_anestesia')->nullable()->default(null);
		    $table->text('descripcion')->nullable();
			//$table->string('concentracion', 45)->nullable();
		    $table->decimal('cantidad_x_envase', 15, 2)->nullable()->default(null);
		    $table->integer('unidad_medida_id')->unsigned()->comment('Del contenido del envase');
		    $table->text('indicaciones')->nullable();
		    $table->integer('via_administracion_id')->unsigned();
		    $table->text('dosis')->nullable();
		
		    $table->unique('insumo_medico_clave','insumo_medico_clave_UNIQUE');
		/*
		    $table->index('presentacion_id','fk_medicamentos_presentaciones_medicamentos1_idx');
		    $table->index('via_administracion_id','fk_medicamentos_vias_administracion1_idx');
		    $table->index('unidad_medida_id','fk_medicamentos_unidades_medida1_idx');
		    $table->index('insumo_medico_clave','fk_medicamentos_insumos_medicos1_idx');
		*/
		    $table->foreign('insumo_medico_clave')->references('clave')->on('insumos_medicos');
		    $table->foreign('presentacion_id')->references('id')->on('presentaciones_medicamentos');
		    $table->foreign('unidad_medida_id')->references('id')->on('unidades_medida');
		    $table->foreign('via_administracion_id')->references('id')->on('vias_administracion');
		
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
       Schema::dropIfExists('medicamentos');
     }
}
