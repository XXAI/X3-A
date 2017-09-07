<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaSincronizacionesProveedores extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('historial_sincronizaciones_proveedores');

        Schema::create('sincronizaciones_proveedores', function (Blueprint $table) {
            
            $table->string('id',255);
            $table->integer('incremento');
            $table->string('servidor_id',4);

            $table->string('clues', 45);
            $table->string('almacen_id',45);
            $table->integer('proveedor_id')->unsigned();
            $table->string('pedido_id',255);
            $table->date('fecha_surtimiento');
            $table->integer('recetas_validas');
            $table->integer('colectivos_validos');
            $table->integer('recetas_duplicadas');
            $table->integer('colectivos_duplicados');
            $table->text('json');
            $table->string('usuario_id', 255);
             
            $table->timestamps();
            $table->softDeletes();

            $table->primary('id');

            $table->foreign('almacen_id')->references('id')->on('almacenes');
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->foreign('pedido_id')->references('id')->on('pedidos');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sincronizaciones_proveedores');
    }
}
