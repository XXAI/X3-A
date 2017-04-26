<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use JWTAuth;
use App\Http\Requests;
use App\Models\Insumo;
use App\Models\Usuario;
use App\Models\Contrato;
use App\Models\Proveedor;
use App\Models\GrupoInsumo;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;

class CatalogoInsumoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        if(Input::get('con_precios')){
            //Harima: obtenemos el contrato activo 
            $proveedor = Proveedor::with('contratoActivo')->find($almacen->proveedor_id);

            if(count($proveedor->contratoActivo) > 1){
                return Response::json(['error' => 'El proveedor tiene mas de un contrato activo'], HttpResponse::HTTP_CONFLICT);
            }else{
                $contrato_activo = $proveedor->contratoActivo;
            }
            
            if(count($contrato_activo) > 1){
                return Response::json(['error' => 'Hay mas de un contrato activo'], HttpResponse::HTTP_CONFLICT);
            }elseif(count($contrato_activo) == 0){
                return Response::json(['error' => 'No se encontraron contratos activos para este proveedor'], HttpResponse::HTTP_CONFLICT);
            }else{
                $contrato_activo = $contrato_activo[0];
            }
            //Se carga un scope con el cual obtenemos los nombres o descripciones de los catalogos que utiliza insumos_medicos
            $insumos = Insumo::conDescripcionesPrecios($contrato_activo->id,$proveedor->id)->with('informacion','generico.grupos');
        }else{
            $insumos = Insumo::conDescripciones()->with('informacion','generico.grupos');
        }
        
        //return Response::json([ 'data' => []],200);
        //return Response::json(['error' => ""], HttpResponse::HTTP_UNAUTHORIZED);

        $parametros = Input::only('q','page','per_page');
        //buscar una cadena en clave, descripcion, nombre del grupo o nombre del generico
        if ($parametros['q']) {
            //Hacemos una busqueda sobre grupos_insumos, para ver si hay grupos que conincidan con el criterio de busqueda, esto reemplaza el leftjoin que estaba en grupos_insumos
            $ids_genericos = [];
            $grupos = GrupoInsumo::where('nombre','LIKE',"%".$parametros['q']."%")->get(); //->genericos()->lists('id');
            if(count($grupos)){
                $grupos->load('genericos');
                foreach($grupos as $grupo){
                    $ids = $grupo->genericos->lists('id','id')->toArray();
                    $ids_genericos = array_merge($ids_genericos,$ids);
                }
            }

             $insumos =  $insumos->where(function($query) use ($parametros,$ids_genericos) {
                 $query->where('clave','LIKE',"%".$parametros['q']."%")->orWhere('descripcion','LIKE',"%".$parametros['q']."%")//->orWhere('grupos_insumos.nombre','LIKE',"%".$parametros['q']."%") //Se cambio a muchos a muchos
                        ->orWhere('genericos.nombre','LIKE',"%".$parametros['q']."%");
                 if(count($ids_genericos)){
                     $query->orWhereIn('genericos.id',$ids_genericos);
                 }
             });
        }
        

        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $insumos = $insumos->paginate($resultadosPorPagina);
        } else {
            $insumos = $insumos->get();
        }
       
        return Response::json([ 'data' => $insumos],200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $object = Insumo::conDescripciones()->with('informacionAmpliada')->find($id);
        

        if(!$object){
            return Response::json(['error' => "No se encuentra el insumo que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }
        
        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }
}