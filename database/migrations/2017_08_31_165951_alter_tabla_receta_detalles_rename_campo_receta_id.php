<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablaRecetaDetallesRenameCampoRecetaId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('receta_detalles', function (Blueprint $table)
        {
            $table->dropForeign(['recetas_id']);
            $table->dropColumn('recetas_id');
            $table->string('receta_id',255)->after('servidor_id');

            $table->foreign('receta_id')->references('id')->on('recetas');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('receta_detalles', function (Blueprint $table)
        {
            $table->dropColumn('receta_id');
            $table->dropForeign(['receta_id']);
            $table->string('recetas_id',255)->after('servidor_id');

            $table->foreign('recetas_id')->references('id')->on('recetas');

        });
    }
}
