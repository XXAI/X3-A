<?php

namespace App\Http\Controllers;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Http\Request;
use \Hash, \Config, Carbon\Carbon;
use App\Models\Usuario, App\Models\Permiso, App\Models\Almacen, App\Models\UnidadMedica, App\Models\Proveedor, App\Models\LogInicioSesion;

class AutenticacionController extends Controller
{
    public function autenticar(Request $request)
    {
        
        // grab credentials from the request
        $credentials = $request->only('id', 'password');

        try {

            //$usuarios = Usuario::where('su',0)->get();
            /*
            $usuarios_hash = [
                'hbcoxchuc' => 'hbcoxchuc',
                'hbcberriozabal' => 'hbcberriozabal',
                'hbcchiapacorzo' => 'hbcchiapacorzo',
                'hbcrosas' => 'hbcrosas'
            ];
            
            foreach ($usuarios_hash as $usuario => $hash) {
                //$usuario->password = str_replace(['á','é','í','ó','ú',' ','.','(',')'],['a','e','i','o','u'], mb_strtolower($usuario->nombre,'UTF-8'));
                //$usuario->password = Hash::make($usuario->password);
                //$usuario->save();
                $usuarios_hash[$usuario] = Hash::make($hash);
            }

            return response()->json(['error' => 'invalid_credentials', 'data'=>$usuarios_hash], 401); 
            */
           
            $usuario = Usuario::where('id',$credentials['id'])->first();

            if(!$usuario) {                
                return response()->json(['error' => 'invalid_credentials'], 401); 
            }

            $log_usuario = new LogInicioSesion();
            $log_usuario->usuario_id = $usuario->id;
            $log_usuario->servidor_id = $usuario->servidor_id;
            $log_usuario->ip = $request->ip();
            $log_usuario->navegador = $request->header('User-Agent');
            $log_usuario->updated_at = Carbon::now();

            if(Hash::check($credentials['password'], $usuario->password)){
                $lista_permisos = "";
                $modulo_inicio = null;
                if ($usuario->su) {
                    $permisos = Permiso::all();
                    foreach ( $permisos as $permiso){
                        if ($lista_permisos != "") {
                            $lista_permisos .= "|";
                        }
                        $lista_permisos.=$permiso->id;
                    }
                } else {
                    $roles = $usuario->roles;
                    
                    foreach ( $roles as $rol){
                        $modulo_inicio = $rol->modulo_inicio;
                        $permisos = $rol->permisos;
                        foreach ( $permisos as $permiso){
                            if ($lista_permisos != "") {
                                $lista_permisos .= "|";
                            }
                            $lista_permisos.=$permiso->id;
                        }
                    }

                    if($usuario->modulo_inicio){
                        $modulo_inicio = $usuario->modulo_inicio;
                    }
                }
                
                $claims = [
                    "sub" => 1,
                    "id" => $usuario->id,
                    //"nombre" => $usuario->nombre,
                    //"apellidos" => $usuario->apellidos,
                    //"permisos" => $lista_permisos
                ];

                $unidades_medicas = [];
               
                if( $usuario->su ){
                    $unidades_medicas = UnidadMedica::has('almacenes')->with('almacenes')->get();
                } else {
                    $ums = $usuario->unidadesMedicas;
                    $almacenes = $usuario->almacenes()->lists("almacenes.id");
                    foreach($ums as $um){
                        $almacenes_res = $um->almacenes()->whereIn('almacenes.id',$almacenes)->get();
                        $um->almacenes = $almacenes_res;
                        //$almacenes = $um->almacenes()->has('usuarios')->where('usuario_id',$usuario->id)->get();
                    }
                    $unidades_medicas = $ums;
                }
                

                $usuario_data = [
                    "id" => $usuario->id,
                    "nombre" => $usuario->nombre,
                    "apellidos" => $usuario->apellidos,
                    "avatar" => $usuario->avatar,
                    "permisos" => $lista_permisos,                    
                    "unidades_medicas" =>  $unidades_medicas,
                    "modulo_inicio" => $modulo_inicio
                ];

                if($usuario->su){ //Harima: se agrego para los modulos de los proveedores
                    $usuario_data["proveedores"] = Proveedor::all();
                }

                $server_info = [
                    "server_datetime_snap" => getdate(),
                    "token_refresh_ttl" => Config::get("jwt.refresh_ttl")
                ];

                $log_usuario->login_status = 'OK';
                $log_usuario->save();

                $payload = JWTFactory::make($claims);
                $token = JWTAuth::encode($payload);
                return response()->json(['token' => $token->get(), 'usuario'=>$usuario_data, 'server_info'=> $server_info], 200);
            } else {
                $log_usuario->login_status = 'ERR_PSW';
                $log_usuario->save();
                return response()->json(['error' => 'invalid_credentials'], 401); 
            }

        } catch (JWTException $e) {
            $log_usuario->login_status = 'ERR_TKN';
            $log_usuario->save();
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
    }
    public function refreshToken(Request $request){
        try{
            $token =  JWTAuth::parseToken()->refresh();
            $server_info = [
                    "server_datetime_snap" => getdate(),
                    "token_refresh_ttl" => Config::get("jwt.refresh_ttl")
            ];
            return response()->json(['token' => $token, 'server_info'=> $server_info], 200);

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'token_expirado'], 401);  
        } catch (JWTException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function verificar(Request $request)
    {   
        try{
            $obj =  JWTAuth::parseToken()->getPayload();
            return $obj;
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'no_se_pudo_validar_token'], 500);
        }
        
    }
}