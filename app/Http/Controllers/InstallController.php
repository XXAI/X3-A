<?php

namespace App\Http\Controllers;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Input;

use Illuminate\Http\Request, DB;
use \Hash, \Config, Carbon\Carbon;
use App\Models\UnidadMedica, App\Models\Servidor, App\Models\Proveedor, App\Models\Almacen;

class InstallController extends Controller
{
    public function runDatabase(Request $request){
        //Si se ejecuta en el servidor offline
        $host      = Config::get('database.connections.mysql.host');
        $database  = Config::get('database.connections.mysql.database');
        $username  = Config::get('database.connections.mysql.username');
        $password  = Config::get('database.connections.mysql.password');
       
        echo shell_exec(env('PATH_MYSQL').'/mysql -h ' . $host . ' -u ' . $username . ' -p' . $password . ' -e "DROP IF EXISTS DATABASE ' . $database . '"');
      
        echo shell_exec(env('PATH_MYSQL').'/mysql -h ' . $host . ' -u ' . $username . ' -p' . $password . ' -e "CREATE DATABASE ' . $database . '"');
        //echo env('PATH_MYSQL').'/mysql -h ' . $host . ' -u ' . $username . ' -p' . $password . ' -e "CREATE DATABASE ' . $database . '"';
        
        \Artisan::call('migrate');
        
        \Artisan::call('db:seed',['--class'=>'DatosCatalogosSeeder']);
      
        //$clues = UnidadMedica::where('es_offline',1)->orderBy('nombre')->get();
        $clues = UnidadMedica::where('es_offline',0)->orderBy('nombre')->get();
        $proveedores = Proveedor::all();
        
        return view('install_step_2',['clues'=>$clues,'proveedores'=>$proveedores]);
    }

    public function configServer(Request $request){
        $parametros = Input::all();
        $mensaje = '';

        $path = base_path('.env');

        $secret = '';
        
        for($i = 0; $i < 10; $i++) {
            $secret .= mt_rand(0, 9);
        }

        if (file_exists($path)) {
            file_put_contents($path, str_replace('SERVIDOR_ID='.env('SERVIDOR_ID'), 'SERVIDOR_ID='.$parametros['id'], file_get_contents($path)));
            file_put_contents($path, str_replace('SECRET_KEY='.env('SECRET_KEY'), 'SECRET_KEY='.$secret, file_get_contents($path)));
            file_put_contents($path, str_replace('CLUES=null', 'CLUES='.$parametros['clues'], file_get_contents($path)));

            // Si no existe la linea en el .env (que debería), lo agregamos
            if(strpos(file_get_contents($path),'SERVIDOR_INSTALADO') === false){
                file_put_contents($path, "\n\nSERVIDOR_INSTALADO=true", FILE_APPEND);
            } else {
                file_put_contents($path, str_replace('SERVIDOR_INSTALADO=false', 'SERVIDOR_INSTALADO=true', file_get_contents($path)));
            }
            

            
        }

        $servidor = Servidor::find(env('SERVIDOR_ID'));

        if(!$servidor){
            $servidor = new Servidor();
        }

        $servidor->id = env('SERVIDOR_ID');
        $servidor->nombre = 'Servidor Nuevo';
        $servidor->secret_key = env('SECRET_KEY');
        $servidor->tiene_internet = 0;
        $servidor->catalogos_actualizados = 0;
        $servidor->version = 1.0;
        $servidor->periodo_sincronizacion = 24;
        $servidor->principal = 0;
        $servidor->save();

        \Artisan::call('db:seed',['--class'=>'UsuariosSeeder']);

        $almacen = Almacen::where('clues',$parametros['clues'])->where('tipo_almacen','ALMPAL')->first();

        if(!$almacen){
            $almacen = new Almacen();
        }

        $almacen->nivel_almacen = 1;
        $almacen->tipo_almacen = 'ALMPAL';
        $almacen->clues = $parametros['clues'];
        $almacen->proveedor_id = $parametros['proveedor'];
        $almacen->subrogado = 0;
        $almacen->unidosis = 0;
        $almacen->nombre = 'ALMACEN PRINCIPAL';
        $almacen->usuario_id = 'root_'.env('SERVIDOR_ID');
        $almacen->save();

        return view('install_complete');
    }
}