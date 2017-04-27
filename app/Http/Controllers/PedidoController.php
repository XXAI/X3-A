<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\Usuario;
use App\Models\Presupuesto;
use App\Models\UnidadMedicaPresupuesto;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


class PedidoController extends Controller
{   
    public function obtenerDatosPresupuesto(Request $request){
        try{
            $obj =  JWTAuth::parseToken()->getPayload();
            $usuario = Usuario::with('almacenes')->find($obj->get('id'));

            if(count($usuario->almacenes) > 1){
                //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
                return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
            }else{
                $almacen = $usuario->almacenes[0];
            }

            $presupuesto = Presupuesto::where('activo',1)->first();

            $presupuesto_unidad_medica = UnidadMedicaPresupuesto::select('clues',
                                            DB::raw('sum(causes_autorizado) as causes_autorizado'),DB::raw('sum(causes_modificado) as causes_modificado'),DB::raw('sum(causes_comprometido) as causes_comprometido'),DB::raw('sum(causes_devengado) as causes_devengado'),DB::raw('sum(causes_disponible) as causes_disponible'),
                                            DB::raw('sum(no_causes_autorizado) as no_causes_autorizado'),DB::raw('sum(no_causes_modificado) as no_causes_modificado'),DB::raw('sum(no_causes_comprometido) as no_causes_comprometido'),DB::raw('sum(no_causes_devengado) as no_causes_devengado'),DB::raw('sum(no_causes_disponible) as no_causes_disponible'),
                                            DB::raw('sum(material_curacion_autorizado) as material_curacion_autorizado'),DB::raw('sum(material_curacion_modificado) as material_curacion_modificado'),DB::raw('sum(material_curacion_comprometido) as material_curacion_comprometido'),DB::raw('sum(material_curacion_devengado) as material_curacion_devengado'),DB::raw('sum(material_curacion_disponible) as material_curacion_disponible'))
                                            ->where('presupuesto_id',$presupuesto->id)
                                            ->where('clues',$almacen->clues)
                                            ->where('proveedor_id',$almacen->proveedor_id)
                                            ->groupBy('clues')->first();
            
            return Response::json([ 'data' => $presupuesto_unidad_medica],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function stats(){

        // Hay que obtener la clues del usuario
        $pedidos = Pedido::select(DB::raw(
            '
            count(
                1
            ) as todos,
            count(
                case when status = "BR" then 1 else null end
            ) as borradores,
            count(
                case when status = "ET" then 1 else null end
            ) as en_transito,
            count(
                case when status = "PS" then 1 else null end
            ) as por_surtir,
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

    public function show(Request $request, $id)
    {
    	$pedido = Pedido::find($id);
        
        if(!$pedido){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
            if($pedido->status == 'BR'){
                $pedido = $pedido->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos");
            }else{
                $pedido = $pedido->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos", "tipoInsumo", "tipoPedido", "almacenProveedor","almacenSolicitante.unidadMedica","proveedor");
            }
        }

        return Response::json([ 'data' => $pedido],200);
    }

    public function store(Request $request)
    {
        $mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'tipo_pedido_id'        => 'required',
            'descripcion'           => 'required',
            'fecha'                 => 'required|date',
            //'almacen_solicitante'   => 'required',
            //'almacen_proveedor'     => 'required',
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
        /*if(count($parametros) == 1){
            $parametros = $parametros[0];
        }*/

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        if($almacen->nivel_almacen == 1 && $almacen->tipo_almacen == 'ALMPAL'){
            $reglas['proveedor_id'] = 'required';
            $parametros['datos']['proveedor_id'] = $almacen->proveedor_id;
            $parametros['datos']['almacen_proveedor'] = null;
            //Harima: Checa proveedor seleccionado, por el momento se saca del alamancen, pero luego podemos poner un dropbox por si se dos o mas proveedores son asignados por clues
        }elseif($almacen->nivel_almacen == 2){
            $reglas['almacen_proveedor'] = 'required';
        }
        
        $parametros['datos']['almacen_solicitante'] = $almacen->id;
        $parametros['datos']['clues'] = $almacen->clues;
        $parametros['datos']['status'] = 'BR'; //estatus de borrador
        $parametros['datos']['tipo_pedido_id'] = 'PA'; //tipo de pedido Pedido de Abatecimiento
        
        $v = Validator::make($parametros['datos'], $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            DB::beginTransaction();
            
            $pedido = Pedido::create($parametros['datos']);

            $total_claves = count($parametros['insumos']);
            $total_insumos = 0;
            $total_monto = 0;

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
                    'cantidad_solicitada' => $value['cantidad'],
                    'monto_solicitado' => $value['monto'],
                    'precio_unitario' => $value['precio'],
                    'pedido_id' => $pedido->id
                ];
                //$value['pedido_id'] = $pedido->id;

                $total_insumos += $value['cantidad'];
                $total_monto += $value['monto'];

                $object_insumo = PedidoInsumo::create($insumo);
            }
            
            $pedido->total_claves_solicitadas = $total_claves;
            $pedido->total_cantidad_solicitada = $total_insumos;
            $pedido->total_monto_solicitado = $total_monto;
            $pedido->save();

            DB::commit();
            return Response::json([ 'data' => $pedido ],200);

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
            'fecha'                 => 'required|date',
            //'almacen_solicitante'   => 'required',
            //'almacen_proveedor'     => 'required',
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

        /*if(count($parametros) == 1){
            $parametros = $parametros[0];
        }*/

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        if($almacen->nivel_almacen == 1 && $almacen->tipo_almacen == 'ALMPAL'){
            $reglas['proveedor_id'] = 'required';
            $parametros['datos']['proveedor_id'] = $almacen->proveedor_id;
            $parametros['datos']['almacen_proveedor'] = null;
            //Harima: Checa proveedor seleccionado, por el momento se saca del alamancen, pero luego podemos poner un dropbox por si se dos o mas proveedores son asignados por clues
        }elseif($almacen->nivel_almacen == 2){
            $reglas['almacen_proveedor'] = 'required';
        }
        
        //$parametros['datos']['tipo_pedido_id'] = 1;

        if(!isset($parametros['datos']['status'])){
            $parametros['datos']['status'] = 'BR'; //estatus Borrador
        }elseif($parametros['datos']['status'] == 'CONCLUIR'){
            if($almacen->nivel_almacen == 1 && $almacen->tipo_almacen == 'ALMPAL'){
                $parametros['datos']['status'] = 'PS';
            }elseif($almacen->nivel_almacen == 2){
                $parametros['datos']['status'] = 'ET';
            }
        }else{
            $parametros['datos']['status'] = 'BR';
        }
        
        $v = Validator::make($parametros['datos'], $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $pedido = Pedido::find($id);

             DB::beginTransaction();

            $pedido->update($parametros['datos']);

            $arreglo_insumos = Array();
            
            PedidoInsumo::where("pedido_id", $id)->delete();

            $total_claves = count($parametros['insumos']);
            $total_insumos = 0;
            $total_monto = 0;

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
                    'cantidad_solicitada' => $value['cantidad'],
                    'monto_solicitado' => $value['monto'],
                    'precio_unitario' => $value['precio'],
                    'pedido_id' => $pedido->id
                ];
                //$value['pedido_id'] = $pedido->id;

                $total_insumos += $value['cantidad'];
                $total_monto += $value['monto'];

                PedidoInsumo::create($insumo);  
            }

            if(!$pedido->folio && $pedido->status != 'BR'){
                $max_folio = Pedido::where('clues',$almacen->clues)->max('folio');
                $anio = date('Y');
                if(!$max_folio){
                    $prox_folio = 1;
                }else{
                    $max_folio = explode('-',$max_folio);
                    $prox_folio = intval($max_folio[3]) + 1;
                }
                $pedido->folio = $almacen->clues . '-' . $anio . '-PA-' . str_pad($prox_folio, 3, "0", STR_PAD_LEFT);
            }

            if($pedido->status == 'PS'){
                $fecha = explode('-',$pedido->fecha);
                $presupuesto = Presupuesto::where('activo',1)->first();
                $presupuesto_unidad = UnidadMedicaPresupuesto::where('presupuesto_id',$presupuesto->id)
                                            ->where('clues',$almacen->clues)
                                            ->where('proveedor_id',$almacen->proveedor_id)
                                            ->where('mes',$fecha[1])
                                            ->where('anio',$fecha[0])
                                            ->first();
                $presupuesto_unidad->causes_comprometido += $total_monto;
                $presupuesto_unidad->causes_disponible -= $total_monto;

                $presupuesto_unidad->save();
            }

            $pedido->total_claves_solicitadas = $total_claves;
            $pedido->total_cantidad_solicitada = $total_insumos;
            $pedido->total_monto_solicitado = $total_monto;
            $pedido->save();
             
             DB::commit(); 

            return Response::json([ 'data' => $pedido ],200);

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
