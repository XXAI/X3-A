<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Permiso;
use App\Models\UnidadMedica;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response;
use DB;



class AutoCompleteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function grupo_permiso()
    {
        $parametros = Input::only('term');
        
		$data =  Permiso::where(function($query) use ($parametros) {
		 	$query->where('grupo','LIKE',"%".$parametros['term']."%");
		});
        
        $variable = $data->distinct()->select(DB::raw("grupo as nombre"))->get();    
        $data = [];    
       	foreach ($variable as $key => $value) {
       		$data[] = $value->nombre;
       	}
        return Response::json([ 'data' => $data],200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function clues()
    {
        $parametros = Input::only('term');
        
		$data =  UnidadMedica::where(function($query) use ($parametros) {
		 	$query->where('clues','LIKE',"%".$parametros['term']."%")
		 	->orWhere('nombre','LIKE',"%".$parametros['term']."%");
		});
        
        $data = $data->get();

        return Response::json([ 'data' => $data],200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function insumos()
    {
        $parametros = Input::only('term', 'clues', 'almacen');

        // $CLAVES = CluesClaves::where('clues',$clues)->get("clave");
///////// agregar abajo de los where para limitar a las claves de la unidad
        // ->whereIn('im.clave',$CLAVES)
        
        $data1 =  DB::table("insumos_medicos AS im")->distinct()->select("im.clave", "im.tipo","g.nombre",DB::raw("um.nombre AS unidad_medida"), "m.cantidad_x_envase", "im.es_causes", "im.es_unidosis", "im.descripcion", DB::raw("'' AS codigo_barras"))
        ->leftJoin('stock AS s', 's.clave_insumo_medico', '=', 'im.clave')
        ->leftJoin('genericos AS g', 'g.id', '=', 'im.generico_id')
        ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
        ->leftJoin('unidades_medida AS um', 'um.id', '=', 'm.unidad_medida_id')
        ->where('almacen_id', $parametros['almacen'])
        ->where(function($query) use ($parametros) {
            $query->where('im.clave','LIKE',"%".$parametros['term']."%")
            ->orWhere('g.nombre','LIKE',"%".$parametros['term']."%")
            ->orWhere('im.descripcion','LIKE',"%".$parametros['term']."%")
            ->orWhere('s.codigo_barras','LIKE',"%".$parametros['term']."%");
        });
        

        $parametros = Input::only('term', 'clues', 'almacen');
        
        $data2 =  DB::table("insumos_medicos AS im")->distinct()->select("im.clave", "im.tipo", "g.nombre",DB::raw("um.nombre AS unidad_medida"), "m.cantidad_x_envase", "im.es_causes", "im.es_unidosis", "im.descripcion", DB::raw("'' AS codigo_barras"))
        ->leftJoin('genericos AS g', 'g.id', '=', 'im.generico_id')
        ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
        ->leftJoin('unidades_medida AS um', 'um.id', '=', 'm.unidad_medida_id')
        ->where(function($query) use ($parametros) {
            $query->where('im.clave','LIKE',"%".$parametros['term']."%")
            ->orWhere('g.nombre','LIKE',"%".$parametros['term']."%")
            ->orWhere('im.descripcion','LIKE',"%".$parametros['term']."%");
        });
        
        
        $data = $data1->union($data2);
        $data = $data->groupBy("clave")->get();

        return Response::json([ 'data' => $data],200);
    }    
}