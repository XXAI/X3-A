<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddComprometidoDevengadoPedidoOrdinarioUnidadMedica extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table) {
            $table->decimal('causes_comprometido',15,2)->default(0)->after('causes_modificado');
            $table->decimal('causes_devengado',15,2)->default(0)->after('causes_comprometido');
            $table->decimal('causes_disponible',15,2)->default(0)->after('causes_devengado');
            $table->decimal('no_causes_comprometido',15,2)->default(0)->after('no_causes_modificado');
            $table->decimal('no_causes_devengado',15,2)->default(0)->after('no_causes_comprometido');
            $table->decimal('no_causes_disponible',15,2)->default(0)->after('no_causes_devengado');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table) {
            $table->dropColumn('causes_comprometido');
            $table->dropColumn('causes_devengado');
            $table->dropColumn('causes_disponible');
            $table->dropColumn('no_causes_comprometido');
            $table->dropColumn('no_causes_devengado');
            $table->dropColumn('no_causes_disponible');
        });
    }
}
