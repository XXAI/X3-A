<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {        
        $this->call(ServidoresSeeder::class);
        $this->call(PermisosSeeder::class);
        $this->call(CatalogosSeeder::class);
        $this->call(UsuariosSeeder::class);
        $this->call(CatalogoInsumosSeeder::class);
        $this->call(TiposMovimientosSeeder::class);
        $this->call(TiposPedidosSeeder::class);
        $this->call(ProveedoresSeeder::class);
        $this->call(UnidadesMedicasPresupuestoSeeder::class);
        $this->call(ContratosPreciosSeeder::class);
    }
}
