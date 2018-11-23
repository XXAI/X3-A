<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\PresupuestoEjercicio, App\Models\PresupuestoUnidadMedica,  App\Models\UnidadMedica, App\Models\Jurisdiccion;
use App\Models\PresupuestoMovimientoEjercicio, App\Models\PresupuestoMovimientoUnidadMedica;

use App\Models\PedidoOrdinario;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class PedidosOrdinariosController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   $parametros = Input::only('q','page','per_page');
        
        $items =  PedidoOrdinario::select('pedidos_ordinarios.*');
                    //->leftJoin('proveedores','contratos.proveedor_id','=','proveedores.id');        

        if ($parametros['q']) {
            
            $items = $items->where('pedidos_ordinarios.descripcion','LIKE',"%".$parametros['q']."%")->orWhere('pedidos_ordinarios.id','LIKE',"%".$parametros['q']."%");
       }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);

    }
}