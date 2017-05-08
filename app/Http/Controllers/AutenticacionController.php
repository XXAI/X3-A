<?php

namespace App\Http\Controllers;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Http\Request;
use \Hash, \Config;
use App\Models\Usuario, App\Models\Permiso, App\Models\Almacen, App\Models\UnidadMedica;

class AutenticacionController extends Controller
{
    public function autenticar(Request $request)
    {
        
        // grab credentials from the request
        $credentials = $request->only('id', 'password');

        try {
           
            $usuario = Usuario::where('id',$credentials['id'])->first();

            if(!$usuario) {                
                return response()->json(['error' => 'invalid_credentials'], 401); 
            }

            if(Hash::check($credentials['password'], $usuario->password)){
                $lista_permisos = "";
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
                        $permisos = $rol->permisos;
                        foreach ( $permisos as $permiso){
                            if ($lista_permisos != "") {
                                $lista_permisos .= "|";
                            }
                            $lista_permisos.=$permiso->id;

                        }
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
                

                $usuario = [
                    "id" => $usuario->id,
                    "nombre" => $usuario->nombre,
                    "apellidos" => $usuario->apellidos,
                    "avatar" => $usuario->avatar,
                    "permisos" => $lista_permisos,                    
                    "unidades_medicas" =>  $unidades_medicas
                ];

                $server_info = [
                    "server_datetime_snap" => getdate(),
                    "token_refresh_ttl" => Config::get("jwt.refresh_ttl")
                ];

                $payload = JWTFactory::make($claims);
                $token = JWTAuth::encode($payload);
                return response()->json(['token' => $token->get(), 'usuario'=>$usuario, 'server_info'=> $server_info], 200);
            } else {
                return response()->json(['error' => 'invalid_credentials'], 401); 
            }

        } catch (JWTException $e) {
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