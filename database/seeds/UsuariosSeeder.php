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
        $super_usuario = DB::table('usuarios')->where('id','root_'.env("SERVIDOR_ID"))->first();
        if(!$super_usuario){
            DB::table('usuarios')->insert([[
                'id' => 'root_'.env("SERVIDOR_ID"),
                'servidor_id' =>  env("SERVIDOR_ID"),
                'password' => Hash::make('ssa.s14l.0ffl1n3.'.env("SERVIDOR_ID")),
                'nombre' => 'Super',
                'apellidos' => 'Usuario',
                'avatar' => 'avatar-circled-root',
                'su' => true,
                'created_at' => new DateTime,
                'updated_at' => new DateTime,
            ]]);
        }
    }
}
