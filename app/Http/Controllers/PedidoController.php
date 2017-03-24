<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Pedido;
use App\Models\PedidoInsumo;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


class PedidoController extends Controller
{
    public function index()
    {
        
        $pedido = Pedido::with("insumos", "acta", "TipoInsumo", "TipoPedido")->get();

        return Response::json([ 'data' => $pedido],200);
    }

    public function show($id)
    {
    	$object = Pedido::find($id);
        
        if(!$object){
            return Response::json(['error' => "No se encuentra el insumo que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else
        {
            $object = $object->with("insumos", "acta", "TipoInsumo", "TipoPedido")->get();        
        }

        return Response::json([ 'data' => $object],200);
    }

    public function store(Request $request)
    {
        $mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'tipo_insumo_id'        => 'required',
            'tipo_pedido_id'        => 'required',
            'pedido_padre'          => 'required',
            'folio'                 => 'required',
            'almacen_solicitante'   => 'required',
            'almacen_proveedor'     => 'required',
            'organismo_dirigido'    => 'required',
            'acta_id'               => 'required',
            'status'                => 'required',
            'usuario_validacion'    => 'required',
            'proveedor_id'          => 'required'
        ];

        $parametros = Input::all();
     
        
        $v = Validator::make($parametros, $reglas, $mensajes);

        if ($v->fails()) {

            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            DB::beginTransaction();
            
            $object = Pedido::create($parametros);


            foreach ($parametros['insumos'] as $key => $value) {
                $reglas_insumos = [
                    'insumo_medico_clave'           => 'required',
                    'cantidad_calculada_sistema'    => 'required',
                    'cantidad_solicitada_um'        => 'required',
                    'cantidad_ajustada_js'          => 'required',
                    'cantidad_ajustada_ca'          => 'required'
                ];  

                $v = Validator::make($value, $reglas_insumos, $mensajes);

                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
                }      

                $value['pedido_id'] = $object->id;

                $object_insumo = PedidoInsumo::create($value);    

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
            'tipo_insumo_id'        => 'required',
            'tipo_pedido_id'        => 'required',
            'pedido_padre'          => 'required',
            'folio'                 => 'required',
            'almacen_solicitante'   => 'required',
            'almacen_proveedor'     => 'required',
            'organismo_dirigido'    => 'required',
            'acta_id'               => 'required',
            'status'                => 'required',
            'usuario_validacion'    => 'required',
            'proveedor_id'          => 'required'
        ];

        $parametros = Input::all();
        
        $v = Validator::make($parametros, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $object = Pedido::find($id);

             DB::beginTransaction();

            $object->update($parametros);

            $arreglo_insumos = Array();

            
            PedidoInsumo::where("pedido_id", $id)->delete();

            foreach ($parametros['insumos'] as $key => $value) {

                $reglas_insumos = [
                    'insumo_medico_clave'           => 'required',
                    'cantidad_calculada_sistema'    => 'required',
                    'cantidad_solicitada_um'        => 'required'
                ];  

                $v = Validator::make($value, $reglas_insumos, $mensajes);

                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
                }      
               
                $value['pedido_id'] = $object->id;
                $object_insumo = PedidoInsumo::create($value);
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
