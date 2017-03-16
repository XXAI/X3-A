<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMediosContactoTable extends Migration{
    /**
     * Run the migrations.
     * @table medios_contacto
     *
     * @return void
     */
    public function up(){
        Schema::create('medios_contacto', function(Blueprint $table) {
            $table->engine = 'InnoDB';
        
            $table->integer('id');
            $table->string('servidor_id', 4);
            $table->string('nombre', 45);
            $table->text('icon');
            $table->string('usuario_id', 255);
            
            $table->primary('id');
            
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
       Schema::dropIfExists('medios_contacto');
     }
}
