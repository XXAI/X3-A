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
                'tipo' => "ENTRADA",
                'nombre' => "Entrada Manual",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 2,
                'tipo' => "SALIDA",
                'nombre' => "Salida Manual",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 3,
                'tipo' => "SALIDA",
                'nombre' => "Entrega de Pedido",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 4,
                'tipo' => "ENTRADA",
                'nombre' => "Recepción de Pedido",             
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            
        ]);
    }
}
