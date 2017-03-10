<?php

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('permisos')->insert([
            [
                'id' => "mGKikN0aJaeF2XrHwwYK3XNw0f9CSZDe",//str_random(32)
                'descripcion' => "Ver usuarios",
                'grupo' => "Administrador",     
                'su' => false,           
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "xJqy3csU5WyOX7pmXL7VBs680uTVGxU3",
                'descripcion' => "Agregar usuarios",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "dlV1H4gX0nqEgHauHC8BRIlwl6SGUoUt",
                'descripcion' => "Editar usuarios",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "U2f38x7uVNFMQMQFeUjrFOJelBt3tbCA",
                'descripcion' => "Eliminar usuarios",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "ICmOKw3HxhgRna4a78OP0QmKrIX0bNsp",
                'descripcion' => "Ver roles",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "hA2wLCnNDQ5Z1OtvdW8lgX5D6wkM6zBE",
                'descripcion' => "Agregar roles",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "9Z9XwxmNbrISFyF5sJkU3HyGUssSPfU5",
                'descripcion' => "Editar roles",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "qkm5NZQYN4ePs43axd0MgERhx5ia8rwh",
                'descripcion' => "Eliminar roles",
                'grupo' => "Administrador",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "DYwQAxJbpHWw07zT09scEogUeFKFdGSu",
                'descripcion' => "Ver permisos",
                'grupo' => "Super Usuario",
                'su' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "dsvilCCjRtFJ9QXuunzbR1CYLKJQ5MYA",
                'descripcion' => "Agregar permisos",
                'grupo' => "Super Usuario",
                'su' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "l4SBZlAj2SYrdFi747wo8yuvJ0sAE0U9",
                'descripcion' => "Editar permisos",
                'grupo' => "Super Usuario",
                'su' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => "ay8Rv5iuSih9ZoHVSFwZ8Ic4memuYm4O",
                'descripcion' => "Eliminar permisos",
                'grupo' => "Super Usuario",
                'su' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
