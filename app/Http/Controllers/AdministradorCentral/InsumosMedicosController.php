<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\ClavesBasicas, App\Models\ClavesBasicasDetalle,App\Models\ClavesBasicasUnidadMedica,App\Models\UnidadMedica;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

use App\Models\Insumo, App\Models\Medicamento, App\Models\MaterialCuracion, App\Models\PresentacionesMedicamentos, App\Models\UnidadMedida, App\Models\ViasAdministracion;

class InsumosMedicosController extends Controller
{

    public function presentaciones(Request $request){
        return Response::json(['data' => PresentacionesMedicamentos::all()],200);
    }
    public function unidadesMedida(Request $request){
        return Response::json(['data' => UnidadMedida::all()],200);
    }

    public function viasAdministracion(Request $request){
        return Response::json(['data' => viasAdministracion::all()],200);
    }
     

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   $parametros = Input::only('q','page','per_page');
        if ($parametros['q']) {
             $items =  Insumo::where('descripcion','LIKE',"%".$parametros['q']."%")->orWhere('clave','LIKE',"%".$parametros['q']."%");
        } else {
             $items =  Insumo::select('*');
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $mensajes = [            
            'required'      => "required",
            'required_if'      => "required",
            'unique'      => "unique",
        ];

        $reglas = [
            //'id'            => 'required|unique:usuarios,id,'.$id,
            'clave'        => 'required|unique:insumos_medicos',
            'tipo'         => 'required',
            'descripcion'        => 'required',
            'medicamento.presentacion_id'        => 'required_if:tipo,"ME"',
            'medicamento.unidad_medida_id'        => 'required_if:tipo,"ME"',
            'medicamento.cantidad_x_envase'        => 'required_if:tipo,"ME"',
            'medicamento.contenido'        => 'required_if:tipo,"ME"',
            'material_curacion.unidad_medida_id'        => 'required_if:tipo,"MC"',
            'material_curacion.cantidad_x_envase'        => 'required_if:tipo,"MC"',
        ];

        $inputs = Input::only('clave','tipo',"es_causes","es_unidosis","tiene_fecha_caducidad","descontinuado","descripcion","medicamento","material_curacion", "atencion_medica","salud_publica");

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $inputs["generico_id"] = "1689";// Esto está así en la base de datos no se porque decidieron ponerle el mismo generico id a todo pero bueno :/

            if(!isset($inputs["tiene_fecha_caducidad"])){
                $inputs["tiene_fecha_caducidad"] = false;
            }
            if(!isset($inputs["descontinuado"])){
                $inputs["descontinuado"] = false;
            }
            
            $insumo = Insumo::create($inputs);
            
            if($inputs["tipo"]=="ME"){
                $medicamento = new Medicamento($inputs["medicamento"]);
                $insumo->medicamento()->save($medicamento);
            } else {
                $material_curacion = new MaterialCuracion($inputs["material_curacion"]);
                $insumo->materialCuracion()->save($material_curacion);
            }
            
            /*
            $items = [];
            foreach($inputs['items'] as $item){
                $items[] = new ClavesBasicasDetalle([
                    'insumo_medico_clave' => $item
                ]);
            }

            $clavesBasicas->detalles()->saveMany($items);
*/
            DB::commit();
            return Response::json([ 'data' => $insumo ],200);

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
        $object = Insumo::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }
        if($object->tipo == "ME"){
            $object->medicamento;
        }
        if($object->tipo == "MC"){
            $object->materialCuracion;
        }
        //$object =  $object->load("detalles.insumoConDescripcion.informacion","detalles.insumoConDescripcion.generico.grupos");

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
            'required_if'      => "required",
            'unique'      => "unique",
        ];

        $reglas = [
            'clave'        => 'required|unique:insumos_medicos,clave,'.$id.',clave',
            'tipo'         => 'required',
            'descripcion'        => 'required',
            'medicamento.presentacion_id'        => 'required_if:tipo,"ME"',
            'medicamento.unidad_medida_id'        => 'required_if:tipo,"ME"',
            'medicamento.cantidad_x_envase'        => 'required_if:tipo,"ME"',
            'medicamento.contenido'        => 'required_if:tipo,"ME"',
            'material_curacion.unidad_medida_id'        => 'required_if:tipo,"MC"',
            'material_curacion.cantidad_x_envase'        => 'required_if:tipo,"MC"',
        ];

        $inputs = Input::only('clave','tipo',"es_causes","es_unidosis","tiene_fecha_caducidad","descontinuado","descripcion","medicamento","material_curacion", "atencion_medica","salud_publica");


        $insumo = Insumo::find($id);

        if(!$insumo){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }
        
        
        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {

            
            if($insumo->tipo == "ME"){
                $medicamento = $insumo->medicamento();
            } else {
 
                $material_curacion = $insumo->materialCuracion();
            }
            

            $insumo->clave = $inputs['clave'];
            $insumo->tipo = $inputs['tipo'];
            $insumo->es_causes = !isset($inputs['es_causes'])? false : $inputs['es_causes'] ;
            $insumo->es_unidosis = !isset($inputs['es_unidosis'])? false : $inputs['es_unidosis'];
            $insumo->descontinuado = !isset($inputs['descontinuado'])? false : $inputs['descontinuado'];
            $insumo->descripcion = $inputs['descripcion'];
            $insumo->atencion_medica = !isset($inputs['atencion_medica'])? false : $inputs['atencion_medica'] ;
            $insumo->salud_publica = !isset($inputs['salud_publica'])? false : $inputs['salud_publica'] ;
          

            if($inputs["tipo"]=="ME"){
                if($medicamento){
                   // $insumo->medicamento->update($inputs["medicamento"]);
                   $medicamento->update($inputs["medicamento"]);
                } else {
                    $medicamento = new Medicamento($inputs["medicamento"]);
                    $insumo->medicamento()->save($medicamento);
                }
                
            } else {
                if($material_curacion){
                    $material_curacion->update($inputs["material_curacion"]);
                 } else {
                     $material_curacion = new Medicamento($inputs["material_curacion"]);
                     $insumo->materialCuracion()->save($material_curacion);
                 }
            }
            $insumo->save();


            /*
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

            $clavesBasicas->detalles()->saveMany($items);*/

            DB::commit();
            return Response::json([ 'data' => $insumo ],200);

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


    public function exportarExcel(Request $request){

        $medicamentos = Insumo::where("tipo","ME")->with("medicamento","medicamento.UnidadMedida","medicamento.PresentacionMedicamento","medicamento.ViaAdministracion")->get();
        $material_curacion = Insumo::where("tipo","MC")->with("materialCuracion","materialCuracion.UnidadMedida")->get();
        
        Excel::create("Insumos médicos SIAL - ".date('Y-m-d'), function($excel) use($medicamentos, $material_curacion) {


            $excel->sheet('Medicamentos', function($sheet) use($medicamentos) {
                $sheet->setAutoSize(true);
                $sheet->row(1, array(
                    'Clave', 'Tipo','Causes','Unidosis','Tiene Fecha Caducidad','Descontinuado','Descripción',
                    'Presentación','Controlado','Surfactante','Concentración','Contenido','Cantidad X Envase','Unidad de medida', 'Vía de Administración','Dosis','Indicaciones'
                ));
                $sheet->cells("A1:Q1", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                });

                $contador_filas = 1;
                foreach($medicamentos as $item){
                    $contador_filas++;
                    $sheet->appendRow(array(
                        $item->clave,
                        $item->tipo,
                        $item->es_causes?1:0,
                        $item->es_unidosis?1:0,
                        $item->tiene_fecha_caducidad?1:0,
                        $item->descontinuado?1:0,
                        $item->descripcion,
                        $item->medicamento->PresentacionMedicamento != null ? $item->medicamento->PresentacionMedicamento->nombre: "",
                        $item->medicamento->es_controlado?1:0,
                        $item->medicamento->es_surfactante?1:0,
                        $item->medicamento->concentracion,
                        $item->medicamento->contenido,
                        $item->medicamento->cantidad_x_envase,
                        $item->medicamento->UnidadMedida != null ? $item->medicamento->unidadMedida->nombre ." (".$item->medicamento->unidadMedida->clave.")": "",
                        $item->medicamento->ViaAdministracion != null ? $item->medicamento->ViaAdministracion->nombre: "",
                        $item->medicamento->dosis,
                        $item->medicamento->indicaciones
                    )); 
                }

                $sheet->setAutoFilter('A1:Q1');
            });

            $excel->sheet('Material de Curación', function($sheet) use($material_curacion) {
                $sheet->setAutoSize(true);
                $sheet->row(1, array(
                    'Clave', 'Tipo','Causes','Unidosis','Tiene Fecha Caducidad','Descontinuado','Descripción',
                    'Cantidad X Envase','Unidad de medida'
                ));
                $sheet->cells("A1:I1", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                });

                $contador_filas = 1;
                foreach($material_curacion as $item){
                    $contador_filas++;
                    $sheet->appendRow(array(
                        $item->clave,
                        $item->tipo,
                        $item->es_causes?1:0,
                        $item->es_unidosis?1:0,
                        $item->tiene_fecha_caducidad?1:0,
                        $item->descontinuado?1:0,
                        $item->descripcion,
                        $item->materialCuracion->cantidad_x_envase,
                        $item->materialCuracion->UnidadMedida != null ? $item->materialCuracion->unidadMedida->nombre ." (".$item->materialCuracion->unidadMedida->clave.")": "",
                    )); 
                }
                $sheet->setBorder("A1:I$contador_filas", 'thin');
                $sheet->setAutoFilter('A1:I1');
            });


            
           
           $excel->setActiveSheetIndex(0);

           


         })->export('xls');
    }   
}
