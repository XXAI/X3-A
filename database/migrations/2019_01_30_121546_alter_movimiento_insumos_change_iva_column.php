<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMovimientoInsumosChangeIvaColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('movimiento_insumos', function (Blueprint $table) {
            $table->decimal('iva', 16, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('movimiento_insumos', function (Blueprint $table) {
            $table->decimal('iva', 5, 2)->change();
        });
    }
}
