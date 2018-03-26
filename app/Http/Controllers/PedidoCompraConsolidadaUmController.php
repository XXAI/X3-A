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



use App\Models\InicializacionInventario;
use App\Models\InicializacionInventarioDetalle;
use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\MovimientoInsumos;
use App\Models\MovimientoAjuste;
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
use App\Models\Programa;
use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\PedidoMetadatoCC;
use App\Models\PedidoCcClues;



/** 
* Controlador
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017-03-22
*
* Controlador ``: Controlador  para 
*
*/

class PedidoCompraConsolidadaUmController extends Controller
{
     
    public function index(Request $request)
    {
         if(!$request->header('X-Clues'))
        {
            return Response::json(array("status" => 404,"messages" => "Debe especificar una Unidad Médica ( clues )."), 200);
        }
        $parametros = Input::only('q','page','per_page','clues','almacen');
        $parametros['clues'] = $request->header('X-Clues'); 
     
        $pedidos  = Pedido::with("metadatoCompraConsolidada","unidadMedica")->where('tipo_pedido_id','PCC')->where('clues',$parametros['clues']);

        if ($parametros['q'])
        {
            $pedidos =  $pedidos->where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")->orWhere('descripcion','LIKE',"%".$parametros['q']."%")->orWhere('folio','LIKE',"%".$parametros['q']."%");
             });
        }
 //////*********************************************************************************************************
 if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $pedidos = $pedidos->paginate($resultadosPorPagina);
        } else {
                    $pedidos = $pedidos->get();
               }
 //////*********************************************************************************************************
 foreach ($pedidos as $key => $pedido){
                 $pedido->estatus = $pedido->status;
 }
 //////*********************************************************************************************************


        if(count($pedidos) <= 0){

            return Response::json(array("status" => 404,"messages" => "No se han aperturado Pedidos de Compra Consolidada para esta Clues","data" => $pedidos), 200);
        } 
        else{
                return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $pedidos, "total" => count($pedidos)), 200);
            }

 
///****************************************************************************************************************************************      
///*******************************************************************************************************************************************      
   }


//////                   S   T   O   R   E
///********************************************************************************************************************************************
///*****************************************************************************************************************************************

public function store(Request $request)
{

    $parametros = Input::only('q','page','per_page');
          
    $input_data = (object)Input::json()->all();
    $servidor_id = property_exists($input_data, "servidor_id") ? $input_data->servidor_id : env('SERVIDOR_ID');

    $errors     = array();
    $nuevo      = 0;

    $presupuesto_compra               = 0;
    $presupuesto_causes               = 0;
    $presupuesto_no_causes            = 0;
    $presupuesto_causes_asignado      = 0;
    $presupuesto_causes_disponible    = 0;
    $presupuesto_no_causes_asignado   = 0;
    $presupuesto_no_causes_disponible = 0;


///*****************************************************************************************************************************************
if($input_data->estatus=="INICIALIZADO")
{
}/// fin if INICIALIZADO
    
///*****************************************************************************************************************************************
if( count($errors) > 0 )
{
    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
} 
///*****************************************************************************************************************************************
    
$success = false;
DB::beginTransaction();
try{
////****************************************************************************************************************************************
        
        $pedido = new Pedido;
        $pedido->clues          = "";
        $pedido->tipo_pedido_id = "PCC";
        $pedido->folio          = "";
        $pedido->fecha          = date('Y-m-d');
        $pedido->status         = $input_data->estatus;
        $pedido->save();

        $insumos = $input_data->insumos;

        foreach ($insumos as $key => $insumo)
        {
            $insumo = (object) $insumo;  

            $insumo_db = new PedidoInsumo; 
            $insumo_db->pedido_id             = $pedido->id;
            $insumo_db->insumo_medico_clave   = $insumo->insumo_medico_clave;
            $insumo_db->cantidad_enviada      = $insumo->cantidad_enviada;
            $insumo_db->cantidad_solicitada   = $insumo->cantidad_solicitada;
            $insumo_db->cantidad_recibida     = 0;
            $insumo_db->precio_unitario       = $insumo->precio_unitario;
            $insumo_db->monto_enviado         = ( $insumo->precio_unitario * $insumo->cantidad_enviada );
            $insumo_db->monto_solicitado      = ( $insumo->precio_unitario * $insumo->cantidad_solicitada );
            $insumo_db->monto_recibido        = 0;
            $insumo_db->save();
        }

////*****************************************************************************************************************************************
        $success = true;
        } catch (\Exception $e) {   
                                    $success = false;
                                    DB::rollback();
                                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                                } 
        if ($success)
        {
            DB::commit();
            return Response::json(array("status" => 201,"messages" => "Pedido para Compra Consolidadda creado correctamente","data" => $pedido), 201);
        }else{
                DB::rollback();
                return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
             }
////*****************************************************************************************************************************************

 }  // fin store method

///*************************************************************************************************************************************
/////                             S    H    O    W 
///*************************************************************************************************************************************


    public function show($id)
    {
        $pedido = Pedido::with('metadatoCompraConsolidada','insumos','unidadMedica')->find($id);
        if(!$pedido){
			return Response::json(array("status" => 404,"messages" => "No se encuentra el pedido solicitado"), 200);
		} 

    $pedido->estatus = $pedido->status;
     return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $pedido), 200);



    }

///***************************************************************************************************************************
///***************************************************************************************************************************

public function update(Request $request, $id)
{
    $parametros = Input::only('q','page','per_page');      
    $input_data = (object)Input::json()->all();
    $servidor_id = property_exists($input_data, "servidor_id") ? $input_data->servidor_id : env('SERVIDOR_ID');

    $errors     = array();
    $nuevo      = 0;
///*****************************************************************************************************************************************
$pedido = Pedido::with('metadatoCompraConsolidada','unidadMedica')->find($id);
    if(!$pedido){
			return Response::json(array("status" => 404,"messages" => "No se encuentra el pedido solicitado"), 200);
		} 


///*****************************************************************************************************************************************
if($input_data->estatus=="INICIALIZADO")
{

}/// fin if INICIALIZADO
    
///*****************************************************************************************************************************************
if( count($errors) > 0 )
{
    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
} 
///*****************************************************************************************************************************************
    
$success = false;
DB::beginTransaction();
try{
////****************************************************************************************************************************************
        
        $pedido->clues          = $input_data->clues;
        $pedido->tipo_pedido_id = "PCC";
        $pedido->folio          = "";
        $pedido->fecha          = $input_data->fecha;
        $pedido->status         = $input_data->estatus;
        $pedido->save();

        $insumos = $input_data->insumos;
        PedidoInsumo::where('pedido_id',$pedido->id)->delete();

        foreach ($insumos as $key => $insumo)
        {
            $insumo = (object) $insumo;             
            $insumo_db = PedidoInsumo::withTrashed()
                                     ->where('pedido_id',$pedido->id)
                                     ->where('insumo_medico_clave',$insumo->insumo_medico_clave)
                                     ->first();
                        
            if($insumo_db)
            {   $insumo_db->restore(); }else{ $insumo_db = new PedidoInsumo; }

            $insumo_db->pedido_id             = $pedido->id;
            $insumo_db->insumo_medico_clave   = $insumo->insumo_medico_clave;
            $insumo_db->cantidad_enviada      = $insumo->cantidad_enviada;
            $insumo_db->cantidad_solicitada   = $insumo->cantidad_solicitada;
            $insumo_db->cantidad_recibida     = 0;
            $insumo_db->precio_unitario       = $insumo->precio_unitario;
            $insumo_db->monto_enviado         = ( $insumo->precio_unitario * $insumo->cantidad_enviada );
            $insumo_db->monto_solicitado      = ( $insumo->precio_unitario * $insumo->cantidad_solicitada );
            $insumo_db->monto_recibido        = 0;
            $insumo_db->save();
        }

////*****************************************************************************************************************************************

        $success = true;
        } catch (\Exception $e) {   
                                    $success = false;
                                    DB::rollback();
                                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                                } 
        if ($success)
        {
            DB::commit();
            $pedido->estatus         = $pedido->status;
            return Response::json(array("status" => 201,"messages" => "Pedido para Compra Consolidadda creado correctamente","data" => $pedido), 201);
        }else{
                DB::rollback();
                return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
             }
////*****************************************************************************************************************************************
    }
     
    public function destroy($id)
    {
        
    }
 
///**************************************************************************************************************************

private function validarPrograma($request)
    { 
        $mensajes = [
                        'required'      => "Debe ingresar este campo.",
                        'integer'       => "Solo cantidades enteras.",
                    ];
        $reglas = [
                        'id'            => 'required'
                  ];
                         
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item) 
            {
                $msg_validacion = array();
                array_push($mensages_validacion, $item);
			}  
			return $mensages_validacion;
        }else{
                return ;
             }
	}

///***************************************************************************************************************************

    private function validarInsumo($request)
    { 
        $mensajes = [
                        'required' => "Debe ingresar este campo."
                    ];
        $reglas = [
                        'clave_insumo_medico'  => 'required'
                  ];
                         
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                array_push($mensages_validacion, $item);
			}  
			return $mensages_validacion;
        }else{
                return ;
             }
	}
///***************************************************************************************************************************
private function validarLote($request)
    { 
        $mensajes = [
                        'required'      => "Debe ingresar este campo.",
                        'email'         => "formato de email invalido",
                        'unique'        => "unique",
                        'integer'       => "Solo cantidades enteras.",
                        'min'           => "La cantidad debe ser mayor de cero.",
                        'after'         => "La fecha de caducidad debe ser mayor a hoy."
                    ];
        $reglas   = [
                        'lote'                  => 'required',
                        'fecha_caducidad'       => 'required|date|after:today', 
                        'existencia'            => 'required|integer',
                        'precio_unitario'       => 'required'
                    ];
                         
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item)
            {
                $msg_validacion = array();
                array_push($mensages_validacion, $item);
			}  
			return $mensages_validacion;
        }else{
                return ;
             }
	}

////**************************************************************************************************************************************************
///****************************************************************************************************************************************************
}
