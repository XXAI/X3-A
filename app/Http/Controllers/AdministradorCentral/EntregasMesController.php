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
                                    proveedores.id as proveedor_id,
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

    public function entregasPedidosStatsDiarias(Request $request){
        try{
            
            $input = Input::only('mes','anio');
            
            $mensajes = [
                'required'      => "required",
                'integer'      => "integer",
            ];
            $reglas = [
                'mes'        			=> 'required|integer',
                'anio'                  => 'required|integer',
                'proveedor_id'          => 'required'
            ];

            $input = Input::all();
        
            $v = Validator::make($input, $reglas, $mensajes);

            if ($v->fails()) {
                return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
            }

            $items = DB::table(
                        DB::raw('(
                            SELECT 
                                movimientos.fecha_movimiento,
                                DAY(movimientos.fecha_movimiento) as dia,
                                CASE MONTH(movimientos.fecha_movimiento)
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
                                ELSE "DICIEMBRE" END AS mes_nombre,

                                CAUSES.claves as causes_claves,
                                CAUSES.cantidad as causes_cantidad,
                                CAUSES.monto as causes_monto,
                                NO_CAUSES.claves as no_causes_claves,
                                NO_CAUSES.cantidad as no_causes_cantidad,
                                NO_CAUSES.monto as no_causes_monto,
                                MATERIAL_CURACION.claves as material_curacion_claves,
                                MATERIAL_CURACION.cantidad as material_curacion_cantidad,
                                MATERIAL_CURACION.monto as material_curacion_monto
                                
                            FROM
                                movimientos 
                            INNER JOIN
                            (
                                SELECT 
                                    fecha_movimiento, 
                                    count(clave_insumo_medico) as claves, 
                                    CAST(sum(cantidad) AS UNSIGNED) as cantidad, 
                                    sum(monto) as monto 
                                FROM 
                                    (SELECT  
                                        movimientos.fecha_movimiento,
                                        DAY(movimientos.fecha_movimiento),
                                        MONTH(movimientos.fecha_movimiento),
                                        stock.clave_insumo_medico, 
                                        sum(movimiento_insumos.cantidad) as cantidad, 
                                        sum(movimiento_insumos.precio_total) as monto 

                                    FROM  pedidos
                                    INNER JOIN movimiento_pedido ON movimiento_pedido.pedido_id = pedidos.id
                                    INNER JOIN movimientos ON movimientos.id = movimiento_pedido.movimiento_id
                                    INNER JOIN  movimiento_insumos ON movimiento_insumos.movimiento_id = movimientos.id 
                                    INNER JOIN stock ON movimiento_insumos.stock_id = stock.id  
                                    INNER JOIN insumos_medicos ON insumos_medicos.clave = stock.clave_insumo_medico  
                                    WHERE 
                                        movimientos.fecha_movimiento  is not null AND
                                        movimientos.fecha_movimiento  != "0000-00-00" AND
                                        pedidos.status != "BR" AND
                                        insumos_medicos.tipo = "ME" AND
                                        insumos_medicos.es_causes = 1 AND
                                        YEAR(pedidos.fecha)= '.$input['anio'].' AND 
                                        MONTH(pedidos.fecha) = '.$input['mes'].' AND
                                        pedidos.proveedor_id = '.$input['proveedor_id'].'
                                    GROUP BY
                                        movimientos.fecha_movimiento,
                                        stock.clave_insumo_medico 
                                    ) as tabla
                                GROUP BY fecha_movimiento
                            ) AS CAUSES ON CAUSES.fecha_movimiento = movimientos.fecha_movimiento

                            INNER JOIN
                            (
                                SELECT 
                                    fecha_movimiento, 
                                    count(clave_insumo_medico) as claves, 
                                    CAST(sum(cantidad) AS UNSIGNED) as cantidad, 
                                    sum(monto) as monto 
                                FROM 
                                    (SELECT  
                                        movimientos.fecha_movimiento,
                                        DAY(movimientos.fecha_movimiento),
                                        MONTH(movimientos.fecha_movimiento),
                                        stock.clave_insumo_medico, 
                                        sum(movimiento_insumos.cantidad) as cantidad, 
                                        sum(movimiento_insumos.precio_total) as monto 

                                    FROM  pedidos
                                    INNER JOIN movimiento_pedido ON movimiento_pedido.pedido_id = pedidos.id
                                    INNER JOIN movimientos ON movimientos.id = movimiento_pedido.movimiento_id
                                    INNER JOIN  movimiento_insumos ON movimiento_insumos.movimiento_id = movimientos.id 
                                    INNER JOIN stock ON movimiento_insumos.stock_id = stock.id  
                                    INNER JOIN insumos_medicos ON insumos_medicos.clave = stock.clave_insumo_medico  
                                    WHERE 
                                        movimientos.fecha_movimiento  is not null AND
                                        movimientos.fecha_movimiento  != "0000-00-00" AND
                                        pedidos.status != "BR" AND
                                        insumos_medicos.tipo = "ME" AND
                                        insumos_medicos.es_causes = 0 AND
                                        YEAR(pedidos.fecha)= '.$input['anio'].' AND 
                                        MONTH(pedidos.fecha) = '.$input['mes'].' AND
                                        pedidos.proveedor_id = '.$input['proveedor_id'].'
                                    GROUP BY
                                        movimientos.fecha_movimiento,
                                        stock.clave_insumo_medico 
                                    ) as tabla
                                GROUP BY fecha_movimiento
                            ) AS NO_CAUSES ON NO_CAUSES.fecha_movimiento = movimientos.fecha_movimiento

                            INNER JOIN
                            (
                                SELECT 
                                    fecha_movimiento, 
                                    count(clave_insumo_medico) as claves, 
                                    CAST(sum(cantidad) AS UNSIGNED) as cantidad, 
                                    sum(monto) as monto 
                                FROM 
                                    (SELECT  
                                        movimientos.fecha_movimiento,
                                        DAY(movimientos.fecha_movimiento),
                                        MONTH(movimientos.fecha_movimiento),
                                        stock.clave_insumo_medico, 
                                        sum(movimiento_insumos.cantidad) as cantidad, 
                                        sum(movimiento_insumos.precio_total) as monto 

                                    FROM  pedidos
                                    INNER JOIN movimiento_pedido ON movimiento_pedido.pedido_id = pedidos.id
                                    INNER JOIN movimientos ON movimientos.id = movimiento_pedido.movimiento_id
                                    INNER JOIN  movimiento_insumos ON movimiento_insumos.movimiento_id = movimientos.id 
                                    INNER JOIN stock ON movimiento_insumos.stock_id = stock.id  
                                    INNER JOIN insumos_medicos ON insumos_medicos.clave = stock.clave_insumo_medico  
                                    WHERE 
                                        movimientos.fecha_movimiento  is not null AND
                                        movimientos.fecha_movimiento  != "0000-00-00" AND
                                        pedidos.status != "BR" AND
                                        insumos_medicos.tipo = "MC" AND
                                        YEAR(pedidos.fecha)= '.$input['anio'].' AND 
                                        MONTH(pedidos.fecha) = '.$input['mes'].' AND
                                        pedidos.proveedor_id = '.$input['proveedor_id'].'
                                    GROUP BY
                                        movimientos.fecha_movimiento,
                                        stock.clave_insumo_medico 
                                    ) as tabla
                                GROUP BY fecha_movimiento
                            ) AS MATERIAL_CURACION ON MATERIAL_CURACION.fecha_movimiento = movimientos.fecha_movimiento
                            GROUP BY movimientos.fecha_movimiento
                        
                        ) as tabla')
                    )->get();
      
            return Response::json([ 'data' => $items],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

}
