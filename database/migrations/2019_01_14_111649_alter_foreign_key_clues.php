<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterForeignKeyClues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Las que no tienen llave foranea
        Schema::table('actas', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('ajuste_pedido_presupuesto_apartado', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('almacenes', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('almacenes_servicios', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('clues_claves', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('clues_servicios', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('clues_turnos', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('contrato_clues', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('cuadros_distribucion', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('inicializacion_inventario', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('negaciones_insumos', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pacientes', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pacientes_admision', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pedido_cc_clues', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pedidos', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pedidos_insumos_clues', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('personal_clues', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('recetas_digitales', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('sincronizaciones_proveedores', function (Blueprint $table){ $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });

        // Los que se tiene que modificar la llave
        
        Schema::table('ajuste_presupuesto_pedidos_cancelados', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('claves_basicas_unidades_medicas', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('consumos_promedios', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('inventarios', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('log_ejecucion_parches', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('log_sync', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('pedido_presupuesto_apartado', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('presupuesto_unidad_medica', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('reporte_salidas', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('servidores', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('unidad_medica_abasto_configuracion', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('unidad_medica_presupuesto', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
        Schema::table('usuario_unidad_medica', function (Blueprint $table){ $table->dropForeign(['clues']); $table->foreign('clues')->references('clues')->on('unidades_medicas')->onUpdate('cascade'); });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Las que no tienen llave foranea
        Schema::table('actas', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('ajuste_pedido_presupuesto_apartado', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('almacenes', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('almacenes_servicios', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('clues_claves', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('clues_servicios', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('clues_turnos', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('contrato_clues', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('cuadros_distribucion', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('inicializacion_inventario', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('negaciones_insumos', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pacientes', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pacientes_admision', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pedido_cc_clues', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pedidos', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pedidos_insumos_clues', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('personal_clues', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('recetas_digitales', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('sincronizaciones_proveedores', function (Blueprint $table){ $table->dropForeign(['clues']); });

        // Los que se tiene que modificar la llave
        Schema::table('sincronizaciones_proveedores', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('ajuste_presupuesto_pedidos_cancelados', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('claves_basicas_unidades_medicas', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('consumos_promedios', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('inventarios', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('log_ejecucion_parches', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('log_sync', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('pedido_presupuesto_apartado', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('presupuesto_unidad_medica', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('reporte_salidas', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('servidores', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('unidad_medica_abasto_configuracion', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('unidad_medica_presupuesto', function (Blueprint $table){ $table->dropForeign(['clues']); });
        Schema::table('usuario_unidad_medica', function (Blueprint $table){ $table->dropForeign(['clues']); });
    }
}
