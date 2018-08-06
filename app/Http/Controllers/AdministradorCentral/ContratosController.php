<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Contrato, App\Models\ContratoPrecio, App\Models\Insumo, App\Models\Proveedor;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class ContratosController extends Controller
{

    public function proveedores(Request $request){
        return Response::json(['data' => Proveedor::all()],200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   $parametros = Input::only('q','page','per_page');
        
        $items =  Contrato::select('contratos.*', 'proveedores.nombre_corto as proveedor')
                    ->leftJoin('proveedores','contratos.proveedor_id','=','proveedores.id');
        
        
        

        if ($parametros['q']) {
            
            $items = $items->where('proveedores.nombre','LIKE',"%".$parametros['q']."%")->orWhere('contratos.id','LIKE',"%".$parametros['q']."%");
       }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $object = Contrato::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }
        $object->precios;
        //$object =  $object->load("detalles.insumoConDescripcion.informacion","detalles.insumoConDescripcion.generico.grupos");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
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
            'numeric'      => "numeric",
            'date'      => "date",
        ];

        $reglas = [
            //'id'            => 'required|unique:usuarios,id,'.$id,
            'proveedor_id'        => 'required',
            'monto_minimo'         => 'required|numeric',
            'monto_maximo'         => 'required|numeric',
            'fecha_inicio'        => 'required|date',
            'fecha_fin'        => 'required|date',
        ];

        $inputs = Input::only('proveedor_id','monto_minimo',"monto_maximo","fecha_inicio","fecha_fin","activo");

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            $errors =  $v->errors();           
            return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
        }
        $errors = [];
        if($inputs['monto_minimo']>$inputs['monto_maximo']){
            $errors["monto_minimo"] =  ["smaller_than"];           
        }

        if (strtotime($inputs["fecha_inicio"]) > strtotime($inputs["fecha_fin"])) {
            $errors["fecha_inicio"] =  ["smaller_than"];           
        }

        if(count($errors)>0){
            return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            

            if(!isset($inputs["activo"])){
                $inputs["activo"] = false;
            }
            if($inputs["activo"] != false){
                Contrato::where('proveedor_id',$inputs["proveedor_id"])->update(['activo' => false]);            
            }
            $insumo = Contrato::create($inputs);           
            DB::commit();
            return Response::json([ 'data' => $insumo ],200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
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
            'numeric'      => "numeric",
            'date'      => "date",
        ];

        $reglas = [
            //'id'            => 'required|unique:usuarios,id,'.$id,
            'proveedor_id'        => 'required',
            'monto_minimo'         => 'required|numeric',
            'monto_maximo'         => 'required|numeric',
            'fecha_inicio'        => 'required|date',
            'fecha_fin'        => 'required|date',
        ];

        $inputs = Input::only('proveedor_id','monto_minimo',"monto_maximo","fecha_inicio","fecha_fin","activo",'precios');


        $contrato = Contrato::find($id);

        if(!$contrato){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }


        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            $errors =  $v->errors();           
            return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
        }
        $errors = [];
        if($inputs['monto_minimo']>$inputs['monto_maximo']){
            $errors["monto_minimo"] =  ["smaller_than"];           
        }

        if (strtotime($inputs["fecha_inicio"]) > strtotime($inputs["fecha_fin"])) {
            $errors["fecha_inicio"] =  ["smaller_than"];           
        }

        if(count($errors)>0){
            return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            

            if(!isset($inputs["activo"])){
                $inputs["activo"] = false;
            }
            if($inputs["activo"] != false){
                Contrato::where('proveedor_id',$inputs["proveedor_id"])->update(['activo' => false]);            
            }
            
            
            $contrato->activo = $inputs["activo"];
            $contrato->proveedor_id = $inputs["proveedor_id"];
            $contrato->monto_minimo = $inputs["monto_minimo"];
            $contrato->monto_maximo = $inputs["monto_maximo"];
            $contrato->fecha_inicio = $inputs["fecha_inicio"];
            $contrato->fecha_fin = $inputs["fecha_fin"];
            $contrato->save();

            $precios = $contrato->precios()->get();

            foreach($precios as $item){
                 Contrato::destroy($item->id);
            }
           

            $items = [];
            foreach($inputs['precios'] as $item){
                
                $items[] = new ContratoPrecio([
                    "contrato_id" => $id, 
                    "tipo_insumo_id" => $item["tipo_insumo_id"],
                    "proveedor_id" => $contrato->proveedor_id,
                    "insumo_medico_clave" => $item["insumo_medico_clave"],
                    "precio" => $item["precio"]
                ]);
            }
            


            $contrato->precios()->saveMany($items);

DB::rollback();
            //DB::commit();
            return Response::json([ 'data' => $contrato ],200);

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
            //$object = ClavesBasicas::destroy($id);
            //return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function activar(Request $request, $id)
    {
        $item = Contrato::find($id);

        if(!$item){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        DB::beginTransaction();
        try {
           
            Contrato::where('proveedor_id',$item->proveedor_id)->update(['activo' => false]);
            $item->activo = true;
            $item->save();
            DB::commit();
            //DB::rollback();
            return Response::json([ 'data' => $item ],200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
}