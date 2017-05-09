<?php

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TiposMovimientosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('tipos_movimientos')->insert([
            [
                'id' => 1,
                'tipo' => "E",
                'nombre' => "Entrada Manual",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 2,
                'tipo' => "S",
                'nombre' => "Salida Manual",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 3,
                'tipo' => "S",
                'nombre' => "Entrega de Pedido",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 4,
                'tipo' => "E",
                'nombre' => "Recepción de Pedido",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 5,
                'tipo' => "S",
                'nombre' => "Surtimiento de Receta",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            
        ]);
    }
}
