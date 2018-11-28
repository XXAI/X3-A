<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\PresupuestoEjercicio, App\Models\PresupuestoUnidadMedica,  App\Models\UnidadMedica, App\Models\Jurisdiccion;
use App\Models\PresupuestoMovimientoEjercicio, App\Models\PresupuestoMovimientoUnidadMedica;

use App\Models\PedidoOrdinario, App\Models\PedidoOrdinarioUnidadMedica;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class PedidosOrdinariosController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   $parametros = Input::only('q','page','per_page');
        
        $items =  PedidoOrdinario::select('pedidos_ordinarios.*');
                    //->leftJoin('proveedores','contratos.proveedor_id','=','proveedores.id');        

        if ($parametros['q']) {
            
            $items = $items->where('pedidos_ordinarios.descripcion','LIKE',"%".$parametros['q']."%")->orWhere('pedidos_ordinarios.id','LIKE',"%".$parametros['q']."%");
       }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
    }

    public function presupuesto(Request $request){
        $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                

        if($presupuesto){            
            $presupuesto->presupuesto_unidades_medicas = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->with('unidadMedica')->orderBy('clues','asc')->get();
        } else {
            $presupuesto = [];
        }
        
        return Response::json([ 'data' => $presupuesto],200);
    }

    public function store(Request $request){
        $mensajes = [            
            'required'      => "required",
            'numeric'       => "numeric",
            'integer'       => "integer",
            'unique'        => "unique",
            'min'           => "min"
        ];

        $reglas = [
            //'id'            => 'required|unique:usuarios,id,'.$id,            
            'descripcion'           => 'required',
            'fecha'        => 'required|date',
            'fecha_expiracion'     => 'required|date',
            'pedidos_ordinarios_unidades_medicas' => 'array',
            'pedidos_ordinarios_unidades_medicas.*.causes_autorizado' => 'required|numeric|min:0',
            'pedidos_ordinarios_unidades_medicas.*.no_causes_autorizado' => 'required|numeric|min:0'
        ];

        $inputs = Input::only('descripcion','fecha',"fecha_expiracion","pedidos_ordinarios_unidades_medicas");

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            $errors =  $v->errors();           
            return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try{
            $presupuesto = PresupuestoEjercicio::where('activo',1)->first();

            if($presupuesto){
                $inputs['fecha_expiracion'] =  date("Y-m-d H:i:s", strtotime("2017-01-10T18:00:00.000Z"));
                $pedido_ordinario = PedidoOrdinario::create($inputs);
                $items = [];
    
                $chuchi = [];
                $error = false;
                $errors = [];
                $i = 0;
                foreach($inputs['pedidos_ordinarios_unidades_medicas'] as $item){
                    
                    $items[] = new PedidoOrdinarioUnidadMedica([
                        "pedido_ordinario_id" => $pedido_ordinario->id, 
                        "clues" =>$item["clues"],
                        "causes_autorizado" => $item["causes_autorizado"],
                        "causes_modificado" => $item["causes_autorizado"],
                        "no_causes_autorizado" => $item["no_causes_autorizado"],
                        "no_causes_modificado" => $item["no_causes_autorizado"]
                    ]);
                    $presupuesto_unidad_medica = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->where("clues",$item["clues"])->first();

                    if($presupuesto_unidad_medica){
                     
                        if($item["causes_autorizado"] > 0){
                            $presupuesto_unidad_medica->causes_disponible = $presupuesto_unidad_medica->causes_disponible - $item["causes_autorizado"];
                            $presupuesto_unidad_medica->causes_comprometido = $presupuesto_unidad_medica->causes_comprometido + $item["causes_autorizado"];
                        }
        
                        if($item["no_causes_autorizado"] > 0){
                            $presupuesto_unidad_medica->no_causes_disponible = $presupuesto_unidad_medica->no_causes_disponible - $item["no_causes_autorizado"];
                            $presupuesto_unidad_medica->no_causes_comprometido = $presupuesto_unidad_medica->no_causes_comprometido + $item["no_causes_autorizado"];
                        }
                        $presupuesto_unidad_medica->save();
                        $chuchi[] = $presupuesto_unidad_medica;
                    } else {
                        
                        $errors["pedidos_ordinarios_unidades_medicas.".$i.".causes_autorizado"] = ["budget"];
                        $errors["pedidos_ordinarios_unidades_medicas.".$i.".no_causes_autorizado"] = ["budget"];
                        $error = true;
                    }
                    
                    $i++;
                    
                }

                if(!$error){
                    $pedido_ordinario->pedidoOrdinarioUnidadesMedicas()->saveMany($items);
                    $pedido_ordinario->pedidoOrdinarioUnidadesMedicas;
                    //DB::rollback();
                    DB::commit();
                    return Response::json([ 'data' => $pedido_ordinario,'chuchi'=>$chuchi],200);
                }   else {
                    DB::rollback();
                    return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
                } 
                
            } else {
                DB::rollback();
            return Response::json(['error' => "No hay presupuesto"], HttpResponse::HTTP_CONFLICT);
            }

           

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
}