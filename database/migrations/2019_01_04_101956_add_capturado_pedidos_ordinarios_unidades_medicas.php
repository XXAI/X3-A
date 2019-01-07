<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCapturadoPedidosOrdinariosUnidadesMedicas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos_ordinarios_unidades_medicas', function (Blueprint $table) {
            $table->decimal('causes_capturado',15,2)->default(0)->after('causes_disponible');
            $table->decimal('no_causes_capturado',15,2)->default(0)->after('no_causes_disponible');
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
            $table->dropColumn('causes_capturado');
            $table->dropColumn('no_causes_capturado');
        });
    }
}
