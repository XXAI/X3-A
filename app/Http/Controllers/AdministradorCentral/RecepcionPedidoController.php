<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Movimiento, App\Models\MovimientoInsumos, App\Models\MovimientoPedido, App\Models\Almacen, App\Models\Proveedor, App\Models\Pedido, App\Models\PedidoInsumo, App\Models\Insumo, App\Models\UnidadMedicaPresupuesto, App\Models\Stock ;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;


class RecepcionPedidoController extends Controller
{
	public function borrarRecepcion($id, Request $request){
        try{
            DB::beginTransaction();
            
            $movimientos =  Movimiento::with("movimientoInsumosStock", "movimientoPedido.pedido")->find($id);
            
            $pedido_id = $movimientos->movimientoPedido->pedido->id;
            $almacen = Almacen::find($movimientos->almacen_id);

            if(!$almacen){
            	DB::rollBack();
                return Response::json(['error' =>"No se encontró el almacen."], 500);
            }
            
            $proveedor = Proveedor::with('contratoActivo')->find($almacen->proveedor_id);

            $contrato_activo = $proveedor->contratoActivo;
            
            $insumos = Insumo::conDescripcionesPrecios($contrato_activo->id, $proveedor->id)->select("precio", "clave", "insumos_medicos.tipo", "es_causes", "insumos_medicos.tiene_fecha_caducidad", "contratos_precios.tipo_insumo_id", "medicamentos.cantidad_x_envase")->withTrashed()->get();
            $lista_insumos = array();
            
            foreach ($insumos as $key => $value) {
                $array_datos = array();
                $array_datos['precio']              = $value['precio'];
                $array_datos['clave']               = $value['clave'];
                $array_datos['tipo']                = $value['tipo'];
                $array_datos['tipo_insumo_id']      = $value['tipo_insumo_id'];
                $array_datos['es_causes']           = $value['es_causes'];
                $array_datos['caducidad']           = $value['tiene_fecha_caducidad'];
                $array_datos['cantidad_unidosis']   = $value['cantidad_x_envase'];
                $lista_insumos[$value['clave']]     = $array_datos;
            }


            $total_causes               = 0;
            $total_no_causes            = 0;
            $total_material_curacion    = 0;

            $total_rows = 0;
            $total_cantidad_insumos = 0;
            $claves = array();
            $total_claves = 0;
            foreach ($movimientos->movimientoInsumosStock as $key => $value) {
                $total_rows += $value['cantidad'];
                if($lista_insumos[$value['stock']['clave_insumo_medico']]['tipo'] == "ME")
                {
                    if($lista_insumos[$value['stock']['clave_insumo_medico']]['es_causes']== 1)
                    {
                        $total_causes += $value['precio_total'];
                    }else
                    {
                        $total_no_causes += $value['precio_total'];
                    }
                }else
                {

                    $total_material_curacion += ($value['precio_total'] * 1.16);
                }

                $pedidoInsumo = PedidoInsumo::where("pedido_id", $pedido_id)
                                          ->where("insumo_medico_clave", $value['stock']['clave_insumo_medico'])
                                          ->first();
                                             
                $pedidoInsumo->cantidad_recibida  = ($pedidoInsumo->cantidad_recibida - $value['cantidad']); 
                $pedidoInsumo->monto_recibido     = ($pedidoInsumo->monto_recibido - $value['precio_total']);
                $pedidoInsumo->save(); 

                $total_cantidad_insumos +=  $value['cantidad'];
                $stock = Stock::find($value['stock_id']);
                $cantidad_actual = $stock->existencia;
                $cantidad_regresar = $value['cantidad'];
                $cantidad_restante = $cantidad_actual - $cantidad_regresar;

                $cantidad_unidosis = 0;
                if($stock->existencia_unidosis > 0)
                    $cantidad_unidosis = ($stock->existencia_unidosis - ($value['cantidad'] *  floatval($lista_insumos[$value['stock']['clave_insumo_medico']]['cantidad_unidosis'])));
                
                if(!array_key_exists($value['stock']['clave_insumo_medico'], $claves))
                    $claves[$value['stock']['clave_insumo_medico']] = 1;
                
                if($cantidad_restante >= 0 || $cantidad_unidosis>=0)
                {
                	$stock->existencia = $cantidad_restante;
                	$stock->save();
                }
                else
                {
                	DB::rollBack();
                	return Response::json(['error' =>"No se puede eliminar la recepción, porque existe insumos que se encuentran utilizados, ".$value['stock']['clave_insumo_medico']." diferencia ".($cantidad_regresar - $cantidad_actual)], 500);
                }
            }

            
           	$fecha = explode("-", $movimientos->movimientoPedido->pedido->fecha);
           	$clues = $movimientos->movimientoPedido->pedido->clues;
           	$almacen = $movimientos->movimientoPedido->pedido->almacen_solicitante;

            $presupuesto = UnidadMedicaPresupuesto::where("clues", $clues)
                                                    ->where("almacen_id", $almacen)            
                                                    ->where("mes", intVal($fecha[1]))
                                                    ->where("anio", intVal($fecha[0]))
                                                    ->where("proveedor_id", $proveedor->id)
                                                    ->first();

             
            if($total_causes > $presupuesto->causes_devengado || $total_no_causes > $presupuesto->no_causes_devengado || round($total_material_curacion,2) > $presupuesto->material_curacion_devengado)
            {
                DB::rollBack();
                    return Response::json(['error' =>"No se puede eliminar la recepción, porque el monto recibido es mayor al monto solicitado"], 500);
            }

            $presupuesto->causes_devengado               = round((floatval($presupuesto->causes_devengado) - $total_causes),2);                                         
            $presupuesto->no_causes_devengado            = round((floatval($presupuesto->no_causes_devengado) - $total_no_causes),2);                                         
            $presupuesto->material_curacion_devengado    = round((floatval($presupuesto->material_curacion_devengado) - $total_material_curacion),2); 

            $presupuesto->causes_comprometido                 = round((floatval($presupuesto->causes_comprometido) + $total_causes),2);     
            $presupuesto->no_causes_comprometido              = round((floatval($presupuesto->no_causes_comprometido) + $total_no_causes),2);                                         
            $presupuesto->material_curacion_comprometido      = round((floatval($presupuesto->material_curacion_comprometido) + $total_material_curacion),2);

            $presupuesto->save();

             $pedido = Pedido::find($pedido_id);
            $pedido->total_monto_recibido = $pedido->total_monto_recibido - round(($total_causes + $total_no_causes + $total_material_curacion),2);
            $pedido->total_claves_recibidas = ($pedido->total_claves_recibidas - count($claves));
            $pedido->total_cantidad_recibida = ($pedido->total_cantidad_recibida - $total_rows);
            $pedido->status = 'PS';
            $pedido->save(); 
            

            /*Eliminacion de la recepcion*/ 
            MovimientoInsumos::where("movimiento_id", $movimientos->id)->delete();
            MovimientoPedido::find($movimientos->movimientoPedido->id)->delete();
            Movimiento::find($id)->delete();
           
            
            
            
            
            /**/         
            DB::commit();
            return Response::json([ 'data' => $movimientos->movimientoPedido->pedido],200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
}