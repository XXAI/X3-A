<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\MovimientoInsumos;
use App\Models\TiposMovimientos;
use App\Models\Insumo;
use App\Models\MovimientoMetadato;
use App\Models\MovimientoDetalle;
use App\Models\Receta;
use App\Models\RecetaDetalle;
use App\Models\RecetaMovimiento;
use App\Models\ContratoPrecio;
use App\Models\NegacionInsumo;
use App\Models\Almacen;


/** 
* Controlador Inventario
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017-03-22
*
* Controlador `Inventario`: Controlador  para la consulta de inventarios
*
*/
class InventarioController extends Controller
{
     
    public function index(Request $request)
    {
        $parametros = Input::only('q','page','per_page','almacen','tipo','es_causes');

        $parametros['almacen'] = $request->get('almacen_id');

        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }                

        if ($parametros['q'])
        {
            if($parametros['tipo'])
            {
                $data = DB::table("stock AS s")
                            ->join('insumos_medicos AS im', 'im.clave', '=', 's.clave_insumo_medico')
                            ->where('im.tipo',$parametros['tipo'])
                            ->where('im.es_causes',$parametros['es_causes'])
                            ->where('s.almacen_id',$parametros['almacen'])
                            ->select(DB::raw('SUM(s.existencia) as existencia'),
                                     DB::raw('SUM(s.existencia_unidosis) as existencia_unidosis'),
                                     's.clave_insumo_medico','s.almacen_id','s.updated_at','im.descripcion',
                                     'im.tipo','im.es_causes','es_unidosis')
                            ->groupBy('s.clave_insumo_medico');
            }else{
                    //BUSQUEDA POR DESCRIPCIÓN DEL NOMBRE Ó POR CLAVE
                    $data = DB::table("stock AS s")
                            ->leftJoin('insumos_medicos AS im', 'im.clave', '=', 's.clave_insumo_medico')
                            ->where('s.almacen_id',$parametros['almacen'])
                            ->where(function($query) use ($parametros) {
                                $query->where('im.descripcion','LIKE',"%".$parametros['q']."%")
                                      ->orWhere('s.clave_insumo_medico','LIKE',"%".$parametros['q']."%");
                            })
                            ->select(DB::raw('SUM(s.existencia) as existencia'),
                                     DB::raw('SUM(s.existencia_unidosis) as existencia_unidosis'),
                                     's.clave_insumo_medico','s.almacen_id','s.updated_at','im.descripcion',
                                     'im.tipo','im.es_causes','es_unidosis')
                            ->groupBy('s.clave_insumo_medico');
                 }

        } else {
            
                 if($parametros['tipo'])
                 {
                    $data =  Stock::with('insumo')
                                        ->where('almacen_id',$parametros['almacen'])
                                        ->where('tipo_movimiento_id',$parametros['tipo'])
                                        ->orderBy('updated_at','DESC');
                 }else{
                     
                        // LEE TODOS LOS STOCKS DEL ALMACEN
                        $data = DB::table("stock AS s")
                            ->leftJoin('insumos_medicos AS im', 'im.clave', '=', 's.clave_insumo_medico')
                            ->where('s.almacen_id',$parametros['almacen'])
                            ->select(DB::raw('SUM(s.existencia) as existencia'),
                                     DB::raw('SUM(s.existencia_unidosis) as existencia_unidosis'),
                                     's.clave_insumo_medico','s.almacen_id','s.updated_at','im.descripcion',
                                     'im.tipo','im.es_causes','es_unidosis')
                            ->groupBy('s.clave_insumo_medico');

                      }

               }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $data = $data->paginate($resultadosPorPagina);
           // $data = \App\Movimientos::paginate($resultadosPorPagina);

        } else {

            $data = $data->get();

        }

        if(count($data) <= 0){

            return Response::json(array("status" => 404,"messages" => "No hay resultados","data" => $data), 200);
        } 
        else{
            return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data, "total" => count($data)), 200);
            
        }
    }

   
    public function store(Request $request)
    {
       
    }


///*************************************************************************************************************************************
///*************************************************************************************************************************************

/////                             S    H    O    W 
///*************************************************************************************************************************************
///*************************************************************************************************************************************

    public function show($id)
    {
        
    }

///***************************************************************************************************************************
///***************************************************************************************************************************

    public function update(Request $request, $id)
    {
 
    }
     
    public function destroy($id)
    {
        
    }

}
