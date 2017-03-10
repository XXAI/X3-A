<?php

use Illuminate\Database\Seeder;

class UsuariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('usuarios')->insert([
            [
                'id' => 'root',
                'servidor_id' =>  env("SERVIDOR_ID"),
                'password' => Hash::make('sumami'),
                'nombre' => 'Super',
                'apellidos' => 'Usuario',
                'avatar' => 'avatar-circled-root',
                'su' => true
            ]
        ]);
    }
}
