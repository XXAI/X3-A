<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Usuario, App\Models\UnidadMedica;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class UnidadesMedicasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       /* //return Response::json([ 'data' => []],200);
        //return Response::json(['error' => ""], HttpResponse::HTTP_UNAUTHORIZED);
        $parametros = Input::only('q','page','per_page');
        if ($parametros['q']) {
             $usuarios =  Usuario::where('su',false)->where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")->orWhere(DB::raw("CONCAT(nombre,' ',apellidos)"),'LIKE',"%".$parametros['q']."%");
             });
        } else {
             $usuarios =  Usuario::where('su',false);
        }
        

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $usuarios = $usuarios->paginate($resultadosPorPagina);
        } else {
            $usuarios = $usuarios->get();
        }*/
        $items = [];
        try{
            $obj =  JWTAuth::parseToken()->getPayload();
            $usuario = Usuario::find($obj->get('id'));
            
            if(!$usuario){
                throw new Exception("Usuario no existe");
            }

            if($usuario->su){
                $items = UnidadMedica::with('almacenes')->get();
            } else {
                $items = $usuario->unidadesMedicas()->with('almacenes')->get();
            }
            
        } catch (TokenExpiredException $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_FORBIDDEN);
        } catch (JWTException $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_FORBIDDEN);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_FORBIDDEN);
        }
        
       
        return Response::json([ 'data' => $items],200);
    }

}
