<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Avance;
use App\Models\AvanceDetalles;
use App\Models\Usuario;
use Carbon\Carbon;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

class AvanceController extends Controller
{
    public function index(Request $request)
    {
        
        $parametros = Input::only('status','q','page','per_page');
		
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

        //return Response::json([ 'data' => $general." , ".$normal],200);
		if($general)
			$avance = DB::table('avances')->whereNull("deleted_at");
		else if($normal)
		{
			$avance = DB::table('avances')->where("usuario_id", $request->get('usuario_id'))->whereNull("deleted_at");
		}else
		{
			return Response::json(['error' => "Error, no tiene permisos "], 500); 
		}
		
       if ($parametros['q']) {
            $avance =  $avance->where(function($query) use ($parametros) {
                 $query->where('area','LIKE',"%".$parametros['q']."%")->orWhere('tema','LIKE',"%".$parametros['q']."%")->orWhere('responsable','LIKE',"%".$parametros['q']."%");
             });
        }

        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $avance = $avance->paginate($resultadosPorPagina);
        } else {
            $avance = $avance->get();
        }
         
        foreach ($avance as $key => $value) {
        	$avanceDetalle = AvanceDetalles::where("avance_id", $avance[$key]->id)->orderBy('id', 'desc')->first();
        	if($avanceDetalle)
        	{
        		$avance[$key]->porcentaje = $avanceDetalle->porcentaje;
                $array_days = explode("-", substr($avanceDetalle->created_at,0,10));
                $avance[$key]->creacion = Carbon::createFromDate($array_days[0],$array_days[1],$array_days[2])->diffInDays(Carbon::now());
                $avance[$key]->comenntario_detalle = $avanceDetalle->comentario;
        	}
        	else
        	{
        		$avance[$key]->porcentaje = "0.00";
        		$avance[$key]->creacion = -1;
                $avance[$key]->comenntario_detalle = '';
        	}
        }
        $aux = array();
        foreach ($avance as $key => $value) {
            foreach ($avance as $key2 => $value2) {
                if($avance[$key]->creacion < $avance[$key2]->creacion)
                 {
                     $aux = $avance[$key];
                     $avance[$key] = $avance[$key2];
                     $avance[$key2] = $aux;   
                 }   
            }
            
        }

        return Response::json([ 'data' => $avance],200);
    }

    public function show(Request $request, $id){
      
        $avance = Avance::find($id);
        
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
            
            $avance = Avance::create($parametros);

            DB::commit();
            return Response::json([ 'data' => $avance ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function update(Request $request, $id){
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
            
            $avance = Avance::find($id);
            $avance->update($parametros);

            DB::commit();
            return Response::json([ 'data' => $avance ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
}
