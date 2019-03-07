<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

use App\Models\Almacen,
    App\Models\Movimiento;


use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class ReporteEntradaSalidaController extends Controller
{
    //
    public function index(Request $request)
    {
        try{
            $parametros = Input::only('desde','hasta','tipo', 'insumo', 'page', 'per_page');
            
            $obj =  JWTAuth::parseToken()->getPayload();
            $almacen = Almacen::with("unidadMedica")->find($request->get('almacen_id'));

            $consulta = DB::table("movimientos")->join('tipos_movimientos', 'tipos_movimientos.id', '=', 'movimientos.tipo_movimiento_id')
                                    ->join('movimiento_insumos', 'movimientos.id', '=', 'movimiento_insumos.movimiento_id')
                                    ->join('insumos_medicos', 'insumos_medicos.clave', '=', 'movimiento_insumos.clave_insumo_medico')
                                    ->join('almacenes', 'almacenes.id', '=', 'movimientos.almacen_id')
                                    ->where("movimientos.status", "=", 'FI')
                                    ->where("movimientos.almacen_id", "=", $almacen->id)
                                    ->whereNull("movimientos.deleted_at")
                                    ->orderBy("movimientos.fecha_movimiento", "asc", "insumos_medicos.clave", "insumos_medicos.clave", "tipos_movimientos.tipo")
                                    ->select("movimientos.id", "movimientos.fecha_movimiento", "tipos_movimientos.nombre", "tipos_movimientos.tipo", "movimiento_insumos.cantidad", "movimiento_insumos.cantidad_unidosis", "insumos_medicos.clave", "insumos_medicos.descripcion", "movimiento_insumos.precio_unitario", "movimiento_insumos.iva", "movimiento_insumos.precio_total" );
            

            if($parametros['desde'] != "" || $parametros['hasta'] != "")
            {
                if($parametros['desde']!="" && $parametros['hasta']=="")
                    $parametros['hasta'] = $parametros['desde'];
                    
                if($parametros['desde']=="" && $parametros['hasta']!="")
                    $parametros['desde'] = $parametros['hasta'];  
                    
                $consulta = $consulta->whereBetween("movimientos.fecha_movimiento", array($parametros['desde'], $parametros['hasta']));    
            }

            if($parametros['tipo'] != 1)
            {
                if($parametros['tipo'] == 2)
                    $tipo = 'E';
                if($parametros['tipo'] == 3)
                    $tipo = 'S';    
                $consulta = $consulta->where("tipos_movimientos.tipo", "=", $tipo);
            }
            if($parametros['insumo'] != "")
            {
                $texto = $parametros['insumo'];
                $consulta = $consulta->where(function ($query) use($parametros) {
                    $query->where('insumos_medicos.clave', 'like', '%' . $parametros['insumo'] . '%')
                          ->orWhere('insumos_medicos.descripcion', 'like', '%' . $parametros['insumo'] . '%');
                });   
            }

            if(isset($parametros['page'])){
                $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
                $consulta = $consulta->paginate($resultadosPorPagina);
            } else {
                $consulta = $consulta->get();
            }
            return Response::json([ 'data' => array("datos"=>$consulta, "almacen" => $almacen)],200);                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }                                        
    }

    public function show(Request $request, $id)
    {
        try{
            $parametros = Input::only('desde','hasta','tipo', 'insumo', 'page', 'per_page', 'tipo_movimiento');
            
            $obj =  JWTAuth::parseToken()->getPayload();
            $almacen = Almacen::find($request->get('almacen_id'));

            $consulta = Movimiento::with("almacen.unidadMedica.director", "almacen.encargado", "tipoMovimiento", "insumosDetalles")->find($id); 

            return Response::json([ 'data' => $consulta],200);                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }                                        
    }

    public function catalogo(Request $request)
    {
        try{
            $consulta = Insumo::all(); 

            return Response::json([ 'data' => "hola"],200);                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }                                        
    }
}
