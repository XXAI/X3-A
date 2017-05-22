<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AgregarTipoInsumoAContratosPrecios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contratos_precios', function (Blueprint $table) {
            $table->integer('tipo_insumo_id')->unsigned()->nullable()->after('contrato_id');
            $table->foreign('tipo_insumo_id')->references('id')->on('tipos_insumos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contratos_precios', function (Blueprint $table) {
            $table->dropColumn('tipo_insumo_id');
            $table->dropForeing('tipo_insumo_id');
        });
    }
}
