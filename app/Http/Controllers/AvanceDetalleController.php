<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Avance;
use App\Models\AvanceDetalles;
use Carbon\Carbon;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

class AvanceDetalleController extends Controller
{
    public function index()
    {
        
        $parametros = Input::only('status','q','page','per_page', 'identificador');
        $avancedetalle = DB::table('avance_detalles')->where("avance_id", $parametros['identificador'])->orderBy('created_at', 'desc');
		
       if ($parametros['q']) {
            $avancedetalle =  $avancedetalle->where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")->orWhere('nombre','LIKE',"%".$parametros['q']."%")->orWhere('comentario','LIKE',"%".$parametros['q']."%");
             });
        }

        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $avancedetalle = $avancedetalle->paginate($resultadosPorPagina);
        } else {
            $avancedetalle = $avancedetalle->get();
        }

        return Response::json([ 'data' => $avancedetalle],200);
    }

    public function descargar($id, Request $request)
     {
        try{

            $variables = Input::all();

            /*$usuario = Usuario::with(['roles.permisos'=>function($permisos){
                $permisos->where('id','MrL06vIO12iNhchP14h57Puvg71eUmYb')->orWhere('id','bsIbPL3qv6XevcAyrRm1GxJufDbzLOax');
            }])->find($request->get('usuario_id'));
            
            $tiene_acceso = false;

            if(!$usuario->su){
                $permisos = [];
                foreach ($usuario->roles as $index => $rol) {
                    foreach ($rol->permisos as $permiso) {
                        $permisos[$permiso->id] = true;
                    }
                }
                if(count($permisos)){
                    $tiene_acceso = true;
                }else{
                    $tiene_acceso = false;
                }
            }else{
                $tiene_acceso = true;
            }
            
            if($tiene_acceso){
               $arreglo_log = array('repositorio_id' => $id,
                            'ip' =>$request->ip(),
                            'navegador' =>$request->header('User-Agent'),
                            'accion' => 'DOWNLOAD'); 

                $log_repositorio = LogRepositorio::create($arreglo_log);

                if(!$log_repositorio)
                {
                    return Response::json(['error' => "Error al descargar el archivo"], 500); 
                }*/

                $avancedetalle = AvanceDetalles::find($id);
                $directorio_path = "avances";
                $pathToFile = $directorio_path."//".$id.".".$avancedetalle->extension;
                
                $headers = array(
                    'Content-Type: application/pdf',
                );
                return response()->download($pathToFile, $avancedetalle->nombre_archivo, $headers);
            /*}else{
                return Response::json(['error' =>"No tiene permisos para ingresar a este modulo" ], 500);
            }*/
        }catch(Exception $e){
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }
    public function view($id, Request $request)
     {
        try{

            $variables = Input::all();

            /*$usuario = Usuario::with(['roles.permisos'=>function($permisos){
                $permisos->where('id','MrL06vIO12iNhchP14h57Puvg71eUmYb')->orWhere('id','bsIbPL3qv6XevcAyrRm1GxJufDbzLOax');
            }])->find($request->get('usuario_id'));
            
            $tiene_acceso = false;

            if(!$usuario->su){
                $permisos = [];
                foreach ($usuario->roles as $index => $rol) {
                    foreach ($rol->permisos as $permiso) {
                        $permisos[$permiso->id] = true;
                    }
                }
                if(count($permisos)){
                    $tiene_acceso = true;
                }else{
                    $tiene_acceso = false;
                }
            }else{
                $tiene_acceso = true;
            }
            
            if($tiene_acceso){
               $arreglo_log = array('repositorio_id' => $id,
                            'ip' =>$request->ip(),
                            'navegador' =>$request->header('User-Agent'),
                            'accion' => 'DOWNLOAD'); 

                $log_repositorio = LogRepositorio::create($arreglo_log);

                if(!$log_repositorio)
                {
                    return Response::json(['error' => "Error al descargar el archivo"], 500); 
                }*/

                $avancedetalle = AvanceDetalles::find($id);
                $directorio_path = "avances";
                $pathToFile = $directorio_path."//".$id.".".$avancedetalle->extension;
                
                $headers = array(
                    'Content-Type: application/pdf',
                );
                return Response::make(file_get_contents($pathToFile), 200, [
				    'Content-Type' => 'application/pdf',
				    'Content-Disposition' => 'inline; filename="'.$id.".".$avancedetalle->extension.'"'
				]);
            /*}else{
                return Response::json(['error' =>"No tiene permisos para ingresar a este modulo" ], 500);
            }*/
        }catch(Exception $e){
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
