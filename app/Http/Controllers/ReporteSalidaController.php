<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

use App\Models\Turno,
    App\Models\Servicio,
    App\Models\UnidadMedica,
    App\Models\Usuario,
    App\Models\Programa;


use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;


class ReporteSalidaController extends Controller
{
    public function index(Request $request)
    {
        $parametros = Input::only('desde','hasta','clues', 'orden');
        
        $clues = "";
        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::where("id", $obj->get('id'))->with("usuariounidad")->first();
        
        $total    = false;
        if($usuario->su == 1)
            $total = true;
        
        if(!$total)
            if($usuario->usuariounidad->clues)
            {
                $clues = $usuario->usuariounidad->clues;  
                $parametros['clues'] = $clues;
            }

        $fechainicial = new \DateTime($parametros['desde']);
        $fechafinal = new \DateTime($parametros['hasta']);
        $diferencia = $fechainicial->diff($fechafinal);
        $meses_diferencia = ( $diferencia->y * 12 ) + $diferencia->m;
        //$clues = "";
        if($meses_diferencia == 0)
            $meses_diferencia = 1;
        $reporte_salida = DB::table("reporte_salidas")
                            ->join('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                             ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.clave")
                            ->groupBy("reporte_salidas.clues")
                            ->groupBy("reporte_salidas.fecha_realizado")
                            ->limit(20);

        $reporte_salida_turno = DB::table("reporte_salidas")
                            ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                            ->leftjoin('turnos', 'turnos.id', '=', 'reporte_salidas.turno_id')
                            ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.turno_id")
                            ->limit(20);

        $reporte_salida_servicio = DB::table("reporte_salidas")
                            ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                            ->leftjoin('servicios', 'servicios.id', '=', 'reporte_salidas.servicio_id')
                            ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.servicio_id")
                            ->limit(20);
        
        if($parametros['clues']!='')
        {
            $reporte_salida = $reporte_salida->where("reporte_salidas.clues", $parametros['clues']);
            $reporte_salida_turno = $reporte_salida_turno->where("reporte_salidas.clues", $parametros['clues']);
            $reporte_salida_servicio = $reporte_salida_servicio->where("reporte_salidas.clues", $parametros['clues']);
            $clues = DB::table("unidades_medicas")->where("clues", "=", $parametros['clues'])->first();
        }                    


        $reporte_salida = $reporte_salida->select("reporte_salidas.clave",
                                                    "insumos_medicos.descripcion",
                                                    "unidades_medicas.nombre",
                                                    "reporte_salidas.fecha_realizado",
                                                    "reporte_salidas.tipo",
                                                    "reporte_salidas.es_causes",
                                                    DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                                                    DB::RAW("sum(reporte_salidas.negado) as negado"),
                                                    
                                                    DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                                                    DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                                                    DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));
                                            

            $reporte_salida_turno = $reporte_salida_turno->select("reporte_salidas.clave",
                                            "insumos_medicos.descripcion",
                                            "reporte_salidas.turno_id",
                                            "turnos.nombre as modulo",
                                            "unidades_medicas.nombre",
                                            
                                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));
            
            $reporte_salida_servicio = $reporte_salida_servicio->select("reporte_salidas.clave",
                                            "insumos_medicos.descripcion",
                                            "reporte_salidas.servicio_id",
                                            "servicios.nombre as modulo",
                                            "unidades_medicas.nombre",
                                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));
            
                                           
        if($parametros['orden'] == 1)
        {
            
            $reporte_salida = $reporte_salida->orderBy("surtido", "desc")->get();
            $reporte_salida_turno = $reporte_salida_turno->orderBy("surtido", "desc")->get();
            $reporte_salida_servicio = $reporte_salida_servicio->orderBy("surtido", "desc")->get();

            

            $key = 0;
            foreach($reporte_salida_turno as $row){
                //return $id_tagg = $t->id;
                $drill = DB::table("reporte_salidas")
                ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                ->leftjoin('turnos', 'turnos.id', '=', 'reporte_salidas.turno_id')
                ->where(function ($query) {
                    $query->where('reporte_salidas.surtido', '>', 0)
                          ->orWhere('reporte_salidas.negado', '>', 0);
                })
                ->where("turno_id", "=", $row->turno_id)
                ->where("reporte_salidas.clues", "like", '%'.$parametros['clues']."%")
                ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                ->groupBy("reporte_salidas.clave")
                ->groupBy("reporte_salidas.clues")
                ->groupBy("reporte_salidas.fecha_realizado")
                ->limit(20)
                ->select("reporte_salidas.clave",
                    "insumos_medicos.descripcion",
                    "reporte_salidas.turno_id",
                    "turnos.nombre as modulo",
                    "unidades_medicas.nombre",
                    DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                    DB::RAW("sum(reporte_salidas.negado) as negado"),
                    DB::RAW("sum(reporte_salidas.surtido) as cantidad"),
                    DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                    DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                    DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"))
                ->orderBy("cantidad", "desc")    
                ->get();
                
                $reporte_salida_turno[$key]->drill = $drill;
                $key++;
            }
            
            $key = 0;
            foreach($reporte_salida_servicio as $row){
                $drill = DB::table("reporte_salidas")
                ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                ->leftjoin('servicios', 'servicios.id', '=', 'reporte_salidas.servicio_id')
                ->where(function ($query) {
                    $query->where('reporte_salidas.surtido', '>', 0)
                          ->orWhere('reporte_salidas.negado', '>', 0);
                })
                ->where("servicio_id", "=", $row->servicio_id)
                ->where("reporte_salidas.clues", "like", '%'.$parametros['clues']."%")
                ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                ->groupBy("reporte_salidas.clave")
                ->groupBy("reporte_salidas.clues")
                ->groupBy("reporte_salidas.fecha_realizado")
                ->limit(20)
                ->select("reporte_salidas.clave",
                    "insumos_medicos.descripcion",
                    "reporte_salidas.servicio_id",
                    "servicios.nombre as modulo",
                    "unidades_medicas.nombre",
                    DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                    DB::RAW("sum(reporte_salidas.negado) as negado"),
                    DB::RAW("sum(reporte_salidas.surtido) as cantidad"),
                    DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                    DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                    DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"))
                ->orderBy("cantidad", "desc")    
                ->get();
                
                $reporte_salida_servicio[$key]->drill = $drill;
                $key++;
            }
            
            
        }else{
            $reporte_salida = $reporte_salida->orderBy("negado", "desc")->get();
            $reporte_salida_turno = $reporte_salida_turno->orderBy("negado", "desc")->get();
            $reporte_salida_servicio = $reporte_salida_servicio->orderBy("negado", "desc")->get();
            
            $key = 0;
            foreach($reporte_salida_turno as $row){
                
                //return $id_tagg = $t->id;
                $drill = DB::table("reporte_salidas")
                ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                ->leftjoin('turnos', 'turnos.id', '=', 'reporte_salidas.turno_id')
                ->where(function ($query) {
                    $query->where('reporte_salidas.surtido', '>', 0)
                          ->orWhere('reporte_salidas.negado', '>', 0);
                })
                ->where("turno_id", "=", $row->turno_id)
                ->where("reporte_salidas.clues", "like", '%'.$parametros['clues']."%")
                ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                ->groupBy("reporte_salidas.clave")
                ->groupBy("reporte_salidas.clues")
                ->groupBy("reporte_salidas.fecha_realizado")
                ->limit(20)
                ->select("reporte_salidas.clave",
                    "insumos_medicos.descripcion",
                    "reporte_salidas.turno_id",
                    "turnos.nombre as modulo",
                    "unidades_medicas.nombre",
                    DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                    DB::RAW("sum(reporte_salidas.negado) as negado"),
                    DB::RAW("sum(reporte_salidas.negado) as cantidad"),
                    DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                    DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                    DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"))
                ->orderBy("cantidad", "desc")    
                ->get();
                
                $reporte_salida_turno[$key]->drill = $drill;
                $key++;
            }
            $key = 0;
            foreach($reporte_salida_servicio as $row){
               
                $drill = DB::table("reporte_salidas")
                ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                ->leftjoin('servicios', 'servicios.id', '=', 'reporte_salidas.servicio_id')
                ->where(function ($query) {
                    $query->where('reporte_salidas.surtido', '>', 0)
                          ->orWhere('reporte_salidas.negado', '>', 0);
                })
                ->where("servicio_id", "=", $row->servicio_id)
                ->where("reporte_salidas.clues", "like", '%'.$parametros['clues']."%")
                ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                ->groupBy("reporte_salidas.clave")
                ->groupBy("reporte_salidas.clues")
                ->groupBy("reporte_salidas.fecha_realizado")
                ->limit(20)
                ->select("reporte_salidas.clave",
                    "insumos_medicos.descripcion",
                    "reporte_salidas.servicio_id",
                    "servicios.nombre as modulo",
                    "unidades_medicas.nombre",
                    DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                    DB::RAW("sum(reporte_salidas.negado) as negado"),
                    DB::RAW("sum(reporte_salidas.negado) as cantidad"),
                    DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                    DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                    DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"))
                ->orderBy("cantidad", "desc")    
                ->get();
                
                $reporte_salida_servicio[$key]->drill = $drill;
                $key++;
            }
        }
        //$reporte_salida = $reporte_salida->get();
        //$reporte_salida_turno = $reporte_salida_turno->get();

        
        //return dd($reporte_salida_turno);
        //$reporte_salida_servicio = $reporte_salida_servicio->get();

        
        //return Response::json(array("data" => array("salidas"=>$reporte_salida, "turnos"=>$reporte_salida_turno, "servicios"=>$reporte_salida_servicio, "clues"=> $clues, "usuario"=> $usuario->su ) ), 200);                                    
        return Response::json(array("data" => array("salidas"=>$reporte_salida, "turnos"=>$reporte_salida_turno, "servicios"=>$reporte_salida_servicio, "clues"=> $clues, "usuario"=> $usuario->su, "desde"=>$parametros['desde'], "hasta"=>$parametros['hasta'], "cantidad_mes_actual"=> $meses_diferencia ) ), 200);                                    
                                            
    }

    public function catalogos(Request $request)
    {
        $catalogo_turno = Turno::all();
        $catalogo_servicio = Servicio::all();
        $catalogo_clues = UnidadMedica::all();
        return Response::json(array("data" => array( "catalogo_turno"=>$catalogo_turno, "catalogo_servicio"=>$catalogo_servicio, "catalogo_clues"=>$catalogo_clues) ), 200); 
    }

    public function reporteExcel()
    {
    $parametros;
    $parametros = Input::only('desde','hasta','clues', 'orden');

    $obj =  JWTAuth::parseToken()->getPayload();
    $usuario = Usuario::where("id", $obj->get('id'))->with("usuariounidad")->first();
        
    $total    = false;
    if($usuario->su == 1)
        $total = true;
    
    if(!$total)
        if($usuario->usuariounidad->clues)
        {
            $clues = $usuario->usuariounidad->clues;  
            $parametros['clues'] = $clues;
        }
        

    $reporte_salida;
    $unidades = "";
    $fechainicial = new \DateTime($parametros['desde']);
        $fechafinal = new \DateTime($parametros['hasta']);
        $diferencia = $fechainicial->diff($fechafinal);
        $meses_diferencia = ( $diferencia->y * 12 ) + $diferencia->m;
        
        //$clues = "";
        $reporte_salida = DB::table("reporte_salidas")
                            ->join('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                             ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.clave")
                            ->select("reporte_salidas.clave",
                            "insumos_medicos.descripcion",
                            "unidades_medicas.nombre",
                            "reporte_salidas.fecha_realizado",
                            "reporte_salidas.tipo",
                            "reporte_salidas.es_causes",
                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                            
                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));

        $reporte_salida_turno = DB::table("reporte_salidas")
                            ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                            ->leftjoin('turnos', 'turnos.id', '=', 'reporte_salidas.turno_id')
                            ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.turno_id")
                            ->select("reporte_salidas.clave",
                            "insumos_medicos.descripcion",
                            "reporte_salidas.turno_id",
                            "turnos.nombre as modulo",
                            "unidades_medicas.nombre",
                            
                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));

        $reporte_salida_turno_detalles = DB::table("reporte_salidas")
                            ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                            ->leftjoin('turnos', 'turnos.id', '=', 'reporte_salidas.turno_id')
                            ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.clues")
                            ->groupBy("reporte_salidas.turno_id")
                            ->groupBy("reporte_salidas.clave")
                            ->select("reporte_salidas.clave",
                            "insumos_medicos.descripcion",
                            "reporte_salidas.turno_id",
                            "turnos.nombre as modulo",
                            "unidades_medicas.nombre",
                            
                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));

                            
        $reporte_salida_servicio = DB::table("reporte_salidas")
                            ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                            ->leftjoin('servicios', 'servicios.id', '=', 'reporte_salidas.servicio_id')
                            ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.servicio_id")
                            ->select("reporte_salidas.clave",
                            "insumos_medicos.descripcion",
                            "reporte_salidas.servicio_id",
                            "servicios.nombre as modulo",
                            "unidades_medicas.nombre",
                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));

        $reporte_salida_servicio_detalles = DB::table("reporte_salidas")
                            ->leftjoin('insumos_medicos', 'insumos_medicos.clave', '=', 'reporte_salidas.clave')
                            ->join('unidades_medicas', 'unidades_medicas.clues', '=', 'reporte_salidas.clues')
                            ->leftjoin('servicios', 'servicios.id', '=', 'reporte_salidas.servicio_id')
                            ->where(function ($query) {
                                $query->where('reporte_salidas.surtido', '>', 0)
                                      ->orWhere('reporte_salidas.negado', '>', 0);
                            })
                            ->whereBetween("fecha_realizado", [$parametros['desde'],$parametros['hasta']])
                            ->groupBy("reporte_salidas.servicio_id")
                            ->groupBy("reporte_salidas.clues")
                            ->groupBy("reporte_salidas.clave")
                            ->select("reporte_salidas.clave",
                            "insumos_medicos.descripcion",
                            "reporte_salidas.servicio_id",
                            "servicios.nombre as modulo",
                            "unidades_medicas.nombre",
                            DB::RAW("sum(reporte_salidas.surtido) as surtido"),
                            DB::RAW("sum(reporte_salidas.negado) as negado"),
                            DB::RAW("sum(reporte_salidas.surtido_unidosis) as surtido_unidosis"),
                            DB::RAW("sum(reporte_salidas.negado_unidosis) as negado_unidosis"),
                            DB::RAW("(select sum(surtido) from reporte_salidas rs where fecha_realizado between '".$parametros['desde']."' and '".$parametros['hasta']."' and rs.clave=reporte_salidas.clave) as cantidad_anual"));

        if($parametros['clues']!='')
        {
            $reporte_salida = $reporte_salida->where("reporte_salidas.clues", $parametros['clues']);
            $reporte_salida_turno = $reporte_salida_turno->where("reporte_salidas.clues", $parametros['clues']);
            $reporte_salida_servicio = $reporte_salida_servicio->where("reporte_salidas.clues", $parametros['clues']);
            $reporte_salida_turno_detalles = $reporte_salida_turno_detalles->where("reporte_salidas.clues", $parametros['clues']);
            $reporte_salida_servicio_detalles = $reporte_salida_servicio_detalles->where("reporte_salidas.clues", $parametros['clues']);
            
            $clues = DB::table("unidades_medicas")->where("clues", "=", $parametros['clues'])->first();
        }                    


        $reporte_salida = $reporte_salida->orderBy("surtido", "desc")->get();
        $reporte_salida_turno = $reporte_salida_turno->orderBy("surtido", "desc")->get();
        $reporte_salida_servicio = $reporte_salida_servicio->orderBy("surtido", "desc")->get();
        $reporte_salida_turno_detalles = $reporte_salida_turno_detalles->orderBy("surtido", "desc")->get();
        $reporte_salida_servicio_detalles = $reporte_salida_servicio_detalles->orderBy("surtido", "desc")->get();
                                            

    //return Response::json(array("data" => array("salidas"=>$reporte_salida) ), 200);    
    Excel::create("Reporte Salida Medicamentos y Mat. de Curacion ", function($excel) use($reporte_salida, $reporte_salida_turno, $reporte_salida_turno_detalles, $reporte_salida_servicio, $reporte_salida_servicio_detalles, $parametros, $unidades) {

        $excel->sheet('Medicamentos Surtidos y', function($sheet) use($reporte_salida, $parametros, $unidades) {
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $periodo = "";
                $periodo = " de ".$parametros['desde']." a ".$parametros['hasta']." del ".date("Y");

            if($unidades)
                $unidad_medica = $parametros['clues']." - ".$unidades->nombre;  
            else
                $unidad_medica =  "Todos";  
            $sheet->setAutoSize(true);
            
            $sheet->mergeCells('A1:E1');
            $sheet->mergeCells('B2:E2');
            $sheet->mergeCells('B3:E3');

            $sheet->row(1, array(
                'Reporte de Salida de Medicamentos y Material de Curación'
            ));
            $sheet->row(2, array(
                'Periodo de: ',$periodo
            ));
            $sheet->row(3, array(
                'Unidad Medica', $unidad_medica
            ));
            $sheet->row(4, array(
                'CLAVE', 'INSUMO', 'TIPO INSUMO', 'SURTIDO', 'NEGADO'
            ));
            
            $sheet->row(1, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(2, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(3, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(4, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            
            $contador_filas = 5;
            foreach($reporte_salida as $item){
                $contador_filas++;					

                $tipo_insumo = "";
                if($item->tipo == "MC")
                    $tipo_insumo = "MATERIAL DE CURACIÓN";
                else
                {
                    if($item->es_causes == 1)
                        $tipo_insumo = "CAUSES";
                    else
                        $tipo_insumo = "NO CAUSES";    
                }    
                $sheet->appendRow(array(
                    $item->clave,
                    $item->descripcion,
                    $tipo_insumo,
                    $item->surtido,
                    $item->negado                    
                )); 
            }
            
            $sheet->appendRow(array(
                '',
                '',
                'TOTAL',
                "=SUM(D5:D".($contador_filas-1).")",
                "=SUM(E5:E".($contador_filas-1).")"
                
            ));

            $sheet->setBorder("A1:E$contador_filas", 'thin');


        });
        $excel->sheet('Turnos', function($sheet) use($reporte_salida_turno, $parametros, $unidades) {
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $periodo = "";
            
            $periodo = " de ".$parametros['desde']." a ".$parametros['hasta']." del ".date("Y");

            if($unidades)
                $unidad_medica = $parametros['clues']." - ".$unidades->nombre;  
            else
                $unidad_medica =  "Todos";  
            
            //$sheet->getColumnDimension('A')->setWidth(500);
            //$sheet->setAutoSize(true);
            $sheet->setWidth('A', 80);
            $sheet->setWidth('B', 40);
            $sheet->setWidth('Cs', 40);

            $sheet->mergeCells('A1:C1');
            $sheet->mergeCells('B2:C2');
            $sheet->mergeCells('B3:C3');

            $sheet->row(1, array(
                'Reporte de Salida de Medicamentos y Material de Curación por Turnos'
            ));
            $sheet->row(2, array(
                'Periodo de: ',$periodo
            ));
            $sheet->row(3, array(
                'Unidad Medica', $unidad_medica
            ));
            $sheet->row(4, array(
                 'TURNO', 'SURTIDO', 'NEGADO'
            ));
            
            $sheet->row(1, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(2, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(3, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(4, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            
            $contador_filas = 5;
            foreach($reporte_salida_turno as $item){
                $contador_filas++;					

                $sheet->appendRow(array(
                    
                    $item->modulo,
                    $item->surtido,
                    $item->negado                    
                )); 
            }
            
            $sheet->appendRow(array(
                'TOTAL',
                "=SUM(B5:B".($contador_filas-1).")",
                "=SUM(C5:C".($contador_filas-1).")"
                
            ));

            $sheet->setBorder("A1:C$contador_filas", 'thin');


        });
        $excel->sheet('Turnos Detalles', function($sheet) use($reporte_salida_turno_detalles, $parametros, $unidades) {
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $periodo = "";
            
            $periodo = " de ".$parametros['desde']." a ".$parametros['hasta']." del ".date("Y");

            if($unidades)
                $unidad_medica = $parametros['clues']." - ".$unidades->nombre;  
            else
                $unidad_medica =  "Todos";  
            
            $sheet->setAutoSize(true);
            
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('B2:F2');
            $sheet->mergeCells('B3:F3');

            $sheet->row(1, array(
                'Reporte de Salida de Medicamentos y Material de Curación por Turnos Detalles'
            ));
            $sheet->row(2, array(
                'Periodo de: ',$periodo
            ));
            $sheet->row(3, array(
                'Unidad Medica', $unidad_medica
            ));
            $sheet->row(4, array(
                'CLAVE', 'INSUMO', 'UNIDAD', 'TURNO', 'SURTIDO', 'NEGADO'
            ));
            
            $sheet->row(1, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(2, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(3, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(4, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            
            $contador_filas = 5;
            foreach($reporte_salida_turno_detalles as $item){
                $contador_filas++;					

                $sheet->appendRow(array(
                    $item->clave,
                    $item->descripcion,
                    $item->nombre,
                    $item->modulo,
                    $item->surtido,
                    $item->negado                    
                )); 
            }
            
            $sheet->appendRow(array(
                '',
                '',
                '',
                'TOTAL',
                "=SUM(E5:E".($contador_filas-1).")",
                "=SUM(F5:F".($contador_filas-1).")"
                
            ));

            $sheet->setBorder("A1:F$contador_filas", 'thin');


        });
        $excel->sheet('Servicios', function($sheet) use($reporte_salida_servicio, $parametros, $unidades) {
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $periodo = "";
            
                $periodo = " de ".$parametros['desde']." a ".$parametros['hasta']." del ".date("Y");

            if($unidades)
                $unidad_medica = $parametros['clues']." - ".$unidades->nombre;  
            else
                $unidad_medica =  "Todos";  
            
            $sheet->setWidth('A', 80);
            $sheet->setWidth('B', 40);
            $sheet->setWidth('C', 40);
            
            $sheet->mergeCells('A1:C1');
            $sheet->mergeCells('B2:C2');
            $sheet->mergeCells('B3:C3');

            $sheet->row(1, array(
                'Reporte de Salida de Medicamentos y Material de Curación por Servicios'
            ));
            $sheet->row(2, array(
                'Periodo de: ',$periodo
            ));
            $sheet->row(3, array(
                'Unidad Medica', $unidad_medica
            ));
            $sheet->row(4, array(
                'SERVICIO', 'SURTIDO', 'NEGADO'
            ));
            
            $sheet->row(1, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(2, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(3, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(4, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            
            $contador_filas = 5;
            foreach($reporte_salida_servicio as $item){
                $contador_filas++;					

                $sheet->appendRow(array(
                    /*$item->clave,
                    $item->descripcion,
                    $item->nombre,*/
                    $item->modulo,
                    $item->surtido,
                    $item->negado                    
                )); 
            }
            
            $sheet->appendRow(array(
               
                'TOTAL',
                "=SUM(B5:B".($contador_filas-1).")",
                "=SUM(C5:C".($contador_filas-1).")"
                
            ));

            $sheet->setBorder("A1:C$contador_filas", 'thin');


        });
        $excel->sheet('Servicios Detalles', function($sheet) use($reporte_salida_servicio_detalles, $parametros, $unidades) {
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $periodo = "";
            
            $periodo = " de ".$parametros['desde']." a ".$parametros['hasta']." del ".date("Y");

            if($unidades)
                $unidad_medica = $parametros['clues']." - ".$unidades->nombre;  
            else
                $unidad_medica =  "Todos";  
            
            $sheet->setAutoSize(true);
            
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('B2:F2');
            $sheet->mergeCells('B3:F3');

            $sheet->row(1, array(
                'Reporte de Salida de Medicamentos y Material de Curación por Turnos Detalles'
            ));
            $sheet->row(2, array(
                'Periodo de: ',$periodo
            ));
            $sheet->row(3, array(
                'Unidad Medica', $unidad_medica
            ));
            $sheet->row(4, array(
                'CLAVE', 'INSUMO', 'UNIDAD', 'SERVICIO', 'SURTIDO', 'NEGADO'
            ));
            
            $sheet->row(1, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(2, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(3, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            $sheet->row(4, function($row) {
                $row->setBackground('#DDDDDD');
                $row->setFontWeight('bold');
                $row->setFontSize(14);
            });
            
            $contador_filas = 5;
            foreach($reporte_salida_servicio_detalles as $item){
                $contador_filas++;					

                $sheet->appendRow(array(
                    $item->clave,
                    $item->descripcion,
                    $item->nombre,
                    $item->modulo,
                    $item->surtido,
                    $item->negado                    
                )); 
            }
            
            $sheet->appendRow(array(
                '',
                '',
                '',
                'TOTAL',
                "=SUM(E5:E".($contador_filas-1).")",
                "=SUM(F5:F".($contador_filas-1).")"
                
            ));

            $sheet->setBorder("A1:F$contador_filas", 'thin');


        });
        })->export('xls');
    }


}
