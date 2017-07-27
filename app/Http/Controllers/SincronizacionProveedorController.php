<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


use App\Models\CluesTurno;
use App\Models\UnidadMedica;
use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\MovimientoPedido;
use App\Models\Usuario;
use App\Models\Almacen;
use App\Models\Contrato;
use App\Models\Proveedor;
use App\Models\Receta;


/** 
* Controlador 
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017
*
* Controlador 
*
*/
class SincronizacionProveedorController extends Controller
{
     
     
///***************************************************************************************************************************
///***************************************************************************************************************************
   
    public function index(Request $request)
    {
        //var_dump(json_encode($request)); die();

    }

 ///***************************************************************************************************************************
///***************************************************************************************************************************
 
 public function store(Request $request)
    {
        $clues = $request->get('clues');
        if(!$clues){
            return Response::json(array("status" => 404,"messages" => "Debe especificar una Unidad Médica."), 404);
        } 


        

        
    }

 ///***************************************************************************************************************************
///***************************************************************************************************************************
 
    public function show($id)
    {

        
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
 

    public function update(Request $request, $id){
 
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
      
    public function destroy($id)
    {
        
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
 
public function listarPedidos(Request $request)
{
    $proveedor_id = $request->get('proveedor_id');

    $data = Pedido::where('proveedor_id',$proveedor_id);
        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $data = $data->paginate($resultadosPorPagina);
        } else {
            $data = $data->get();
        }
       
        
        //return Response::json(["data"=>$data], 200);
    return Response::json(array("data" => array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data, "total" => count($data))), 200);


}

///***************************************************************************************************************************
///***************************************************************************************************************************
 
public function analizarJson(Request $request)
{
    $proveedor_id = $request->get('proveedor_id');

    $input_data = (object)Input::json()->all();

    $pedido_id      = $input_data->pedido;
    $json_proveedor = $input_data->json;

    if(is_array($json_proveedor))
    { $json_proveedor = (object)$json_proveedor; }

    $errors                  = array();

    $total_recetas           = 0;
    $recetas_validas         = 0;
    $recetas_invalidas       = 0;

    $total_colectivos        = 0;
    $colectivos_validos      = 0;
    $colectivos_invalidos    = 0;

    if(property_exists($json_proveedor, "recetas"))
    {
        foreach($json_proveedor->recetas as $receta)
        {   $total_recetas++;
            $receta = (object) $receta;

            $folio_buscar = $receta->folio;

            $receta_buscar = Receta::where("folio",$folio_buscar);
            if($receta_buscar)
            { $recetas_invalidas++; }else{ $recetas_validas++; }
        }

    }else{
            array_push($errors, array(array('recetas' => array('no_exite_recetas'))));
         }

    if(property_exists($json_proveedor, "colectivos"))
    {
        foreach($json_proveedor->colectivos as $colectivo)
        {   $total_colectivos++;
            $colectivo = (object) $colectivo;

            $folio_buscar = $colectivo->folio;

            $colectivo_buscar = Receta::where("folio",$folio_buscar);
            if($colectivo_buscar)
            { $colectivos_invalidos++; }else{ $colectivos_validos++; }
        }

    }else{
            array_push($errors, array(array('colectivos' => array('no_exite_colectivos'))));
         }

    if( count($errors) > 0 )
    {
        return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
    }else{

        $data = array(
                     "total_recetas" => $total_recetas,
                     "recetas_validas" => $recetas_validas,
                     "recetas_invalidas" => $recetas_invalidas,
                     "total_colectivos" => $total_colectivos,
                     "colectivos_validos" => $colectivos_validos,
                     "colectivos_invalidos" => $colectivos_invalidos);

        return Response::json(array("data" => array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data)), 200);

    }
        
            
        
         


            
}

////////////////////////   P R O C E S A R      J S O N                              P R O C E S A R      J S O N  
///***************************************************************************************************************************
///***************************************************************************************************************************

 public function procesarJson(Request $request)
{
    $proveedor_id = $request->get('proveedor_id');

    $input_data = (object)Input::json()->all();

    $pedido_id      = $input_data->pedido;
    $json_proveedor = $input_data->json;

    if(is_array($json_proveedor))
    { $json_proveedor = (object)$json_proveedor; }

    $errors                  = array();

    $total_recetas           = 0;
    $recetas_validas         = 0;
    $recetas_invalidas       = 0;

    $total_colectivos        = 0;
    $colectivos_validos      = 0;
    $colectivos_invalidos    = 0;

    if(property_exists($json_proveedor, "recetas"))
    {
        foreach($json_proveedor->recetas as $receta)
        {   $total_recetas++;
            $receta = (object) $receta;

            $folio_buscar = $receta->folio;

            $receta_buscar = Receta::where("folio",$folio_buscar);
            if($receta_buscar)
            { $recetas_invalidas++; }else{ $recetas_validas++; }

            $movimiento = new Movimiento();
            $movimiento->almacen_id                     = $json_proveedor->almacen_id;
            $movimiento->tipo_movimiento_id             = 9;
            $movimiento->status                         = "FI";
            $movimiento->fecha_movimiento               = $receta->fecha;

            if($movimiento->save())
            {
                $receta = new Receta();
                $receta_insertar->movimiento_id  = $movimiento->id;
                $receta_insertar->folio          = $receta->folio;
                $receta_insertar->folio_receta   = $receta->folio_receta;
                $receta_insertar->fecha_receta   = $receta->fecha_receta;
                $receta_insertar->fecha_surtido  = $receta->fecha_surtido;
                $receta_insertar->tipo_receta_id = $receta->tipo_receta_id;
                $receta_insertar->doctor         = $receta->doctor;
                $receta_insertar->paciente       = $receta->paciente;
                $receta_insertar->diagnostico    = $receta->diagnostico;

                if($receta_insertar->save())
                {
                    foreach ($receta->insumos as $key => $receta_insumo){

                        $receta_detalles = new RecetaDetalle();
                        $receta_detalles->clave_insumo_medico    = $receta_insumo->clave_insumo_medico;
                        $receta_detalles->cantidad_recetada      = $receta_insumo->cantidad_recetada;
                        $receta_detalles->cantidad               = $receta_insumo->cantidad_surtida;
                        $receta_detalles->dosis                  = $receta_insumo->dosis;
                        $receta_detalles->frecuencia             = $receta_insumo->frecuencia;
                        $receta_detalles->duracion               = $receta_insumo->duracion;

                        if($receta_detalles->save())
                        {
                            // CONSEGUIR PRECIO Y DETALLES DEL iNSUMO 
                            ///*************************************************************************************************************
                            $pedido            = Pedido::find($pedido_id);
                            $movimiento_pedido = MovimientoPedido::where('pedido_id',$pedido_id);
                            $movimiento_insumo = MovimientoInsumo::where('movimiento_id',$movimiento_pedido->movimiento_id)
                                                ->where('clave_insumo_medico',$receta_insumo->clave_insumo_medico);

                            ///stock de donde se sacará lo indicado en la receta                    
                            $stock = Stock::where('stock_id',$movimiento_insumo->stock_id);

                            $clave_insumo_medico = $receta_insumo->clave_insumo_medico;
                            $insumo = Insumo::conDescripciones()->with('informacionAmpliada')->find($clave_insumo_medico);
                            $cantidad_x_envase = $insumo->informacion_ampliada->cantidad_x_envase;

                            $precios = $this->conseguirPrecio($clave_insumo_medico);
                            ///*************************************************************************************************************

                            

                            $movimiento_insumo = new MovimientoInsumo();
                            $movimiento_insumo->movimiento_id        = $movimiento->id; 
                            $movimiento_insumo->stock_id             = $stock->id;
                            $movimiento_insumo->clave_insumo_medico  = $receta_insumo->clave_insumo_medico;
                            $movimiento_insumo->modo_salida          = "N";
                            $movimiento_insumo->cantidad             = $receta_insumo->cantidad_surtida;
                            $movimiento_insumo->cantidad_unidosis    = $cantidad_x_envase * $receta_insumo->cantidad_surtida;
                            $movimiento_insumo->precio_unitario      = $precios->precio_unitario;
                            $movimiento_insumo->iva                  = $precios->iva;
                            $movimiento_insumo->precio_total         = $receta_insumo->cantidad_surtida * ( $precio_unitario + $precios->iva);

                            $stock->existencia          = $stock->existencia - $receta_insumo->cantidad_surtida;
                            $stock->existencia_unidosis = $stock->existencia_unidosis - ($cantidad_x_envase * $receta_insumo->cantidad_surtida);
                            $stock->save();

                            




                        }else{

                        }

                    }
                    

                }else{

                }

            }else{

            }



        }

    }else{
            array_push($errors, array(array('recetas' => array('no_exite_recetas'))));
         }

    if(property_exists($json_proveedor, "colectivos"))
    {
        foreach($json_proveedor->colectivos as $colectivo)
        {   $total_colectivos++;
            $colectivo = (object) $colectivo;

            $folio_buscar = $colectivo->folio;

            $colectivo_buscar = Receta::where("folio",$folio_buscar);
            if($colectivo_buscar)
            { $colectivos_invalidos++; }else{ $colectivos_validos++; }
        }

    }else{
            array_push($errors, array(array('colectivos' => array('no_exite_colectivos'))));
         }

    if( count($errors) > 0 )
    {
        return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
    }else{

        $data = array(
                     "total_recetas" => $total_recetas,
                     "recetas_validas" => $recetas_validas,
                     "recetas_invalidas" => $recetas_invalidas,
                     "total_colectivos" => $total_colectivos,
                     "colectivos_validos" => $colectivos_validos,
                     "colectivos_invalidos" => $colectivos_invalidos);

        return Response::json(array("data" => array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data)), 200);

    }
        
            
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
                    'tipo_movimiento_id' => 'required|integer|in:1,2,3,4,5,6,7,8,9',
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


///***************************************************************************************************************************
///***************************************************************************************************************************
   
   public function validarArrayRecetasColectivos(Request $request)
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

        if(property_exists($datos, "recetas"))
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
                    array_push($errors, array(array('recetas' => array('objeto_recetas_vacio'))));
                 }
        }else{
                array_push($errors, array(array('recetas' => array('no_existe_objeto_recetas'))));
             }

 

        return Response::json(array("status" => 200,"messages" => "Estatus Entrega Pedidos"), 200);
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
   public function sincronizarRecetasColectivos(Request $request)
    {
        return Response::json(array("status" => 200,"messages" => "Estatus Entrega Pedidos"), 200);
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
   
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

        if($tipo=='RECETA')
                {
                    $reglas = [
                                'clave_insumo_medico'     => 'required',
                                'cantidad_recetada'       => 'required|integer',
                                'cantidad_surtida'        => 'required|integer',
                                'dosis'                   => 'required|integer',
                                'frecuencia'              => 'required|integer',
                                'duracion'                => 'required|integer'
                              ];
                }else{ // ES COLECTIVO
                        $reglas = [
                                    'clave_insumo_medico'    => 'required',
                                    'cantidad_solicitada'    => 'required|numeric',
                                    'cantidad_surtida'       => 'required|integer',
                                  ];
                     }
                     
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

    return (object)$response;
 }
///**************************************************************************************************************************
///**************************************************************************************************************************
     

}
