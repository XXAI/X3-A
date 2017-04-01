<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Almacen;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


class AlmacenController extends Controller{
    public function index(){
        $parametros = Input::only('q','page','per_page');

        if(count($parametros)){
            if ($parametros['q']) {
                $almacenes =  Almacen::where(function($query) use ($parametros) {
                    $query->where('nombre','LIKE',"%".$parametros['q']."%")
                        ->where('tipo','LIKE',"%".$parametros['q']."%")
                        ->where('clues','LIKE',"%".$parametros['q']."%");
                });
            } else {
                $almacenes = Almacen::getModel();
            }

            //$pedido = Pedido::with("insumos", "acta", "TipoInsumo", "TipoPedido")->get();
            if(isset($parametros['page'])){
                $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
                $almacenes = $almacenes->paginate($resultadosPorPagina);
            } else {
                $almacenes = $almacenes->get();
            }
        }else{
            $almacenes = Almacen::all();
        }
        return Response::json([ 'data' => $almacenes],200);
    }
}
