<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\PresupuestoEjercicio, App\Models\PresupuestoUnidadMedica,  App\Models\UnidadMedica, App\Models\Jurisdiccion;
use App\Models\PresupuestoMovimientoEjercicio, App\Models\PresupuestoMovimientoUnidadMedica;

use App\Models\PedidoOrdinario, App\Models\PedidoOrdinarioUnidadMedica, App\Models\Pedido, App\Models\PedidoInsumo;

use Carbon\Carbon;

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
    {   $parametros = Input::only('q','tipo','page','per_page');
        
        $items =  PedidoOrdinario::select(DB::raw('
                    pedidos_ordinarios.*,
                    (select count(id) from pedidos_ordinarios_unidades_medicas where pedidos_ordinarios_unidades_medicas.pedido_ordinario_id = pedidos_ordinarios.id) as total_unidades_medicas,
                    (select count(id) from pedidos_ordinarios_unidades_medicas where pedidos_ordinarios_unidades_medicas.pedido_ordinario_id = pedidos_ordinarios.id and pedidos_ordinarios_unidades_medicas.status="FI" ) as capturas_unidades_medicas
                '))->orderBy('id','desc');
                    //->leftJoin('proveedores','contratos.proveedor_id','=','proveedores.id');        

        if ($parametros['q']) {
           $items =  $items->where(function($query) use ($parametros){
                $query->where('pedidos_ordinarios.descripcion','LIKE',"%".$parametros['q']."%")->orWhere('pedidos_ordinarios.id','LIKE',"%".$parametros['q']."%");
            });
       }

       if ($parametros['tipo'] && $parametros["tipo"] != "") {            
            $items = $items->where('pedidos_ordinarios.tipo_pedido_id',$parametros["tipo"]);
        }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
    }

    public function solicitudes(Request $request)
    {   $parametros = Input::only('q','page','per_page');
        
        $items =  Pedido::select('*')->where('tipo_pedido_id','PXT')->where('status','PA')->orderBy('id','desc');
                    //->leftJoin('proveedores','contratos.proveedor_id','=','proveedores.id');        

        if ($parametros['q']) {
            $items = $items->where(function($query) use ($parametros){
                $query->where('pedidos.descripcion','LIKE',"%".$parametros['q']."%")->orWhere('pedidos.folio','LIKE',"%".$parametros['q']."%");
            });
           // $items = $items->where('pedidos.descripcion','LIKE',"%".$parametros['q']."%")->orWhere('pedidos.folio','LIKE',"%".$parametros['q']."%");
       }

       $items = $items->with('unidadMedica');
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

    public function modificarPresupuesto(Request $request, $id)
    {
        //AKIRA PENDIENTE
        $pedidoOrdinario = PedidoOrdinario::find($id);

        if(!$pedidoOrdinario){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $input = Input::all();
        DB::beginTransaction();
        try{
            if(isset($input["pedido_ordinario_unidad_medica"])){
                $poum = PedidoOrdinarioUnidadMedica::find($input["pedido_ordinario_unidad_medica"]["id"]);
                $presupuesto = PresupuestoUnidadMedica::where('presupuesto_id',$pedidoOrdinario->presupuesto_ejercicio_id)->where('clues',$poum->clues)->first();

                if(!$poum){
                    return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
                }

                if($poum->status == "CA"){
                    return Response::json(['error' => "No se puede modificar el presupuesto de un pedido cancelado."], HttpResponse::HTTP_NOT_FOUND);
                }

                if(!$presupuesto){
                    return Response::json(['error' => "No hay presupuesto."], HttpResponse::HTTP_NOT_FOUND);
                }

                

                if($poum->pedido_ordinario_id != $pedidoOrdinario->id){
                    return Response::json(['error' => "El pedido ordinario no corresponde al de la unidad médica."], HttpResponse::HTTP_NOT_FOUND);
                }

                $causes_diff = 0;
                $no_causes_diff = 0;
                $modificado = false;

                if($input["pedido_ordinario_unidad_medica"]["causes_nuevo"]  >= 0){
                    $diff = $input["pedido_ordinario_unidad_medica"]["causes_nuevo"] - $poum->causes_modificado;
                        
                    if( $diff > 0 && $presupuesto->causes_disponible >  $diff){
                        // Aumentar
                        $modificado = true;
                        $presupuesto->causes_disponible -= $diff;
                        $presupuesto->causes_comprometido += $diff;

                        $poum->causes_modificado = $input["pedido_ordinario_unidad_medica"]["causes_nuevo"];
                        $poum->causes_disponible =  $input["pedido_ordinario_unidad_medica"]["causes_nuevo"];
                    } else if( $diff < 0 ){

                        // Liberar
                        $modificado = true;
                        $presupuesto->causes_disponible += abs( $diff);
                        $presupuesto->causes_comprometido -= abs( $diff);

                        $poum->causes_modificado = $input["pedido_ordinario_unidad_medica"]["causes_nuevo"];
                        $poum->causes_disponible = $input["pedido_ordinario_unidad_medica"]["causes_nuevo"];
                    }
                } 


                if($input["pedido_ordinario_unidad_medica"]["no_causes_nuevo"] >= 0 ){
                    $diff = $input["pedido_ordinario_unidad_medica"]["no_causes_nuevo"] - $poum->no_causes_modificado;
                        
                    if( $diff > 0 && $presupuesto->no_causes_disponible >  $diff){
                        $modificado = true;
                        $presupuesto->no_causes_disponible -= $diff;
                        $presupuesto->no_causes_comprometido += $diff;

                        $poum->no_causes_modificado = $input["pedido_ordinario_unidad_medica"]["no_causes_nuevo"];
                        $poum->no_causes_disponible =  $input["pedido_ordinario_unidad_medica"]["no_causes_nuevo"];
                    } else if( $diff < 0){
                        $modificado = true;

                        $presupuesto->no_causes_disponible += abs( $diff);
                        $presupuesto->no_causes_comprometido -= abs( $diff);

                        $poum->no_causes_modificado = $input["pedido_ordinario_unidad_medica"]["no_causes_nuevo"];
                        $poum->no_causes_disponible = $input["pedido_ordinario_unidad_medica"]["no_causes_nuevo"];
                    }
                }

                if($modificado && $poum->status != "CA"){                            
                    $poum->save();
                    $presupuesto->save();
                }

                DB::commit();

                return Response::json(['data' => ['poum' => $poum, 'presupuesto' => $presupuesto ]], HttpResponse::HTTP_OK);
                // Aumentar presupuesto especifico

                
               
                
            } else {
                //Aumentar el presupuesto a todos
                $input_pedidos = $input["pedidos"];
                if(count($input_pedidos) == 0){
                    return Response::json(['error' => "No se seleccionaron pedidos."], HttpResponse::HTTP_CONFLICT);
                }
                $pedidosOrdinariosUnidadesMedicas = PedidoOrdinarioUnidadMedica::where('pedido_ordinario_id',$id)->whereIn('id',$input["pedidos"]);

                $aumentar = false;
                $liberar = false;

                if(isset($input["aumentar"]) && $input["aumentar"]== true){
                    $aumentar = true;
                    $pedidosOrdinariosUnidadesMedicas = $pedidosOrdinariosUnidadesMedicas->where(function ($query){
                        $query->where('status','!=','FI')->where('status','!=','CA');
                    })->where(function ($query){
                        $query->where('causes_capturado','>','causes_modificado')->orWhere('no_causes_capturado','>','no_causes_modificado');
                    })->get();
                    
                } else if(isset($input["liberar"]) && $input["liberar"]== true){
                    $liberar = true;
                    $pedidosOrdinariosUnidadesMedicas = $pedidosOrdinariosUnidadesMedicas->where(function ($query){
                        $query->where('status','=','FI');
                    })->where(function ($query){
                        $query->where('causes_disponible','>','0')->orWhere('no_causes_disponible','>','0');
                    })->get();
                } else {
                    return Response::json(['error' => "No se especificó el tipo de modificación."], HttpResponse::HTTP_CONFLICT);
                }
                $sin_modificar = [];
                $presupuestos = [];
                foreach($pedidosOrdinariosUnidadesMedicas as $poum){
                    $presupuesto = PresupuestoUnidadMedica::where('presupuesto_id',$pedidoOrdinario->presupuesto_ejercicio_id)->where('clues',$poum->clues)->first();
                    if($presupuesto){                       
                        
                        $modificado = false;
                        if($aumentar){
                            $causes_diff = $poum->causes_capturado - $poum->causes_modificado;
                            $no_causes_diff = $poum->no_causes_capturado - $poum->no_causes_modificado;

                            if( $causes_diff > 0 && $presupuesto->causes_disponible >  $causes_diff){
                                $modificado = true;
                                $presupuesto->causes_disponible -= $causes_diff;
                                $presupuesto->causes_comprometido += $causes_diff;

                                $poum->causes_modificado = $poum->causes_capturado;
                                $poum->causes_disponible =  $poum->causes_capturado;
                            }

                            if( $no_causes_diff > 0 && $presupuesto->no_causes_disponible >  $no_causes_diff){
                                $modificado = true;
                                $presupuesto->no_causes_disponible -= $no_causes_diff;
                                $presupuesto->no_causes_comprometido += $no_causes_diff;

                                $poum->no_causes_modificado = $poum->no_causes_capturado;
                                $poum->no_causes_disponible = $poum->no_causes_capturado;
                            }
                        } else {
                            if($poum->causes_disponible > 0){
                                $modificado = true;
                                $presupuesto->causes_disponible += $poum->causes_disponible;
                                $presupuesto->causes_comprometido -= $poum->causes_disponible;

                                $poum->causes_modificado -= $poum->causes_disponible;
                                $poum->causes_disponible =  0;
                            }

                            if($poum->no_causes_disponible > 0){
                                $modificado = true;
                                $presupuesto->no_causes_disponible += $poum->no_causes_disponible;
                                $presupuesto->no_causes_comprometido -= $poum->no_causes_disponible;

                                $poum->no_causes_modificado -= $poum->no_causes_disponible;
                                $poum->no_causes_disponible =  0;
                            }
                        }
                
                        //$modificado =false;
                        //$presupuestos [] = $presupuesto;

                        if($modificado && $poum->status != "CA"){                            
                            $poum->save();
                            $presupuesto->save();
                        } else {
                            $sin_modificar[] = $poum;
                        }
                    }                
                }
                DB::commit();
                return Response::json(['data' =>[ 'sin_modificar' => $sin_modificar/*, 'poum' =>$pedidosOrdinariosUnidadesMedicas, 'presupuestos' => $presupuestos*/ ]],HttpResponse::HTTP_OK);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 



       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    // Aprobar pedidos extraordinarios
    public function aprobarPresupuesto(Request $request, $id)
    {
        //AKIRA PENDIENTE
        $pedido = Pedido::find($id);

        if(!$pedido){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

    

        $input = Input::all();
        DB::beginTransaction();
        try{
            $presupuesto =  PresupuestoEjercicio::where('activo','1')->first();

            if(!$presupuesto){
                return Response::json(['error' => "No hay presupuesto."], HttpResponse::HTTP_NOT_FOUND);
            }

            $presupuesto_unidad_medica = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->where('clues',$pedido->clues)->first();

            if(!$presupuesto_unidad_medica){
                return Response::json(['error' => "No hay presupuesto para esta unidad médica."], HttpResponse::HTTP_NOT_FOUND);
            }

            if($pedido->status  != 'PA'){
                return Response::json(['error' => "El estatus del pedido no permite aprobar presupuesto."], HttpResponse::HTTP_CONFLICT);
            }

            

            $mensajes = [            
                'required'      => "required",
                'numeric'       => "numeric",
                'min'           => "min"
            ];
    
            $reglas = [
                'causes_autorizado' => 'required|numeric|min:0',
                'no_causes_autorizado' => 'required|numeric|min:0'
            ];
    
            $input = Input::only("causes_autorizado","no_causes_autorizado");
    
            $v = Validator::make($input, $reglas, $mensajes);
    
            if ($v->fails()) {
                $errors =  $v->errors();           
                return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
            }



            
            $errors = [];
            $error = false;
            $causes_autorizado = 0;
            $pedido_extraordinario = PedidoOrdinario::create([
                "presupuesto_ejercicio_id" => $presupuesto->id,
                "tipo_pedido_id" => "PXT",
                "descripcion" => $pedido->descripcion,
                "fecha" => $pedido->fecha
            ]);


            $insumos = PedidoInsumo::select(DB::raw('insumos_medicos.clave, SUM(pedidos_insumos.monto_solicitado) as monto_solicitado, SUM(pedidos_insumos.cantidad_solicitada) as cantidad_solicitada, insumos_medicos.tipo, insumos_medicos.es_causes'))->where('pedido_id', $id)->leftJoin('insumos_medicos','insumos_medicos.clave','=','pedidos_insumos.insumo_medico_clave')->groupBy('insumos_medicos.clave')->get();

            $capturado_causes = 0;
            $capturado_no_causes = 0;      

            foreach($insumos as $insumo){
                if($insumo->tipo == "MC"){
                    $capturado_causes += $insumo->monto_solicitado * 1.16;
                } else {
                    if($insumo->es_causes){
                        $capturado_causes += $insumo->monto_solicitado;
                    } else {
                        $capturado_no_causes += $insumo->monto_solicitado;
                    }
                }
            }

            $pedido_extraordinario_unidad_medica_obj = [
                "pedido_ordinario_id" => $pedido_extraordinario->id, 
                "pedido_id" => $pedido->id,
                "clues" =>$pedido->clues,
                "status" => "EP",
                "causes_autorizado" => 0,
                "causes_modificado" => 0,
                "causes_disponible" => 0,
                "causes_capturado" => $capturado_causes,
                "no_causes_autorizado" => 0,
                "no_causes_modificado" => 0,
                "no_causes_disponible" => 0,
                "no_causes_capturado" => $capturado_no_causes,
            ];
                
            

            if($input["causes_autorizado"]  >= 0){
                $diff = $presupuesto_unidad_medica->causes_disponible - $input["causes_autorizado"];

                if($diff < 0){ 
                    $error = true;
                    $errors["causes_autorizado"] = ["budget"];
                } else {
                    $pedido_extraordinario_unidad_medica_obj["causes_autorizado"] = $input["causes_autorizado"];
                    $pedido_extraordinario_unidad_medica_obj["causes_modificado"] = $input["causes_autorizado"];
                    $pedido_extraordinario_unidad_medica_obj["causes_disponible"] = $input["causes_autorizado"];
                    $presupuesto_unidad_medica->causes_comprometido += $input["causes_autorizado"];
                    $presupuesto_unidad_medica->causes_disponible -= $input["causes_autorizado"];
                }
            } 

            if($input["no_causes_autorizado"]  >= 0){
                $diff = $presupuesto_unidad_medica->no_causes_disponible - $input["no_causes_autorizado"];

                if($diff < 0){ 
                    $error = true;
                    $errors["no_causes_autorizado"] = ["budget"];
                } else {
                    $pedido_extraordinario_unidad_medica_obj["no_causes_autorizado"] = $input["no_causes_autorizado"];
                    $pedido_extraordinario_unidad_medica_obj["no_causes_modificado"] = $input["no_causes_autorizado"];
                    $pedido_extraordinario_unidad_medica_obj["no_causes_disponible"] = $input["no_causes_autorizado"];
                    $presupuesto_unidad_medica->no_causes_comprometido += $input["no_causes_autorizado"];
                    $presupuesto_unidad_medica->no_causes_disponible -= $input["no_causes_autorizado"];
                }
            } 

            if(!$error){  
                $pedido_extraordinario_unidad_medica =  PedidoOrdinarioUnidadMedica::create($pedido_extraordinario_unidad_medica_obj);      
                $presupuesto_unidad_medica->save();
                $pedido->status = "BRA";
                $pedido->presupuesto_ejercicio_id =  $presupuesto->id;
                $pedido->save();
                DB::commit();
                //DB::rollback();
                return Response::json(['data' => $pedido], HttpResponse::HTTP_OK);
            } else {
                DB::rollback();
                return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
            }
                
        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }
    public function verPedido(Request $request, $id){
        $pedido = Pedido::find($id);

        if($pedido){
            return Response::json(['data' =>$pedido],HttpResponse::HTTP_OK);
        } else {
            return Response::json(['error' => "Pedido no existe"],404);
        }
    }


    public function regresarACaptura(Request $request, $id)
    {
        $pedidoOrdinario = PedidoOrdinario::find($id);

        if(!$pedidoOrdinario){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $input = Input::all();
        DB::beginTransaction();
        try{
            if(isset($input['pedidos'])){
                
                $pedidosOrdinariosUnidadesMedicas = PedidoOrdinarioUnidadMedica::whereIn('id',$input['pedidos'])->get();
                $sin_modificar = [];
                
                foreach($pedidosOrdinariosUnidadesMedicas as $poum){

                   
                    $modificado = false;
                       
                    if($poum->status == "FI" ){

                        $modificar = false;
                        if($poum->pedido_id){
                            
                            $pedido = $poum->pedido;
                            
                            if($pedido->status == "PS" || $pedido->status == "EX"){
                                $modificar = true;
                                $poum->causes_disponible += $poum->causes_comprometido;                                  
                                $poum->causes_comprometido = 0;
                                
                                $poum->no_causes_disponible += $poum->no_causes_comprometido;                                  
                                $poum->no_causes_comprometido = 0; 

                                $ahora = strtotime("now");                
                                $expiracion = strtotime($poum->fecha_expiracion);
                            
                                if($ahora > $expiracion){                                        
                                    $poum->status = "EX";
                                } else {
                                    $poum->status = "EP";
                                }       
                                $poum->save();

                                
                                $pedido->status = "BR";    
                                $pedido->save();
                            } 
                        }
                    }
                    
                    if(!$modificar){
                        $sin_modificar[] = $poum;
                    }    

                    
                }
                DB::commit();
                return Response::json(['data' =>[ 'sin_modificar' => $sin_modificar ]],HttpResponse::HTTP_OK);
            } else {
                throw new \Exception("No se especificaron los pedidos a cancelar");
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 



       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    public function cancelar(Request $request, $id)
    {
        $pedidoOrdinario = PedidoOrdinario::find($id);

        if(!$pedidoOrdinario){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $input = Input::all();
        DB::beginTransaction();
        try{
            if(isset($input['pedidos'])){
                
                $pedidosOrdinariosUnidadesMedicas = PedidoOrdinarioUnidadMedica::whereIn('id',$input['pedidos'])->get();
                $sin_modificar = [];
                $presupuestos = [];
                
                foreach($pedidosOrdinariosUnidadesMedicas as $poum){

                    $presupuesto = PresupuestoUnidadMedica::where('presupuesto_id',$pedidoOrdinario->presupuesto_ejercicio_id)->where('clues',$poum->clues)->first();                 
                    $modificado = false;

                    if($presupuesto ){                          
                        if($poum->status != "CA" ){
                            $causes_devolver = $poum->causes_modificado - $poum->causes_devengado;
                            $no_causes_devolver = $poum->no_causes_modificado - $poum->causes_devengado;

                            $presupuesto->causes_disponible += $causes_devolver;
                            $presupuesto->causes_comprometido -= $causes_devolver;

                            $presupuesto->no_causes_disponible += $no_causes_devolver;
                            $presupuesto->no_causes_comprometido -= $no_causes_devolver;

                            $modificar = true;
                            if($poum->pedido_id){
                                $pedido = $poum->pedido;
                                
                                if($pedido->status != "FI"){
                                    $pedido->status = "EX-CA";
                                    $pedido->fecha_cancelacion = Carbon::now();
                                    $pedido->save();
                                } else {
                                    $modificar = false;
                                }
                            }
                        }
                        
                        if($modificar){
                            $poum->status = "CA";
                            $presupuestos[] = $presupuesto;
                            $presupuesto->save();
                            $poum->save();
                        } else {
                            $sin_modificar[] = $poum;
                        }    

                    }  
                }
                DB::commit();
                return Response::json(['data' =>[ 'sin_modificar' => $sin_modificar, 'presupuestos' => $presupuestos ]],HttpResponse::HTTP_OK);
            } else {
                throw new \Exception("No se especificaron los pedidos a cancelar");
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 



       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    public function anularCancelacion(Request $request, $id)
    {
        $pedidoOrdinario = PedidoOrdinario::find($id);

        if(!$pedidoOrdinario){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $input = Input::all();
        DB::beginTransaction();
        try{
            if(isset($input['pedidos'])){
                
                $pedidosOrdinariosUnidadesMedicas = PedidoOrdinarioUnidadMedica::whereIn('id',$input['pedidos'])->get();
                $sin_modificar = [];
                $presupuestos = [];
                
                foreach($pedidosOrdinariosUnidadesMedicas as $poum){
                    $presupuesto = PresupuestoUnidadMedica::where('presupuesto_id',$pedidoOrdinario->presupuesto_ejercicio_id)->where('clues',$poum->clues)->first();                 
                    $modificado = false;

                    if($presupuesto ){                          
                        
                        if($poum->status == "CA" ){

                            $causes_recuperar = $poum->causes_modificado - $poum->causes_devengado;
                            $no_causes_recuperar = $poum->no_causes_modificado - $poum->causes_devengado;

                            $presupuesto->causes_disponible -= $causes_recuperar;
                            $presupuesto->causes_comprometido += $causes_recuperar;

                            $presupuesto->no_causes_disponible -= $no_causes_recuperar;
                            $presupuesto->no_causes_comprometido += $no_causes_recuperar;

                            $modificar = true;
                            if($poum->pedido_id){
                                $pedido = $poum->pedido;                             
                                
                                if($pedido->folio == null){
                                    $pedido->status = "BR";
                                    $poum->status = "EP";
                                } else {

                                    $poum->status = "FI";

                                    $ahora = strtotime("now");                
                                    $expiracion = strtotime($pedido->fecha_expiracion);
                                
                                    if($ahora > $expiracion){                                        
                                        $pedido->status = "EX";
                                    } else {
                                        $pedido->status = "PS";
                                    }                                    
                                }
                               
                                $pedido->fecha_cancelacion = null;                              


                                if($pedido->status != "FI"){
                                    
                                    $pedido->save();
                                } else {
                                    $modificar = false;
                                }
                            } else {
                                //Checar expiracion;
                                $ahora = strtotime("now");                
                                $expiracion = strtotime($poum->fecha_expiracion);

                                if($ahora > $expiracion){
                                    $poum->status = "EX";
                                } else {
                                    $poum->status = "S/P";                                   
                                }                                
                            }
                        }
                        if($modificar){
                            $presupuestos[] = $presupuesto;
                            $presupuesto->save();
                            $poum->save();
                        } else {
                            $sin_modificar[] = $poum;
                        }    

                    }  
                }
                DB::commit();
                return Response::json(['data' =>[ 'sin_modificar' => $sin_modificar, 'presupuestos' => $presupuestos ]],HttpResponse::HTTP_OK);
            } else {
                throw new \Exception("No se especificaron los pedidos a cancelar");
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 



       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    public function show(Request $request, $id)
    {
        $object = PedidoOrdinario::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica","presupuesto.presupuestoUnidadesMedicas");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    public function verSolicitud(Request $request, $id)
    {
        $object = Pedido::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $insumos = PedidoInsumo::select(DB::raw('insumos_medicos.clave, SUM(pedidos_insumos.monto_solicitado) as monto_solicitado, SUM(pedidos_insumos.cantidad_solicitada) as cantidad_solicitada, insumos_medicos.tipo, insumos_medicos.es_causes'))->where('pedido_id', $id)->leftJoin('insumos_medicos','insumos_medicos.clave','=','pedidos_insumos.insumo_medico_clave')->groupBy('insumos_medicos.clave')->get();

        $total_insumos_causes = 0;
        $total_insumos_no_causes = 0;
        $total_monto_causes = 0;
        $total_monto_no_causes = 0;

        $total_claves_causes = 0;
        $total_claves_no_causes = 0;        

        foreach($insumos as $insumo){
            if($insumo->tipo == "MC"){
                $total_monto_causes += $insumo->monto_solicitado * 1.16;
                $total_insumos_causes += $insumo->cantidad_solicitada;
                $total_claves_causes++;
            } else {
                if($insumo->es_causes){
                    $total_monto_causes += $insumo->monto_solicitado;
                    $total_insumos_causes += $insumo->cantidad_solicitada;
                    $total_claves_causes++;
                } else {
                    $total_monto_no_causes += $insumo->monto_solicitado;
                    $total_insumos_no_causes += $insumo->cantidad_solicitada;
                    $total_claves_no_causes++;
                }
            }
        }

        $object->total_insumos_causes = $total_insumos_causes;
        $object->total_insumos_no_causes = $total_insumos_no_causes;

        $object->total_monto_causes = $total_monto_causes;
        $object->total_monto_no_causes = $total_monto_no_causes;

        $object->total_claves_causes = $total_claves_causes;
        $object->total_claves_no_causes = $total_claves_no_causes;
        $presupuesto = PresupuestoEjercicio::where('activo', 1)->first();
        if($presupuesto){
            $pum = PresupuestoUnidadMedica::where('clues', $object->clues)->where('presupuesto_id', $presupuesto->id)->first();
            $object->presupuesto = $pum;
        } else {
            $object->presupuesto = null;
        }
        $object->unidadMedica;
        

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
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
                
                $inputs["tipo_pedido_id"] = "PO";
                $inputs['fecha_expiracion'] =  date("Y-m-d H:i:s", strtotime($inputs["fecha_expiracion"]));
               
                $inputs['presupuesto_ejercicio_id'] = $presupuesto->id;
                $pedido_ordinario = PedidoOrdinario::create($inputs);

                $items = [];
    
                $error = false;
                $errors = [];
                $i = 0;
                foreach($inputs['pedidos_ordinarios_unidades_medicas'] as $item){
                    
                    $items[] = new PedidoOrdinarioUnidadMedica([
                        "pedido_ordinario_id" => $pedido_ordinario->id, 
                        "clues" =>$item["clues"],
                        "causes_autorizado" => $item["causes_autorizado"],
                        "causes_modificado" => $item["causes_autorizado"],
                        "causes_disponible" => $item["causes_autorizado"],
                        "no_causes_autorizado" => $item["no_causes_autorizado"],
                        "no_causes_modificado" => $item["no_causes_autorizado"],
                        "no_causes_disponible" => $item["no_causes_autorizado"],
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
                    } else {
                        
                        $errors["pedidos_ordinarios_unidades_medicas.".$i.".causes_autorizado"] = ["budget"];
                        $errors["pedidos_ordinarios_unidades_medicas.".$i.".no_causes_autorizado"] = ["budget"];
                        $error = true;
                    }
                    
                    $i++;
                    
                }

                if(!$error){
                    $pedido_ordinario->pedidosOrdinariosUnidadesMedicas()->saveMany($items);
                    $pedido_ordinario->pedidosOrdinariosUnidadesMedicas;
                    //DB::rollback();
                    DB::commit();
                    return Response::json([ 'data' => $pedido_ordinario],200);
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

    public function update(Request $request, $id){
        $mensajes = [            
            'required'      => "required",
            'required_if'      => "required",
            'numeric'       => "numeric",
            'integer'       => "integer",
            'unique'        => "unique",
            'min'           => "min",
            'date'           => "date"
        ];

        $reglas = [
            //'id'            => 'required|unique:usuarios,id,'.$id,            
            'descripcion'           => 'required',
            'fecha'        => 'required|date',
            'fecha_expiracion'     => 'requiredIf:tipo_pedido_id,PO|date'
        ];

        $input = Input::only('descripcion','tipo_pedido_id','fecha',"fecha_expiracion","pedidos_ordinarios_unidades_medicas");

        $pedido_ordinario = PedidoOrdinario::find($id);

        if(!$pedido_ordinario){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }


        $v = Validator::make($input, $reglas, $mensajes);

        if ($v->fails()) {
            $errors =  $v->errors();           
            return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try{

           
                
                
                $input['fecha_expiracion'] =  date("Y-m-d H:i:s", strtotime($input["fecha_expiracion"]));


                $cambiarDescripcion = false;
                $cambiarFechaPedido = false;
                $cambiarFechaExpiracion = false;
                
                if($pedido_ordinario->descripcion  != $input['descripcion']){
                    $cambiarDescripcion = true;
                }

                


                $pedido_ordinario->descripcion  = $input['descripcion'];
                
                $fecha_anterior = $pedido_ordinario->fecha;
                $pedido_ordinario->fecha  = $input['fecha'];

                $fecha_expiracion_anterior =  $pedido_ordinario->fecha_expiracion;
                $pedido_ordinario->fecha_expiracion  = $input['fecha_expiracion'];
               
               
                $pedido_ordinario->save();

                if($pedido_ordinario->fecha  != $fecha_anterior){
                    $cambiarFechaPedido = true;
                }

                if($pedido_ordinario->fecha_expiracion  != $fecha_expiracion_anterior && $pedido_ordinario->tipo_pedido_id == 'PO'){
                    $cambiarFechaExpiracion = true;
                }

                // Cambiar estatus de pedidos ordinarios unidades medicas
                $ahora = strtotime("now");
                 
                $expiracion = strtotime($pedido_ordinario->fecha_expiracion);


                $expirado = false;
                if($ahora > $expiracion &&  $pedido_ordinario->tipo_pedido_id == 'PO'){
                    $expirado = true;
                }

                if($expirado || $cambiarDescripcion || $cambiarFechaPedido || $cambiarFechaExpiracion){
                    $poum = $pedido_ordinario->pedidosOrdinariosUnidadesMedicas;
                    foreach($poum as $item){

                        if($expirado && $item->status != "FI" && $item->status != "CA"){
                            $item->status = "EX";
                            $item->save();
                        } else  if($cambiarFechaExpiracion && !$expirado && $item->status != "FI" && $item->status != "CA"){
                            if($item->pedido_id != null){
                                $item->status = "EP";
                            } else {
                                $item->status = "S/P";
                            }
                            $item->save();
                        }

                        if($item->pedido_id != null && ($cambiarFechaPedido || $cambiarDescripcion)){
                            $pedido = $item->pedido;
                           
                            if($cambiarDescripcion){
                                $pedido->descripcion = $pedido_ordinario->descripcion;
                            }

                            if($cambiarFechaPedido){
                                $pedido->fecha = $pedido_ordinario->fecha;
                                if($pedido_ordinario->tipo_pedido_id == 'PO'){
                                    $pedido->fecha_expiracion = strtotime("+20 days", strtotime($pedido->fecha));
                                }
                            }

                            if($cambiarFechaPedido || $cambiarDescripcion){
                                $pedido->save();
                            }
                            
                        } 
                    }
                }
               

                DB::commit();
                return Response::json([ 'data' => $pedido_ordinario, "fecha" =>$pedido_ordinario->fecha, "cambiar_fecha" =>$cambiarFechaPedido],200);
                
            

           

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function descargarFormato(Request $request){

        $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                

        if($presupuesto){            
            $presupuesto->presupuesto_unidades_medicas = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->with('unidadMedica')->orderBy('clues','asc')->get();
        } else {
            return Response::json(['error' => "No hay presupuesto"], HttpResponse::HTTP_CONFLICT);
        }
        
      //  $unidades_medicas = UnidadMedica::where('activa',1)->orderBy('jurisdiccion_id','asc','clues','asc')->get();


        
        Excel::create("Formato de carga de pedidos ordinarios", function($excel) use($presupuesto) {


            $excel->sheet('Presupuesto '.$presupuesto->ejercicio, function($sheet) use($presupuesto)  {
                $sheet->setAutoSize(true);
                $sheet->row(1, array(
                    'Clues',
                    'Tipo',
                    'Nombre',
                    'Jurisdicción',
                    '$ CAUSES',
                    '$ CAUSES Disponible',
                    '$ NO CAUSES',                    
                    '$ NO CAUSES Disponible'
                ));
                $sheet->cells("A1:F1", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                });

                $factor_meses = $presupuesto->factor_meses | 0;
                $c = 0;
                foreach($presupuesto->presupuesto_unidades_medicas as $item){
                    $unidad_medica = $item->unidadMedica;
                    $causes=  $item->causes_modificado / $factor_meses;
                    if($causes > $item->causes_disponible){
                      $causes =  $item->causes_disponible;
                    }
        
                    $no_causes  = $item->no_causes_modificado / $factor_meses;
                    if($no_causes > $item->no_causes_disponible){
                      $no_causes =   $item->no_causes_disponible;
                    }

                    if($no_causes > 0 || $causes > 0){
                        $unidad_medica->jurisdiccion;
                        $sheet->appendRow(array(
                            $item->clues,
                            $unidad_medica->tipo,
                            $unidad_medica->nombre,
                            $unidad_medica->jurisdiccion? $unidad_medica->jurisdiccion->numero." - ".$unidad_medica->jurisdiccion->nombre :  "",
                            number_format($causes,2),                            
                            $item->causes_disponible,
                            number_format($no_causes,2),
                            $item->no_causes_disponible
                        )); 
                        $c++;
                    }

                    
                }
                
                if($c > 0){
                    $c++;
                    $sheet->setColumnFormat(array(
                        "E2:H$c" => '"$" #,##0.00_-',
                    ));
                }
                
                //$sheet->getProtection()->setSheet(true);
                //$sheet->getStyle("A1:E$c")->getProtection()->setLocked(\PHPExcel_Style_Protection::PROTECTION_UNPROTECTED);
                //$sheet->getStyle("G1:G$c")->getProtection()->setLocked(\PHPExcel_Style_Protection::PROTECTION_UNPROTECTED);
                $sheet->setAutoFilter('A1:H1');
                    $sheet->protectCells("F2:F$c", 'PHPExcel');
                    $sheet->protectCells("H2:H$c", 'PHPExcel');
                
                $sheet->cells("F1:F$c", function($cells) {
                   $cells->setBackground('#DDDDDD');
                });

                $sheet->cells("H1:H$c", function($cells) {
                    $cells->setBackground('#DDDDDD');
                });

                
            });
            


           


         })->export('xls');
    }

    public function cargarExcel(Request $request){
        ini_set('memory_limit', '-1');

        try{
            if ($request->hasFile('archivo')){
				$file = $request->file('archivo');

				if ($file->isValid()) {
                    $path = $file->getRealPath();

                    $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                
                    $unidades_medicas_con_presupuesto = [];

                    if($presupuesto){            
                        $unidades_medicas_con_presupuesto = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->with('unidadMedica')->orderBy('clues','asc')->get();
                    } else {
                        return Response::json(['error' => "No hay presupuesto"], HttpResponse::HTTP_CONFLICT);
                    }

                    $unidades_medicas = [];
                    $total_causes = 0;
                    $total_no_causes = 0;
                    $con_errores = false;
                    Excel::load($file, function($reader) use (&$unidades_medicas,&$unidades_medicas_con_presupuesto, &$total_causes, &$total_no_causes, &$con_errores) {
                        $objExcel = $reader->getExcel();
                        $sheet = $objExcel->getSheet(0);
                        $highestRow = $sheet->getHighestRow();
                        $highestColumn = $sheet->getHighestColumn();
        
                        //  Loop through each row of the worksheet in turn
                        DB::beginTransaction();
                       
                        for ($row = 2; $row <= $highestRow; $row++)
                        {
                            //  Read a row of data into an array
                            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row,
                                NULL, TRUE, FALSE);
                            $data =  $rowData[0];
                            


                            /*
                                0 'CLUES',
                                1 'TIPO',
                                2 'NOMBRE',
                                3 'JURISDICCION'
                                4 'CAUSES'
                                5 'CAUSES Disponible'
                                6 'NO CAUSES'
                                7 'NO CAUSES Disponible'
                            */
                            
                            $unidad_medica = null;
                            $causes_disponible = 0;
                            $no_causes_disponible = 0;
                            for($i = 0; $i < count($unidades_medicas_con_presupuesto);$i++){
                                $presupuesto_um = $unidades_medicas_con_presupuesto[$i];
                                if($presupuesto_um['clues'] == $data[0]){
                                    $unidad_medica = UnidadMedica::find($data[0]);
                                    $causes_disponible = $presupuesto_um['causes_disponible'];
                                    $no_causes_disponible = $presupuesto_um['no_causes_disponible'];
                                    break;
                                }
                            }
                            

                            if($unidad_medica){
                                $causes = floatval($data[4]);
                                $total_causes += $causes;

                                $no_causes = floatval($data[6]);
                                $total_no_causes += $no_causes;

                                $um = array(
                                    'clues'=>$data[0],
                                    'unidad_medica' => $unidad_medica,
                                    'causes_autorizado' => $causes,
                                    'causes_disponible' => $causes_disponible,
                                    'no_causes_autorizado' => $no_causes,
                                    'no_causes_disponible' => $no_causes_disponible
                                );
                                if($causes > $causes_disponible){
                                    $um['error'] = true;
                                    $um['error_causes'] = true;
                                }
                                if($no_causes > $no_causes_disponible){
                                    $um['error'] = true;
                                    $um['error_no_causes'] = true;
                                }
                                $unidades_medicas[] = $um;
                            } else {
                                
                                $fake_unidad_medica =  new UnidadMedica();
                                $fake_unidad_medica->clues = $data[0];
                                $fake_unidad_medica->tipo = $data[1];
                                $fake_unidad_medica->nombre = $data[2];
                                $fake_unidad_medica->jurisdiccion = null;
                                $unidades_medicas[] = array(
                                    'clues'=>$data[0],
                                    'unidad_medica' => $fake_unidad_medica,
                                    'causes_autorizado' => floatval($data[4]),
                                    'causes_disponible' => 0,
                                    'no_causes_autorizado' => floatval($data[5]),
                                    'no_causes_disponible' => 0,
                                    'error' => true,
                                    'no_existe' => true
                                );
                                $con_errores = true;
                            }
                            
                        }
                        
                       

                        DB::rollback();
                    });

                    $presupuesto = array(
                        'causes' => $total_causes,
                        'no_causes' => $total_no_causes,
                        'pedidos_ordinarios_unidades_medicas' => $unidades_medicas,
                        'con_errores' => $con_errores

                    );

					return Response::json([ 'data' => $presupuesto],200);

				} else {
					throw new \Exception("Archivo inválido.");
				}
			} else {
				throw new \Exception("No hay archivo.");
			}
        } catch(\Exception $e){
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    } 
 }