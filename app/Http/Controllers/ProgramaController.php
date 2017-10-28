<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Programa;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response;



class ProgramaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $parametros = Input::only('q','page','per_page');
        if ($parametros['q']) {
             $data =  Programa::where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")
                 ->orWhere('nombre','LIKE',"%".$parametros['q']."%")
                 ->orWhere('clave','LIKE',"%".$parametros['q']."%");
             });
        } else {
                 $data =  Programa::where('id','>',0);
               }
        

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $data = $data->paginate($resultadosPorPagina);
        } else {
                 $data = $data->get();
               }
       
        //return Response::json([ 'data' => $data],200);
        return Response::json([ 'data' => $data], 200, [], JSON_NUMERIC_CHECK);
         
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $errors = array(); 

        $mensajes = [
                        'required'      => "required",
                        'unique'        => "unique"
                    ];

        $reglas = [
                        'nombre'        => 'required',
                  ];

        $inputs = Input::only( 'nombre', 'clave', 'status');
        $datos = (object) Input::json()->all();

 
        if(property_exists($datos,'clave'))
        {}else{
            array_push($errors, array(array('Clave' => array('Ingrese la clave'))));
         }
        if(property_exists($datos,'nombre'))
        {}else{
             array_push($errors, array(array('Nombre' => array('Ingrese el nombre'))));
        }
        if(property_exists($datos,'status'))
        {}else{
             array_push($errors, array(array('Status' => array('Ingrese el status'))));
        }

        if( count($errors) > 0 )
                {
                    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
                } 


        $programa = new Programa;
        $programa->clave  = $datos->clave;
        $programa->nombre = $datos->nombre;
        $programa->status = $datos->status;

        $programa_duplicado = Programa::where("clave",$programa->clave)
                                        ->where("nombre",$programa->nombre)
                                        ->first();
        if($programa_duplicado)
        {
             array_push($errors, array(array('Duplicado' => array('Este programa ya existe !'))));
             return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
        }

        try {
           
            $data = $programa;
            $data->save();

            return Response::json([ 'data' => $data ],200);

        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Programa::find($id);

        if(!$data){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

         return Response::json([ 'data' => $data ], HttpResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $mensajes = [
            
            'required'      => "required",
            'unique'        => "unique"
        ];

        $reglas = [
            'nombre'        => 'required',
        ];

        $inputs = Input::only('id','nombre', 'clave', 'status');

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
           $data = Programa::find($id);
           $data->id =  $inputs['id'];
           $data->nombre =  $inputs['nombre'];
           $data->clave =  $inputs['clave'];
           $data->status =  $inputs['status'];
            
            $data->save();
            return Response::json([ 'data' => $data ],200);

        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
			$data = Programa::destroy($id);
			return Response::json(['data'=>$data],200);
		} catch (Exception $e) {
		   return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
		}
    }
}
