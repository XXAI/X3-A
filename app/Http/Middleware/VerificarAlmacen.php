<?php

namespace App\Http\Middleware;

use Closure, Request, \Exception;
use App\Models\Usuario;

class VerificarAlmacen
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
           
            $usuario = Usuario::find($request->get('usuario_id'));
            $almacen_id = Request::header('X-Almacen-Id');
            $clues = Request::header('X-clues');
            if($almacen_id == null){
                throw new Exception('Debes especificar el almacén');
            }

            if(!$usuario->su){
                $almacen = $usuario->almacenes()->where('almacenes.id',$almacen_id)->first();
                if($almacen == null){
                    throw new Exception('No tienes permiso para usar este almacén: '.$almacen_id);
                }
            }
            
            $request->attributes->add(['almacen_id' => $almacen_id]);
            $request->attributes->add(['clues' => $clues]);
            
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        return $next($request);
    }

}