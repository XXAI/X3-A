<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\Usuario;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


class PedidoController extends Controller
{   
    public function stats(){

        // Hay que obtener la clues del usuario
        $pedidos = Pedido::select(DB::raw(
            '
            count(
                case when status = "AB" then 1 else null end
            ) as abiertos,
            count(
                case when status = "ES" then 1 else null end
            ) as en_espera,
            count(
                case when status = "PE" then 1 else null end
            ) as pendientes,
            count(
                case when status = "FI" then 1 else null end
            ) as finalizados
            '
        ))->first();

        return Response::json($pedidos,200);
    }

    public function index()
    {
        $parametros = Input::only('status','q','page','per_page');

       if ($parametros['q']) {
            $pedidos =  Pedido::with("insumos", "acta", "tipoInsumo", "tipoPedido","almacenSolicitante","almacenProveedor")->where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")->orWhere('descripcion','LIKE',"%".$parametros['q']."%");
             });
        } else {
             $pedidos = Pedido::with("insumos", "acta", "tipoInsumo", "tipoPedido","almacenSolicitante","almacenProveedor");
        }

        if(isset($parametros['status'])) {
            $pedidos = $pedidos->where("pedidos.status",$parametros['status']);
        }
        
        //$pedido = Pedido::with("insumos", "acta", "TipoInsumo", "TipoPedido")->get();
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $pedidos = $pedidos->paginate($resultadosPorPagina);
        } else {
            $pedidos = $pedidos->get();
        }

        return Response::json([ 'data' => $pedidos],200);
    }

    public function show($id)
    {
    	$object = Pedido::find($id);
        
        if(!$object){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
            if($object->status == 'AB'){
                $object = $object->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos");
            }else{
                $object = $object->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos", "tipoInsumo", "tipoPedido", "almacenProveedor","almacenSolicitante");
            }
        }

        return Response::json([ 'data' => $object],200);
    }

    public function store(Request $request)
    {
        $mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'tipo_pedido_id'        => 'required',
            'descripcion'           => 'required',
            //'almacen_solicitante'   => 'required',
            'almacen_proveedor'     => 'required',
            //'observaciones'         => 'required',
            'status'                => 'required',
            //'tipo_insumo_id'        => 'required',
            //'pedido_padre'          => 'required',
            //'folio'                 => 'required',
            //'organismo_dirigido'    => 'required',
            //'acta_id'               => 'required',
            //'usuario_validacion'    => 'required',
            //'proveedor_id'          => 'required'
        ];

        $parametros = Input::all();

        //return Response::json([ 'data' => $parametros ],500);
        if(count($parametros) == 1){
            $parametros = $parametros[0];
        }

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::find($obj->get('id'));

        $almacenes_id = $usuario->almacenes()->lists('almacenes.id');

        if(count($almacenes_id) > 1){
            //error
        }
        
        $parametros['datos']['almacen_solicitante'] = $almacenes_id[0];
        $parametros['datos']['status'] = 'AB';
        $parametros['datos']['tipo_pedido_id'] = 'PA';
        
        $v = Validator::make($parametros['datos'], $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            DB::beginTransaction();
            
            $object = Pedido::create($parametros['datos']);

            foreach ($parametros['insumos'] as $key => $value) {
                $reglas_insumos = [
                    'clave'           => 'required',
                    'cantidad'        => 'required'
                ];  

                $v = Validator::make($value, $reglas_insumos, $mensajes);

                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
                }      
                
                $insumo = [
                    'insumo_medico_clave' => $value['clave'],
                    'cantidad_solicitada_um' => $value['cantidad'],
                    'pedido_id' => $object->id
                ];
                //$value['pedido_id'] = $object->id;

                $object_insumo = PedidoInsumo::create($insumo);    

            }    

            DB::commit();
            return Response::json([ 'data' => $object ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function update(Request $request, $id)
    {
        $mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            //'tipo_pedido_id'        => 'required',
            'descripcion'           => 'required',
            //'almacen_solicitante'   => 'required',
            'almacen_proveedor'     => 'required',
            //'observaciones'         => 'required',
            'status'                => 'required',
            //'tipo_insumo_id'        => 'required',
            //'pedido_padre'          => 'required',
            //'folio'                 => 'required',
            //'organismo_dirigido'    => 'required',
            //'acta_id'               => 'required',
            //'usuario_validacion'    => 'required',
            //'proveedor_id'          => 'required'
        ];

        $parametros = Input::all();

        if(count($parametros) == 1){
            $parametros = $parametros[0];
        }

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::find($obj->get('id'));

        //$parametros['datos']['tipo_pedido_id'] = 1;

        if(!isset($parametros['datos']{'status'})){
            $parametros['datos']['status'] = 'AB';
        }
        
        $v = Validator::make($parametros['datos'], $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $object = Pedido::find($id);

             DB::beginTransaction();

            $object->update($parametros['datos']);

            $arreglo_insumos = Array();
            
            PedidoInsumo::where("pedido_id", $id)->delete();

            foreach ($parametros['insumos'] as $key => $value) {

                $reglas_insumos = [
                    'clave'           => 'required',
                    'cantidad'        => 'required'
                ];  

                $v = Validator::make($value, $reglas_insumos, $mensajes);

                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
                }      
                
                $insumo = [
                    'insumo_medico_clave' => $value['clave'],
                    'cantidad_solicitada_um' => $value['cantidad'],
                    'pedido_id' => $object->id
                ];
                //$value['pedido_id'] = $object->id;

                $object_insumo = PedidoInsumo::create($insumo);  
            }   

             
             DB::commit(); 

            return Response::json([ 'data' => $object ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    function destroy($id)
    {
        try {
            $object = Pedido::destroy($id);
            return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }

    }
}
