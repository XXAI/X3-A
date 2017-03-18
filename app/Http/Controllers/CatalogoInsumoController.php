<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Insumo;
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

        //Se carga un scope con el cual obtenemos los datos de los catalogos que utiliza insumos_medicos, tambien se carga la relación con medicamentos
        $insumos = Insumo::conDetalles()->with('medicamentoDetalle');

        //buscar una cadena en clave, descripcion, nombre del grupo o nombre del generico
        if ($parametros['q']) {
             $insumos =  $insumos->where(function($query) use ($parametros) {
                 $query->where('clave','LIKE',"%".$parametros['q']."%")->orWhere('descripcion','LIKE',"%".$parametros['q']."%")->orWhere('grupos_insumos.nombre','LIKE',"%".$parametros['q']."%")
                        ->orWhere('genericos.nombre','LIKE',"%".$parametros['q']."%");
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
        $object = Insumo::conDetalles()->with('medicamentoDetalle')->find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el insumo que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

         return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }
}