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
* Controlador `AjusteMasInventario`: Controlador  para los ajustes más de inventario de insumos medicos
*
*/
class AjusteMasInventarioController extends Controller
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

///*******************************************************************
        $input_data = (object)Input::json()->all();

        $pedido_id      = $input_data->pedido;
        $json_proveedor = $input_data->json;
        $almacen_id     = $json_proveedor->almacen_id;
///*******************************************************************

        $almacen = Almacen::find($almacen_id);
        $clues   = $almacen->clues;



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
        $input_data = (object)Input::json()->all();

        $insumos  = NULL;
        if(property_exists($input_data, "insumos"))
        {
            foreach($input_data->insumos as $insumo)
            {
                $insumo = (object) $insumo;

                foreach($insumo->lotes as $lote)
                {
                    $lote = (object) $lote;
                    


                }

            }
        }else{

             }

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
