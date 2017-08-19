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

class AvanceController extends Controller
{
    public function index()
    {
        
        $parametros = Input::only('status','q','page','per_page');
		$avance = Avance::getModel();
		
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
        	$avanceDetalle = AvanceDetalles::where("avance_id", $avance[$key]['id'])->orderBy('id', 'desc')->first();
        	if($avanceDetalle)
        	{
        		$avance[$key]['porcentaje'] = $avanceDetalle->porcentaje;
        		$avance[$key]['creacion'] = $avanceDetalle->created_at;
        	}
        	else
        	{
        		$avance[$key]['porcentaje'] = "0";
        		$avance[$key]['creacion'] = Null;
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
