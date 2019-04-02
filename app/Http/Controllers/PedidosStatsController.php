<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;

use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\PedidoInsumoClues;
use App\Models\PedidoAlterno;
use App\Models\Acta;
use App\Models\Usuario;
use App\Models\Almacen;
use App\Models\Presupuesto;
use App\Models\MovimientoPedido;
use App\Models\Movimiento;
use App\Models\MovimientoInsumos;
use App\Models\Stock;
use App\Models\UnidadMedicaPresupuesto;
use App\Models\HistorialMovimientoTransferencia;


use App\Models\PresupuestoEjercicio, App\Models\PresupuestoUnidadMedica,  App\Models\UnidadMedica, App\Models\Jurisdiccion;
use App\Models\PresupuestoMovimientoEjercicio, App\Models\PresupuestoMovimientoUnidadMedica;


use App\Models\PedidoOrdinario, App\Models\PedidoOrdinarioUnidadMedica;


use \Excel;
use Carbon\Carbon;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


class PedidosStatsController extends Controller{
    public function stats(Request $request){
        try{

           // $clues = $request->get('clues');
            $parametros = Input::all();
            
            $almacen = Almacen::find($request->get('almacen_id'));
            $clues = $almacen->externo == 1 ? $almacen->clues_perteneciente : $almacen->clues;
            // Akira: en los datos de alternos hay que ver si se pone la cantidad de alternos o en base a su estatus
            $pedidos = Pedido::select(DB::raw(
                '
                count(
                    1
                ) as todos,
                count(
                    case when tipo_pedido_id = "PO" then 1 else null end
                ) as ordinarios ,
                count(
                    case when status = "BR" and tipo_pedido_id = "PO" then 1 else null end
                ) as ordinarios_borradores ,
                
                count(
                    case when tipo_pedido_id = "PXT" then 1 else null end
                ) as extraordinarios ,
                count(
                    case when status = "BR" and tipo_pedido_id = "PXT" then 1 else null end
                ) as extraordinarios_borradores ,
                count(
                    case when status = "PA" and tipo_pedido_id = "PXT" then 1 else null end
                ) as extraordinarios_por_aprobar ,
                count(
                    case when status = "BRA" and tipo_pedido_id = "PXT" then 1 else null end
                ) as extraordinarios_aprobados,

                count(
                    case when status = "BR" then 1 else null end
                ) as borradores,
                count(
                    case when status = "SD" then 1 else null end
                ) as solicitados,
                count(
                    case when status = "ET" then 1 else null end
                ) as en_transito,
                count(
                    case when status = "PS" then 1 else null end
                ) as por_surtir,
                count(
                    case when status = "FI" then 1 else null end
                ) as finalizados,
                count(
                    case when status = "EX" then 1 else null end
                ) as expirados,
                count(
                    case when status = "EX-CA" then 1 else null end
                ) as expirados_cancelados,
                count(
                    case when status = "EF" then 1 else null end
                ) as farmacia,
                count(
                    case when tipo_pedido_id = "PALT" then 1 else null end
                ) as alternos,
                (
                    select count(id) from actas where clues = "'.$clues.'"
                ) as actas
    
                '
            //))->where('almacen_solicitante',$almacen->id)->where('clues',$almacen->clues)->first();
            ))->where('clues',$clues); //->first();
                
            //Harima: Filtro para diferentes tipos de almacenes, solo los almacenes principales pueden ver pedidos a farmcias subrogadas
            if($almacen->tipo_almacen == 'ALMPAL'){
                $almacenes = Almacen::where('subrogado',1)->where('nivel_almacen',1)->get();
                $almacenes = $almacenes->lists('id');
                $almacenes[] = $almacen->id;
                $pedidos = $pedidos->whereIn('almacen_solicitante',$almacenes);
            }else{
                //Harima: Los demas almacenes solo veran los pedidos que ellos hayan hecho
                $pedidos = $pedidos->where('almacen_solicitante',$almacen->id);
            }

            $presupuesto_ejercicio = null;
            if(isset($parametros['presupuesto'])){
                if($parametros['presupuesto']){
                    if(isset($parametros["nueva_version"]) && $parametros["nueva_version"]){
                        $presupuesto_ejercicio = PresupuestoEjercicio::find($parametros['presupuesto']);
                        $pedidos = $pedidos->where('presupuesto_ejercicio_id',$parametros['presupuesto']);
                     } else{
                        $pedidos = $pedidos->where('presupuesto_id',$parametros['presupuesto']== false);
                     }
                }
            }else{
                if(isset($parametros["nueva_version"]) && $parametros["nueva_version"]){
                    $presupuesto_ejercicio = PresupuestoEjercicio::where('activo',1)->first();
                    $pedidos = $pedidos->where('presupuesto_ejercicio_id',$presupuesto_ejercicio->id);
                } else {
                    $presupuesto = Presupuesto::where('activo',1)->first();
                    $pedidos = $pedidos->where('presupuesto_id',$presupuesto->id);
                }
                
            }
            /*
            if(isset($parametros['presupuesto'])){
                if($parametros['presupuesto']){
                    $pedidos = $pedidos->where('presupuesto_id',$parametros['presupuesto']);
                }
            }else{
                $presupuesto = Presupuesto::where('activo',1)->first();
                $pedidos = $pedidos->where('presupuesto_id',$presupuesto->id);
            }*/
    
            $pedidos = $pedidos->orWhere(function($query)use($almacen){
                $query->where('almacen_solicitante',$almacen->id)->where('tipo_pedido_id','PEA')->where('status','!=','BR');
            })->first();
            if($presupuesto_ejercicio){
                $pedidos_ordinarios = PedidoOrdinario::where('presupuesto_ejercicio_id',$presupuesto_ejercicio->id)->get();
                $pedidos_ordinarios_ids = [];
                foreach($pedidos_ordinarios as $item){
                    $pedidos_ordinarios_ids[] = $item['id'];
                }

                $bandeja = PedidoOrdinarioUnidadMedica::select(DB::raw('count(*) as bandeja'))->where('clues',$clues)->whereIn("pedido_ordinario_id",$pedidos_ordinarios_ids)->whereNull('pedido_id')->first();            
                $pedidos->ordinarios_bandeja = $bandeja->bandeja;
            } else {
                $pedidos->ordinarios_bandeja = 0;
            }
           

            return Response::json($pedidos,200);
        }catch(\Exception $e) {
            return Response::json(['error' => $e->getMessage(), 'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        }
    }

    public function presupuestos(Request $request){
        $presupuestos = PresupuestoEjercicio::all();
       
        return Response::json([ 'data' => $presupuestos],200);
    }

    public function presupuestoEjercicioUnidadMedica(Request $request){
        try{
            $parametros = Input::all();
            $clues = $request->get('clues');
            $presupuesto_id = "0";
            if(isset($parametros['presupuesto'])){
                $presupuesto = PresupuestoEjercicio::find($parametros['presupuesto']);
                if($presupuesto){
                    $presupuesto_id = $presupuesto->id;
                }
            } else {
                $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                if($presupuesto){
                    $presupuesto_id = $presupuesto->id;
                }
            }

           // $almacen = Almacen::find($request->get('almacen_id'));

            
            $presupuesto_unidad_medica = PresupuestoUnidadMedica::select('clues',
                                                DB::raw('causes_autorizado as insumos_autorizado'),
                                                DB::raw('causes_modificado as insumos_modificado'),
                                                DB::raw('causes_comprometido as insumos_comprometido'),
                                                DB::raw('causes_devengado as insumos_devengado'),
                                                DB::raw('causes_disponible as insumos_disponible'),
                                                'no_causes_autorizado',
                                                'no_causes_modificado',
                                                'no_causes_comprometido',
                                                'no_causes_devengado',
                                                'no_causes_disponible')
                                                ->where('clues',$clues)
                                                ->where('presupuesto_id', $presupuesto_id )->first();


            return Response::json([ 'data' => $presupuesto_unidad_medica, 'presupuesto'=>$presupuesto],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
    public function presupuestoPedidoOrdinarioUnidadMedica(Request $request, $id){
        try{

            $pedido_ordinario = PedidoOrdinarioUnidadMedica::find($id);
            

            if($pedido_ordinario){
                $clues = $request->get('clues');
                if($pedido_ordinario->clues == $clues ){
                    $pedido_ordinario->pedidoOrdinario;
                    return Response::json([ 'data' => $pedido_ordinario],200);
                } else {
                    throw new \Exception("Pedido no existe");
                }
            } else {
              throw new \Exception("Pedido no existe");
            }

        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function pedidosOrdinariosUnidadMedica(Request $request){
        try{
            $parametros = Input::all();
            $clues = $request->get('clues');
            $presupuesto_id = "0";
            if(isset($parametros['presupuesto'])){
                $presupuesto = PresupuestoEjercicio::find($parametros['presupuesto']);
                if($presupuesto){
                    $presupuesto_id = $presupuesto->id;
                }
            } else {
                $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                if($presupuesto){
                    $presupuesto_id = $presupuesto->id;
                }
            }
            $pedidos = PedidoOrdinarioUnidadMedica::select('pedidos_ordinarios_unidades_medicas.*',
                'pedidos_ordinarios.descripcion',
                'pedidos_ordinarios.fecha',
                'pedidos_ordinarios.fecha_expiracion',
                DB::raw('timestampdiff(DAY, current_timestamp(), pedidos_ordinarios.fecha_expiracion) as expira_en_dias'),
                DB::raw('IF(timestampdiff(DAY, current_timestamp(), pedidos_ordinarios.fecha_expiracion) = 0, 
                        timestampdiff(HOUR, current_timestamp(), pedidos_ordinarios.fecha_expiracion) ,
                        IF(current_date() = date( pedidos_ordinarios.fecha_expiracion),
                            timestampdiff(HOUR, current_timestamp(), pedidos_ordinarios.fecha_expiracion) - timestampdiff(HOUR, current_timestamp(), date(pedidos_ordinarios.fecha_expiracion)),
                            timestampdiff(HOUR, current_timestamp(), pedidos_ordinarios.fecha_expiracion) - (24 * timestampdiff(DAY, current_timestamp(), pedidos_ordinarios.fecha_expiracion))
                        )
                    ) as expira_en_horas'),
                DB::raw('timestampdiff(MINUTE, current_timestamp(), pedidos_ordinarios.fecha_expiracion) as expira_en_minutos')
                    )
            ->leftJoin('pedidos_ordinarios','pedidos_ordinarios_unidades_medicas.pedido_ordinario_id','=','pedidos_ordinarios.id')
            ->where('pedidos_ordinarios_unidades_medicas.clues',$clues)
            ->whereNull('pedidos_ordinarios_unidades_medicas.pedido_id')
            ->where('pedidos_ordinarios.presupuesto_ejercicio_id',$presupuesto_id)->orderBy('fecha', 'desc')->orderBy('id','desc')->get();
           // $almacen = Almacen::find($request->get('almacen_id'));

            /*
            $presupuesto_unidad_medica = PresupuestoUnidadMedica::select('clues',
                                                DB::raw('causes_autorizado as insumos_autorizado'),
                                                DB::raw('causes_modificado as insumos_modificado'),
                                                DB::raw('causes_comprometido as insumos_comprometido'),
                                                DB::raw('causes_devengado as insumos_devengado'),
                                                DB::raw('causes_disponible as insumos_disponible'),
                                                'no_causes_autorizado',
                                                'no_causes_modificado',
                                                'no_causes_comprometido',
                                                'no_causes_devengado',
                                                'no_causes_disponible')
                                                ->where('clues',$clues)
                                                ->where('presupuesto_id', $presupuesto_id )->first();*/


            return Response::json([ 'data' => $pedidos],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }
}