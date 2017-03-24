<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Insumo;
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
    public function index()
    {
        //return Response::json([ 'data' => []],200);
        //return Response::json(['error' => ""], HttpResponse::HTTP_UNAUTHORIZED);
        $parametros = Input::only('q','page','per_page');

        //Se carga un scope con el cual obtenemos los nombres o descripciones de los catalogos que utiliza insumos_medicos
        $insumos = Insumo::conDescripciones()->with('informacion','generico.grupos');

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