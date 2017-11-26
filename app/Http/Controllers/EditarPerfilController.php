<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Usuario;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;

class EditarPerfilController extends Controller
{
/**
     * Editar perfil.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        $mensajes = [
            
            'required'      => "required",
            'email'         => "email",
			'unique'        => "unique",
			'required_with' => "required"
        ];

        $reglas = [
            'id'            => 'required|unique:usuarios,id,'.$id,
			'passwordNuevo'      => 'required_with:cambiarPassword',
			'passwordAnterior'      => 'required_with:cambiarPassword',
            'nombre'        => 'required',
            'apellidos'     => 'required'
        ];
        $object = Usuario::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $inputs = Input::only('id','passwordAnterior','passwordNuevo','nombre', 'apellidos','avatar','cambiarPassword');

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
			if(isset($inputs['cambiarPassword'])){
				if(!Hash::check($inputs['passwordAnterior'], $object->password)){	
					$error = [ 'passwordAnterior' => ["wrong"]];
					return Response::json(['error' => $error], HttpResponse::HTTP_CONFLICT);		
				}
			}
			

			$object->nombre =  $inputs['nombre'];
			$object->apellidos =  $inputs['apellidos'];
			$object->avatar =  $inputs['avatar'];
			$object->id =  $inputs['id'];
			if ($inputs['cambiarPassword'] ){
				$object->password = Hash::make($inputs['passwordNuevo']);
			}
			
			$object->save();
			DB::commit();
			return Response::json([ 'data' => $object ],200);
			
            

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 

	}
}