<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use JWTAuth;

use App\Http\Requests;
use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\Usuario;
use App\Models\Presupuesto;
use App\Models\UnidadMedica;
use App\Models\UnidadMedicaPresupuesto;
use \Excel;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


class PedidoController extends Controller{
    public function obtenerDatosPresupuesto(Request $request){
        try{
            $obj =  JWTAuth::parseToken()->getPayload();
            $usuario = Usuario::with('almacenes')->find($obj->get('id'));

            if(count($usuario->almacenes) > 1){
                //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
                return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
            }else{
                $almacen = $usuario->almacenes[0];
            }

            $parametros = Input::all();

            $presupuesto = Presupuesto::where('activo',1)->first();

            $presupuesto_unidad_medica = UnidadMedicaPresupuesto::select('clues',
                                            DB::raw('sum(causes_autorizado) as causes_autorizado'),DB::raw('sum(causes_modificado) as causes_modificado'),DB::raw('sum(causes_comprometido) as causes_comprometido'),DB::raw('sum(causes_devengado) as causes_devengado'),DB::raw('sum(causes_disponible) as causes_disponible'),
                                            DB::raw('sum(no_causes_autorizado) as no_causes_autorizado'),DB::raw('sum(no_causes_modificado) as no_causes_modificado'),DB::raw('sum(no_causes_comprometido) as no_causes_comprometido'),DB::raw('sum(no_causes_devengado) as no_causes_devengado'),DB::raw('sum(no_causes_disponible) as no_causes_disponible'),
                                            DB::raw('sum(material_curacion_autorizado) as material_curacion_autorizado'),DB::raw('sum(material_curacion_modificado) as material_curacion_modificado'),DB::raw('sum(material_curacion_comprometido) as material_curacion_comprometido'),DB::raw('sum(material_curacion_devengado) as material_curacion_devengado'),DB::raw('sum(material_curacion_disponible) as material_curacion_disponible'))
                                            ->where('presupuesto_id',$presupuesto->id)
                                            ->where('clues',$almacen->clues)
                                            ->where('proveedor_id',$almacen->proveedor_id)
                                            ->groupBy('clues');
            if(isset($parametros['mes'])){
                if($parametros['mes']){
                    $presupuesto_unidad_medica = $presupuesto_unidad_medica->where('mes',$parametros['mes']);
                }
            }

            $presupuesto_unidad_medica = $presupuesto_unidad_medica->first();
            return Response::json([ 'data' => $presupuesto_unidad_medica],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function stats(Request $request){
        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        // Hay que obtener la clues del usuario
        $pedidos = Pedido::select(DB::raw(
            '
            count(
                1
            ) as todos,
            count(
                case when status = "BR" then 1 else null end
            ) as borradores,
            count(
                case when status = "ET" then 1 else null end
            ) as en_transito,
            count(
                case when status = "PS" then 1 else null end
            ) as por_surtir,
            count(
                case when status = "FI" then 1 else null end
            ) as finalizados,
            count(
                case when status = "EX" then 1 else null end
            ) as expirados
            '
        ))->where('almacen_solicitante',$almacen->id)->where('clues',$almacen->clues)->first();

        return Response::json($pedidos,200);
    }

    public function index(Request $request){
        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }
        
        $parametros = Input::only('status','q','page','per_page');

        $pedidos = Pedido::with("insumos", "acta", "tipoInsumo", "tipoPedido","almacenSolicitante","almacenProveedor");

       if ($parametros['q']) {
            $pedidos =  $pedidos->where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")->orWhere('descripcion','LIKE',"%".$parametros['q']."%")->orWhere('folio','LIKE',"%".$parametros['q']."%");
             });
        }

        $pedidos = $pedidos->where('almacen_solicitante',$almacen->id)->where('clues',$almacen->clues);

        if(isset($parametros['status'])) {
            $pedidos = $pedidos->where("pedidos.status",$parametros['status']);
        }

        $pedidos = $pedidos->select('pedidos.*',DB::raw('datediff(fecha_expiracion,current_date()) as expira_en_dias'));
        
        //$pedido = Pedido::with("insumos", "acta", "TipoInsumo", "TipoPedido")->get();
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
            $pedidos = $pedidos->paginate($resultadosPorPagina);
        } else {
            $pedidos = $pedidos->get();
        }

        return Response::json([ 'data' => $pedidos],200);
    }

    public function show(Request $request, $id){
    	$pedido = Pedido::find($id);
        
        if(!$pedido){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }else{
            if($pedido->status == 'BR'){
                $pedido = $pedido->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos");
            }else{
                $pedido = $pedido->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos", "tipoInsumo", "tipoPedido", "almacenProveedor","almacenSolicitante.unidadMedica","proveedor","encargadoAlmacen","director");
            }
        }

        return Response::json([ 'data' => $pedido],200);
    }

    public function store(Request $request){
        $mensajes = [
            'required'      => "required",
        ];

        $reglas = [
            'tipo_pedido_id'        => 'required',
            'descripcion'           => 'required',
            'fecha'                 => 'required|date',
            //'almacen_solicitante'   => 'required',
            //'almacen_proveedor'     => 'required',
            //'observaciones'         => 'required',
            'status'                => 'required',
            //'tipo_insumo_id'        => 'required',
            //'pedido_padre'          => 'required',
            //'folio'                 => 'required',
            //'organismo_dirigido'    => 'required',
            //'acta_id'               => 'required',
            //'usuario_validacion'    => 'required',
            //'proveedor_id'          => 'required'
        ];

        $parametros = Input::all();

        //return Response::json([ 'data' => $parametros ],500);
        /*if(count($parametros) == 1){
            $parametros = $parametros[0];
        }*/

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], 500);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        if($almacen->nivel_almacen == 1 && $almacen->tipo_almacen == 'ALMPAL'){
            $reglas['proveedor_id'] = 'required';
            $parametros['datos']['proveedor_id'] = $almacen->proveedor_id;
            $parametros['datos']['almacen_proveedor'] = null;
        }elseif($almacen->nivel_almacen == 2){
            $reglas['almacen_proveedor'] = 'required';
        }
        
        $parametros['datos']['almacen_solicitante'] = $almacen->id;
        $parametros['datos']['clues'] = $almacen->clues;
        $parametros['datos']['status'] = 'BR'; //estatus de borrador
        $parametros['datos']['tipo_pedido_id'] = 'PA'; //tipo de pedido Pedido de Abatecimiento

        $fecha = date($parametros['datos']['fecha']);
        $fecha_expiracion = strtotime("+20 days", strtotime($fecha));
        $parametros['datos']['fecha_expiracion'] = date("Y-m-d", $fecha_expiracion);

        $v = Validator::make($parametros['datos'], $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        if(count($parametros['insumos']) == 0){
            return Response::json(['error' => 'Se necesita capturar al menos un insumo'], 500);
        }

        try {
            DB::beginTransaction();
            
            $pedido = Pedido::create($parametros['datos']);

            $total_claves = count($parametros['insumos']);
            $total_insumos = 0;
            $total_monto = 0;
            $monto_iva = 0;

            foreach ($parametros['insumos'] as $key => $value) {
                $reglas_insumos = [
                    'clave'           => 'required',
                    'cantidad'        => 'required|integer|min:1'
                ];  

                $v = Validator::make($value, $reglas_insumos, $mensajes);

                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => 'El insumo con clave: '.$value['clave'].' tiene un valor incorrecto.'], 500);
                }      
                
                $insumo = [
                    'insumo_medico_clave' => $value['clave'],
                    'cantidad_solicitada' => $value['cantidad'],
                    'monto_solicitado' => $value['monto'],
                    'precio_unitario' => $value['precio'],
                    'pedido_id' => $pedido->id
                ];
                //$value['pedido_id'] = $pedido->id;

                $total_insumos += $value['cantidad'];
                $total_monto += $value['monto'];

                if($value['tipo'] == 'MC'){
                    $monto_iva += $value['monto'];
                }

                $object_insumo = PedidoInsumo::create($insumo);
            }

            if($monto_iva > 0){
                $total_monto += $monto_iva*16/100;
            }
            
            $pedido->total_claves_solicitadas = $total_claves;
            $pedido->total_cantidad_solicitada = $total_insumos;
            $pedido->total_monto_solicitado = $total_monto;
            $pedido->save();

            DB::commit();
            return Response::json([ 'data' => $pedido ],200);

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
            //'tipo_pedido_id'        => 'required',
            'descripcion'           => 'required',
            'fecha'                 => 'required|date',
            //'almacen_solicitante'   => 'required',
            //'almacen_proveedor'     => 'required',
            //'observaciones'         => 'required',
            'status'                => 'required',
            //'tipo_insumo_id'        => 'required',
            //'pedido_padre'          => 'required',
            //'folio'                 => 'required',
            //'organismo_dirigido'    => 'required',
            //'acta_id'               => 'required',
            //'usuario_validacion'    => 'required',
            //'proveedor_id'          => 'required'
        ];

        $parametros = Input::all();

        /*if(count($parametros) == 1){
            $parametros = $parametros[0];
        }*/

        $obj =  JWTAuth::parseToken()->getPayload();
        $usuario = Usuario::with('almacenes')->find($obj->get('id'));

        if(count($usuario->almacenes) > 1){
            //Harima: Aqui se checa si el usuario tiene asignado mas de un almacen, se busca en el request si se envio algun almacen seleccionado desde el cliente, si no marcar error
            return Response::json(['error' => 'El usuario tiene asignado mas de un almacen'], HttpResponse::HTTP_CONFLICT);
        }else{
            $almacen = $usuario->almacenes[0];
        }

        if($almacen->nivel_almacen == 1 && $almacen->tipo_almacen == 'ALMPAL'){
            $reglas['proveedor_id'] = 'required';
            $parametros['datos']['proveedor_id'] = $almacen->proveedor_id;
            $parametros['datos']['almacen_proveedor'] = null;
        }elseif($almacen->nivel_almacen == 2){
            $reglas['almacen_proveedor'] = 'required';
        }
        
        //$parametros['datos']['tipo_pedido_id'] = 1;

        if(!isset($parametros['datos']['status'])){
            $parametros['datos']['status'] = 'BR'; //estatus Borrador
        }elseif($parametros['datos']['status'] == 'CONCLUIR'){
            if($almacen->nivel_almacen == 1 && $almacen->tipo_almacen == 'ALMPAL'){
                $parametros['datos']['status'] = 'PS';
            }elseif($almacen->nivel_almacen == 2){
                $parametros['datos']['status'] = 'ET';
            }
        }else{
            $parametros['datos']['status'] = 'BR';
        }

        $fecha = date($parametros['datos']['fecha']);
        $fecha_expiracion = strtotime("+20 days", strtotime($fecha));
        $parametros['datos']['fecha_expiracion'] = date("Y-m-d", $fecha_expiracion);
        
        $v = Validator::make($parametros['datos'], $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $pedido = Pedido::find($id);

             DB::beginTransaction();

            $pedido->update($parametros['datos']);

            $arreglo_insumos = Array();
            
            PedidoInsumo::where("pedido_id", $id)->delete();

            $total_claves = count($parametros['insumos']);
            $total_insumos = 0;
            $total_monto = ['causes' => 0, 'no_causes' => 0, 'material_curacion' => 0];

            foreach ($parametros['insumos'] as $key => $value) {

                $reglas_insumos = [
                    'clave'           => 'required',
                    'cantidad'        => 'required|integer|min:1'
                ];  

                $v = Validator::make($value, $reglas_insumos, $mensajes);

                if ($v->fails()) {
                    DB::rollBack();
                    return Response::json(['error' => 'El insumo con clave: '.$value['clave'].' tiene un valor incorrecto.'], 500);
                }      
                
                $insumo = [
                    'insumo_medico_clave' => $value['clave'],
                    'cantidad_solicitada' => $value['cantidad'],
                    'monto_solicitado' => $value['monto'],
                    'precio_unitario' => $value['precio'],
                    'pedido_id' => $pedido->id
                ];
                //$value['pedido_id'] = $pedido->id;

                $total_insumos += $value['cantidad'];

                if($value['tipo'] == 'ME' && $value['es_causes']){
                    $total_monto['causes'] += $value['monto'];
                }elseif($value['tipo'] == 'ME' && !$value['es_causes']){
                    $total_monto['no_causes'] += $value['monto'];
                }else{
                    $total_monto['material_curacion'] += $value['monto'];
                }
                
                PedidoInsumo::create($insumo);  
            }

            if($total_monto['material_curacion'] > 0){
                $total_monto['material_curacion'] += $total_monto['material_curacion']*16/100;
            }

            if(!$pedido->folio && $pedido->status != 'BR'){
                $max_folio = Pedido::where('clues',$almacen->clues)->max('folio');
                $anio = date('Y');
                if(!$max_folio){
                    $prox_folio = 1;
                }else{
                    $max_folio = explode('-',$max_folio);
                    $prox_folio = intval($max_folio[3]) + 1;
                }
                $pedido->folio = $almacen->clues . '-' . $anio . '-PA-' . str_pad($prox_folio, 3, "0", STR_PAD_LEFT);
            }

            if($pedido->status == 'PS'){
                $fecha = explode('-',$pedido->fecha);
                $presupuesto = Presupuesto::where('activo',1)->first();
                $presupuesto_unidad = UnidadMedicaPresupuesto::where('presupuesto_id',$presupuesto->id)
                                            ->where('clues',$almacen->clues)
                                            ->where('proveedor_id',$almacen->proveedor_id)
                                            ->where('mes',$fecha[1])
                                            ->where('anio',$fecha[0])
                                            ->first();
                if(!$presupuesto_unidad){
                    DB::rollBack();
                    return Response::json(['error' => 'No existe presupuesto asignado al mes y/o año del pedido'], 500);
                }
                
                $presupuesto_unidad->causes_comprometido += $total_monto['causes'];
                $presupuesto_unidad->causes_disponible -= $total_monto['causes'];

                $presupuesto_unidad->no_causes_comprometido += $total_monto['no_causes'];
                $presupuesto_unidad->no_causes_disponible -= $total_monto['no_causes'];

                $presupuesto_unidad->material_curacion_comprometido += $total_monto['material_curacion'];
                $presupuesto_unidad->material_curacion_disponible -= $total_monto['material_curacion'];

                if($presupuesto_unidad->causes_disponible < 0 || $presupuesto_unidad->no_causes_disponible < 0 || $presupuesto_unidad->material_curacion_disponible < 0){
                    DB::rollBack();
                    return Response::json(['error' => 'El presupuesto es insuficiente para este pedido, los cambios no se guardaron.', 'data'=>$presupuesto_unidad], 500);
                }else{
                    $presupuesto_unidad->save();
                }
            }

            $almacen->load('unidadMedica');

            $pedido->director_id = $almacen->unidadMedica->director_id;
            $pedido->encargado_almacen_id = $almacen->encargado_almacen_id;

            $pedido->total_claves_solicitadas = $total_claves;
            $pedido->total_cantidad_solicitada = $total_insumos;
            $pedido->total_monto_solicitado = $total_monto['causes'] + $total_monto['no_causes'] + $total_monto['material_curacion'];
            $pedido->save();
             
             DB::commit(); 

            return Response::json([ 'data' => $pedido ],200);

        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage(), 'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    function destroy($id){
        try {
            $object = Pedido::destroy($id);
            return Response::json(['data'=>$object],200);
        } catch (Exception $e) {
           return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }

    }

    public function generarExcel(Request $request, $id) {
        $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
        $data = [];

        $pedido = Pedido::find($id);
        
        if(!$pedido){
            return Response::json(['error' => "No se encuentra el pedido que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $pedido->load("insumos.insumosConDescripcion","insumos.insumosConDescripcion.informacion","insumos.insumosConDescripcion.generico.grupos", "tipoInsumo", "tipoPedido", "almacenProveedor","almacenSolicitante.unidadMedica","proveedor","encargadoAlmacen","director");

        //$usuario = JWTAuth::parseToken()->getPayload();
        //$configuracion = Configuracion::where('clues',$usuario->get('clues'))->first();

        $fecha = explode('-',$pedido->fecha);
        $fecha[1] = $meses[$fecha[1]];
        $pedido->fecha = $fecha;
        
        //return Response::json(['data'=>$pedido],500);
        /*
        $pdf = PDF::loadView('pdf.requisiciones', $data);
        $pdf->output();
        $dom_pdf = $pdf->getDomPDF();
        $canvas = $dom_pdf->get_canvas();
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $canvas->page_text(($w/2)-10, ($h-40), "{PAGE_NUM} de {PAGE_COUNT}", null, 10, array(0, 0, 0));

        return $pdf->stream($data['acta']->folio.'Requisiciones.pdf');
        */

        $nombre_archivo = $pedido->folio;  

        Excel::create($nombre_archivo, function($excel) use($pedido) {

            $excel->sheet('Insumos', function($sheet) use($pedido) {
                $sheet->setAutoSize(true);

                $sheet->mergeCells('A1:G1');
                $sheet->row(1, array('FOLIO: '.$pedido->folio));

                $sheet->mergeCells('A2:G2'); 
                $sheet->row(2, array('UNIDAD MEDICA: '.$pedido->almacenSolicitante->unidadMedica->nombre));

                $sheet->mergeCells('A3:G3'); 
                $sheet->row(3, array('PROVEEDOR: '.$pedido->proveedor->nombre));

                $sheet->mergeCells('A4:G4'); 
                $sheet->row(4, array('FECHA: '.$pedido->fecha[2]." DE ".$pedido->fecha[1]." DEL ".$pedido->fecha[0]));

                $sheet->mergeCells('A5:G5'); 
                $sheet->row(5, array(''));

                $sheet->mergeCells('A6:G6');
                $sheet->row(6, array(''));

                $sheet->row(7, array(
                    '','No. DE LOTE', 'CLAVE','DESCRIPCIÓN DE LOS INSUMOS','CANTIDAD','PRECIO UNITARIO','PRECIO TOTAL'
                ));

                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(16);
                });
                $sheet->row(2, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $sheet->row(3, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $sheet->row(4, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $sheet->row(5, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $sheet->row(6, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $sheet->row(7, function($row) {
                    // call cell manipulation methods
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');

                });
            });
            /*
            foreach($requisiciones as $index => $requisicion) {
                $excel->sheet($tipo, function($sheet) use($requisicion,$acta,$unidad) {
                            $contador_filas = 7;
                            foreach($requisicion->insumos as $indice => $insumo){
                            
                              if($acta->estatus < 3){
                                    $sheet->appendRow(array(
                                        $insumo['lote'], 
                                        $insumo['clave'],
                                        $insumo['descripcion'],
                                        $insumo['pivot']['cantidad'],
                                        $insumo['unidad'],
                                        $insumo['precio'],
                                        $insumo['pivot']['total']
                                    ));
                              } else{
                                    $sheet->appendRow(array(
                                        $insumo['lote'], 
                                        $insumo['clave'],
                                        $insumo['descripcion'],
                                        $insumo['pivot']['cantidad_validada'],
                                        $insumo['unidad'],
                                        $insumo['precio'],
                                        $insumo['pivot']['total_validado']
                                    ));
                              }
				
                                
                                $contador_filas += 1;
                            }
                            if($acta->estatus < 3){
                                $sheet->appendRow(array(
                                        '', 
                                        '',
                                        '',
                                        '',
                                        '',
                                        'SUBTOTAL',
                                        $requisicion->sub_total
                                    ));
                            } else {
                                $sheet->appendRow(array(
                                        '', 
                                        '',
                                        '',
                                        '',
                                        '',
                                        'SUBTOTAL',
                                        $requisicion->sub_total_validado
                                    ));
                            }

                            if($acta->estatus < 3){
                                $sheet->appendRow(array(
                                        '', 
                                        '',
                                        '',
                                        '',
                                        '',
                                        'IVA',
                                        $requisicion->iva
                                    ));
                            } else {
                                $sheet->appendRow(array(
                                        '', 
                                        '',
                                        '',
                                        '',
                                        '',
                                        'IVA',
                                        $requisicion->iva_validado
                                    ));
                            }

                            if($acta->estatus < 3){
                                $sheet->appendRow(array(
                                        '', 
                                        '',
                                        '',
                                        '',
                                        '',
                                        'TOTAL',
                                        $requisicion->gran_total
                                    ));
                            } else {
                                $sheet->appendRow(array(
                                        '', 
                                        '',
                                        '',
                                        '',
                                        '',
                                        'TOTAL',
                                        $requisicion->gran_total_validado
                                    ));
                            }
                            $contador_filas += 3;

                            $sheet->setBorder("A1:G$contador_filas", 'thin');


                            $sheet->cells("F1:G$contador_filas", function($cells) {

                                $cells->setAlignment('right');

                            });

                            $sheet->cells("A7:A$contador_filas", function($cells) {
                                $cells->setAlignment('center');
                            });
                            $sheet->cells("B7:B$contador_filas", function($cells) {
                                $cells->setAlignment('center');
                            });
                            $sheet->cells("D7:D$contador_filas", function($cells) {
                                $cells->setAlignment('center');
                            });

                            $sheet->setColumnFormat(array(
                                "F8:G$contador_filas" => '"$"#,##0.00_-'
                            ));
                });
            }
            */
        })->export('xls');
    }
}
