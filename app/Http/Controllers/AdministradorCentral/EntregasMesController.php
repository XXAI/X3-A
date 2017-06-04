<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Pedido;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class EntregasMesController extends Controller
{
    public function mesesAnioPresupuestos(Request $request){
        try{

            
            $anio = date('Y');
            $mes = date('n');

            $items = Pedido::select(DB::raw('CONCAT( MONTH(pedidos.fecha),"/",YEAR(pedidos.fecha)) as fecha'), DB::raw('MONTH(pedidos.fecha) as mes'), DB::raw('YEAR(pedidos.fecha) as anio'), DB::raw(
                'CASE MONTH(pedidos.fecha)
                    WHEN 1 THEN "ENERO" 
                    WHEN 2 THEN "FEBRERO" 
                    WHEN 3 THEN "MARZO" 
                    WHEN 4 THEN "ABRIL" 
                    WHEN 5 THEN "MAYO" 
                    WHEN 6 THEN "JUNIO" 
                    WHEN 7 THEN "JULIO" 
                    WHEN 8 THEN "AGOSTO" 
                    WHEN 9 THEN "SEPTIEMBRE" 
                    WHEN 10 THEN "OCTUBRE" 
                    WHEN 11 THEN "NOVIEMBRE" 
                    ELSE "DICIEMBRE" END AS mes_nombre'
            ))
            ->where('status','!=','BR')
            ->groupBy(DB::raw('YEAR(pedidos.fecha) DESC'))->groupBy(DB::raw('MONTH(pedidos.fecha) DESC'))->get();
      
            return Response::json([ 'data' => $items],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function statsMesAnio(Request $request){
        try{
            
            $input = Input::only('mes','anio');
            
            $mensajes = [
                'required'      => "required",
                'integer'      => "integer",
            ];
            $reglas = [
                'mes'        			=> 'required|integer',
                'anio'                  => 'required|integer'
            ];

            $input = Input::all();
        
            $v = Validator::make($input, $reglas, $mensajes);

            if ($v->fails()) {
                return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
            }

            $items = DB::table(
                        DB::raw('(
                            SELECT 
                                    proveedores.nombre_corto as proveedor,
                                    SUM(total_monto_solicitado) total_monto_solicitado, 
                                    IF ((SUM(total_monto_recibido) * 100 / SUM(total_monto_solicitado)) IS NULL, 0.00, (SUM(total_monto_recibido) * 100 / SUM(total_monto_solicitado)) ) as porcentaje_entregado
                            FROM pedidos
                            LEFT JOIN proveedores on proveedores.id = pedidos.proveedor_id
                            where pedidos.status != "BR" AND YEAR(pedidos.fecha) = '.$input['anio'].' AND MONTH(pedidos.fecha) = '.$input['mes'].'
                            GROUP BY pedidos.proveedor_id
                        ) as tabla')
                    )->get();
      
            return Response::json([ 'data' => $items],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

}
