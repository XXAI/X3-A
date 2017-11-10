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
use \Excel;


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
class InventarioInsumosController extends Controller
{
     
    public function index(Request $request)
    {
        //buscar_en:  MC , TC,       seleccionar :  NO_EXISTENTE, EXISTENTE
        $parametros = Input::only('q','page','per_page','clues','clave_insumo','almacen','tipo','es_causes','buscar_en','seleccionar');
        

        $parametros['almacen'] = $request->get('almacen_id');

        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }     

        $data = array();
        $claves = NULL;

        $almacen_id = $request->get('almacen_id');

        $almacen = Almacen::find($almacen_id);
        $clues   = $almacen->clues;


            if($parametros['buscar_en'] == "MIS_CLAVES")
            {
                $claves = DB::table("clues_claves AS cc")->leftJoin('insumos_medicos AS im', 'im.clave', '=', 'cc.clave_insumo_medico')
                              ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'cc.clave_insumo_medico')
                              ->select('cc.clave_insumo_medico','im.descripcion','im.tipo','im.es_causes','es_unidosis')
                              ->where('clues',$clues);

                if($parametros['clave_insumo'] != "")
                {
                    $claves = $claves->where('cc.clave_insumo_medico',$parametros['clave_insumo']);
                }
            }
            if($parametros['buscar_en'] == "TODAS_LAS_CLAVES")
            {
                $claves = DB::table('insumos_medicos AS im')
                              ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
                              ->select('im.clave AS clave_insumo_medico','im.descripcion','im.tipo','im.es_causes','im.es_unidosis');
                if($parametros['clave_insumo'] != "")
                {
                    $claves = $claves->where('im.clave',$parametros['clave_insumo']);
                }
            }


            if($parametros['tipo'] == "TODO")
            {
            }else{
                    if($parametros['tipo'] == "CAUSES")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('es_causes',1);
                        }
                    if($parametros['tipo'] == "NO_CAUSES")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('es_causes',0);
                        }
                    if($parametros['tipo'] == "MC")
                        {
                            $claves = $claves->where('im.tipo','MC');
                        }
                    if($parametros['tipo'] == "CONTROLADO")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('m.es_controlado',1);
                        }
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


 //////*********************************************************************************************************
 //////*********************************************************************************************************
         if(isset($parametros['page']))
         {

            ///$resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            ///$data2 = $this->paginadorMaster($data,$resultadosPorPagina);
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

         } else {
                    $data2 = $data;
                }
 //////*********************************************************************************************************
 //////*********************************************************************************************************

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

////*******************************************************************************************************************************************************************
public function excel(Request $request)
    {
        $parametros = Input::only('q','page','per_page','clues','clave_insumo','almacen','tipo','es_causes','buscar_en','seleccionar');

        
        //return $this->getItemsInventarioDetalles($parametros);
        Excel::create('Inventario_'.$parametros['clues'].'_'.$parametros['almacen'].'_'.date('d-m-Y H-i-s'), function($excel)use($parametros){
            

             //$excel->sheet('Reporte de almacenIventari', function($sheet) use($items)
            
            $excel->sheet('Insumos Medicos', function($sheet)use($parametros)
            {
                //$sheet->setAutoSize(true);
                $items = $this->getItemsInventario($parametros);

                $claves       = "";
                $seleccionar  = "";
                $tipo_insumos = "";
                $clave        = "";

                if($parametros['buscar_en'] == "TODAS_LAS_CLAVES")
                {
                    $claves = "TODAS LAS CLAVES";
                }else{
                        $claves = "MIS CLAVES";
                     }


                if($parametros['seleccionar'] == "TODO")
                {
                    $seleccionar = "TODOS INSUMOS";
                }  
                if($parametros['seleccionar'] == "EXISTENTE")
                {
                    $seleccionar = "INSUMOS EXISTENTES";
                } 
                if($parametros['seleccionar'] == "NO_EXISTENTE")
                {
                    $seleccionar = "INSUMOS AGOTADOS";
                }  

                if($parametros['tipo'] == "TODO")
                {
                    $tipo_insumos = "TODOS";
                }
                if($parametros['tipo'] == "CAUSES")
                {
                    $tipo_insumos = "MED. CAUSES";
                }
                if($parametros['tipo'] == "NO_CAUSES")
                {
                    $tipo_insumos = "MED. NO CAUSES";
                } 
                if($parametros['tipo'] == "CONTROLADO")
                {
                    $tipo_insumos = "MED. CONTROLADO";
                } 

                 
                

                
               
                $sheet->row(2, array('','INVENTARIO DE ALMACÉN '.$parametros['almacen'].' EN CLUES '.$parametros['clues'].' AL '.date('d-m-Y H:i:s'),'','','','','','','','','',''));
                $sheet->row(2, function($row) {
                                                    $row->setBackground('#DDDDDD');
                                                    $row->setFontWeight('bold');
                                                    $row->setFontSize(14);
                                              });

                $sheet->row(4, array('','BUSQUEDA EN : '.$claves.' | SELECCIONAR : '.$seleccionar.' | TIPO INSUMOS : '.$tipo_insumos.' | CLAVE : '.$parametros['clave_insumo']));

                $sheet->row(4, function($row) {
                                                    $row->setBackground('#DDDDDD');
                                                    $row->setFontWeight('bold');
                                                    $row->setFontSize(12);
                                              });

                $sheet->row(6, array('Clave','Descripción', 'C.P.D','C.P.S','C.P.M','Existencia','Existencia Unidosis'));
                $sheet->row(6, function($row) {
                                                    $row->setBackground('#DDDDDD');
                                                    $row->setFontWeight('bold');
                                                    $row->setFontSize(12);
                                              });
                


                $sheet->cells("A6:M6", function($cells) {
                                                            $cells->setAlignment('center');
                                                        });

                 $sheet->setSize('A2', 25, 18);
                 $sheet->setSize('B2', 70, 18);
                 $sheet->setSize('F2', 20, 18);
                 $sheet->setSize('G2', 30, 18);

                 $sheet->setSize('A4', 25, 18);
                 $sheet->setSize('B4', 70, 18);
                 $sheet->setSize('F4', 20, 18);
                 $sheet->setSize('G4', 30, 18);
                 
                 $sheet->setSize('A6', 25, 18);
                 $sheet->setSize('B6', 70, 18);
                 $sheet->setSize('F6', 20, 18);
                 $sheet->setSize('G6', 30, 18);

                foreach($items as $item)
                {
                    //$sheet->setColumnFormat(array('J' => '0.00', 'K' => '0.00'));

                    $sheet->appendRow(array(
                        
                        $item->clave_insumo_medico,
                        $item->descripcion,
                        "--",
                        "--",
                        "--",
                        $item->existencia,
                        $item->existencia_unidosis
                    )); 


                } // FIN FOREACH ITEMS
 
            });
            $excel->sheet('Insumos Medicos Detalles', function($sheet)use($parametros)
            {
                //$sheet->setAutoSize(true);
                $items = $this->getItemsInventarioDetalles($parametros);

                $claves       = "";
                $seleccionar  = "";
                $tipo_insumos = "";
                $clave        = "";

                if($parametros['buscar_en'] == "TODAS_LAS_CLAVES")
                {
                    $claves = "TODAS LAS CLAVES";
                }else{
                        $claves = "MIS CLAVES";
                     }


                if($parametros['seleccionar'] == "TODO")
                {
                    $seleccionar = "TODOS INSUMOS";
                }  
                if($parametros['seleccionar'] == "EXISTENTE")
                {
                    $seleccionar = "INSUMOS EXISTENTES";
                } 
                if($parametros['seleccionar'] == "NO_EXISTENTE")
                {
                    $seleccionar = "INSUMOS AGOTADOS";
                }  

                if($parametros['tipo'] == "TODO")
                {
                    $tipo_insumos = "TODOS";
                }
                if($parametros['tipo'] == "CAUSES")
                {
                    $tipo_insumos = "MED. CAUSES";
                }
                if($parametros['tipo'] == "NO_CAUSES")
                {
                    $tipo_insumos = "MED. NO CAUSES";
                } 
                if($parametros['tipo'] == "CONTROLADO")
                {
                    $tipo_insumos = "MED. CONTROLADO";
                } 

                 
                

                
               
                $sheet->row(2, array('','INVENTARIO DE ALMACÉN '.$parametros['almacen'].' EN CLUES '.$parametros['clues'].' AL '.date('d-m-Y H:i:s'),'','','','','','','','','',''));
                $sheet->row(2, function($row) {
                                                    $row->setBackground('#DDDDDD');
                                                    $row->setFontWeight('bold');
                                                    $row->setFontSize(14);
                                              });

                $sheet->row(4, array('','BUSQUEDA EN : '.$claves.' | SELECCIONAR : '.$seleccionar.' | TIPO INSUMOS : '.$tipo_insumos.' | CLAVE : '.$parametros['clave_insumo']));

                $sheet->row(4, function($row) {
                                                    $row->setBackground('#DDDDDD');
                                                    $row->setFontWeight('bold');
                                                    $row->setFontSize(12);
                                              });

                $sheet->row(6, array('Clave','Descripción', 'C.P.D','C.P.S','C.P.M', 'Lote', 'Caducidad','Existencia','Existencia Unidosis', 'P. Unitario C/IVA', 'Precio Total'));
                $sheet->row(6, function($row) {
                                                    $row->setBackground('#DDDDDD');
                                                    $row->setFontWeight('bold');
                                                    $row->setFontSize(12);
                                              });
                $sheet->cells("A6:M6", function($cells) {
                                                            $cells->setAlignment('center');
                                                        });
                 $sheet->setColumnFormat(array('J' => '0.00','K' => '0.00'));

                 $sheet->setSize('A2', 25, 18);
                 $sheet->setSize('B2', 70, 18);
                 $sheet->setSize('F2', 20, 18);
                 $sheet->setSize('G2', 30, 18);

                 $sheet->setSize('A4', 25, 18);
                 $sheet->setSize('B4', 70, 18);
                 $sheet->setSize('F4', 20, 18);
                 $sheet->setSize('G4', 30, 18);
                 
                 $sheet->setSize('A6', 25, 18);
                 $sheet->setSize('B6', 70, 18);
                 $sheet->setSize('F6', 10, 18);
                 $sheet->setSize('G6', 15, 18);
                 $sheet->setSize('H6', 20, 18);
                 $sheet->setSize('I6', 25, 18);
                 $sheet->setSize('J6', 30, 18);
                 $sheet->setSize('K6', 20, 18);

                foreach($items as $item)
                {
                    $sheet->appendRow(array(
                        
                        $item->clave_insumo_medico,
                        $item->descripcion,
                        "--",
                        "--",
                        "--",                        
                        $item->lote,
                        $item->fecha_caducidad,
                        $item->existencia,
                        $item->existencia_unidosis,
                        $item->precio_unitario,
                        $item->precio_total
                    )); 
                } // FIN FOREACH ITEMS
 
            });
          
        })->export('xls');
    }
////*******************************************************************************************************************************************************************
////***************************************************************************************************************************************************
public function getItemsInventario($parametros)
    {
        

        if(!$parametros['almacen']){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }     

        $data = array();
        $claves = NULL;

        $almacen_id = $parametros['almacen'];

        $almacen = Almacen::find($almacen_id);
        $clues   = $almacen->clues;

            if($parametros['buscar_en'] == "MIS_CLAVES")
            {
                $claves = DB::table("clues_claves AS cc")->leftJoin('insumos_medicos AS im', 'im.clave', '=', 'cc.clave_insumo_medico')
                              ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'cc.clave_insumo_medico')
                              ->select('cc.clave_insumo_medico','im.clave','im.descripcion','im.tipo','im.es_causes','im.es_unidosis')
                              ->where('clues',$clues);

                if($parametros['clave_insumo'] != "")
                {
                   // $claves = $claves->where('cc.clave_insumo_medico',$parametros['clave_insumo']);
                   $claves = $claves->where('im.descripcion','LIKE',"%".$parametros['clave_insumo']."%");
                                                
                }
                
            }
            if($parametros['buscar_en'] == "TODAS_LAS_CLAVES")
            {
                $claves = DB::table('insumos_medicos AS im')
                              ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
                              ->select('im.clave AS clave_insumo_medico','im.descripcion','im.tipo','im.es_causes','im.es_unidosis');

                if($parametros['clave_insumo'] != "")
                {
                   // $claves = $claves->where('im.clave',$parametros['clave_insumo']);
                   $claves = $claves->where('im.descripcion','LIKE',"%".$parametros['clave_insumo']."%");
                }

            }


            if($parametros['tipo'] == "TODO")
            {
            }else{
                    if($parametros['tipo'] == "CAUSES")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('es_causes',1);
                        }
                    if($parametros['tipo'] == "NO_CAUSES")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('es_causes',0);
                        }
                    if($parametros['tipo'] == "MC")
                        {
                            $claves = $claves->where('im.tipo','MC');
                        }
                    if($parametros['tipo'] == "CONTROLADO")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('m.es_controlado',1);
                        }
                  }


            if($parametros['clave_insumo'] != "")
            {
                /*
                $claves = $claves->where(function($query) use ($parametros) {
                                                $query->where('im.descripcion','LIKE',"%".$parametros['clave_insumo']."%")
                                                ->orWhere('im.clave','LIKE',"%".$parametros['clave_insumo']."%");
                                                });
                                                */
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

            //return $data;
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

            return $data;
    }

    public function getItemsInventarioDetalles($parametros)
    {
        

        if(!$parametros['almacen']){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }     

        $data = array();
        $claves = NULL;

        $almacen_id = $parametros['almacen'];

        $almacen = Almacen::find($almacen_id);
        $clues   = $almacen->clues;

            if($parametros['buscar_en'] == "MIS_CLAVES")
            {
                $claves = DB::table("clues_claves AS cc")->leftJoin('insumos_medicos AS im', 'im.clave', '=', 'cc.clave_insumo_medico')
                              ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'cc.clave_insumo_medico')
                              ->select('cc.clave_insumo_medico','im.clave','im.descripcion','im.tipo','im.es_causes','im.es_unidosis')
                              ->where('clues',$clues);

                if($parametros['clave_insumo'] != "")
                {
                   // $claves = $claves->where('cc.clave_insumo_medico',$parametros['clave_insumo']);
                   $claves = $claves->where('im.descripcion','LIKE',"%".$parametros['clave_insumo']."%");
                                                
                }
                
            }
            if($parametros['buscar_en'] == "TODAS_LAS_CLAVES")
            {
                $claves = DB::table('insumos_medicos AS im')
                              ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
                              ->select('im.clave AS clave_insumo_medico','im.descripcion','im.tipo','im.es_causes','im.es_unidosis');

                if($parametros['clave_insumo'] != "")
                {
                   // $claves = $claves->where('im.clave',$parametros['clave_insumo']);
                   $claves = $claves->where('im.descripcion','LIKE',"%".$parametros['clave_insumo']."%");
                }

            }


            if($parametros['tipo'] == "TODO")
            {
            }else{
                    if($parametros['tipo'] == "CAUSES")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('es_causes',1);
                        }
                    if($parametros['tipo'] == "NO_CAUSES")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('es_causes',0);
                        }
                    if($parametros['tipo'] == "MC")
                        {
                            $claves = $claves->where('im.tipo','MC');
                        }
                    if($parametros['tipo'] == "CONTROLADO")
                        {
                            $claves = $claves->where('im.tipo','ME')->where('m.es_controlado',1);
                        }
                  }


            if($parametros['clave_insumo'] != "")
            {
                /*
                $claves = $claves->where(function($query) use ($parametros) {
                                                $query->where('im.descripcion','LIKE',"%".$parametros['clave_insumo']."%")
                                                ->orWhere('im.clave','LIKE',"%".$parametros['clave_insumo']."%");
                                                });
                                                */
            }

            $claves = $claves->get();
            $data_completo = array();
            
            foreach($claves as $clave)
            {
                $existencia = 0; $existencia_unidosis = 0;
                $updated_at = NULL;
                $stocks = Stock::with("movimientoInsumo")->where('almacen_id',$almacen_id)->where('clave_insumo_medico',$clave->clave_insumo_medico)->get();

                $contador = 0;
                if($stocks)
                {
                    foreach ($stocks as $key => $stock) 
                    {
                        //$data_flag = array();    
                        //echo $stock->existencia."--";

                        $count = count($data_completo);
                        $data_completo[$count]['clave_insumo_medico'] = $clave->clave_insumo_medico;
                        $data_completo[$count]['descripcion'] = $clave->descripcion;
                        $data_completo[$count]['es_causes'] = $clave->es_causes;
                        $data_completo[$count]['es_unidosis'] = $clave->es_unidosis;
                        $data_completo[$count]['tipo'] = $clave->tipo;
                        $data_completo[$count]['existencia'] = $stock->existencia;
                        $data_completo[$count]['existencia_unidosis'] = $stock->existencia_unidosis;
                        $data_completo[$count]['lote'] = $stock->lote;
                        $data_completo[$count]['fecha_caducidad'] = $stock->fecha_caducidad;
                        if($stock->existencia == "")
                            $stock->existencia = 0;
                        if($stock->existencia_unidosis == "")
                            $stock->existencia_unidosis = 0;

                        
                        if(isset($stock->movimientoinsumo))
                        {
                            $precio_unitario_medicamento = ($stock->movimientoinsumo->precio_unitario + $stock->movimientoinsumo->iva);
                            $data_completo[$count]['precio_unitario'] = $precio_unitario_medicamento;
                            $data_completo[$count]['precio_total'] = ($stock->existencia * $precio_unitario_medicamento);
                        }else{
                            $precio_unitario_medicamento = 0;
                            $data_completo[$count]['precio_unitario'] = $precio_unitario_medicamento;
                            $data_completo[$count]['precio_total'] = 0;
                        }

                       
                        $contador ++;
                        
                    }
                }
                if($contador == 0)
                {
                    $count = count($data_completo);
                       
                    $data_completo[$count]['clave_insumo_medico'] = $clave->clave_insumo_medico;
                    $data_completo[$count]['descripcion'] = $clave->descripcion;
                    $data_completo[$count]['es_causes'] = $clave->es_causes;
                    $data_completo[$count]['es_unidosis'] = $clave->es_unidosis;
                    $data_completo[$count]['tipo'] = $clave->tipo;
                    $data_completo[$count]['existencia'] = 0;
                    $data_completo[$count]['existencia_unidosis'] = 0;
                    $data_completo[$count]['lote'] = "";
                    $data_completo[$count]['fecha_caducidad'] = "";
                    $data_completo[$count]['precio_unitario'] = 0;
                    $data_completo[$count]['precio_total'] = 0;
                    
                }
            }

            //return $data;
            $data_existente    = array();
            $data_no_existente = array();
            $data_sin_filtro = array();

            foreach ($data_completo as $key => $clave) 
            {
                $clave = (object) ($clave);

                    if($clave->existencia > 0)
                    {
                        array_push($data_existente,$clave);
                    }else{
                            array_push($data_no_existente,$clave);
                         }
                    array_push($data_sin_filtro,$clave);
            }

            if($parametros['seleccionar'] == "EXISTENTE")
            {
                $data_completo = $data_existente;
            }else if($parametros['seleccionar'] == "NO_EXISTENTE")
            {
                $data_completo = $data_no_existente;
            }else
            {
                $data_completo = $data_sin_filtro;
            }

            return $data_completo;
    }
        
 ///****************************************************************************************************************************************
 ///****************************************************************************************************************************************       

 
}