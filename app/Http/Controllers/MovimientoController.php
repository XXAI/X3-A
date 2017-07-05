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
* Controlador Movimientos
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017-03-22
*
* Controlador `Movimientos`: Controlador  para el manejo de entradas y salidas
*
*/
class MovimientoController extends Controller
{
     
    public function index(Request $request)
    {
        $parametros = Input::only('q','page','per_page','almacen','tipo');

        $parametros['almacen'] = $request->get('almacen_id');

        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }                

        if ($parametros['q'])
        {
            if($parametros['tipo'])
            {
                $data = Movimiento::with('movimientoMetadato','movimientoUsuario')
                                    ->where('id','LIKE',"%".$parametros['q']."%")
                                    ->where('tipo_movimiento_id',$parametros['tipo'])
                                    ->where('almacen_id',$parametros['almacen'])
                                    ->orderBy('updated_at','DESC');
            }else{
                    $data = Movimiento::with('movimientoMetadato','movimientoUsuario')
                                        ->where('id','LIKE',"%".$parametros['q']."%")
                                        ->where('almacen_id',$parametros['almacen'])
                                        ->orderBy('updated_at','DESC');
                 }

        } else {
            
                 if($parametros['tipo'])
                 {
                    $data =  Movimiento::with('movimientoMetadato','movimientoUsuario')
                                        ->where('almacen_id',$parametros['almacen'])
                                        ->where('tipo_movimiento_id',$parametros['tipo'])
                                        ->orderBy('updated_at','DESC');
                 }else{
                        $data =  Movimiento::with('movimientoMetadato','movimientoUsuario')
                                ->where('almacen_id',$parametros['almacen'])
                                ->orderBy('updated_at','DESC');
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
        $errors = array(); 

        $almacen_id=$request->get('almacen_id');       

        $validacion = $this->ValidarMovimiento("", NULL, Input::json()->all(),$almacen_id);
		if(is_array($validacion))
        {
			return Response::json(['error' => $validacion], HttpResponse::HTTP_CONFLICT);
		}
        $datos = (object) Input::json()->all();	
        $success = false;

        $id_tipo_movimiento = $datos->tipo_movimiento_id;
        $tipo_movimiento = TiposMovimientos::Find($datos->tipo_movimiento_id);

        $tipo = NULL;
        if($tipo_movimiento)
            $tipo = $tipo_movimiento->tipo;

///*************************************************************************************************************************************
       
        if($id_tipo_movimiento == 1)
        {

                if(property_exists($datos, "insumos"))
                {
                    if(count($datos->insumos) > 0 )
                    {
                        $detalle = array_filter($datos->insumos, function($v){return $v !== null;});
                        foreach ($detalle as $key => $value)
                            {
                                $validacion_insumos = $this->ValidarInsumos($key, NULL, $value, $tipo);
                                if($validacion_insumos != "")
                                    {
                                        array_push($errors, $validacion_insumos);
                                    }
                            }
                    }else{
                            array_push($errors, array(array('insumos' => array('no_items_insumos'))));
                         }
                    
                }else{
                        array_push($errors, array(array('insumos' => array('no_existe_insumos'))));
                     }

                if( count($errors) > 0 )
                {
                    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
                } 

                DB::beginTransaction();
                try{
                        $movimiento_entrada = new Movimiento;
                        $success = $this->validarTransaccionEntrada($datos, $movimiento_entrada,$almacen_id);
                } catch (\Exception $e) {
                    DB::rollback();
                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                } 
                if ($success){
                    DB::commit();
                    return Response::json(array("status" => 201,"messages" => "Creado","data" => $movimiento_entrada), 201);
                } 
                else{
                    DB::rollback();
                    return Response::json(array("status" => 409,"messages" => "Conflicto"), 409);
                }
        }//FIN IF TIPO MOVIMIENTO = 1  -> ENTRADA MANUAL

///*************************************************************************************************************************************
    
        if($id_tipo_movimiento == 2)
        {
                if(property_exists($datos, "insumos"))
                {
                    if(count($datos->insumos) > 0 )
                    {
                        $detalle = array_filter($datos->insumos, function($v){return $v !== null;});
                        foreach ($detalle as $key => $value)
                            {
                                $validacion_insumos = $this->ValidarInsumos($key, NULL, $value, $tipo);
                                if($validacion_insumos != "")
                                    {
                                        array_push($errors, $validacion_insumos);
                                    }
                            }
                    }else{
                            array_push($errors, array(array('insumos' => array('no_items_insumos'))));
                    }
                }else{
                        array_push($errors, array(array('insumos' => array('no_exist_insumos'))));
                }

                if( count($errors) > 0 )
                {
                    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
                } 

                DB::beginTransaction();
                try{
                        $movimiento_salida = new Movimiento;
                        $success = $this->validarTransaccionSalida($datos, $movimiento_salida,$almacen_id);
                } catch (\Exception $e) {
                    DB::rollback();
                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                } 
                if ($success){
                    DB::commit();
                    $ms = Movimiento::with('movimientoMetadato')->find($movimiento_salida->id);
                    return Response::json(array("status" => 201,"messages" => "Creado","data" => $ms), 201);
                } 
                else{
                    DB::rollback();
                    return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
                }
                
        }///FIN IF TIPO MOVIMIENTO = 2 -->  SALIDA MANUAL

///*************************************************************************************************************************************
////        SALIDA POR RECETA CON METADATOS 
            if($id_tipo_movimiento == 5)
            {

                    if(property_exists($datos, "insumos"))
                {
                    if(count($datos->insumos) > 0 )
                    {
                        $detalle = array_filter($datos->insumos, function($v){return $v !== null;});
                        foreach ($detalle as $key => $value)
                            {
                                $validacion_insumos = $this->ValidarInsumosReceta($key, NULL, $value, $tipo);
                                if($validacion_insumos != "")
                                    {
                                        array_push($errors, $validacion_insumos);
                                    }
                            }
                    }else{
                            array_push($errors, array(array('insumos' => array('no_items_insumos'))));
                    }
                }else{
                        array_push($errors, array(array('insumos' => array('no_exist_insumos'))));
                }

                if( count($errors) > 0 )
                {
                    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
                } 

                DB::beginTransaction();
                try{
                        $movimiento_salida_receta = new Movimiento;
                        $success = $this->validarTransaccionSalidaReceta($datos, $movimiento_salida_receta,$almacen_id);
                } catch (\Exception $e) {
                    DB::rollback();
                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                } 
                if ($success){
                    DB::commit();
                    $ms = Movimiento::with('movimientoMetadato')->find($movimiento_salida_receta->id);
                    return Response::json(array("status" => 201,"messages" => "Creado","data" => $ms), 201);
                } 
                else{
                    DB::rollback();
                    return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
                }


            }/// FIN IF TIPO MOVIMIENTO = 5   -->  SALIDA POR RECETA MEDICA

///*************************************************************************************************************************************

    }


///*************************************************************************************************************************************
///*************************************************************************************************************************************

/////                             S    H    O    W 
///*************************************************************************************************************************************
///*************************************************************************************************************************************

    public function show($id)
    {

        $movimiento =  Movimiento::with('almacen','movimientoMetadato')->find($id);

        if(!$movimiento){
			return Response::json(array("status" => 404,"messages" => "No se encuentra el movimiento solicitado"), 200);
		} 
        $movimiento = (object) $movimiento;


///**************************************************************************************************************************************
///**************************************************************************************************************************************
   
        if( $movimiento->tipo_movimiento_id == 1)
        {
            $insumos = DB::table('movimiento_insumos')
                    ->join('stock', 'movimiento_insumos.stock_id', '=', 'stock.id')
                    ->where('movimiento_insumos.movimiento_id', '=', $id)
                    ->groupby('stock.clave_insumo_medico')
                    ->select(DB::raw('SUM(movimiento_insumos.cantidad) as total_insumo'), 'stock.clave_insumo_medico','modo_salida')
                    ->get();

            $array_insumos = array();   

        ///*****************************************************************************************
            foreach($insumos as $insumo)
            {
                    $objeto_insumo = new \stdClass();
                    $array_lotes = array();

                    $insumos2 = DB::table('movimiento_insumos')
                                ->where('movimiento_id',$id)
                                ->get();

                    foreach($insumos2 as $insumo2)
                    {
                        $lote = DB::table('stock')->find($insumo2->stock_id);
                        
                        if($insumo->clave_insumo_medico == $lote->clave_insumo_medico)
                        {
                            $objeto_lote = new \stdClass();
                            $objeto_lote->id                  = $lote->id;
                            $objeto_lote->clave_insumo_medico = $lote->clave_insumo_medico;
                            $objeto_lote->marca_id            = $lote->marca_id;
                            $objeto_lote->lote                = $lote->lote;
                            $objeto_lote->codigo_barras       = $lote->codigo_barras;
                            $objeto_lote->fecha_caducidad     = $lote->fecha_caducidad;

                            $objeto_lote->modo_salida         = $insumo2->modo_salida;
                            $objeto_lote->cantidad            = $insumo2->cantidad;

                            array_push($array_lotes,$objeto_lote);
                        }
                    }

                    $insumo_detalles = Insumo::conDescripciones()->with('informacionAmpliada')->find($insumo->clave_insumo_medico);

                    $insumo_detalles_temp = Insumo::conDescripciones()->find($insumo->clave_insumo_medico);

                    $movimiento_detalle = MovimientoDetalle::where('movimiento_id',$movimiento->id)
                                          ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                                          ->first();
                    

                    $objeto_insumo              = $insumo_detalles_temp;
                    $objeto_insumo->nombre      = $insumo_detalles_temp->generico_nombre;

                    $objeto_insumo->modo_salida         = $insumo->modo_salida;

                    $objeto_insumo->clave               = $insumo->clave_insumo_medico;
                    $objeto_insumo->cantidad            = $insumo->total_insumo;

                    $objeto_insumo->cantidad_solicitada = $movimiento_detalle ? $movimiento_detalle->cantidad_solicitada : 0;

                    $objeto_insumo->detalles            = property_exists($objeto_insumo, "detalles") ? $objeto_insumo->detalles : $insumo_detalles;
                    $objeto_insumo->lotes               = $array_lotes;

              array_push($array_insumos,$objeto_insumo);
            }
        ///*****************************************************************************************
         
                $movimiento->insumos = $array_insumos;
        }

////**************************************************************************************************************************************
////**************************************************************************************************************************************
        if( $movimiento->tipo_movimiento_id == 2)
        {
            $insumos = DB::table('movimiento_insumos')
                    ->join('stock', 'movimiento_insumos.stock_id', '=', 'stock.id')
                    ->where('movimiento_insumos.movimiento_id', '=', $id)
                    ->where('movimiento_insumos.modo_salida', '=', "N")
                    ->groupby('stock.clave_insumo_medico')
                    ->select(DB::raw('SUM(movimiento_insumos.cantidad) as total_insumo'), 'stock.clave_insumo_medico','modo_salida')
                    ->get();

            $array_insumos = array();   

        ///*****************************************************************************************
            foreach($insumos as $insumo)
            {
                    $objeto_insumo = new \stdClass();
                    $array_lotes = array();

                    $insumos2 = DB::table('movimiento_insumos')
                                ->where('movimiento_id',$id)
                                ->where('modo_salida',"N")
                                ->get();
                    foreach($insumos2 as $insumo2)
                    {
                        $lote = DB::table('stock')->find($insumo2->stock_id);
                        
                        if($insumo->clave_insumo_medico == $lote->clave_insumo_medico)
                        {
                            $objeto_lote = new \stdClass();
                            $objeto_lote->id                  = $lote->id;
                            $objeto_lote->clave_insumo_medico = $lote->clave_insumo_medico;
                            $objeto_lote->marca_id            = $lote->marca_id;
                            $objeto_lote->lote                = $lote->lote;
                            $objeto_lote->codigo_barras       = $lote->codigo_barras;
                            $objeto_lote->fecha_caducidad     = $lote->fecha_caducidad;

                            $objeto_lote->modo_salida         = $insumo2->modo_salida;
                            $objeto_lote->cantidad            = $insumo2->cantidad;

                            array_push($array_lotes,$objeto_lote);
                        }
                    }

                    $insumo_detalles = Insumo::conDescripciones()->with('informacionAmpliada')->find($insumo->clave_insumo_medico);

                    $insumo_detalles_temp = Insumo::conDescripciones()->find($insumo->clave_insumo_medico);

                    $movimiento_detalle = MovimientoDetalle::where('movimiento_id',$movimiento->id)
                                          ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                                          ->first();
                    

                    $objeto_insumo              = $insumo_detalles_temp;
                    $objeto_insumo->nombre      = $insumo_detalles_temp->generico_nombre;

                    $objeto_insumo->modo_salida         = $insumo->modo_salida;

                    $objeto_insumo->clave               = $insumo->clave_insumo_medico;
                    $objeto_insumo->cantidad            = $insumo->total_insumo;

                    $objeto_insumo->cantidad_solicitada = $movimiento_detalle ? $movimiento_detalle->cantidad_solicitada : 0;

                    $objeto_insumo->detalles            = property_exists($objeto_insumo, "detalles") ? $objeto_insumo->detalles : $insumo_detalles;
                    $objeto_insumo->lotes               = $array_lotes;

              array_push($array_insumos,$objeto_insumo);
            }
        ///*****************************************************************************************
        $insumos = DB::table('movimiento_insumos')
                    ->join('stock', 'movimiento_insumos.stock_id', '=', 'stock.id')
                    ->where('movimiento_insumos.movimiento_id', '=', $id)
                    ->where('movimiento_insumos.modo_salida', '=', "U")
                    ->groupby('stock.clave_insumo_medico')
                    ->select(DB::raw('SUM(movimiento_insumos.cantidad_unidosis) as total_insumo'), 'stock.clave_insumo_medico','modo_salida')
                    ->get();
        ///*****************************************************************************************
            foreach($insumos as $insumo)
            {
                    $objeto_insumo = new \stdClass();
                    $array_lotes = array();

                    $insumos2 = DB::table('movimiento_insumos')
                                ->where('movimiento_id',$id)
                                ->where('modo_salida',"U")
                                ->get();
                    foreach($insumos2 as $insumo2)
                    {
                        $lote = DB::table('stock')->find($insumo2->stock_id);
                        
                        if($insumo->clave_insumo_medico == $lote->clave_insumo_medico)
                        {
                            $objeto_lote = new \stdClass();
                            $objeto_lote->id                  = $lote->id;
                            $objeto_lote->clave_insumo_medico = $lote->clave_insumo_medico;
                            $objeto_lote->marca_id            = $lote->marca_id;
                            $objeto_lote->lote                = $lote->lote;
                            $objeto_lote->codigo_barras       = $lote->codigo_barras;
                            $objeto_lote->fecha_caducidad     = $lote->fecha_caducidad;

                            $objeto_lote->modo_salida         = $insumo2->modo_salida;
                            $objeto_lote->cantidad            = $insumo2->cantidad_unidosis;

                            array_push($array_lotes,$objeto_lote);
                        }
                    }

                    $insumo_detalles = Insumo::conDescripciones()->with('informacionAmpliada')->find($insumo->clave_insumo_medico);
                    $insumo_detalles_temp = Insumo::conDescripciones()->find($insumo->clave_insumo_medico);

                    $movimiento_detalle = MovimientoDetalle::where('movimiento_id',$movimiento->id)
                                          ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                                          ->first();
                    

                    $objeto_insumo              = $insumo_detalles_temp;
                    $objeto_insumo->nombre      = $insumo_detalles_temp->generico_nombre;

                    $objeto_insumo->clave               = $insumo->clave_insumo_medico;
                    $objeto_insumo->cantidad            = $insumo->total_insumo;

                    $objeto_insumo->modo_salida         = $insumo->modo_salida;

                    $objeto_insumo->cantidad_solicitada = $movimiento_detalle ? $movimiento_detalle->cantidad_solicitada : 0;

                    $objeto_insumo->detalles            = property_exists($objeto_insumo, "detalles") ? $objeto_insumo->detalles : $insumo_detalles;
                    $objeto_insumo->lotes               = $array_lotes;

              array_push($array_insumos,$objeto_insumo);
            }


        ///*******************************************************************************************************************************
        ///*******************************************************************************************************************************
 
                     
                $movimiento_detalle_negados = MovimientoDetalle::where('movimiento_id',$movimiento->id)
                                          ->where('cantidad_surtida','<=',0)
                                          ->get();
                $array_insumos_negados = array();

                $objeto_negado = new \stdClass();

                foreach($movimiento_detalle_negados as $negado)
                {
                    $insumo_detalles = Insumo::conDescripciones()->with('informacionAmpliada')->find($negado->clave_insumo_medico);

                    $objeto_negado  = $insumo_detalles;

                    $objeto_negado->clave_insumo_medico          = $negado->clave_insumo_medico;
                    $objeto_negado->nombre                       = $insumo_detalles->generico_nombre;
                    $objeto_negado->modo_salida                  = $negado->modo_salida;

                    $objeto_negado->cantidad_solicitada          = $negado->cantidad_solicitada;
                    $objeto_negado->cantidad_solicitada_unidosis = $negado->cantidad_solicitada_unidosis;

                    $objeto_negado->cantidad_surtida          = $negado->cantidad_surtida;
                    $objeto_negado->cantidad_surtida_unidosis = $negado->cantidad_surtida_unidosis;

                    $objeto_negado->cantidad_negada              = $negado->cantidad_negada;
                    $objeto_negado->cantidad_negada_unidosis     = $negado->cantidad_negada_unidosis;

                    array_push($array_insumos_negados,$objeto_negado);

                }
                                          

                //var_dump($movimiento_detalle_negados); die();

            
        ///******************************************************************************************************************************
        ///*******************************************************************************************************************************
                $movimiento->insumos = $array_insumos;

                $movimiento->insumos_negados = $array_insumos_negados;


        }

////**************************************************************************************************************************************
////**************************************************************************************************************************************
 
       
        if($movimiento->tipo_movimiento_id == 5)
        {
              $insumos = DB::table('movimiento_insumos')
                    ->join('stock', 'movimiento_insumos.stock_id', '=', 'stock.id')
                    ->where('movimiento_insumos.movimiento_id', '=', $id)
                    ->groupby('stock.clave_insumo_medico')
                    ->select(DB::raw('SUM(movimiento_insumos.cantidad) as total_insumo'), 'stock.clave_insumo_medico')
                    ->get();

             //$receta = NULL;
             $receta_movimiento = RecetaMovimiento::where('movimiento_id',$movimiento->id)->with('receta')->first();

             $receta            = $receta_movimiento->receta;
             
             $receta_detalles   = $receta->recetaDetalles; 

             $array_insumos  = array();            

                foreach($insumos as $insumo)
                {
                    $objeto_insumo = new \stdClass();
                    $array_lotes = array();

                    $insumos2 = DB::table('movimiento_insumos')->where('movimiento_id',$id)->get();
                    foreach($insumos2 as $insumo2)
                    {
                        $lote = DB::table('stock')->find($insumo2->stock_id);

                        if($insumo->clave_insumo_medico == $lote->clave_insumo_medico)
                        {
                            $objeto_lote = new \stdClass();
                            
                            $objeto_lote->id                  = $lote->id;
                            $objeto_lote->clave_insumo_medico = $lote->clave_insumo_medico;
                            $objeto_lote->marca_id            = $lote->marca_id;
                            $objeto_lote->lote                = $lote->lote;
                            $objeto_lote->codigo_barras       = $lote->codigo_barras;
                            $objeto_lote->fecha_caducidad     = $lote->fecha_caducidad;

                            $objeto_lote->cantidad            = $insumo2->cantidad;

                            array_push($array_lotes,$objeto_lote);
                        }
                    }

                    $insumo_detalles       = Insumo::conDescripciones()->find($insumo->clave_insumo_medico);
                    //$insumo_detalles_temp  = Insumo::conDescripciones()->with('informacionAmpliada')->find($insumo->clave_insumo_medico);
                    //$nombre_temp           = $insumo_detalles_temp->informacionAmpliada->nombre;

                    $detalle = NULL;
                    foreach ($receta_detalles as $key => $item_detalle)
                    {
                        if($item_detalle->clave_insumo_medico == $insumo->clave_insumo_medico)
                        {
                            $detalle = $item_detalle;
                        }
                    }

                    $objeto_insumo                    = $insumo_detalles;
                    $objeto_insumo->nombre            = $insumo_detalles->generico_nombre;

                    $objeto_insumo->clave             = $insumo->clave_insumo_medico;
                    $objeto_insumo->cantidad          = $insumo->total_insumo;
                    $objeto_insumo->cantidad_surtida  = $insumo->total_insumo;

                    $objeto_insumo->dosis               = $detalle->dosis;
                    $objeto_insumo->frecuencia          = $detalle->frecuencia;
                    $objeto_insumo->duracion            = $detalle->duracion;
                    $objeto_insumo->cantidad_recetada   = $detalle->cantidad;

                    //$objeto_insumo->detalles = property_exists($objeto_insumo, "detalles") ? $objeto_insumo->detalles : $insumo_detalles;

                    $objeto_insumo->lotes    = $array_lotes;

                    array_push($array_insumos,$objeto_insumo);
                }
        
                $movimiento->receta  = $receta;
                $movimiento->insumos = $array_insumos;
        }
///****************************************************************************************************************************************

        

			return Response::json(array("status" => 200,"messages" => "Operación realizada con exito","data" => $movimiento), 200);
		 
        
    }

   

///***************************************************************************************************************************
///***************************************************************************************************************************
 


    public function update(Request $request, $id)
    {
 
    }
     
    public function destroy($id)
    {
        
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
 
	private function ValidarMovimiento($key, $id, $request)
    { 
        $mensajes = [
                        'required'      => "required",
                        'email'         => "email",
                        'unique'        => "unique",
                        'integer'       => "not_integer",
                        'in'            => 'no_valido',
                    ];

        $reglas = array();
        $reglas = [
                    'tipo_movimiento_id' => 'required|integer|in:1,2,3,4,5,6,7,8',
                  ];

        if($request['movimiento_metadato'] != NULL)
        {
            if($request['tipo_movimiento_id'] == 1 )
            {
                $reglas = [
                            'tipo_movimiento_id'                 => 'required|integer|in:1,2,3,4,5,6,7,8',
                            'movimiento_metadato.persona_recibe' => 'required|string',
                          ];
            }
            if($request['tipo_movimiento_id'] == 2 )
            {
                $reglas = [
                            'tipo_movimiento_id'                 => 'required|integer|in:1,2,3,4,5,6,7,8',
                            'movimiento_metadato.servicio_id'    => 'required|integer',
                            'movimiento_metadato.persona_recibe' => 'required|string',
                            'movimiento_metadato.turno_id'       => 'required|integer',
                          ];
            }
            

        }else if($request['receta'] != NULL){
                
            $reglas = [
                        'tipo_movimiento_id'    => 'required|integer|in:5',
                        'receta.folio'          => 'required|string',
                        'receta.tipo_receta'    => 'required|string',
                        'receta.fecha_receta'   => 'required',
                        'receta.doctor'         => 'required|string',
                        'receta.paciente'       => 'required|string',
                        'receta.diagnostico'    => 'required|string',
                        'receta.imagen_receta'  => 'required|string',
                      ];
        }

    $v = \Validator::make($request, $reglas, $mensajes );

    //*********************************************************************************************************
        if ($v->fails())
        {
			$mensages_validacion = array();
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                    foreach ($item as $msg)
                    {
                        array_push($msg_validacion, $msg);
                    }
                    array_push($mensages_validacion, array($indice.''.$key => $msg_validacion));
			}
			return $mensages_validacion;
        }else{
                return true;
             }
	}

///**************************************************************************************************************************
///**************************************************************************************************************************
   
    private function ValidarInsumos($key, $id, $request,$tipo){ 
        $mensajes = [
                        'required'      => "required",
                        'email'         => "email",
                        'unique'        => "unique",
                        'integer'       => "integer",
                        'min'           => "min"
                    ];

        if($tipo=='E')
                {
                    $reglas = [
                                'clave'                 => 'required',
                                'cantidad'              => 'required|integer',
                                'cantidad_x_envase'     => 'required|integer',
                                'lote'                  => 'required',
                                'fecha_caducidad'       => 'required',
                                
                              ];
                }else{
                        $reglas = [
                                    'clave'                 => 'required',
                                    'cantidad'              => 'required|integer',
                                    'cantidad_solicitada'   => 'required|numeric',
                                    'cantidad_x_envase'     => 'required|integer',
                                  ];
                     }
                     
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        //$lotes = array();
        //$request_object = (object) $request;
        //$lotes = $request_object->lotes;

        if($tipo=='S')
        {
            foreach($request['lotes'] as $i => $lote)
            {
                $lote = (object) $lote;
                $lote_check =  Stock::where('clave_insumo_medico',$request['clave'])->find($lote->id);

                $v->after(function($v) use($lote,$lote_check,$i)
                {
                    ///****************************************************************************************
                    if($lote_check)
                    {
                        if($lote->cantidad <= 0)
                        {
                            $v->errors()->add('lote_'.$lote->id.'_', 'cantidad_invalida');
                        }

                        /// validar cantidad solicitada en req contra lo del find
                        if($lote->modo_salida=='N')
                        {
                            if($lote->cantidad <= $lote_check->existencia)
                            {
                            }else{
                                    $v->errors()->add('lote_'.$lote->id.'_', 'lote_insuficiente');
                                }
                        }else {
                                if($lote->cantidad <= $lote_check->existencia_unidosis)
                                {
                                }else{
                                        $v->errors()->add('lote_'.$lote->id.'_', 'lote_insuficiente');
                                    }
                               }
                        

                    }else{
                            if($lote->cantidad <= 0)
                            {
                                $v->errors()->add('lote_'.$lote->id.'_', 'cantidad_invalida');
                            }

                            if(property_exists($lote, 'nuevo'))
                            {
                                // verificar si existe lote,codigo, barra y fecha cad
                            }else{
                                    $v->errors()->add('lote_'.$lote->id.'_', 'no_existe'); 
                                 }
                         }
                  ///****************************************************************************************    
                     
                  

                });
            }    
        }// FIN IF TIPO SALIDA

        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                    foreach ($item as $msg)
                    {
                        array_push($msg_validacion, $msg);
                    }
                    array_push($mensages_validacion, array($indice.''.$key => $msg_validacion));
			}
           
			return $mensages_validacion;
        }else{
                return ;
             }
	}

///***************************************************************************************************************************
///***************************************************************************************************************************
  
    private function ValidarInsumosReceta($key, $id, $request,$tipo){ 
        $mensajes = [
                        'required'      => "required",
                        'email'         => "email",
                        'unique'        => "unique",
                        'integer'       => "integer",
                        'min'           => "min"
                    ];

        $reglas = [
                        'clave'                 => 'required|string',
                        'cantidad'              => 'required|integer',
                        'cantidad_x_envase'     => 'required|integer',
                        'dosis'                 => 'required|numeric',
                        'frecuencia'            => 'required|numeric',
                        'duracion'              => 'required|numeric',
                        'cantidad_recetada'     => 'required|integer',
                  ];
                           
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();

        if($tipo=='S')
        {
            foreach($request['lotes'] as $i => $lote)
            {
                $lote = (object) $lote;
                $lote_check =  Stock::where('clave_insumo_medico',$request['clave'])->find($lote->id);

                $v->after(function($v) use($lote,$lote_check,$i)
                {
                    if($lote_check)
                    {
                        if($lote->cantidad <= 0)
                        {
                            $v->errors()->add('lote_'.$lote->id.'_', 'cantidad_invalida');
                        }
                        /// validar cantidad solicitada en req contra lo del find
                        if($lote->cantidad <= $lote_check->existencia)
                        {

                        }else{
                                $v->errors()->add('lote_'.$lote->id.'_', 'lote_insuficiente');
                             }
                    }else{
                            if($lote->cantidad <= 0)
                            {
                                $v->errors()->add('lote_'.$lote->id.'_', 'cantidad_invalida');
                            }

                            if(property_exists($lote, 'nuevo'))
                            {
                                // verificar si existe lote,codigo, barra y fecha cad
                            }else{
                                    $v->errors()->add('lote_'.$lote->id.'_', 'no_existe'); 
                                 }
                        }
                });
            }    
        }// FIN IF TIPO SALIDA

        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                    foreach ($item as $msg)
                    {
                        array_push($msg_validacion, $msg);
                    }
                    array_push($mensages_validacion, array($indice.''.$key => $msg_validacion));
			}
           
			return $mensages_validacion;
        }else{
                return ;
             }
	}




///***************************************************************************************************************************
///                  M O V I M I E N T O         E  N  T  R  A  D  A
///***************************************************************************************************************************


    private function validarTransaccionEntrada($datos, $movimiento_entrada,$almacen_id){
		$success = false;
        //comprobar que el servidor id no me lo envian por parametro, si no poner el servidor por default de la configuracion local, si no seleccionar el servidor del parametro
        $servidor_id = property_exists($datos, "servidor_id") ? $datos->servidor_id : env('SERVIDOR_ID');

        //agregar al modelo los datos
        $movimiento_entrada->almacen_id                   =  $almacen_id;
        $movimiento_entrada->tipo_movimiento_id           =  $datos->tipo_movimiento_id;
        $movimiento_entrada->status                       =  $datos->status; 
        $movimiento_entrada->fecha_movimiento             =  property_exists($datos, "fecha_movimiento")          ? $datos->fecha_movimiento          : '';
        $movimiento_entrada->observaciones                =  property_exists($datos, "observaciones")             ? $datos->observaciones             : '';
        $movimiento_entrada->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
        $movimiento_entrada->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';

        // si se guarda el maestro tratar de guardar el detalle  
        if( $movimiento_entrada->save() )
        {
            $success = true;

            if(property_exists($datos,"movimiento_metadato"))
            {
                $metadatos = new MovimientoMetadato;
                $metadatos->movimiento_id  = $movimiento_entrada->id;
                $metadatos->servicio_id    = $datos->movimiento_metadato['servicio_id'];
                $metadatos->persona_recibe = $datos->movimiento_metadato['persona_recibe'];


                $metadatos->save();   
            }

            //verificar si existe contacto, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "insumos")){
                 $detalle = array_filter($datos->insumos, function($v){return $v !== null;});


                 foreach ($detalle as $key => $value)
                {
                     if($value != null)
                     {
                         if(is_array($value))
                            $value = (object) $value;

                        $precio_insumo = $this->conseguirPrecio($value->clave);

                        //Verificar si esta en la lista de negados
                        $negacion = NegacionInsumo::where('almacen_id',$almacen_id)
                                                ->where('clave_insumo_medico',$value->clave)
                                                ->first();
                        if($negacion)
                        {
                            $negacion->delete();
                        }


                        $item_stock = new Stock;

                        $item_stock->almacen_id             = $almacen_id;
                        $item_stock->clave_insumo_medico    = $value->clave;
                        $item_stock->marca_id               = NULL;
                        $item_stock->lote                   = $value->lote;
                        $item_stock->fecha_caducidad        = $value->fecha_caducidad;
                        $item_stock->codigo_barras          = $value->codigo_barras;
                        $item_stock->existencia             = $value->cantidad;
                        $item_stock->existencia_unidosis    = ( $value->cantidad_x_envase * $value->cantidad );

                        $item_stock_check = Stock::where('clave_insumo_medico',$value->clave)
                                                 ->where('lote',$value->lote)
                                                 ->where('fecha_caducidad',$value->fecha_caducidad)
                                                 ->where('codigo_barras',$value->codigo_barras)
                                                 ->where('almacen_id',$almacen_id)->first();
                        if($item_stock_check)
                        {
                            $item_stock_check->existencia           = $item_stock_check->existencia + $value->cantidad;
                            $item_stock_check->existencia_unidosis  = $item_stock_check->existencia_unidosis + ( $value->cantidad_x_envase * $value->cantidad );
                            
                            if( $item_stock_check->save() )
                            {
                                    $item_detalles = new MovimientoInsumos;

                                    $item_detalles->movimiento_id           = $movimiento_entrada->id; 
                                    $item_detalles->stock_id                = $item_stock_check->id;
                                    $item_detalles->clave_insumo_medico     = $value->clave;

                                    $item_detalles->modo_salida             = "N";
                                    $item_detalles->cantidad                = $value->cantidad;
                                    $item_detalles->cantidad_unidosis       = $value->cantidad * $value->cantidad_x_envase;

                                    $item_detalles->precio_unitario         = $precio_insumo['precio_unitario'];
                                    $item_detalles->iva                     = $precio_insumo['iva']; 
                                    $item_detalles->precio_total            = ( $precio_insumo['precio_unitario'] + $precio_insumo['iva'] ) * $value->cantidad;

                                    $item_detalles->save(); 
                            }else{   
                                    return Response::json(['error' => $validacion_insumos], HttpResponse::HTTP_CONFLICT);
                                }

                        }else{
                                    if($item_stock->save())
                                    {
                                        $item_detalles = new MovimientoInsumos;

                                        $item_detalles->movimiento_id           = $movimiento_entrada->id; 
                                        $item_detalles->stock_id                = $item_stock->id;
                                        $item_detalles->clave_insumo_medico     = $value->clave;

                                        $item_detalles->modo_salida             = "N";
                                        $item_detalles->cantidad                = $item_stock->existencia;
                                        $item_detalles->cantidad_unidosis       = $item_stock->existencia_unidosis;

                                        $item_detalles->precio_unitario         = $precio_insumo['precio_unitario'];
                                        $item_detalles->iva                     = $precio_insumo['iva']; 
                                        $item_detalles->precio_total            = ( $precio_insumo['precio_unitario'] + $precio_insumo['iva'] ) * $item_stock->existencia;

                                        $item_detalles->save();

                                    }else{
                                            return Response::json(['error' => $validacion_insumos], HttpResponse::HTTP_CONFLICT);
                                         }
                             }

                            
                    }
                }
            }               
        }
        
        return $success;
    }

    
///**************************************************************************************************************************
///                  M O V I M I E N T O         S  A  L  I  D  A
///**************************************************************************************************************************
       

    private function validarTransaccionSalida($datos, $movimiento_salida,$almacen_id){
		$success = false;

        //comprobar que el servidor id no me lo envian por parametro, si no poner el servidor por default de la configuracion local, si no seleccionar el servidor del parametro
        $servidor_id = property_exists($datos, "servidor_id") ? $datos->servidor_id : env('SERVIDOR_ID');

        //agregar al modelo los datos
        $movimiento_salida->almacen_id                   =  $almacen_id;
        $movimiento_salida->tipo_movimiento_id           =  $datos->tipo_movimiento_id;
        $movimiento_salida->status                       =  $datos->status; 
        $movimiento_salida->fecha_movimiento             =  property_exists($datos, "fecha_movimiento")          ? $datos->fecha_movimiento          : '';
        $movimiento_salida->observaciones                =  property_exists($datos, "observaciones")             ? $datos->observaciones             : '';
        $movimiento_salida->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
        $movimiento_salida->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';

        $lotes_master = array();

        // si se guarda el movimiento tratar de guardar el detalle de insumos
        if( $movimiento_salida->save() )
        {
            $success = true;

            if(property_exists($datos,"movimiento_metadato"))
            {
                $metadatos = new MovimientoMetadato;
                $metadatos->movimiento_id  = $movimiento_salida->id;
                $metadatos->servicio_id    = $datos->movimiento_metadato['servicio_id'];
                $metadatos->persona_recibe = $datos->movimiento_metadato['persona_recibe'];
                $metadatos->turno_id       = $datos->movimiento_metadato['turno_id'];

                $metadatos->save();
                
            }


            if(property_exists($datos, "insumos"))
            {
                $insumos = array_filter($datos->insumos, function($v){return $v !== null;});

                $lotes_nuevos  = array();
                $lotes_ajustar = array();
 
        ///  PRIMER PASADA PARA IDENTIFICAR LOS LOTES NUEVOS A AJUSTAR / GENERAR ENTRADA 
                 foreach ($insumos as $key => $insumo)
                { 
                     if($insumo != null)
                     {
                         if(is_array($insumo)){ $insumo = (object) $insumo; }

                         $clave_insumo_medico = $insumo->clave;
                         
                         $precio = $this->conseguirPrecio($clave_insumo_medico);

                                foreach($insumo->lotes as $index => $lote)
                                {
                                     if(is_array($lote)){ $lote = (object) $lote; }


                                     ///REVISAR MODO NORMAL Ó MODO UNIDOSIS AQUI

                                    if($lote->nuevo == 1)
                                    {
                                         $lote_temp = Stock::where('lote',$lote->lote)
                                                            ->where('fecha_caducidad',$lote->fecha_caducidad)
                                                            ->where('codigo_barras',$lote->codigo_barras)
                                                            ->where('clave_insumo_medico',$clave_insumo_medico)
                                                            ->where('almacen_id',$almacen_id)
                                                            ->orderBy('created_at','DESC')->first();

                                        /// si ya existe un lote vacio con esos detalles : se agrega uno
                                        if($lote_temp)
                                          { 
                                                // Se actualiza el stock y sus detalles
                                                $lote_temp->existencia          = $lote_temp->existencia + $lote->existencia;
                                                $lote_temp->existencia_unidosis = $lote_temp->existencia_unidosis + ($lote->existencia * $insumo->cantidad_x_envase);
                                                
                                                //$lote_temp->unidosis_sueltas    = 0;
                                                //$lote_temp->existencia_unidosis = 0;
                                                //aqui se calcula la exstencia unidosis parcial

                                                $lote_temp->save();
                                                // adicion del campo cantidad al objeto lote/stock
                                                $lote_temp->cantidad    = property_exists($lote_temp, "cantidad") ? $lote_temp->cantidad : $lote->cantidad;
                                                $lote_temp->modo_salida = property_exists($lote_temp, "modo_salida") ? $lote_temp->modo_salida : $lote->modo_salida;

                                                array_push($lotes_nuevos,$lote_temp);
                                                array_push($lotes_master,$lote_temp);
                                          }else{
                                                    $lote_insertar = new Stock;

                                                    $lote_insertar->almacen_id             = $movimiento_salida->almacen_id;
                                                    $lote_insertar->clave_insumo_medico    = $clave_insumo_medico;
                                                    $lote_insertar->marca_id               = NULL;
                                                    $lote_insertar->lote                   = $lote->lote;
                                                    $lote_insertar->fecha_caducidad        = $lote->fecha_caducidad;
                                                    $lote_insertar->codigo_barras          = $lote->codigo_barras;
                                                    $lote_insertar->existencia             = $lote->existencia;
                                                    $lote_insertar->existencia_unidosis    = ( $insumo->cantidad_x_envase * $lote->cantidad );

                                                    $lote_insertar->unidosis_sueltas  = 0;
                                                    $lote_insertar->envases_parciales = 0;

                                                    $lote_insertar->save();
                                                    //      adicion del campo cantidad al objeto lote/stock
                                                    $lote_insertar->cantidad    = property_exists($lote_insertar, "cantidad") ? $lote_insertar->cantidad : $lote->cantidad;
                                                    $lote_insertar->modo_salida = property_exists($lote_insertar, "modo_salida") ? $lote_insertar->modo_salida : $lote->modo_salida;

                                                    array_push($lotes_nuevos,$lote_insertar);
                                                    array_push($lotes_master,$lote_insertar);
                                               }

                                    }else{
                                            // aqui ya trae el campo cantidad el objeto lote/stock
                                            array_push($lotes_master,$lote);
                                         }
                                /// *********************************************************************************************************************************************************************************************************
                                /// *********************************************************************************************************************************************************************************************************
                                                                    
                                } /// FIN PRIMER FOREACH QUE RECORRE TODOS LOS INSUMOS PARA SALIR

                        /// GUARDAR MOVIMIENTO_DETALLES DEL INSUMO RECORRIDO 
                       $stocks = Stock::where('clave_insumo_medico',$clave_insumo_medico)
                                      ->where('existencia','>',0)
                                      ->where('almacen_id',$almacen_id)
                                      ->orderBy('fecha_caducidad','ASC')->get();

                        $existencia = 0;
                        $existencia_unidosis = 0;
 
                        foreach($stocks as $stock)
                        {   
                            $existencia          += $stock->existencia; 
                            $existencia_unidosis += $stock->existencia_unidosis; 
                        }  

                        $movimiento_detalle = new MovimientoDetalle;
                        $movimiento_detalle->movimiento_id       = $movimiento_salida->id;
                        $movimiento_detalle->clave_insumo_medico = $clave_insumo_medico;
                        $movimiento_detalle->modo_salida         = $insumo->modo_salida;

                        if($insumo->modo_salida == 'N')
                        {
                            $movimiento_detalle->cantidad_solicitada          = $insumo->cantidad_solicitada;
                            $movimiento_detalle->cantidad_solicitada_unidosis = $insumo->cantidad_solicitada * $insumo->cantidad_x_envase;

                            $movimiento_detalle->cantidad_existente           = $existencia;
                            $movimiento_detalle->cantidad_existente_unidosis  = $existencia_unidosis;

                            $movimiento_detalle->cantidad_surtida             = $insumo->cantidad_surtida;
                            $movimiento_detalle->cantidad_surtida_unidosis    = $insumo->cantidad_surtida * $insumo->cantidad_x_envase;

                            $movimiento_detalle->cantidad_negada              = $insumo->cantidad_solicitada - $insumo->cantidad_surtida;
                            $movimiento_detalle->cantidad_negada_unidosis     = ( $insumo->cantidad_solicitada - $insumo->cantidad_surtida ) * ( $insumo->cantidad_x_envase ); 

                        }else{
                                $movimiento_detalle->cantidad_solicitada          = $insumo->cantidad_solicitada / $insumo->cantidad_x_envase;
                                $movimiento_detalle->cantidad_solicitada_unidosis = $insumo->cantidad_solicitada ;

                                $movimiento_detalle->cantidad_existente           = $existencia;
                                $movimiento_detalle->cantidad_existente_unidosis  = $existencia_unidosis;

                                $movimiento_detalle->cantidad_surtida             = $insumo->cantidad_surtida / $insumo->cantidad_x_envase;
                                $movimiento_detalle->cantidad_surtida_unidosis    = $insumo->cantidad_surtida ;

                                $movimiento_detalle->cantidad_negada              = ( $insumo->cantidad_solicitada - $insumo->cantidad_surtida ) / ( $insumo->cantidad_x_envase );
                                $movimiento_detalle->cantidad_negada_unidosis     = ( $insumo->cantidad_solicitada - $insumo->cantidad_surtida ) ; 

                             } 
                        
                        

                        
                        $movimiento_detalle->save();




                        // AQUI  LA TABLA DE ESTADISTICAS PARA NEGACIONES

                        if($movimiento_detalle->cantidad_negada > 0)
                        {
                            $negacion_resusitada = 0;
                            $tipo_insumo_id = 0;

                            $contrato_precio = ContratoPrecio::where('insumo_medico_clave',$clave_insumo_medico)->first();
                            if($contrato_precio)
                            {
                                $tipo_insumo_id = $contrato_precio->tipo_insumo_id;
                            }





                            // Si no existe registro para resusitar, se comprueba existencia de registro activo
                            $negacion = NegacionInsumo::where('almacen_id',$almacen_id)
                                                ->where('clave_insumo_medico',$clave_insumo_medico)
                                                ->first();
                            if(!$negacion)
                            {
                                    // Busqueda de registro de negación a resusitar para el insumo negado
                                    $negacion = DB::table('negaciones_insumos')
                                                    ->where('clave_insumo_medico',$clave_insumo_medico)
                                                    ->where('deleted_at','!=',NULL)
                                                    ->first();

                                    // Si existe registro muerto se resusita
                                    if($negacion)
                                    {
                                        DB::update("update negaciones_insumos set deleted_at = null where id = '".$negacion->id."'");
                                        $negacion_resusitada = 1;
                                    } 
                                 }

                            $negacion_insumo = null;
                            $almacen = Almacen::find($almacen_id);

                            ///*************************************************************************************************
                            // Encontrar ultima entrada al almacen del insumo negado
                            $ultima_entrada                  = NULL;
                            $cantidad_entrada                = 0;
                            $cantidad_entrada_unidosis       = 0;

                                $ultima_entrada_insumo = DB::table('movimientos')
                                            ->join('movimiento_insumos', 'movimientos.id', '=', 'movimiento_insumos.movimiento_id')
                                            ->select('movimientos.*', 'movimiento_insumos.clave_insumo_medico', 'movimiento_insumos.cantidad', 'movimiento_insumos.cantidad_unidosis')
                                            ->where('movimientos.almacen_id',$almacen_id)
                                            ->where('movimiento_insumos.clave_insumo_medico',$clave_insumo_medico)
                                            ->where('movimientos.tipo_movimiento_id',1)
                                            ->orderBy('created_at','DESC')
                                            ->first();

                                //var_dump($ultima_entrada_insumo); die();


                                if($ultima_entrada_insumo)
                                {
                                    $ultima_entrada                = $ultima_entrada_insumo->created_at;
                                    $cantidad_entrada              = $ultima_entrada_insumo->cantidad;
                                    $cantidad_entrada_unidosis     = $ultima_entrada_insumo->cantidad_unidosis;
                                }
                             ///**************************************************************************************************************************
                           
                           $cantidad_negada          = 0;
                           $cantidad_negada_unidosis = 0;

                                if($insumo->modo_salida == 'N')
                                {
                                    $cantidad_negada          = $movimiento_detalle->cantidad_negada;
                                    $cantidad_negada_unidosis = $movimiento_detalle->cantidad_negada * $insumo->cantidad_x_envase;
                                }else{
                                        $cantidad_negada          = $movimiento_detalle->cantidad_negada / $insumo->cantidad_x_envase;
                                        $cantidad_negada_unidosis = $movimiento_detalle->cantidad_negada;
                                     }
                           
                            // Si existe registro de negación de insumo ( activo ó resusitado )
                            if($negacion)
                            { 
                                $negacion_insumo  = NegacionInsumo::find($negacion->id);

                                if($negacion_resusitada == 1)
                                {
                                    //$negacion_insumo                                = $negacion;

                                    $negacion_insumo->fecha_inicio                  = date("Y-m-d");
                                    $negacion_insumo->fecha_fin                     = date("Y-m-d");
                                    $negacion_insumo->cantidad_acumulada            = $cantidad_negada;
                                    $negacion_insumo->cantidad_acumulada_unidosis   = $cantidad_negada_unidosis;
                                    $negacion_insumo->ultima_entrada                = $ultima_entrada;
                                    $negacion_insumo->cantidad_entrada              = $cantidad_entrada;
                                    $negacion_insumo->cantidad_entrada_unidosis     = $cantidad_entrada_unidosis;

                                }else{
                                        //$negacion_insumo                                = $negacion;

                                        $negacion_insumo->cantidad_acumulada            = $negacion_insumo->cantidad_acumulada + $cantidad_negada;
                                        $negacion_insumo->cantidad_acumulada_unidosis   = $negacion_insumo->cantidad_acumulada_unidosis + $cantidad_negada_unidosis;
                                        $negacion_insumo->fecha_fin                     = date("Y-m-d");
                                        $negacion_insumo->ultima_entrada                = $ultima_entrada;
                                        $negacion_insumo->cantidad_entrada              = $cantidad_entrada;
                                        $negacion_insumo->cantidad_entrada_unidosis     = $cantidad_entrada_unidosis; 
                                     }

                                
                                $negacion_insumo->save();     
                                
                            }else{
                                    $negacion_insumo = new NegacionInsumo;

                                   // var_dump(json_encode($negacion_insumo)); die();

                                    $negacion_insumo->clave_insumo_medico           = $clave_insumo_medico;
                                    $negacion_insumo->clues                         = $almacen->clues;
                                    $negacion_insumo->almacen_id                    = $almacen_id;
                                    $negacion_insumo->tipo_insumo                   = $tipo_insumo_id;
                                    $negacion_insumo->fecha_inicio                  = date("Y-m-d");
                                    $negacion_insumo->fecha_fin                     = date("Y-m-d");
                                    $negacion_insumo->cantidad_acumulada            = $cantidad_negada;
                                    $negacion_insumo->cantidad_acumulada_unidosis   = $cantidad_negada_unidosis;
                                    $negacion_insumo->ultima_entrada                = $ultima_entrada;
                                    $negacion_insumo->cantidad_entrada              = $cantidad_entrada;
                                    $negacion_insumo->cantidad_entrada_unidosis     = $cantidad_entrada_unidosis;

                                    //var_dump($negacion_insumo); die();

                                    $negacion_insumo->save();
                                 }
                        }

                        

        
                        

                    }///FIN IF INSUMO != NULL
                }////   FIN FOREACH     I N S U M O S     -> PRIMERA PASADA
///********************************************************************************************************************************************
                                /// insertar el movimiento entrada de ajuste y ligar los detalles con su stock ya agregado en pasada anterior
                                if(count($lotes_nuevos) > 0)
                                {
                                    /// insertar movimiento de entrada por ajuste ( tipo_movimiento_id = 6 )
                                    $movimiento_ajuste = new Movimiento;
                                    $movimiento_ajuste->almacen_id                   =  $almacen_id;
                                    $movimiento_ajuste->tipo_movimiento_id           =  6;
                                    $movimiento_ajuste->status                       =  "FI";  
                                    $movimiento_ajuste->fecha_movimiento             =  date("Y-m-d");
                                    $movimiento_ajuste->observaciones                =  "SE REALIZA ENTRADA POR AJUSTE";
                                    $movimiento_ajuste->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
                                    $movimiento_ajuste->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';
                                
                                    $movimiento_ajuste->save();

                                    foreach($lotes_nuevos as $lote_link)
                                    {
                                        $item_detalles = new MovimientoInsumos;

                                        ///var_dump(json_encode($lote_link));

                                        $item_detalles->movimiento_id           = $movimiento_ajuste->id; 
                                        $item_detalles->stock_id                = $lote_link->id;
                                        $item_detalles->clave_insumo_medico     = $clave_insumo_medico; 


                                        $item_detalles->cantidad                = $lote_link->cantidad;

                                        ///AQUI METER LA CANTIDAD UNIDOSIS

                                        $item_detalles->precio_unitario         = $precio['precio_unitario'];
                                        $item_detalles->iva                     = $precio['iva']; 
                                        $item_detalles->precio_total            = ( $precio['precio_unitario'] + $precio['iva'] ) * $lote_link->cantidad;

                                        $item_detalles->save();
                                    }
                                } /// FIN IF EXISTEN LOTES NUEVOS 

////*************************************************************************************************************************
                            //FOREACH SEGUNDA PASADA A INSUMOS PARA ACTUALIZAR STOCK DE SALIDA
                                foreach($lotes_master as $index => $lote)
                                {
                                    $lote_stock = Stock::find($lote->id);
                                    
                                    /// INICIA CALCULO DEL NUEVO STOCK SEGUN EL MODO ELEGIDO
                                    if($lote->modo_salida == "N")
                                    {
                                        $lote_stock->existencia          = ($lote_stock->existencia - $lote->cantidad );
                                        $lote_stock->existencia_unidosis = ($lote_stock->existencia_unidosis - ( $lote->cantidad * $insumo->cantidad_x_envase) );

                                    }else{ 
                                            /// la variable cantidad se interpreta para existencia_unidosis
                                            $para_salir = ($lote->cantidad / $insumo->cantidad_x_envase);
                                            $enteros_salir = 0;

                                            if($lote->cantidad <= $lote_stock->unidosis_sueltas)
                                            {
                                                $enteros_salir = 0;
                                            }else{
                                            //aqui el error
                                                    if($para_salir > 0 && $para_salir <= 1)
                                                    {
                                                        $enteros_salir = 1;
                                                    }else{                                        

                                                            if($para_salir != intval($para_salir))
                                                            { // es un decimal y tambien es mayor que uno
                                                                
                                                                // valida si para surtir la unidosis solicitada es necesario abrir una caja nueva 
                                                                // ( aparte de la que ya esta abierta )
                                                                if( ( $lote_stock->unidosis_sueltas + (intval($para_salir)*$insumo->cantidad_x_envase)) >= $lote->cantidad )
                                                                {
                                                                    $enteros_salir = intval($para_salir);
                                                                }else{ $enteros_salir = intval($para_salir)+1; }

                                                            }else{ // es un numero entero
                                                                    $enteros_salir = intval($para_salir);
                                                                }
                                                         }
                                                 }

                                            $nueva_existencia            =  $lote_stock->existencia - $enteros_salir;
                                            $nueva_existencia_unidosis   =  $lote_stock->existencia_unidosis - $lote->cantidad;

                                            $unidosis_enteras  = ( $nueva_existencia * $insumo->cantidad_x_envase );
                                            $unidosis_sueltas  = $nueva_existencia_unidosis - $unidosis_enteras;

                                            $lote_stock->existencia          = $nueva_existencia;
                                            $lote_stock->existencia_unidosis = $nueva_existencia_unidosis;
                                            $lote_stock->unidosis_sueltas    = $unidosis_sueltas;

                                            if($unidosis_sueltas > 0)
                                            {
                                                $lote_stock->envases_parciales   = 1;
                                            }else{
                                                    $lote_stock->envases_parciales   = 0;
                                                }
                                     
                                    }// fin else ( si es salida unidosis )

                                    $lote_stock->save();

                                    $item_detalles = new MovimientoInsumos;

                                    $item_detalles->movimiento_id           = $movimiento_salida->id; 
                                    $item_detalles->stock_id                = $lote_stock->id;
                                    $item_detalles->clave_insumo_medico     = $clave_insumo_medico;

                                    // se agregan las cantidades correspondientes segun el modo de salida
                                    if($lote->modo_salida == "N")
                                    {
                                        $item_detalles->cantidad                = $lote->cantidad;
                                        $item_detalles->cantidad_unidosis       = $lote->cantidad * $insumo->cantidad_x_envase;
                                    }else{
                                            $item_detalles->cantidad               = $lote->cantidad/$insumo->cantidad_x_envase;
                                            $item_detalles->cantidad_unidosis      = $lote->cantidad;
                                         }
                                    
                                    $item_detalles->modo_salida             = $lote->modo_salida;

                                    $item_detalles->precio_unitario         = $precio['precio_unitario'];
                                    $item_detalles->iva                     = $precio['iva'];

                        $item_detalles->precio_total            = ( $precio['precio_unitario'] + $precio['iva'] ) * $item_detalles->cantidad;

                                    $item_detalles->save();
                                
                                }/// FIN FOREACH SEGUNDA PASADA A INSUMOS

            } /// FIN IF EXISTE INSUMOS           
        }
        
        return $success;
    }




///**************************************************************************************************************************
///                  M O V I M I E N T O          S   A  L  I   D  A       R  E  C  E  T  A 
///**************************************************************************************************************************
    
    
      private function validarTransaccionSalidaReceta($datos, $movimiento_salida_receta,$almacen_id)
      {
		$success = false;

        //comprobar que el servidor id no me lo envian por parametro, si no poner el servidor por default de la configuracion local, si no seleccionar el servidor del parametro
        $servidor_id = property_exists($datos, "servidor_id") ? $datos->servidor_id : env('SERVIDOR_ID');

        //agregar al modelo los datos
        $movimiento_salida_receta->almacen_id                   =  $almacen_id;
        $movimiento_salida_receta->tipo_movimiento_id           =  $datos->tipo_movimiento_id;
        $movimiento_salida_receta->status                       =  $datos->status; 
        $movimiento_salida_receta->fecha_movimiento             =  property_exists($datos, "fecha_movimiento")          ? $datos->fecha_movimiento          : '';
        $movimiento_salida_receta->observaciones                =  property_exists($datos, "observaciones")             ? $datos->observaciones             : '';
        $movimiento_salida_receta->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
        $movimiento_salida_receta->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';

        $lotes_master = array();

        // si se guarda el movimiento tratar de guardar el detalle de insumos
        if( $movimiento_salida_receta->save() )
        {
            $success = true;
            $receta = new Receta;

            if(property_exists($datos,"receta"))
            {
                $receta->folio          = $datos->receta['folio'];
                $receta->tipo_receta    = $datos->receta['tipo_receta'];
                $receta->fecha_receta   = $datos->receta['fecha_receta'];
                $receta->doctor         = $datos->receta['doctor'];
                $receta->paciente       = $datos->receta['paciente'];
                $receta->diagnostico    = $datos->receta['diagnostico'];
                $receta->imagen_receta  = $datos->receta['imagen_receta'];

                $receta->save();

                $receta_movimiento = new RecetaMovimiento;
                $receta_movimiento->receta_id      = $receta->id;
                $receta_movimiento->movimiento_id  = $movimiento_salida_receta->id;

                $receta_movimiento->save();

            }

            if(property_exists($datos,"movimiento_metadato"))
            {
                $metadatos = new MovimientoMetadato;
                $metadatos->movimiento_id  = $movimiento_salida_receta->id;
                //$metadatos->servicio_id    = $datos->movimiento_metadato['servicio_id'];
                //$metadatos->persona_recibe = $datos->movimiento_metadato['persona_recibe'];
                $metadatos->turno_id       = $datos->movimiento_metadato['turno_id'];

                $metadatos->save();
                
            }

            if(property_exists($datos, "insumos"))
            {
                $insumos = array_filter($datos->insumos, function($v){return $v !== null;});

                $lotes_nuevos  = array();
                $lotes_ajustar = array();
          ///  PRIMER PASADA PARA IDENTIFICAR LOS LOTES NUEVOS A AJUSTAR / GENERAR ENTRADA 
                 foreach ($insumos as $key => $insumo)
                {
                     if($insumo != null)
                     {
                         if(is_array($insumo))
                            $insumo = (object) $insumo;

                            $clave_insumo_medico = $insumo->clave;
                            $precio_unitario = 0;
                            $iva             = 0;

                            $contrato_precio = ContratoPrecio::where('insumo_medico_clave',$clave_insumo_medico)->first();
                            if($contrato_precio){
                                $precio_unitario = $contrato_precio->precio;
                                if($contrato_precio->tipo_insumo_id == 3){
                                    $iva = $precio_unitario - ($precio_unitario/1.16 );
                                }
                            }

                            //****************************************************************************************************
                                /// AQUI INSERTAR DETALLES DE RECETA 
                                $receta_detalle = new RecetaDetalle;

                                $receta_detalle->receta_id            = $receta->id;
                                $receta_detalle->clave_insumo_medico  = $insumo->clave;
                                $receta_detalle->cantidad             = $insumo->cantidad_recetada;
                                $receta_detalle->dosis                = $insumo->dosis;
                                $receta_detalle->frecuencia           = $insumo->frecuencia;
                                $receta_detalle->duracion             = $insumo->duracion;

                                $receta_detalle->save();
                            //****************************************************************************************************
                                foreach($insumo->lotes as $index => $lote)
                                {
                                     if(is_array($lote))
                                        $lote = (object) $lote;

                                    if(property_exists($lote, "nuevo"))
                                    {
                                         $lote_temp = Stock::where('lote',$lote->lote)->where('fecha_caducidad',$lote->fecha_caducidad)->
                                                             where('codigo_barras',$lote->codigo_barras)->where('clave_insumo_medico',$insumo->clave)->orderBy('created_at','DESC')->first();

                                        if($lote_temp){ /// si ya existe un lote vacio con esos detalles : se agrega un
                                                $lote_temp->existencia = $lote->existencia;
                                                $lote_temp->save();
                                                // adicion del campo cantidad al objeto lote/stock
                                                $lote_temp->cantidad = property_exists($lote_temp, "cantidad") ? $lote_temp->cantidad : $lote->cantidad;

                                                array_push($lotes_nuevos,$lote_temp);
                                                array_push($lotes_master,$lote_temp);
                                          }else{
                                                    $lote_insertar = new Stock;

                                                    $lote_insertar->almacen_id             = $movimiento_salida_receta->almacen_id;
                                                    $lote_insertar->clave_insumo_medico    = $insumo->clave;
                                                    $lote_insertar->marca_id               = NULL;
                                                    $lote_insertar->lote                   = $lote->lote;
                                                    $lote_insertar->fecha_caducidad        = $lote->fecha_caducidad;
                                                    $lote_insertar->codigo_barras          = $lote->codigo_barras;
                                                    $lote_insertar->existencia             = $lote->existencia;
                                                    $lote_insertar->existencia_unidosis    = ( $insumo->cantidad_x_envase * $lote->cantidad );

                                                    $lote_insertar->save();
                                                    // adicion del campo cantidad al objeto lote/stock
                                                    $lote_insertar->cantidad = property_exists($lote_insertar, "cantidad") ? $lote_insertar->cantidad : $lote->cantidad;

                                                    array_push($lotes_nuevos,$lote_insertar);
                                                    array_push($lotes_master,$lote_insertar);
                                               }

                                    }else{
                                            // aqui ya trae el campo cantidad el objeto lote/stock
                                            array_push($lotes_master,$lote);
                                         }
                            /// ***************************************************************************************************************************************************
                                } /// FIN PRIMER FOREACH QUE RECORRE TODOS LOS INSUMOS PARA SALIR
                    
                    }///FIN IF INSUMO != NULL

                }////   FIN FOREACH     I N S U M O S     -> PRIMERA PASADA
            ///********************************************************************************************************************************************
                                /// insertar el movimiento entrada de ajuste y ligar los detalles con su stock ya agregado en pasada anterior
                                if(count($lotes_nuevos) > 0)
                                {
                                    /// insertar movimiento de entrada por ajuste ( tipo_movimiento_id = 6 )
                                    $movimiento_ajuste = new Movimiento;
                                    $movimiento_ajuste->almacen_id                   =  $almacen_id;
                                    $movimiento_ajuste->tipo_movimiento_id           =  6;
                                    $movimiento_ajuste->status                       =  "FI";  
                                    $movimiento_ajuste->fecha_movimiento             =  date("Y-m-d");
                                    $movimiento_ajuste->observaciones                =  "SE REALIZA ENTRADA POR AJUSTE";
                                    $movimiento_ajuste->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
                                    $movimiento_ajuste->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';
                                
                                    $movimiento_ajuste->save();

                                    foreach($lotes_nuevos as $lote_link)
                                    {
                                        $item_detalles = new MovimientoInsumos;

                                        ///var_dump(json_encode($lote_link));

                                        $item_detalles->movimiento_id           = $movimiento_ajuste->id; 
                                        $item_detalles->stock_id                = $lote_link->id; 
                                        $item_detalles->cantidad                = $lote_link->cantidad;
                                        $item_detalles->precio_unitario         = $precio_unitario;
                                        $item_detalles->iva                     = $iva; 
                                        $item_detalles->precio_total            = ( $precio_unitario + $iva ) * $lote_link->cantidad; 

                                        $item_detalles->save();
                                    }
                                } /// FIN IF EXISTEN LOTES NUEVOS 

////*************************************************************************************************************************
                    /// FOREACH SEGUNDA PASADA A INSUMOS PARA ACTUALIZAR STOCK DE SALIDA
                        foreach($lotes_master as $index => $lote)
                        {
                            $lote_stock = Stock::find($lote->id);
                            $lote_stock->existencia = ($lote_stock->existencia - $lote->cantidad );
                            $lote_stock->save();

                            $item_detalles = new MovimientoInsumos;

                            $item_detalles->movimiento_id           = $movimiento_salida_receta->id; 
                            $item_detalles->stock_id                = $lote_stock->id; 
                            $item_detalles->cantidad                = $lote->cantidad;
                            $item_detalles->precio_unitario         = 0;
                            $item_detalles->iva                     = 0; 
                            $item_detalles->precio_total            = 0;

                            $item_detalles->save();

                            
                        
                         }/// FIN FOREACH SEGUNDA PASADA A INSUMOS



            } /// FIN IF EXISTE INSUMOS           
        }
        
        return $success;
    }

///**************************************************************************************************************************
///                  F U N C I O N      C O N S E G U I R      P R E C I O    I N S U M O  
///**************************************************************************************************************************
 public function conseguirPrecio($clave_insumo_medico)
 {
    $precio_unitario = 0; 
    $iva             = 0;
    $response = array();

    $contrato_precio = ContratoPrecio::where('insumo_medico_clave',$clave_insumo_medico)->first();
                            if($contrato_precio){
                                $precio_unitario = $contrato_precio->precio;
                                if($contrato_precio->tipo_insumo_id == 3){
                                    $iva = $precio_unitario - ($precio_unitario/1.16 );
                                }
                            }

    $response['precio_unitario'] = $precio_unitario;
    $response['iva']             = $iva;

    return $response;
 }
///**************************************************************************************************************************
///**************************************************************************************************************************
     


}
