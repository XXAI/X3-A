<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Avance;
use App\Models\AvanceDetalles;
use App\Models\AvanceUsuarioPrivilegio;
use App\Models\Usuario;
use Carbon\Carbon;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

class AvanceController extends Controller
{
    public function index(Request $request)
    {
        $parametros = Input::only('status','q','page','per_page', 'prioridad', 'estatus', 'visto', 'area');
		
        $usuario = Usuario::find($request->get('usuario_id'));

        $usuario_general = Usuario::with(['roles.permisos'=>function($permisos){
                $permisos->where('id','79B3qKuUbuEiR2qKS0CFgHy2zRWfmO4r');
            }])->find($request->get('usuario_id'));
        $general    = false;
        $normal     = false;
        $permisos = [];
        if($usuario_general->su == 1)
            $general = true;
        else{
            foreach ($usuario_general->roles as $index => $rol) {
                foreach ($rol->permisos as $permiso) {
                    $permisos[$permiso->id] = true;

                    if(count($permisos)){
                        $general = true;
                    }
                }
            }
            if(count($permisos)){
                $general = true;
            }
            
            
            $usuario_normal = Usuario::with(['roles.permisos'=>function($permisos){
                    $permisos->where('id','f0CT1EvFcj4hqK1rNEpsSXhAlhdE9duM');
                }])->find($request->get('usuario_id'));
                $permisos_normal = [];
                foreach ($usuario->roles as $index => $rol) {
                    foreach ($rol->permisos as $permiso) {
                        $permisos_normal[$permiso->id] = true;
                    }
                }
                if(count($permisos_normal)){
                    $normal = true;
                }
        }    

        if($general)
			$avance = DB::table('avances')->whereNull("deleted_at");

		else if($normal)
		{

			$avance = DB::table('avances')
                            ->whereRaw("avances.id in (select avance_id from avance_usuario_privilegio where usuario_id='".$request->get('usuario_id')."')" );
		}else
		{
			return Response::json(['error' => "Error, no tiene permisos "], 500); 
		}

        if($parametros['estatus'] != '')
            $avance = $avance->where("estatus", $parametros['estatus']);

         if($parametros['prioridad'] != '')
            $avance = $avance->where("prioridad", $parametros['prioridad']);
		
       if ($parametros['q']) {
            $avance =  $avance->where(function($query) use ($parametros) {
                 $query->where('area','LIKE',"%".$parametros['q']."%")->orWhere('tema','LIKE',"%".$parametros['q']."%")->orWhere('responsable','LIKE',"%".$parametros['q']."%");
             });
        }

        if (isset($parametros['area']) && $parametros['area']!='') {
            $avance =  $avance->where(function($query) use ($parametros) {
                 $query->where('area', $parametros['area']);
             });
        }

        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $avance = $avance->paginate($resultadosPorPagina);
        } else {
            $avance = $avance->get();
        }
         
         $visualizados = array();
         $no_visualizados = array();
        foreach ($avance as $key => $value) {
        	$avanceDetalle = AvanceDetalles::where("avance_id", $avance[$key]->id)->orderBy('id', 'desc')->first();
            $avances_sin_visualizar = AvanceDetalles::where("avance_id", $avance[$key]->id)
                                                        ->whereRaw("(avance_detalles.created_at > (select if(count(updated_at) = 0, '0000-00-00', updated_at) from avance_visualizacion where usuario_id='".$request->get('usuario_id')."' and avance_id='".$avance[$key]->id."'))");

            $avance[$key]->visualizaciones = $avances_sin_visualizar->count();                                            
        	if($avanceDetalle)
        	{
        		$avance[$key]->porcentaje = $avanceDetalle->porcentaje;
                $array_days = explode("-", substr($avanceDetalle->created_at,0,10));
                $avance[$key]->creacion = Carbon::createFromDate($array_days[0],$array_days[1],$array_days[2])->diffInDays(Carbon::now());
                $avance[$key]->comentario_detalle = $avanceDetalle->comentario;
                

        	}
        	else
        	{
        		$avance[$key]->porcentaje = "0.00";
        		$avance[$key]->creacion = -1;
                $avance[$key]->comentario_detalle = '';
        	}
            
            if($avance[$key]->visualizaciones == 0)
                $visualizados[] = $avance[$key];
            else
                $no_visualizados[] = $avance[$key];

            
        }
        
        $response = $avance->toArray();
        if($parametros['visto'] == 1)
            $response['data'] = $visualizados;
        else if($parametros['visto'] == 2)
            $response['data'] = $no_visualizados;

        $response['total'] = count($response['data']);
            
        $aux = array();
        
        foreach ($response['data'] as $key => $value) {
            foreach ($response['data'] as $key2 => $value2) {
                if($response['data'][$key]->creacion < $response['data'][$key2]->creacion)
                 {
                     $aux = $response['data'][$key];
                     $response['data'][$key] = $response['data'][$key2];
                     $response['data'][$key2] = $aux;   
                 }   
            }
            
        }

        return Response::json([ 'data' => $response],200);
    }

    public function show(Request $request, $id){
        
        $parametros = Input::all();
        
        $avance = Avance::find($id);

        if(isset($parametros['informacion']))
        {
            $avance = $avance->load('avanceDetalles', "usuario");
            $avance->actualizacion = count($avance->avanceDetalles);
            $avance->creado_por = $avance->usuario->nombre." ".$avance->usuario->apellidos;
        }

       
        
        if(!$avance){
            return Response::json(['error' => "No se encuentra el tema que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
        	return Response::json([ 'data' => $avance],200);
        }            
        
    }


     public function store(Request $request){
        $mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'tema'        	=> 'required',
            'responsable'   => 'required',
            'area'          => 'required',
            'comentario'	=> 'required|date'
        ];

        $parametros = Input::all();

        try {
            DB::beginTransaction();
            
            $parametros['usuario_id'] = $request->get('usuario_id');
            $avance = Avance::create($parametros);

            $arreglo_privilegios = array("avance_id"    => $avance->id,
                                         "usuario_id"   => $request->get('usuario_id'),
                                         "agregar"      => 1,
                                         "editar"       => 1,
                                         "eliminar"     => 1);
            $avance_usuario = AvanceUsuarioPrivilegio::create($arreglo_privilegios);
            DB::commit();
            return Response::json([ 'data' => $avance ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function update(Request $request, $id){
       

        $parametros = Input::all();

        try {
            DB::beginTransaction();
            
            $privilegios = AvanceUsuarioPrivilegio::where("avance_id",$id)->where("usuario_id", $request->get('usuario_id'))->first();
            $usuario = Usuario::find($request->get('usuario_id'));    

            if($usuario->su == 1 || (isset($privilegios) && $privilegios->editar == 1 ))
            {
                $avance = Avance::find($id);
                $avance->update($parametros);
            }else{
                return Response::json([ 'error' => "No tiene permisos para hacer esta modificacion" ],401);
            }   

            DB::commit();
            return Response::json([ 'data' => $avance ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    function destroy(Request $request, $id){
        try {
            $avance = Avance::find($id);
                
            if($avance){
                   $avance->delete();
                }else{
                    return Response::json(['error' => 'Este avance ya no puede eliminarse'], 500);
                }
           
            //$object = Pedido::where('almacen_proveedor',$request->get('almacen_id'))->where('id',$id)->delete();
            return Response::json(['data'=>$avance],200);
        
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }

    }

    public function areas(Request $request){
        try {
            DB::beginTransaction();
            
            $avance = Avance::groupBy('area')->get();

            $usuario = Usuario::find($request->get('usuario_id'));

            $usuario_general = Usuario::with(['roles.permisos'=>function($permisos){
                    $permisos->where('id','79B3qKuUbuEiR2qKS0CFgHy2zRWfmO4r');
                }])->find($request->get('usuario_id'));
            $general    = false;
            $normal     = false;
            $permisos = [];
            if($usuario_general->su == 1)
                $general = true;
            else{
                foreach ($usuario_general->roles as $index => $rol) {
                    foreach ($rol->permisos as $permiso) {
                        $permisos[$permiso->id] = true;

                        if(count($permisos)){
                            $general = true;
                        }
                    }
                }
                if(count($permisos)){
                    $general = true;
                }
            }
            
            DB::commit();
            return Response::json([ 'data' => array('datos'=>$avance, 'general'=>$general) ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
}
