<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Movimiento;
use App\Models\MovimientoInsumos;
use App\Models\Stock;
use App\Models\Pedido;
use App\Models\MovimientoPedido;
use App\Models\Proveedor;
use App\Models\Insumo;
use App\Models\Almacen;
use App\Models\PedidoInsumo;
use App\Models\Presupuesto;
use App\Models\UnidadMedicaPresupuesto;

use App\Models\Usuario;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

class RecepcionPedidoController extends Controller
{

	public function obtenerDatosPresupuesto($clues,$proveedor_id,$mes){
        try{
            $parametros = Input::all();

            $presupuesto = Presupuesto::where('activo',1)->first();

            $presupuesto_unidad_medica = UnidadMedicaPresupuesto::where('presupuesto_id',$presupuesto->id)
                                            ->where('clues',$clues)
                                            ->where('proveedor_id',$proveedor_id)
											->where('mes',$mes)
                                            ->groupBy('clues')
											->first();
			
            return $presupuesto_unidad_medica;
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function index(){
		/*
    	$pedidos = Movimiento::with("movimientoInsumos.stock")->get();
    	return Response::json([ 'data' => $pedidos],200);
		*/
    }

    public function show(Request $request, $id){
		
    	$pedido = Pedido::where('almacen_solicitante',$request->get('almacen_id'))->with(['recepciones'=>function($recepciones){
			$recepciones->has('entradaAbierta')->with('entradaAbierta.insumos');
		}])->where('status','PS')->find($id);
        
        if(!$pedido){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
        	$pedido = $pedido->load("insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos", "tipoPedido", "almacenProveedor","almacenSolicitante.unidadMedica","proveedor");
        }

        return Response::json([ 'data' => $pedido],200);
    }

    public function update(Request $request, $id){


		$mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'almacen_id'        	=> 'required',
            'tipo_movimiento_id'    => 'required',
            'fecha_movimiento'     	=> 'required'
        ];

        $parametros = Input::all();
		
		/*Verifica de primera instancia que no se haya sobrepasado las cantidades solicitadas con la recibida, por error*/
		$pedido_verificar = PedidoInsumo::where("pedido_id", $id)
							->select( DB::raw('SUM(cantidad_solicitada) as cantidad_solicitada'), DB::raw('SUM(cantidad_recibida) as cantidad_recibida') )
							->groupBy("pedido_id")
							->first();
		

		if($pedido_verificar->cantidad_solicitada < intval($pedido_verificar->cantidad_recibida))
		{
			return Response::json(['error' =>"Existe un error al comprobar la cantidad recibida, por favor contactese con el area de soporte" ], 500);
		}
		/*Fin validador*/


		$almacen = Almacen::find($request->get('almacen_id'));
        
        /*Recepcion de precios por insumo*/
		$proveedor = Proveedor::with('contratoActivo')->find($almacen->proveedor_id);

		$contrato_activo = $proveedor->contratoActivo;
        
        if(!$contrato_activo){
            return Response::json(['error' => 'No se encontraron contratos activos para este proveedor'], 500);
        }

        //cargamos en un arreglo los insumos, para poder obtener datos, de los insumos que envie 
        $insumos = Insumo::conDescripcionesPrecios($contrato_activo->id, $proveedor->id)->select("precio", "clave", "insumos_medicos.tipo", "es_causes", "insumos_medicos.tiene_fecha_caducidad")->get();
        $lista_insumos = array();
        foreach ($insumos as $key => $value) {
        	$array_datos = array();
        	$array_datos['precio'] 			= $value['precio'];
        	$array_datos['clave'] 			= $value['clave'];
        	$array_datos['tipo'] 			= $value['tipo'];
        	$array_datos['es_causes'] 		= $value['es_causes'];
        	$array_datos['caducidad'] 		= $value['tiene_fecha_caducidad'];
        	$lista_insumos[$value['clave']] = $array_datos;
        }
		/**/
		DB::beginTransaction();

		$pedido = Pedido::where('almacen_solicitante',$almacen->id)->with(['recepciones'=>function($recepciones){
			$recepciones->has('entradaAbierta')->with('entradaAbierta.insumos');
		}])->where('status','PS')->find($id);

		if(!$pedido){
			DB::rollBack();
			return Response::json(['error' => 'No se encontró el pedido'],500);
		}


		if(count($pedido->recepciones) > 1){
			return Response::json(['error' => 'El pedido tiene mas de una recepción abierta'], 500);
		}elseif(count($pedido->recepciones) == 1){
			$recepcion = $pedido->recepciones[0];
		}else{

			//$movimiento_validador = MovimientoPedido::where() 
			$recepcion = new MovimientoPedido;

			$recepcion->recibe = 'RECIBE';
			$recepcion->entrega = 'ENTREGA';
			$recepcion->pedido_id = $pedido->id;
		}

		if(!isset($parametros['fecha_movimiento'])){
			$parametros['fecha_movimiento'] = date('Y-m-d');
		}

		/* Validador de  movimientos, se verifica que no exista un movimiento con las mismas característicasa*/
		

		
		if($parametros['status'] == 'FI') //Actualizamod datos en caso de ser necesario
		{												
			$movimiento_validador = Movimiento::where("fecha_movimiento", $parametros['fecha_movimiento'])
											->where("observaciones", ($parametros['observaciones'])?$parametros['observaciones']:null) 
											->where("almacen_id", $almacen->id);
								
			if($movimiento_validador->count() > 1)
			{
				return Response::json(['error' => "Error, se ha detectado un movimiento con los mismos datos, por favor oprima la tecla F5, y verifique sus datos."], 500);		
			}									
		}
		/**/

		if(!isset($parametros['observaciones'])){
			$parametros['observaciones'] = null;
		}

		$datos_movimiento = [
			'status' => $parametros['status'],
			'tipo_movimiento_id' => 4, //Recepcion de pedido
			'fecha_movimiento' => $parametros['fecha_movimiento'],
			'almacen_id' => $almacen->id,
			'observaciones' => ($parametros['observaciones'])?$parametros['observaciones']:null
		];

		if(count($parametros['stock']) == 0){
            return Response::json(['error' => 'Se necesita capturar al menos un lote'], 500);
        }
		
        try {
            
	        $v = Validator::make($datos_movimiento, $reglas, $mensajes);

	        if ($v->fails()) {
	        	DB::rollBack();
	            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
	        }
			
			if($recepcion->entradaAbierta){
				
				$movimiento = $recepcion->entradaAbierta;
				$movimiento->update($datos_movimiento);

				MovimientoInsumos::where("movimiento_id", $movimiento->id)->forceDelete();    

				/*En caso de Existir una abierta actualizamos datos*/
				if($parametros['status'] == 'FI') //Actualizamod datos en caso de ser necesario
				{
					$reglas_movimiento_pedido = [
			            'entrega'        		=> 'required',
			            'recibe'    			=> 'required',
			            'fecha_movimiento'     	=> 'required'
			        ];

			        $v = Validator::make($parametros, $reglas_movimiento_pedido, $mensajes);

			        if ($v->fails()) {
			        	DB::rollBack();
			            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
			        }

			        $recepcion->recibe = $parametros['entrega']; // Se actualiza la persona que entrega
					$recepcion->entrega = $parametros['recibe']; // Se actualiza la perosna que recibe

					$recepcion->save();

			    }
				/**/
			}else{
				$movimiento = Movimiento::create($datos_movimiento);
				$recepcion->movimiento_id = $movimiento->id;

				
				if($parametros['status'] == 'FI') //Actualizamod datos en caso de ser necesario
				{
					$reglas_movimiento_pedido = [
			            'entrega'        	=> 'required',
			            'recibe'    		=> 'required',
			            'fecha_movimiento'  => 'required'
			        ];

			        $v = Validator::make($parametros, $reglas_movimiento_pedido, $mensajes);

			        if ($v->fails()) {
			        	DB::rollBack();
			            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
			        }

			        $recepcion->recibe = $parametros['entrega']; // Se actualiza la persona que entrega
					$recepcion->entrega = $parametros['recibe']; // Se actualiza la perosna que recibe

					$movimiento->fecha_movimiento 	= $parametros['fecha_movimiento']; //Se actualizan datos de movimiento (fecha)
					$movimiento->observaciones 		= $parametros['observaciones']; //Se actualizan datos de movimiento (observaciones)

					$movimiento->update($datos_movimiento);
			    }

				$recepcion->save();
			}

	        
	        $stock = $parametros['stock'];

	        /*Variable para ir sumando lo devengado y actualizar la tabla de unidad presupuesto*/
	        $causes_unidad_presupuesto 				= 0;
	        $no_causes_unidad_presupuesto 			= 0;
	        $material_curacion_unidad_presupuesto 	= 0;
	        /*                                                                                  */
	        foreach ($stock as $key => $value) {
	        	$reglas_stock = [
		            'almacen_id'        	=> 'required',
		            'clave_insumo_medico'   => 'required',
		            'lote'     				=> 'required',
					'existencia'     		=> 'required'
		        ];

		        /*Obtenemos variables del insumo a procesar. de acuerdo a la lista de insumos cargada anteriormente*/
		        $tipo_insumo 	= $lista_insumos[$value['clave_insumo_medico']]['tipo'];
		        $es_causes 		= $lista_insumos[$value['clave_insumo_medico']]['es_causes'];
		        $caducidad 		= $lista_insumos[$value['clave_insumo_medico']]['caducidad'];
		        /**/

		        if($tipo_insumo == "ME")
		        	$reglas_stock['fecha_caducidad'] = 'required';

				$value['almacen_id'] = $almacen->id;

		        $v = Validator::make($value, $reglas_stock, $mensajes);

		        if ($v->fails()) {
		        	DB::rollBack();
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }

		        if(!isset($value['fecha_caducidad']) or $this->limpia_espacios($value['fecha_caducidad']) == "")
		        	$value['fecha_caducidad'] = null;

		        
		        if($this->validacion_fecha_caducidad($value['fecha_caducidad'], $caducidad)) //Validacion de Fecha de caducidad
		        {
		          if(!isset($value['fecha_caducidad'])){
						$value['fecha_caducidad'] = null;
					}elseif(!$value['fecha_caducidad']){
						$value['fecha_caducidad'] = null;
					}
		        
					if(isset($value['codigo_barras'])){
						$insert_stock = Stock::where('codigo_barras',$value['codigo_barras'])->where('fecha_caducidad',$value['fecha_caducidad'])->where('lote',$value['lote'])->where('clave_insumo_medico',$value['clave_insumo_medico'])->where('almacen_id', $almacen->id)->first(); //Verifica si existe el medicamento en el stock
						
					}
					else{
						
						$insert_stock = Stock::where('fecha_caducidad',$value['fecha_caducidad'])->where('lote',$value['lote'])->where('clave_insumo_medico',$value['clave_insumo_medico'])->where('almacen_id', $almacen->id)->Where(function ($query) {
			                $query->whereNull('codigo_barras')
			                      ->orWhere('codigo_barras', '');
			            })->first(); //Verifica si existe el medicamento en el stock
					}

					if($parametros['status'] == 'FI')
					{
						if($tipo_insumo == "ME") //Verifico si es medicamento o material de curación, para agregar el IVA
			        	{		        		
			        						    		
							$pedido_insumo = PedidoInsumo::where("pedido_id", $pedido->id)->where("insumo_medico_clave", $value['clave_insumo_medico'])->first(); //modificamos el insumo de los pedidos



							if($pedido_insumo->cantidad_solicitada >= intval(($pedido_insumo->cantidad_recibida + $value['existencia'])))
							{
								$pedido_insumo->cantidad_recibida += $value['existencia'];
								$cantidad_recibida = ( $value['existencia'] * $pedido_insumo->precio_unitario );

								$pedido_insumo->monto_recibido 	  += $cantidad_recibida;
								$pedido_insumo->update();  //Actualizamos existencia y  monto de pedidos insumo

								if($insert_stock){
									$insert_stock->existencia += $value['existencia'];
									$insert_stock->save();
								}else{					
									$insert_stock = Stock::create($value);
								}		

								if($es_causes == 1)
									$causes_unidad_presupuesto 				+= $cantidad_recibida;
								else
						        	$no_causes_unidad_presupuesto 			+= $cantidad_recibida;
						    }else
						    {
						    	DB::rollBack();
								return Response::json(['error' => 'Existe un error, el insumo '.$value['clave_insumo_medico'].' ha sobrepasado el monto solicitando, por favor contactese con soporte de la aplicacion'], 500);
						    }   
								
			        	}else
			        	{
			        		$pedido_insumo = PedidoInsumo::where("pedido_id", $pedido->id)->where("insumo_medico_clave", $value['clave_insumo_medico'])->first(); //modificamos el insumo de los pedidos

			        		
			        		if(($pedido_insumo->cantidad_solicitada) >= intval(($pedido_insumo->cantidad_recibida + $value['existencia'])))
							{
							
								$pedido_insumo->cantidad_recibida += $value['existencia'];

								$cantidad_recibida 						= ( $value['existencia'] * $pedido_insumo->precio_unitario ) * (1.16);
								$cantidad_recibida_sin_iva				= ( $value['existencia'] * $pedido_insumo->precio_unitario );

								$pedido_insumo->monto_recibido 	  		+= $cantidad_recibida_sin_iva;
								$pedido_insumo->update();  //Actualizamos existencia y  monto de pedidos insumo

								$material_curacion_unidad_presupuesto 	+= $cantidad_recibida; //Se suma el monto de material de curazion

								if($insert_stock){
									$insert_stock->existencia += $value['existencia'];
									$insert_stock->save();
									
								}else{					
									$insert_stock = Stock::create($value);
									
								}
							}else
							{
								DB::rollBack();
								return Response::json(['error' => 'Existe un error,el insumo '.$value['clave_insumo_medico'].' se ha sobrepasado el monto solicitando, por favor contactese con soporte de la aplicación'], 500);
							}
			        	}
					}else
					{
						if($insert_stock){
							$insert_stock->existencia += 0;
							$insert_stock->save();
						}else{		

							$insert_stock = Stock::create($value);
							$insert_stock->existencia = 0;
							$insert_stock->update();
						}
					}
				}else
				{
					DB::rollBack();
					return Response::json(['error' => 'El medicamento con clave '.$value['clave_insumo_medico']."  con número de lote ".$value['lote']." tiene fecha de caducidad menor a 6 meses o es invalido"], 500);
				}	

		       $reglas_movimiento_insumos = [
					'movimiento_id'		=> 'required',
		            'cantidad'        	=> 'required',
		            'precio_unitario'   => 'required',
		            'precio_total'     	=> 'required'
		        ];

		        $value['precio_unitario'] 	= $lista_insumos[$value['clave_insumo_medico']]['precio'];
		        $value['iva'] = 0;

		        if($tipo_insumo != "ME")  //Verifico si es material de curación, para agregar el IVA
		        {
		        	$value['iva'] = ($value['precio_unitario'] * $value['cantidad']) * (0.16);
		        }	
		        $value['precio_total'] 		= ($value['precio_unitario'] * $value['cantidad']);

		        $value['movimiento_id'] = $movimiento->id;

		        $v = Validator::make($value, $reglas_movimiento_insumos, $mensajes);

		        if ($v->fails()) {
		        	DB::rollBack();
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }
		        $value['stock_id'] = $insert_stock->id;


		        /*$pedido_insumo_validador = PedidoInsumo::where("pedido_id", $pedido->id)->where("insumo_medico_clave", $value['clave_insumo_medico'])->first(); //modificamos el insumo de los pedidos
		       
		        if(($pedido_insumo_validador->cantidad_solicitada) >= ($pedido_insumo_validador->cantidad_recibida + $value['existencia']))
				{*/
		        	$movimiento_insumo = MovimientoInsumos::Create($value);	  //Aqui debo de verificar     
		        /*}else
				{
					DB::rollBack();
					return Response::json(['error' => 'Existe un error,el insumo '.$value['clave_insumo_medico'].' se ha sobrepasado el monto solicitando, por favor contactese con soporte de la aplicación'], 500);
				}*/	 	
	        }
	        
		     

	        if($parametros['status'] == 'FI') //Verificamos si se finaliza la recepcion (parcial o completa)
	        {
	        	$total_cantidad_solicitado  = 0;
	        	$total_monto_recibido 		= 0;
		        $total_claves_recibido 		= 0;
		        $total_cantidad_recibido 	= 0;

	        	$pedido_totales = PedidoInsumo::where("pedido_id", $pedido->id)->get(); //Recorremos los insumos del pedido para actualizar los montos (cantidad, claves montos)
	        	$validador_cantidad_solicitada = 0;
	        	foreach ($pedido_totales as $key => $value) {
	        		$validador_cantidad_solicitada += $value['cantidad_solicitada'];
	        		if($value['cantidad_recibida'] != null)
	        		{
	        			$tipo_insumo 	= $lista_insumos[$value['insumo_medico_clave']]['tipo'];
		        
	        			$total_cantidad_solicitado 	+= $value['cantidad_solicitada'];	        			
	        			$total_cantidad_recibido 	+= $value['cantidad_recibida'];
	        			if($tipo_insumo == "ME")
	        				$total_monto_recibido 		+= $value['monto_recibido'];
	        				else
	        				$total_monto_recibido 		+= ( $value['monto_recibido'] * 1.16);
	        							
	        			$total_claves_recibido++;
	        		}
	        	}

	        	$pedido->total_monto_recibido 		= $total_monto_recibido;
	        	$pedido->total_claves_recibidas 	= $total_claves_recibido;
	        	$pedido->total_cantidad_recibida 	= $total_cantidad_recibido;

	        	if($validador_cantidad_solicitada == $total_cantidad_recibido)
	        		$pedido->status = "FI";
	        	
	        	$pedido->update();

	        	/*Calculo de unidad presupuesto*/
	        	$unidad_presupuesto = $this->obtenerDatosPresupuesto($almacen->clues,$almacen->proveedor_id,substr($pedido->fecha,5,2));

	        	$unidad_presupuesto->causes_comprometido 				-= $causes_unidad_presupuesto;
	        	$unidad_presupuesto->causes_devengado 					+= $causes_unidad_presupuesto;
        		
        		$unidad_presupuesto->no_causes_comprometido 			-= $no_causes_unidad_presupuesto;
	        	$unidad_presupuesto->no_causes_devengado 				+= $no_causes_unidad_presupuesto;
        
        		$unidad_presupuesto->material_curacion_comprometido 	-= $material_curacion_unidad_presupuesto;
	        	$unidad_presupuesto->material_curacion_devengado 		+= $material_curacion_unidad_presupuesto;

	        	$unidad_presupuesto->update();
        
	        	/*Fin calculo de unidad presupuesto*/

	        }
	        DB::commit();
	        return Response::json([ 'data' => $movimiento ],200);

	    } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
    
    function destroy($id){
    	 /*
		 try {
            $object = Movimiento::destroy($id);
            return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
		*/
    }


    private function validacion_fecha_caducidad($fecha_validar, $caducidad){
    	if($caducidad == 1){

    		if($this->valida_fecha($fecha_validar))
    		{
    			
	   	    	$fecha = date('Y-m-j');
				$nuevafecha = strtotime ( '+6 month' , strtotime ( $fecha ) ) ;
				$fecha_validar_convertida = strtotime ( $fecha_validar ) ; 
		    	
		    	if($nuevafecha < $fecha_validar_convertida)
		    		return true;
		    	else
		    		return false;
		    }else{
		    	return false;
		    }	
	    }else if($caducidad == 0)
	    {
	    	if($fecha_validar != null)
	    	{
		    	if($this->valida_fecha($fecha_validar))
	    		{
		   	    	$fecha = date('Y-m-j');
					$nuevafecha = strtotime ( '+6 month' , strtotime ( $fecha ) ) ;
					$fecha_validar_convertida = strtotime ( $fecha_validar ) ; 
			    	
			    	if($nuevafecha < $fecha_validar_convertida)
			    		return true;
			    	else
			    		return false;
			    }else{
			    	return false;
			    }
			}else
				return true;
    	}
	}

	private function valida_fecha($fecha)
	{
		$mes = substr($fecha, 5,2);
		$dia = substr($fecha, 8,2);
		$anio = substr($fecha, 0,4);

		if(checkdate ( $mes , $dia , $anio ))
		{
			if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$fecha))
		    {
		        return true;
		    }else{
		        return false;
		    }
		}else
			return false;
		
	}

	private function limpia_espacios($cadena){
		$cadena = str_replace(' ', '', $cadena);
		return $cadena;
	}
}
