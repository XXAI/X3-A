<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Usuario;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class VerificarJWT
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        try{
            $obj =  JWTAuth::parseToken()->getPayload();
            $usuario = Usuario::find($obj->get('id'));
            
            

            if(!$usuario){
                return response()->json(['error' => 'formato_token_invalido'], 401);                
            }

            // Esta linea es para que si un usuario quiere editar/agregar/eliminar información
            // en un servidor al cual no corresponde se le deniegue la petición.
            // Digamos que la información de un servidor offline sincronizada en el principal quiera ser editada
            // si un usuario entra e inicia sesión. Así que solo le permitimos lectura.
            if($usuario->servidor != env('SERVIDOR_ID') && $request->getMethod() != "GET"){
                return response()->json(['error' => 'usuario_servidor_invitado_solo_lectura'], 403); 
            }

            // Pasamos el usuario id como verificado
            $request->attributes->add(['usuario_id' => $usuario->id]);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'token_expirado'], 401);  
        } catch (JWTException $e) {
            return response()->json(['error' => 'token_invalido'], 500);
        }

        //return $next($request);
        $response = $next($request);
        
        $response->header('Api-Version','1.0');

        return $response;
    }

}