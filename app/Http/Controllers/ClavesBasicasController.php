<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\ClavesBasicas, App\Models\ClavesBasicasDetalle;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;

class ClavesBasicasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        $items = ClavesBasicas::where('clues',$request->get('clues'))->get();
        return Response::json([ 'data' => $items],200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $mensajes = [            
            'required'      => "required",
            'array'        => "array"
        ];

        $reglas = [
            'nombre'        => 'required',
            'tipo'        => 'required',
            'items'        => 'required|array',
        ];

        $inputs = Input::only('nombre','tipo','items');

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
           


            $inputs['clues'] = $request->get('clues');

            $comprobar = ClavesBasicas::where('clues',$inputs['clues'])->where('tipo',$inputs['tipo'])->first();
            if($comprobar){
                return Response::json(['error' =>  ['tipo' => ['unique']]], HttpResponse::HTTP_CONFLICT);
            }

            $clavesBasicas = ClavesBasicas::create($inputs);

            $items = [];
            foreach($inputs['items'] as $item){
                $items[] = new ClavesBasicasDetalle([
                    'insumo_medico_clave' => $item
                ]);
            }

            $clavesBasicas->detalles()->saveMany($items);

            DB::commit();
            return Response::json([ 'data' => $clavesBasicas ],200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $object = ClavesBasicas::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }
        if($request->get('clues') != $object->clues){
            return Response::json(['error' =>  'No tienes permiso para editar esta lista'], HttpResponse::HTTP_FORBIDDEN);
        }
        $object =  $object->load("detalles.insumoConDescripcion.informacion","detalles.insumoConDescripcion.generico.grupos");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
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
            'array'        => "array"
        ];

        $reglas = [
            'nombre'        => 'required',
            'tipo'        => 'required',
            'items'        => 'required|array',
        ];

        $inputs = Input::only('nombre','tipo','items');
        $inputs['clues'] = $request->get('clues');

        $clavesBasicas = ClavesBasicas::find($id);

        if(!$clavesBasicas){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }
        
        if($inputs['clues'] != $clavesBasicas->clues){
            return Response::json(['error' =>  'No tienes permiso para editar esta lista'], HttpResponse::HTTP_FORBIDDEN);
        }
        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {

            $comprobar = ClavesBasicas::where('id','!=',$clavesBasicas->id)->where('clues',$inputs['clues'])->where('tipo',$inputs['tipo'])->first();
            if($comprobar){
                return Response::json(['error' =>  ['tipo' => ['unique']]], HttpResponse::HTTP_CONFLICT);
            }


            $clavesBasicas->nombre = $inputs['nombre'];
            $clavesBasicas->tipo = $inputs['tipo'];
            $clavesBasicas->save();

            $detalles = $clavesBasicas->detalles()->get();

            foreach($detalles as $item){
                 ClavesBasicasDetalle::destroy($item->id);
            }
           


            $items = [];
            foreach($inputs['items'] as $item){
                $items[] = new ClavesBasicasDetalle([
                    'insumo_medico_clave' => $item
                ]);
            }

            $clavesBasicas->detalles()->saveMany($items);

            DB::commit();
            return Response::json([ 'data' => $clavesBasicas ],200);

        } catch (\Exception $e) {
            DB::rollback();
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
            $object = ClavesBasicas::destroy($id);
            return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }
}
