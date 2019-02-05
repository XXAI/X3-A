<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\PresupuestoEjercicio, App\Models\PresupuestoUnidadMedica,  App\Models\UnidadMedica, App\Models\Jurisdiccion;
use App\Models\PresupuestoMovimientoEjercicio, App\Models\PresupuestoMovimientoUnidadMedica;

use App\Models\PedidoOrdinario, App\Models\PedidoOrdinarioUnidadMedica;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class PedidosOrdinariosController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   $parametros = Input::only('q','page','per_page');
        
        $items =  PedidoOrdinario::select('pedidos_ordinarios.*')->orderBy('id','desc');
                    //->leftJoin('proveedores','contratos.proveedor_id','=','proveedores.id');        

        if ($parametros['q']) {
            
            $items = $items->where('pedidos_ordinarios.descripcion','LIKE',"%".$parametros['q']."%")->orWhere('pedidos_ordinarios.id','LIKE',"%".$parametros['q']."%");
       }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
    }

    public function presupuesto(Request $request){
        $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                

        if($presupuesto){            
            $presupuesto->presupuesto_unidades_medicas = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->with('unidadMedica')->orderBy('clues','asc')->get();
        } else {
            $presupuesto = [];
        }
        
        return Response::json([ 'data' => $presupuesto],200);
    }

    public function aumentarPresupuesto(Request $request, $id)
    {
        //AKIRA PENDIENTE
        $pedidoOrdinario = PedidoOrdinario::find($id);

        if(!$pedidoOrdinario){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $input = Input::all();

        if(isset($input["pedido_ordinario_unidad_medica"])){
            $pedidoOrdinarioUnidadMedica = PedidoOrdinarioUnidadMedica::find($input["pedido_ordinario_unidad_medica"]["id"]);

            if(!$pedidoOrdinarioUnidadMedica){
                return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
            }

            

            if($pedidoOrdinarioUnidadMedica->pedido_ordinario_id != $pedidoOrdinario->id){
                return Response::json(['error' => "El pedido ordinario no corresponde al de la unidad médica."], HttpResponse::HTTP_NOT_FOUND);
            }
            // Aumentar presupuesto especifico
            return Response::json(['data' => "presupeusto específico ajustado."], HttpResponse::HTTP_OK);
        } else {
            //Aumentar el presupuesto a todos
            return Response::json(['data' => "Todos los presupuestos ezxcedidos ajustados."],HttpResponse::HTTP_OK);
        }
        



       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    public function show(Request $request, $id)
    {
        $object = PedidoOrdinario::find($id);

        if(!$object){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

       
       /* $precios = $object->precios;
        foreach($precios as $precio){
            $precio->tipo;
            $precio->insumo;
        }*/
       
        
        $object =  $object->load("pedidosOrdinariosUnidadesMedicas.unidadMedica");

        return Response::json([ 'data' => $object ], HttpResponse::HTTP_OK);
    }

    public function store(Request $request){
        $mensajes = [            
            'required'      => "required",
            'numeric'       => "numeric",
            'integer'       => "integer",
            'unique'        => "unique",
            'min'           => "min"
        ];

        $reglas = [
            //'id'            => 'required|unique:usuarios,id,'.$id,            
            'descripcion'           => 'required',
            'fecha'        => 'required|date',
            'fecha_expiracion'     => 'required|date',
            'pedidos_ordinarios_unidades_medicas' => 'array',
            'pedidos_ordinarios_unidades_medicas.*.causes_autorizado' => 'required|numeric|min:0',
            'pedidos_ordinarios_unidades_medicas.*.no_causes_autorizado' => 'required|numeric|min:0'
        ];

        $inputs = Input::only('descripcion','fecha',"fecha_expiracion","pedidos_ordinarios_unidades_medicas");

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            $errors =  $v->errors();           
            return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try{
            $presupuesto = PresupuestoEjercicio::where('activo',1)->first();

            if($presupuesto){
                
                
                $inputs['fecha_expiracion'] =  date("Y-m-d H:i:s", strtotime($inputs["fecha_expiracion"]));
               
                $inputs['presupuesto_ejercicio_id'] = $presupuesto->id;
                $pedido_ordinario = PedidoOrdinario::create($inputs);

                $items = [];
    
                $error = false;
                $errors = [];
                $i = 0;
                foreach($inputs['pedidos_ordinarios_unidades_medicas'] as $item){
                    
                    $items[] = new PedidoOrdinarioUnidadMedica([
                        "pedido_ordinario_id" => $pedido_ordinario->id, 
                        "clues" =>$item["clues"],
                        "causes_autorizado" => $item["causes_autorizado"],
                        "causes_modificado" => $item["causes_autorizado"],
                        "causes_disponible" => $item["causes_autorizado"],
                        "no_causes_autorizado" => $item["no_causes_autorizado"],
                        "no_causes_modificado" => $item["no_causes_autorizado"],
                        "no_causes_disponible" => $item["causes_autorizado"],
                    ]);
                    $presupuesto_unidad_medica = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->where("clues",$item["clues"])->first();

                    if($presupuesto_unidad_medica){
                     
                        if($item["causes_autorizado"] > 0){
                            $presupuesto_unidad_medica->causes_disponible = $presupuesto_unidad_medica->causes_disponible - $item["causes_autorizado"];
                            $presupuesto_unidad_medica->causes_comprometido = $presupuesto_unidad_medica->causes_comprometido + $item["causes_autorizado"];
                        }
        
                        if($item["no_causes_autorizado"] > 0){
                            $presupuesto_unidad_medica->no_causes_disponible = $presupuesto_unidad_medica->no_causes_disponible - $item["no_causes_autorizado"];
                            $presupuesto_unidad_medica->no_causes_comprometido = $presupuesto_unidad_medica->no_causes_comprometido + $item["no_causes_autorizado"];
                        }
                        $presupuesto_unidad_medica->save();
                    } else {
                        
                        $errors["pedidos_ordinarios_unidades_medicas.".$i.".causes_autorizado"] = ["budget"];
                        $errors["pedidos_ordinarios_unidades_medicas.".$i.".no_causes_autorizado"] = ["budget"];
                        $error = true;
                    }
                    
                    $i++;
                    
                }

                if(!$error){
                    $pedido_ordinario->pedidosOrdinariosUnidadesMedicas()->saveMany($items);
                    $pedido_ordinario->pedidosOrdinariosUnidadesMedicas;
                    //DB::rollback();
                    DB::commit();
                    return Response::json([ 'data' => $pedido_ordinario],200);
                }   else {
                    DB::rollback();
                    return Response::json(['error' =>$errors], HttpResponse::HTTP_CONFLICT);
                } 
                
            } else {
                DB::rollback();
            return Response::json(['error' => "No hay presupuesto"], HttpResponse::HTTP_CONFLICT);
            }

           

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function descargarFormato(Request $request){

        $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                

        if($presupuesto){            
            $presupuesto->presupuesto_unidades_medicas = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->with('unidadMedica')->orderBy('clues','asc')->get();
        } else {
            return Response::json(['error' => "No hay presupuesto"], HttpResponse::HTTP_CONFLICT);
        }
        
      //  $unidades_medicas = UnidadMedica::where('activa',1)->orderBy('jurisdiccion_id','asc','clues','asc')->get();


        
        Excel::create("Formato de carga de pedidos ordinarios", function($excel) use($presupuesto) {


            $excel->sheet('Presupuesto '.$presupuesto->ejercicio, function($sheet) use($presupuesto)  {
                $sheet->setAutoSize(true);
                $sheet->row(1, array(
                    'Clues',
                    'Tipo',
                    'Nombre',
                    'Jurisdicción',
                    '$ CAUSES',
                    '$ CAUSES Disponible',
                    '$ NO CAUSES',                    
                    '$ NO CAUSES Disponible'
                ));
                $sheet->cells("A1:F1", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                });

                $factor_meses = $presupuesto->factor_meses | 0;
                $c = 0;
                foreach($presupuesto->presupuesto_unidades_medicas as $item){
                    $unidad_medica = $item->unidadMedica;
                    $causes=  $item->causes_modificado / $factor_meses;
                    if($causes > $item->causes_disponible){
                      $causes =  $item->causes_disponible;
                    }
        
                    $no_causes  = $item->no_causes_modificado / $factor_meses;
                    if($no_causes > $item->no_causes_disponible){
                      $no_causes =   $item->no_causes_disponible;
                    }

                    if($no_causes > 0 || $causes > 0){
                        $unidad_medica->jurisdiccion;
                        $sheet->appendRow(array(
                            $item->clues,
                            $unidad_medica->tipo,
                            $unidad_medica->nombre,
                            $unidad_medica->jurisdiccion? $unidad_medica->jurisdiccion->numero." - ".$unidad_medica->jurisdiccion->nombre :  "",
                            number_format($causes,2),                            
                            $item->causes_disponible,
                            number_format($no_causes,2),
                            $item->no_causes_disponible
                        )); 
                        $c++;
                    }

                    
                }
                
                if($c > 0){
                    $c++;
                    $sheet->setColumnFormat(array(
                        "E2:H$c" => '"$" #,##0.00_-',
                    ));
                }
                
                //$sheet->getProtection()->setSheet(true);
                //$sheet->getStyle("A1:E$c")->getProtection()->setLocked(\PHPExcel_Style_Protection::PROTECTION_UNPROTECTED);
                //$sheet->getStyle("G1:G$c")->getProtection()->setLocked(\PHPExcel_Style_Protection::PROTECTION_UNPROTECTED);
                $sheet->setAutoFilter('A1:H1');
                    $sheet->protectCells("F2:F$c", 'PHPExcel');
                    $sheet->protectCells("H2:H$c", 'PHPExcel');
                
                $sheet->cells("F1:F$c", function($cells) {
                   $cells->setBackground('#DDDDDD');
                });

                $sheet->cells("H1:H$c", function($cells) {
                    $cells->setBackground('#DDDDDD');
                });

                
            });
            


           


         })->export('xls');
    }

    public function cargarExcel(Request $request){
        ini_set('memory_limit', '-1');

        try{
            if ($request->hasFile('archivo')){
				$file = $request->file('archivo');

				if ($file->isValid()) {
                    $path = $file->getRealPath();

                    $presupuesto = PresupuestoEjercicio::where('activo',1)->first();
                
                    $unidades_medicas_con_presupuesto = [];

                    if($presupuesto){            
                        $unidades_medicas_con_presupuesto = PresupuestoUnidadMedica::where('presupuesto_id',$presupuesto->id)->with('unidadMedica')->orderBy('clues','asc')->get();
                    } else {
                        return Response::json(['error' => "No hay presupuesto"], HttpResponse::HTTP_CONFLICT);
                    }

                    $unidades_medicas = [];
                    $total_causes = 0;
                    $total_no_causes = 0;
                    $con_errores = false;
                    Excel::load($file, function($reader) use (&$unidades_medicas,&$unidades_medicas_con_presupuesto, &$total_causes, &$total_no_causes, &$con_errores) {
                        $objExcel = $reader->getExcel();
                        $sheet = $objExcel->getSheet(0);
                        $highestRow = $sheet->getHighestRow();
                        $highestColumn = $sheet->getHighestColumn();
        
                        //  Loop through each row of the worksheet in turn
                        DB::beginTransaction();
                       
                        for ($row = 2; $row <= $highestRow; $row++)
                        {
                            //  Read a row of data into an array
                            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row,
                                NULL, TRUE, FALSE);
                            $data =  $rowData[0];
                            


                            /*
                                0 'CLUES',
                                1 'TIPO',
                                2 'NOMBRE',
                                3 'JURISDICCION'
                                4 'CAUSES'
                                5 'CAUSES Disponible'
                                6 'NO CAUSES'
                                7 'NO CAUSES Disponible'
                            */
                            
                            $unidad_medica = null;
                            $causes_disponible = 0;
                            $no_causes_disponible = 0;
                            for($i = 0; $i < count($unidades_medicas_con_presupuesto);$i++){
                                $presupuesto_um = $unidades_medicas_con_presupuesto[$i];
                                if($presupuesto_um['clues'] == $data[0]){
                                    $unidad_medica = UnidadMedica::find($data[0]);
                                    $causes_disponible = $presupuesto_um['causes_disponible'];
                                    $no_causes_disponible = $presupuesto_um['no_causes_disponible'];
                                    break;
                                }
                            }
                            

                            if($unidad_medica){
                                $causes = floatval($data[4]);
                                $total_causes += $causes;

                                $no_causes = floatval($data[6]);
                                $total_no_causes += $no_causes;

                                $um = array(
                                    'clues'=>$data[0],
                                    'unidad_medica' => $unidad_medica,
                                    'causes_autorizado' => $causes,
                                    'causes_disponible' => $causes_disponible,
                                    'no_causes_autorizado' => $no_causes,
                                    'no_causes_disponible' => $no_causes_disponible
                                );
                                if($causes > $causes_disponible){
                                    $um['error'] = true;
                                    $um['error_causes'] = true;
                                }
                                if($no_causes > $no_causes_disponible){
                                    $um['error'] = true;
                                    $um['error_no_causes'] = true;
                                }
                                $unidades_medicas[] = $um;
                            } else {
                                
                                $fake_unidad_medica =  new UnidadMedica();
                                $fake_unidad_medica->clues = $data[0];
                                $fake_unidad_medica->tipo = $data[1];
                                $fake_unidad_medica->nombre = $data[2];
                                $fake_unidad_medica->jurisdiccion = null;
                                $unidades_medicas[] = array(
                                    'clues'=>$data[0],
                                    'unidad_medica' => $fake_unidad_medica,
                                    'causes_autorizado' => floatval($data[4]),
                                    'causes_disponible' => 0,
                                    'no_causes_autorizado' => floatval($data[5]),
                                    'no_causes_disponible' => 0,
                                    'error' => true,
                                    'no_existe' => true
                                );
                                $con_errores = true;
                            }
                            
                        }
                        
                       

                        DB::rollback();
                    });

                    $presupuesto = array(
                        'causes' => $total_causes,
                        'no_causes' => $total_no_causes,
                        'pedidos_ordinarios_unidades_medicas' => $unidades_medicas,
                        'con_errores' => $con_errores

                    );

					return Response::json([ 'data' => $presupuesto],200);

				} else {
					throw new \Exception("Archivo inválido.");
				}
			} else {
				throw new \Exception("No hay archivo.");
			}
        } catch(\Exception $e){
            DB::rollback();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    } 
 }