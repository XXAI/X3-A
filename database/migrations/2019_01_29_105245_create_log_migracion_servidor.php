<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogMigracionServidor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_migracion_servidor_offline', function (Blueprint $table) {
            $table->increments('id');
            $table->string('servidor_migrado_id', 4);
            $table->decimal('duration',12,3);
            $table->string("status",5)->comment('OK - ERROR');
            $table->string('mensaje', 255)->nullable();
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
    public function down()
    {
        Schema::drop('log_migracion_servidor_offline');
    }
}
