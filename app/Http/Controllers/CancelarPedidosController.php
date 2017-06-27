<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Presupuesto, App\Models\UnidadMedicaPresupuesto,  App\Models\TransferenciaPresupuesto, App\Models\Pedido;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;
use Carbon\Carbon;

class CancelarPedidosController extends Controller
{
    /**
     * Transfiere recursos de una clus origen a una destino.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cancelarYTransferir(Request $request, $id){
        $input = Input::only('transferir_a_mes','transferir_a_anio');

        $pedido = Pedido::with("insumos.insumoDetalle","recepciones.entrada")->find($id);

        $recepion_abierta = false;

        foreach($pedido->recepciones as $recepcion){
            if($recepcion->entrada->status == 'BR'){
                $recepion_abierta = true;
                break;
            }
        }

        if($recepion_abierta){
            return Response::json([ 'data' => $pedido, 'error' => 'No se puede cancelar el pedido, ya que tiene una recepión abierta.' ],500);
        }

        //return Response::json([ 'data' => $pedido, 'error' => 'error en el servidor bla bla bla' ],500);

        DB::beginTransaction();
        try {
            $total_causes_disponible = 0.00;
            $total_no_causes_disponible = 0.00;
            $total_material_curacion_disponible = 0.00;

            foreach($pedido->insumos as $insumo){
                if($insumo->insumoDetalle->tipo == 'ME' && $insumo->insumoDetalle->es_causes){
                    $total_causes_disponible += ($insumo->monto_solicitado - $insumo->monto_recibido);
                }elseif($insumo->insumoDetalle->tipo == 'ME' && !$insumo->insumoDetalle->es_causes){
                    $total_no_causes_disponible += ($insumo->monto_solicitado - $insumo->monto_recibido);
                }else{
                    $total_material_curacion_disponible += ($insumo->monto_solicitado - $insumo->monto_recibido);
                }
            }

            $total_material_curacion_disponible += ($total_material_curacion_disponible*16/100);

            $fecha_pedido = explode('-',$pedido->fecha);

            $pedido_mes = $fecha_pedido[1];
            $pedido_anio = $fecha_pedido[0];

            $pedido_almacen = $pedido->almacen_solicitante;
            $pedido_clues = $pedido->clues;

            $pedido->status = 'EX-CA';
            $pedido->fecha_cancelacion = Carbon::now();

            //DB::rollBack();
            //return Response::json([ 'data' => ['total_causes_disponible'=>$total_causes_disponible, 'total_no_causes_disponible'=>$total_no_causes_disponible, 'total_material_curacion_disponible'=>$total_material_curacion_disponible], 'error' => 'error en el servidor bla bla bla' ],500);

            if($pedido->save()){
                if($pedido_mes == $input['transferir_a_mes'] && $pedido_anio == $input['transferir_a_anio']){
                    $presupuesto_pedido = UnidadMedicaPresupuesto::where('clues',$pedido_clues)->where('almacen_id',$pedido_almacen)->where('mes',$pedido_mes)->where('anio',$pedido_anio)->first();
                    $presupuesto_pedido->causes_comprometido -= $total_causes_disponible;
                    $presupuesto_pedido->causes_disponible += $total_causes_disponible;

                    $presupuesto_pedido->no_causes_comprometido -= $total_no_causes_disponible;
                    $presupuesto_pedido->no_causes_disponible += $total_no_causes_disponible;

                    $presupuesto_pedido->material_curacion_comprometido -= $total_material_curacion_disponible;
                    $presupuesto_pedido->material_curacion_disponible += $total_material_curacion_disponible;

                    $presupuesto_pedido->save();
                }else{
                    $unidad_medica_origen_presupuesto = UnidadMedicaPresupuesto::where('clues',$pedido_clues)->where('almacen_id',$pedido_almacen)->where('mes',$pedido_mes)->where('anio',$pedido_anio)->first();
                    $unidad_medica_destino_presupuesto = UnidadMedicaPresupuesto::where('clues',$pedido_clues)->where('almacen_id',$pedido_almacen)->where('mes',$input['transferir_a_mes'])->where('anio',$input['transferir_a_anio'])->first();
                    
                    if(!$unidad_medica_origen_presupuesto || !$unidad_medica_destino_presupuesto){
                        throw new Exception("Una de meses no tiene presupuesto configurado para los valores proporcionados.");
                    }

                    $unidad_medica_origen_presupuesto->causes_modificado   -= $total_causes_disponible;
                    $unidad_medica_origen_presupuesto->causes_comprometido -= $total_causes_disponible;

                    $unidad_medica_destino_presupuesto->causes_modificado += $total_causes_disponible;
                    $unidad_medica_destino_presupuesto->causes_disponible += $total_causes_disponible;

                    $unidad_medica_origen_presupuesto->no_causes_modificado -= $total_no_causes_disponible;
                    $unidad_medica_origen_presupuesto->no_causes_comprometido -= $total_no_causes_disponible;

                    $unidad_medica_destino_presupuesto->no_causes_modificado += $total_no_causes_disponible;
                    $unidad_medica_destino_presupuesto->no_causes_disponible += $total_no_causes_disponible;
                    
                    $unidad_medica_origen_presupuesto->material_curacion_modificado -= $total_material_curacion_disponible;
                    $unidad_medica_origen_presupuesto->material_curacion_comprometido -= $total_material_curacion_disponible;

                    $unidad_medica_destino_presupuesto->material_curacion_modificado += $total_material_curacion_disponible;
                    $unidad_medica_destino_presupuesto->material_curacion_disponible += $total_material_curacion_disponible;
                        
                    $unidad_medica_origen_presupuesto->save();
                    $unidad_medica_destino_presupuesto->save();

                    $datos_transferencia = [];
                    $datos_transferencia['presupuesto_id'] = $unidad_medica_origen_presupuesto->presupuesto_id;
                    $datos_transferencia['clues_origen'] = $pedido_clues;
                    $datos_transferencia['almacen_origen'] = $pedido_almacen;
                    $datos_transferencia['mes_origen'] = $pedido_mes;
                    $datos_transferencia['anio_origen'] = $pedido_anio;
                    $datos_transferencia['causes'] = $total_causes_disponible;
                    $datos_transferencia['no_causes'] = $total_no_causes_disponible;
                    $datos_transferencia['material_curacion'] = $total_material_curacion_disponible;
                    $datos_transferencia['clues_destino'] = $pedido_clues;
                    $datos_transferencia['almacen_destino'] = $pedido_almacen;
                    $datos_transferencia['mes_destino'] = $input['transferir_a_mes'];
                    $datos_transferencia['anio_destino'] = $input['transferir_a_anio'];

                    //throw new Exception("La cantidad de No causes es mayor al presupuesto disponible del origen.");

                    $transferencia = TransferenciaPresupuesto::create($datos_transferencia);
                }

                DB::commit();
                return Response::json([ 'data' => $pedido ],200);
            }else{
                throw new Exception("No se pudieron guardar los cambios en el pedido.");
            }

        }catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

}
