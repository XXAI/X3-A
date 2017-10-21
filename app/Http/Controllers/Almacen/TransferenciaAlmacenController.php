<?php

namespace App\Http\Controllers\Almacen;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Usuario, App\Models\Insumo, App\Models\Almacen, App\Models\Inventario, App\Models\InventarioDetalle, App\Models\Pedido, App\Models\PedidoInsumo, App\Models\Stock, App\Models\Movimiento, App\Models\MovimientoInsumos, App\Models\MovimientoPedido;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;
use DateTime;

class TransferenciaAlmacenController extends Controller{

    public function stats(Request $request){
        $almacen = Almacen::find($request->get('almacen_id'));
        // Akira: en los datos de alternos hay que ver si se pone la cantidad de alternos o en base a su estatus
        $pedidos_stats = Pedido::select(DB::raw(
            '
            count(
                1
            ) as todos,
            count(
                case when status = "BR" then 1 else null end
            ) as borradores,
            count(
                case when status = "SD" then 1 else null end
            ) as por_surtir,
            count(
                case when status = "ET" then 1 else null end
            ) as en_transito,
            count(
                case when status = "PFI" then 1 else null end
            ) as por_finalizar,
            count(
                case when status = "FI" then 1 else null end
            ) as finalizados,
            count(
                case when status = "CA" then 1 else null end
            ) as cancelados
            '
        ))->where('almacen_proveedor',$almacen->id)->where('tipo_pedido_id','PEA')->first();

        $presupuesto_stats = DB::select('
            select 
                sum(IF((IM.tipo = "ME" and IM.es_causes = 1) OR (IM.tipo = "MC"),PI.monto_enviado,0)) as causes_y_material,
                sum(IF(IM.tipo = "ME" and IM.es_causes = 0,PI.monto_enviado,0)) as no_causes
            from
                pedidos_insumos PI, pedidos P, insumos_medicos IM
            where PI.deleted_at is null and P.deleted_at is null and PI.pedido_id = P.id and IM.clave = PI.insumo_medico_clave and P.tipo_pedido_id = "PEA" and P.status != "BR" and P.almacen_proveedor = :almacen_id
            ',['almacen_id'=>$almacen->id]);

        return Response::json(['stats'=>$pedidos_stats,'presupuesto'=>$presupuesto_stats[0]],200);
    }

    public function index(Request $request){
        $parametros = Input::only('status','q','page','per_page');

        $almacen = Almacen::find($request->get('almacen_id'));

        $transferencias = Pedido::where('almacen_proveedor',$almacen->id)->where('tipo_pedido_id','PEA');
        
        if ($parametros['q']) {
            $transferencias =  $transferencias->where(function($query) use ($parametros) {
                    $query->where('descripcion','LIKE',"%".$parametros['q']."%")->orWhere('folio','LIKE',"%".$parametros['q']."%")->orWhere('clues','LIKE',"%".$parametros['q']."%");
            });
        }

        if(isset($parametros['status'])) {
            $transferencias = $transferencias->where("status",$parametros['status']);
        }
        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $transferencias = $transferencias->paginate($resultadosPorPagina);
        } else {
            $transferencias = $transferencias->get();
        }

        return Response::json([ 'data' => $transferencias],200);
    }

    public function show(Request $request, $id){
        $almacen = Almacen::find($request->get('almacen_id'));
    	//$pedido = Pedido::where('almacen_solicitante',$request->get('almacen_id'))->find($id);
        $pedido = Pedido::where('almacen_proveedor',$almacen->id)->find($id);
        
        if(!$pedido){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
            $pedido = $pedido->load("insumos.tipoInsumo","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos","almacenSolicitante.unidadMedica","almacenProveedor","proveedor","encargadoAlmacen","director");
            $pedido = $pedido->load("movimientos.transferenciaSurtida.insumos","movimientos.transferenciaRecibidaBorrador.insumos","movimientos.transferenciaRecibida.insumos","movimientos.reintegro.insumos");
            $pedido = $pedido->load("movimientosTransferenciasCompleto");
        }
        return Response::json([ 'data' => $pedido],200);
    }

    public function store(Request $request){
        $mensajes = [
            'required'      => "required",
        ];
        
        $reglas = [
            'descripcion' => 'required',
            'lista_insumos' => 'required|array|min:1'
            //'fecha_inicio_captura',
            //'fecha_conclusion_captura',
            //'observaciones',
            //'status',
            //'almacen_id',
            //'clues',
            //'total_claves',
            //'total_monto_causes',
            //'total_monto_no_causes',
            //'total_monto_material_curacion'
        ];

        $inventario = Inventario::where('almacen_id',$request->get('almacen_id'))->first();

        if($inventario){
            return Response::json(['error' => 'Ya hay una inicialización de inventario capturada, no se puede inicializar el inventario'], HttpResponse::HTTP_CONFLICT);
        }
        
        $parametros = Input::all();

        $v = Validator::make($parametros, $reglas, $mensajes);
        
        if ($v->fails()) {
            return Response::json(['error' => $v->errors(), 'error_formulario'=>1], HttpResponse::HTTP_CONFLICT);
        }

        $almacen = Almacen::find($request->get('almacen_id'));

        try{
            DB::beginTransaction();
            $datos_inventario = [
                'descripcion' => $parametros['descripcion'],
                'observaciones' => ($parametros['observaciones'])?$parametros['observaciones']:null,
                'fecha_inicio_captura',
                'status' => 'BR',
                'almacen_id' => $almacen->id,
                'clues' => $almacen->clues,
                'total_claves' => 0,
                'total_monto_causes' => 0,
                'total_monto_no_causes' => 0,
                'total_monto_material_curacion' => 0
            ];
            
            $nuevo_inventario = Inventario::create($datos_inventario);
            DB::commit();

            $response = $this->guardarDatosInventario($nuevo_inventario,$parametros['lista_insumos']);

            if($response['status'] == 200){
                return Response::json([ 'data' => $nuevo_inventario],200);
            }else{
                return Response::json(['error' => $response['error']], HttpResponse::HTTP_CONFLICT);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    private function guardarDatosInventario($inventario,$lista_insumos_form){
        try{
            DB::beginTransaction();

            $lista_insumos_db = InventarioDetalle::where('inventario_id',$inventario->id)->withTrashed()->get();
            
            if(count($lista_insumos_db) > count($lista_insumos_form)){
                $total_max_insumos = count($lista_insumos_db);
            }else{
                $total_max_insumos = count($lista_insumos_form);
            }
            
            for ($i=0; $i < $total_max_insumos ; $i++) {
                if(isset($lista_insumos_db[$i])){ //Si existen en la base de datos se editan o eliminan.
                    $insumo_db = $lista_insumos_db[$i];
                    if(isset($lista_insumos_form[$i])){ //Si hay insumos desde el fomulario, editamos el insumo de la base de datos.
                        $insumo_form = $lista_insumos_form[$i];
    
                        $insumo_db->deleted_at = null; //Por si el elemento ya esta liminado, lo restauramos
                        $insumo_db->insumo_medico_clave = $lista_insumos_form['clave'];
                        $insumo_db->codigo_barras = $lista_insumos_form['codigo_barras'];
                        $insumo_db->lote = $lista_insumos_form['lote'];
                        $insumo_db->fecha_caducidad = $lista_insumos_form['fecha_caducidad'];
                        $insumo_db->cantidad = $lista_insumos_form['cantidad'];
                        $insumo_db->precio_unitario = $lista_insumos_form['precio_unitario'];
                        $insumo_db->monto = $lista_insumos_form['monto'];
    
                        $insumo_db->save();
                    }else{ //de lo contrario eliminamos el insumo de la base de datos.
                        $insumo_db->delete();
                    }
                }else{ //SI no existen en la base de datos, se crean nuevos
                    $insumo_db = new InventarioDetalle();

                    $insumo_db->inventario_id = $inventario->id;
                    $insumo_db->insumo_medico_clave = $lista_insumos_form['clave'];
                    $insumo_db->codigo_barras = $lista_insumos_form['codigo_barras'];
                    $insumo_db->lote = $lista_insumos_form['lote'];
                    $insumo_db->fecha_caducidad = $lista_insumos_form['fecha_caducidad'];
                    $insumo_db->cantidad = $lista_insumos_form['cantidad'];
                    $insumo_db->precio_unitario = $lista_insumos_form['precio_unitario'];
                    $insumo_db->monto = $lista_insumos_form['monto'];

                    $insumo_db->save();
                }
            }

            //Falta sumar los totales, de claves y montos

            DB::commit();

            return array('status'=>200, 'msg'=>'Exito');

        }catch (\Exception $e) {
            DB::rollBack();
            return array('status'=>HttpResponse::HTTP_CONFLICT, 'error'=>$e->getMessage());
        }
    }

    public function surtir($id, Request $request){
        $datos = Input::all();

        try{
            $pedido = Pedido::find($id);
            if($pedido->status != 'SD' && $pedido->status != 'ET'){
                return Response::json(['error' => 'El pedido ya no puede surtirse.'], 500);
            }
            
            $pedido->load('insumos.conDatosInsumo');

            $stock_ids = [];
            $claves = [];
            $cantidades_stock = [];
            foreach($datos['lista'] as $stock){
                $stock_ids[] = $stock['stock_id'];
                if(!isset($claves[$stock['clave']])){
                    $claves[$stock['clave']] = ['cantidad'=>0,'tipo_insumo_id'=>0,'precio'=>0,'cantidad_x_envase'=>0];
                }
                $claves[$stock['clave']]['cantidad'] += $stock['cantidad'];
                $cantidades_stock[$stock['stock_id']] = $stock['cantidad'];
            }

            $stocks = Stock::where('almacen_id',$pedido->almacen_proveedor)->whereIn('id',$stock_ids)->get();

            DB::beginTransaction();
            //Harima: Actualizamos el pedido para mostrar lo que se envio
            foreach($pedido->insumos as $insumo){
                if(isset($claves[$insumo->insumo_medico_clave])){
                    $insumo->cantidad_enviada = $claves[$insumo->insumo_medico_clave]['cantidad'];
                    $insumo->monto_enviado = $insumo->precio_unitario * $insumo->cantidad_enviada;

                    $insumo->save();

                    $claves[$insumo->insumo_medico_clave]['tipo_insumo_id'] = $insumo->tipo_insumo_id;
                    $claves[$insumo->insumo_medico_clave]['precio'] = $insumo->precio_unitario;
                    $claves[$insumo->insumo_medico_clave]['cantidad_x_envase'] = $insumo->conDatosInsumo->cantidad_x_envase;
                }
            }

            $pedido->status = 'ET';
            $pedido->recepcion_permitida = 1;
            $pedido->save();

            $movimiento_insumos = [];
            //Harima: Actualizamos el stock y creamos los datos de insumos para el movimiento
            foreach($stocks as $stock_item){
                $stock_item->existencia -= $cantidades_stock[$stock_item->id];
                $stock_item->existencia_unidosis -= ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);

                $stock_item->save();

                $movimiento_insumo_item = new MovimientoInsumos();
                $movimiento_insumo_item->tipo_insumo_id = $claves[$stock_item->clave_insumo_medico]['tipo_insumo_id'];
                $movimiento_insumo_item->stock_id = $stock_item->id;
                $movimiento_insumo_item->clave_insumo_medico = $stock_item->clave_insumo_medico;
                $movimiento_insumo_item->modo_salida = 'N';
                $movimiento_insumo_item->cantidad = $cantidades_stock[$stock_item->id];
                $movimiento_insumo_item->cantidad_unidosis = ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);
                $movimiento_insumo_item->precio_unitario = $claves[$stock_item->clave_insumo_medico]['precio'];
                $movimiento_insumo_item->precio_total = $movimiento_insumo_item->precio_unitario * $movimiento_insumo_item->cantidad;

                $movimiento_insumos[] = $movimiento_insumo_item;
            }

            $movimiento = new Movimiento();
            $movimiento->almacen_id = $pedido->almacen_proveedor;
            $movimiento->tipo_movimiento_id = 3;
            $movimiento->status = 'FI';
            $movimiento->fecha_movimiento = date('Y-m-d');
            
            $movimiento->save();
            $movimiento->insumos()->saveMany($movimiento_insumos);

            $movimiento_pedido = new MovimientoPedido();
            $movimiento_pedido->pedido_id = $id;

            $movimiento->movimientoPedido()->save($movimiento_pedido);

            DB::commit();
	        return Response::json(['message'=>'surtido','data'=>$movimiento],200);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    public function actualizarTransferencia($id, Request $request){
        $datos = Input::all();
        try{
            $pedido = Pedido::where('tipo_pedido_id','PEA')->find($id);
            
            if($pedido->status != 'SD' && $pedido->status != 'ET'){
                return Response::json(['error' => 'El pedido ya no puede surtirse.'], 500);
            }
            
            $pedido->load('insumos.conDatosInsumo');

            $stock_ids = [];
            $claves = [];
            $cantidades_stock = [];
            foreach($datos['insumos'] as $stock){
                $stock_ids[] = $stock['stock_id'];
                if(!isset($claves[$stock['clave']])){
                    $claves[$stock['clave']] = ['cantidad'=>0,'tipo_insumo_id'=>0,'precio'=>0,'cantidad_x_envase'=>0];
                }
                $claves[$stock['clave']]['cantidad'] += $stock['cantidad'];
                $cantidades_stock[$stock['stock_id']] = $stock['cantidad'];
            }

            $stocks = Stock::where('almacen_id',$pedido->almacen_proveedor)->whereIn('id',$stock_ids)->get();

            DB::beginTransaction();
            //Harima: Actualizamos el pedido para mostrar lo que se envio
            foreach($pedido->insumos as $insumo){
                if(isset($claves[$insumo->insumo_medico_clave])){
                    $claves[$insumo->insumo_medico_clave]['tipo_insumo_id'] = $insumo->tipo_insumo_id;
                    $claves[$insumo->insumo_medico_clave]['precio'] = $insumo->precio_unitario;
                    $claves[$insumo->insumo_medico_clave]['cantidad_x_envase'] = $insumo->conDatosInsumo->cantidad_x_envase;
                }
            }

            //$pedido->status = 'FI-P';
            //$pedido->recepcion_permitida = 0;
            //$pedido->save();

            $movimiento_mas_insumos = [];
            $movimiento_menos_insumos = [];

            foreach($stocks as $stock_item){

                if($datos['accion'] == 'reintegrar'){
                    $stock_item->existencia += $cantidades_stock[$stock_item->id];
                    $stock_item->existencia_unidosis += ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);
                    $stock_item->save();
                }
                
                if($datos['accion'] == 'reintegrar' || $datos['accion'] == 'eliminar'){
                    $insumo_mas = new MovimientoInsumos();
                    $insumo_mas->tipo_insumo_id = $claves[$stock_item->clave_insumo_medico]['tipo_insumo_id'];
                    $insumo_mas->stock_id = $stock_item->id;
                    $insumo_mas->clave_insumo_medico = $stock_item->clave_insumo_medico;
                    //$insumo_mas->modo_salida = 'N';
                    $insumo_mas->cantidad = $cantidades_stock[$stock_item->id];
                    $insumo_mas->cantidad_unidosis = ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);
                    $insumo_mas->precio_unitario = $claves[$stock_item->clave_insumo_medico]['precio'];
                    $insumo_mas->precio_total = $insumo_mas->precio_unitario * $insumo_mas->cantidad;
    
                    $movimiento_mas_insumos[] = $insumo_mas;
                }
                
                if($datos['accion'] == 'eliminar'){
                    $insumo_menos = new MovimientoInsumos();
                    $insumo_menos->tipo_insumo_id = $claves[$stock_item->clave_insumo_medico]['tipo_insumo_id'];
                    $insumo_menos->stock_id = $stock_item->id;
                    $insumo_menos->clave_insumo_medico = $stock_item->clave_insumo_medico;
                    $insumo_menos->modo_salida = 'N';
                    $insumo_menos->cantidad = $cantidades_stock[$stock_item->id];
                    $insumo_menos->cantidad_unidosis = ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);
                    $insumo_menos->precio_unitario = $claves[$stock_item->clave_insumo_medico]['precio'];
                    $insumo_menos->precio_total = $insumo_menos->precio_unitario * $insumo_menos->cantidad;
    
                    $movimiento_menos_insumos[] = $insumo_menos;
                }
            }

            if(count($movimiento_mas_insumos) > 0){
                $movimiento_mas = new Movimiento();
                $movimiento_mas->almacen_id = $pedido->almacen_proveedor;
                $movimiento_mas->tipo_movimiento_id = 1;
                $movimiento_mas->status = 'FI';
                $movimiento_mas->observaciones = 'SE REGRESAN AL INVENTARIO INSUMOS NO ENTREGADOS EN EL PEDIDO CON FOLIO: '.$pedido->folio;
                $movimiento_mas->fecha_movimiento = date('Y-m-d');
                
                $movimiento_mas->save();
                $movimiento_mas->insumos()->saveMany($movimiento_mas_insumos);

                $movimiento_pedido = new MovimientoPedido();
                $movimiento_pedido->pedido_id = $id;
    
                $movimiento_mas->movimientoPedido()->save($movimiento_pedido);
            }
            
            if(count($movimiento_menos_insumos) > 0){
                $movimiento_menos = new Movimiento();
                $movimiento_menos->almacen_id = $pedido->almacen_proveedor;
                $movimiento_menos->tipo_movimiento_id = 7;
                $movimiento_menos->status = 'FI';
                $movimiento_menos->observaciones = 'SE DAN DE BAJA INSUMOS NO ENTREGADOS EN EL PEDIDO CON FOLIO: '.$pedido->folio;
                $movimiento_menos->fecha_movimiento = date('Y-m-d');
                
                $movimiento_menos->save();
                $movimiento_menos->insumos()->saveMany($movimiento_menos_insumos);

                $movimiento_pedido = new MovimientoPedido();
                $movimiento_pedido->pedido_id = $id;
    
                $movimiento_menos->movimientoPedido()->save($movimiento_pedido);
            }
            
            DB::commit();
	        return Response::json(['message'=>'finalizado','data'=>$pedido],200);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    public function finalizar($id, Request $request){
        $datos = Input::all();

        try{
            $pedido = Pedido::where('tipo_pedido_id','PEA')->find($id);
            if($pedido->status != 'SD' && $pedido->status != 'ET'){
                return Response::json(['error' => 'El pedido ya no puede surtirse.'], 500);
            }
            
            $pedido->load('insumos.conDatosInsumo');

            $stock_ids = [];
            $claves = [];
            $cantidades_stock = [];
            foreach($datos['lista'] as $stock){
                $stock_ids[] = $stock['stock_id'];
                if(!isset($claves[$stock['clave']])){
                    $claves[$stock['clave']] = ['cantidad'=>0,'tipo_insumo_id'=>0,'precio'=>0,'cantidad_x_envase'=>0];
                }
                $claves[$stock['clave']]['cantidad'] += $stock['cantidad'];
                $cantidades_stock[$stock['stock_id']] = $stock['cantidad'];
            }

            $stocks = Stock::where('almacen_id',$pedido->almacen_proveedor)->whereIn('id',$stock_ids)->get();

            DB::beginTransaction();
            //Harima: Actualizamos el pedido para mostrar lo que se envio
            foreach($pedido->insumos as $insumo){
                if(isset($claves[$insumo->insumo_medico_clave])){
                    $claves[$insumo->insumo_medico_clave]['tipo_insumo_id'] = $insumo->tipo_insumo_id;
                    $claves[$insumo->insumo_medico_clave]['precio'] = $insumo->precio_unitario;
                    $claves[$insumo->insumo_medico_clave]['cantidad_x_envase'] = $insumo->conDatosInsumo->cantidad_x_envase;
                }
            }

            $pedido->status = 'FI-P';
            $pedido->recepcion_permitida = 0;
            $pedido->save();

            $movimiento_mas_insumos = [];
            $movimiento_menos_insumos = [];
            //Harima: Creamos los datos para los ajustes
            foreach($stocks as $stock_item){
                $insumo_mas = new MovimientoInsumos();
                $insumo_mas->tipo_insumo_id = $claves[$stock_item->clave_insumo_medico]['tipo_insumo_id'];
                $insumo_mas->stock_id = $stock_item->id;
                $insumo_mas->clave_insumo_medico = $stock_item->clave_insumo_medico;
                $insumo_mas->modo_salida = 'N';
                $insumo_mas->cantidad = $cantidades_stock[$stock_item->id];
                $insumo_mas->cantidad_unidosis = ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);
                $insumo_mas->precio_unitario = $claves[$stock_item->clave_insumo_medico]['precio'];
                $insumo_mas->precio_total = $insumo_mas->precio_unitario * $insumo_mas->cantidad;

                $movimiento_mas_insumos[] = $insumo_mas;

                $insumo_menos = new MovimientoInsumos();
                $insumo_menos->tipo_insumo_id = $claves[$stock_item->clave_insumo_medico]['tipo_insumo_id'];
                $insumo_menos->stock_id = $stock_item->id;
                $insumo_menos->clave_insumo_medico = $stock_item->clave_insumo_medico;
                $insumo_menos->modo_salida = 'N';
                $insumo_menos->cantidad = $cantidades_stock[$stock_item->id];
                $insumo_menos->cantidad_unidosis = ($claves[$stock_item->clave_insumo_medico]['cantidad_x_envase'] * $cantidades_stock[$stock_item->id]);
                $insumo_menos->precio_unitario = $claves[$stock_item->clave_insumo_medico]['precio'];
                $insumo_menos->precio_total = $insumo_menos->precio_unitario * $insumo_menos->cantidad;

                $movimiento_menos_insumos[] = $insumo_menos;
            }

            $movimiento_mas = new Movimiento();
            $movimiento_mas->almacen_id = $pedido->almacen_proveedor;
            $movimiento_mas->tipo_movimiento_id = 6;
            $movimiento_mas->status = 'FI';
            $movimiento_mas->observaciones = 'SE REGRESAN AL INVENTARIO INSUMOS NO ENTREGADOS EN EL PEDIDO CON FOLIO: '.$pedido->folio;
            $movimiento_mas->fecha_movimiento = date('Y-m-d');
            
            $movimiento_mas->save();
            $movimiento_mas->insumos()->saveMany($movimiento_mas_insumos);

            $movimiento_menos = new Movimiento();
            $movimiento_menos->almacen_id = $pedido->almacen_proveedor;
            $movimiento_menos->tipo_movimiento_id = 7;
            $movimiento_menos->status = 'FI';
            $movimiento_menos->observaciones = 'SE DAN DE BAJA INSUMOS NO ENTREGADOS EN EL PEDIDO CON FOLIO: '.$pedido->folio;
            $movimiento_menos->fecha_movimiento = date('Y-m-d');
            
            $movimiento_menos->save();
            $movimiento_menos->insumos()->saveMany($movimiento_menos_insumos);
            
            DB::commit();
	        return Response::json(['message'=>'finalizado','data'=>$pedido],200);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }
}