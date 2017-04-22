<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\RecepcionPedido;
use App\Models\MovimientoInsumos;
use App\Models\Stock;

use App\Models\Usuario;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

class RecepcionPedidoController extends Controller
{
    public function index()
    {
    	$pedidos = RecepcionPedido::with("MovimientoInsumo", "MovimientoInsumo.Stock")->get();
    	return Response::json([ 'data' => $pedidos],200);

    }

    public function show($id)
    {
    	$pedidos = RecepcionPedido::find($id)->with("MovimientoInsumo", "MovimientoInsumo.Stock")->first();
    	return Response::json([ 'data' => $pedidos],200);
    }

    public function store(Request $request)
    {
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
        $usuario = Usuario::find($obj->get('id'));

        try {
            DB::beginTransaction();
	        $v = Validator::make($parametros, $reglas, $mensajes);

	        if ($v->fails()) {
	            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
	        }

	        $movimiento = RecepcionPedido::create($parametros);

	        $stock = $parametros['stock'];

	        foreach ($stock as $key => $value) {
	        	$reglas_stock = [
		            'almacen_id'        	=> 'required',
		            'clave_insumo_medico'   => 'required',
		            'marca_id'     			=> 'required',
		            'lote'     				=> 'required',
		            'fecha_caducidad'     	=> 'required',
		            'codigo_barras'     	=> 'required',
		            'existencia'     		=> 'required',
		            'existencia_unidosis'   => 'required'
		        ];

		        $v = Validator::make($value, $reglas_stock, $mensajes);

		        if ($v->fails()) {
		            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
		        }

		        $value['movimiento_id'] = $movimiento->id;
		        $insert_stock = Stock::create($value);

		        $reglas_movimiento_insumos = [
		            'cantidad'        	=> 'required',
		            'precio_unitario'   => 'required',
		            'iva'     			=> 'required',
		            'precio_total'     	=> 'required'
		        ];

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

    public function update(Request $request, $id)
    {
    }
    
    function destroy($id)
    {
    	 try {
            $object = RecepcionPedido::destroy($id);
            return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
