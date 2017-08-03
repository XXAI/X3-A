<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;


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
        //buscar_en:  MC , TC,       seleccionar :  NO_EXISTENTE, EXISTENTE
        $parametros = Input::only('q','page','per_page','almacen','tipo','es_causes','buscar_en','seleccionar');
        

        $parametros['almacen'] = $request->get('almacen_id');

        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }     

        $data = array();
        $claves = NULL;

        $almacen_id = $request->get('almacen_id');

            if($parametros['buscar_en'] == "MIS_CLAVES")
            {
                $claves = DB::table("clues_claves AS cc")->leftJoin('insumos_medicos AS im', 'im.clave', '=', 'cc.clave_insumo_medico')
                              ->select('cc.clave_insumo_medico','im.descripcion','im.tipo','im.es_causes','es_unidosis');
            }
            if($parametros['buscar_en'] == "TODAS_LAS_CLAVES")
            {
                $claves = DB::table('insumos_medicos AS im')
                              ->select('im.clave AS clave_insumo_medico','im.descripcion','im.tipo','im.es_causes','es_unidosis');
            }

            if($parametros['q'])
            {
                $claves = $claves->where(function($query) use ($parametros) {
                                                $query->where('im.descripcion','LIKE',"%".$parametros['q']."%")
                                                ->orWhere('im.clave','LIKE',"%".$parametros['q']."%");
                                                });
            }

            $claves = $claves->get();

            foreach($claves as $clave)
            {
                $existencia = 0; $existencia_unidosis = 0;
                $updated_at = NULL;
                $stocks = Stock::where('almacen_id',$almacen_id)->where('clave_insumo_medico',$clave->clave_insumo_medico)->get();

                if($stocks)
                {
                    foreach ($stocks as $key => $stock) 
                    {
                        $existencia          += $stock->existencia;
                        $existencia_unidosis += $stock->existencia_unidosis;
                        //$updated_at           = $stock->updated_at;
                    }
                }
                
                $clave->existencia          = property_exists($clave, "existencia") ? $clave->existencia : $existencia;
                $clave->existencia_unidosis = property_exists($clave, "existencia_unidosis") ? $clave->existencia_unidosis : $existencia_unidosis;
                $clave->updated_at          = property_exists($clave, "updated_at") ? $clave->updated_at : $updated_at;
                array_push($data,$clave);
            }

            $data_existente    = array();
            $data_no_existente = array();

            foreach ($data as $key => $clave) 
            {
                $clave = (object) ($clave);

                    if($clave->existencia > 0)
                    {
                        array_push($data_existente,$clave);
                    }else{
                            array_push($data_no_existente,$clave);
                         }
            }

            if($parametros['seleccionar'] == "EXISTENTE")
            {
                $data = $data_existente;
            }
            if($parametros['seleccionar'] == "NO_EXISTENTE")
            {
                $data = $data_no_existente;
            }

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $itemCollection = new Collection($data);
            $perPage = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $currentPageItems = $itemCollection->slice(($currentPage * $perPage) - $perPage, $perPage)->all();

    ///************************************************************************
                    $dataz;
                    if($currentPage > 1)
                    {
                        $tempdata = $currentPageItems;
                        foreach ($tempdata as $key => $value)
                        {
                            $dataz[] = $value;
                        }
                    }
                    else
                    {   $dataz = $currentPageItems; }
    ///************************************************************************
            $data2= new LengthAwarePaginator($dataz , count($itemCollection), $perPage);
            //$data2= new LengthAwarePaginator($currentPageItems , count($itemCollection), $perPage);
    
            $data2->setPath($request->url());


 /*
         if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            
            $data2 = $this->paginadorMaster($data,$resultadosPorPagina);

        } else {
            $data2 = $data;
        }
 */

        if(count($data2) <= 0){

            return Response::json(array("status" => 404,"messages" => "No hay resultados","data" => $data2), 200);
        } 
        else{
                return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data2, "total" => count($data2)), 200);
            }

 ///****************************************************************************************************************************************
 ///****************************************************************************************************************************************
 ///****************************************************************************************************************************************       
        
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

    public function paginadorMaster($items,$perPage)
{
    $pageStart = \Request::get('page', 1);
    // Start displaying items from this number;
    $offSet = ($pageStart * $perPage) - $perPage; 

    // Get only the items you need using array_slice 
    $itemsForCurrentPage = array_slice($items, $offSet, $perPage, true);

    

    return new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage,Paginator::resolveCurrentPage(), array('path' => Paginator::resolveCurrentPath()));
}

}
