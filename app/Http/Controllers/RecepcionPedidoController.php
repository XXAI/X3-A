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

use App\Models\Usuario;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

class RecepcionPedidoController extends Controller
{
    public function index()
    {
    	$pedidos = Movimiento::with("movimientoInsumos.stock")->get();
    	return Response::json([ 'data' => $pedidos],200);

    }

    public function show(Request $request, $id){
    	$pedido = Pedido::with(['recepciones'=>function($recepciones){
			$recepciones->has('entradaAbierta')->with('entradaAbierta.insumos');
		}])->where('status','PS')->find($id);
        
        if(!$pedido){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
        	$pedido = $pedido->load("insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos", "tipoPedido", "almacenProveedor","almacenSolicitante.unidadMedica","proveedor");
        }

        return Response::json([ 'data' => $pedido],200);
    }

    public function store(Request $request)
    {
		/*
    	$mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'almacen_id'        	=> 'required',
            'tipo_movimiento_id'    => 'required',
            'fecha_movimiento'     	=> 'required'
        ];

        $parametros = Input::all();

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));
		
		if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

		if(!isset($parametros['fecha_movimiento'])){
			$parametros['fecha_movimiento'] = '2017-05-01';
		}

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

        try {
            DB::beginTransaction();
	        $v = Validator::make($datos_movimiento, $reglas, $mensajes);

	        if ($v->fails()) {
	            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
	        }

	        $movimiento = Movimiento::create($datos_movimiento);

	        $stock = $parametros['stock'];

	        foreach ($stock as $key => $value) {
	        	$reglas_stock = [
		            'almacen_id'        	=> 'required',
		            'clave_insumo_medico'   => 'required',
		            'lote'     				=> 'required',
		            'fecha_caducidad'     	=> 'required',
		            'codigo_barras'     	=> 'required',
					'existencia'     		=> 'required'
					//'marca_id'     		=> 'required',
		            //'existencia_unidosis' => 'required'
		        ];
				$value['almacen_id'] = $almacen->id;

		        $v = Validator::make($value, $reglas_stock, $mensajes);

		        if ($v->fails()) {
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }

				$insert_stock = Stock::where('codigo_barras',$value['codigo_barras'])->where('fecha_caducidad',$value['fecha_caducidad'])->where('lote',$value['lote'])->where('clave_insumo_medico',$value['clave_insumo_medico'])->first();
				
				if($insert_stock){
					$insert_stock->existencia += $value['existencia'];
					$insert_stock->save();
				}else{					
					$insert_stock = Stock::create($value);
				}

		        $reglas_movimiento_insumos = [
					'movimiento_id'		=> 'required',
		            'cantidad'        	=> 'required',
		            'precio_unitario'   => 'required',
		            'precio_total'     	=> 'required'
					//'iva'     			=> 'required',
		        ];

				$value['movimiento_id'] = $movimiento->id;

		        $v = Validator::make($value, $reglas_movimiento_insumos, $mensajes);

		        if ($v->fails()) {
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }
		        $value['stock_id'] = $insert_stock->id;

		        $movimiento_insumo = MovimientoInsumos::Create($value);	        	
	        }
	        DB::commit();
	        return Response::json([ 'data' => $movimiento ],200);

	    } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
		*/
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

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));


		if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        /*Recepcion de precios por insumo*/
		$proveedor = Proveedor::with('contratoActivo')->find($almacen->proveedor_id);

        if(count($proveedor->contratoActivo) > 1){
            return Response::json(['error' => 'El proveedor tiene mas de un contrato activo'], HttpResponse::HTTP_CONFLICT);
        }else{
            $contrato_activo = $proveedor->contratoActivo;
        }
        
        if(count($contrato_activo) > 1){
            return Response::json(['error' => 'Hay mas de un contrato activo'], HttpResponse::HTTP_CONFLICT);
        }elseif(count($contrato_activo) == 0){
            return Response::json(['error' => 'No se encontraron contratos activos para este proveedor'], HttpResponse::HTTP_CONFLICT);
        }else{
            $contrato_activo = $contrato_activo[0];
        }

        //Se carga un scope con el cual obtenemos los nombres o descripciones de los catalogos que utiliza insumos_medicos
        $insumos = Insumo::conDescripcionesPrecios($contrato_activo->id, $proveedor->id)->with('informacion','generico.grupos')->lists("precio","clave");
		/**/

		$pedido = Pedido::with(['recepciones'=>function($recepciones){
			$recepciones->has('entradaAbierta')->with('entradaAbierta.insumos');
		}])->where('status','PS')->find($id);

		
		
		if(!$pedido){
			return Response::json(['error' => 'No se encontró el pedido'],500);
		}

		if(count($pedido->recepciones) > 1){
			return Response::json(['error' => 'El pedido tiene mas de una recepción abierta'], 500);
		}elseif(count($pedido->recepciones) == 1){
			$recepcion = $pedido->recepciones[0];
		}else{
			$recepcion = new MovimientoPedido;
			$recepcion->recibe = 'RECIBE';
			$recepcion->entrega = 'ENTREGA';
			$recepcion->pedido_id = $pedido->id;
		}


		if(!isset($parametros['fecha_movimiento'])){
			$parametros['fecha_movimiento'] = date('Y-m-d');
		}

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

		
        try {
            DB::beginTransaction();
	        $v = Validator::make($datos_movimiento, $reglas, $mensajes);

	        if ($v->fails()) {
	            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
	        }

	        

			if($recepcion->entradaAbierta){
				
				$movimiento = $recepcion->entradaAbierta;
				$movimiento->update($datos_movimiento);

				MovimientoInsumos::where("movimiento_id", $movimiento->id)->delete();    

			}else{
				$movimiento = Movimiento::create($datos_movimiento);
				$recepcion->movimiento_id = $movimiento->id;
				$recepcion->save();
			}

	        
	        $stock = $parametros['stock'];

	        foreach ($stock as $key => $value) {
	        	$reglas_stock = [
		            'almacen_id'        	=> 'required',
		            'clave_insumo_medico'   => 'required',
		            'lote'     				=> 'required',
		            'fecha_caducidad'     	=> 'required',
		            'codigo_barras'     	=> 'required',
					'existencia'     		=> 'required'
		        ];
				$value['almacen_id'] = $almacen->id;

		        $v = Validator::make($value, $reglas_stock, $mensajes);

		        if ($v->fails()) {
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }

		        
		        if($this->validacion_fecha_caducidad($value['fecha_caducidad']))
		        {
			        if($parametros['status'] == 'FI')
			        {
						$insert_stock = Stock::where('codigo_barras',$value['codigo_barras'])->where('fecha_caducidad',$value['fecha_caducidad'])->where('lote',$value['lote'])->where('clave_insumo_medico',$value['clave_insumo_medico'])->first();
						
						if($insert_stock){
							$insert_stock->existencia += $value['existencia'];
							$insert_stock->save();
						}else{					
							$insert_stock = Stock::create($value);
						}
					}else
					{
						$value['existencia'] = 0;
						$insert_stock = Stock::create($value);
					}
				}else
				{
					return Response::json(['error' => 'Existe medicamento con fecha de caducidad menor a 6 meses, favor de verificar'], HttpResponse::HTTP_CONFLICT);
				}

		        $reglas_movimiento_insumos = [
					'movimiento_id'		=> 'required',
		            'cantidad'        	=> 'required',
		            'precio_unitario'   => 'required',
		            'precio_total'     	=> 'required'
		        ];

		        $value['precio_unitario'] 	= $insumos[$value['clave_insumo_medico']];
		        $value['precio_total'] 		= ($insumos[$value['clave_insumo_medico']] * $value['cantidad']);


				$value['movimiento_id'] = $movimiento->id;

		        $v = Validator::make($value, $reglas_movimiento_insumos, $mensajes);

		        if ($v->fails()) {
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }
		        $value['stock_id'] = $insert_stock->id;

		        $movimiento_insumo = MovimientoInsumos::Create($value);	        	
	        }
	        DB::commit();
	        return Response::json([ 'data' => $movimiento ],200);

	    } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
    
    function destroy($id)
    {
    	 try {
            $object = Movimiento::destroy($id);
            return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }


    private function validacion_fecha_caducidad($fecha_validar)
    {
    	$fecha = date('Y-m-j');
		$nuevafecha = strtotime ( '+6 month' , strtotime ( $fecha ) ) ;
		$fecha_validar_convertida = strtotime ( $fecha_validar ) ; 
    	
    	if($nuevafecha < $fecha_validar_convertida)
    		return true;
    	else
    		return false;
		 
    }
}
