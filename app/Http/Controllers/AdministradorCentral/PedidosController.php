<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Proveedor, App\Models\Presupuesto, App\Models\UnidadMedicaPresupuesto, App\Models\Pedido, App\Models\Insumo, App\Models\Almacen;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class PedidosController extends Controller
{
    public function presupuesto(Request $request){
        try{
          //  $almacen = Almacen::find($request->get('almacen_id'));

            $parametros = Input::all();

            $presupuesto = Presupuesto::where('activo',1)->first();

            $presupuesto_unidad_medica = UnidadMedicaPresupuesto::select(
                                            DB::raw('sum(causes_autorizado) as causes_autorizado'),DB::raw('sum(causes_modificado) as causes_modificado'),DB::raw('sum(causes_comprometido) as causes_comprometido'),DB::raw('sum(causes_devengado) as causes_devengado'),DB::raw('sum(causes_disponible) as causes_disponible'),
                                            DB::raw('sum(no_causes_autorizado) as no_causes_autorizado'),DB::raw('sum(no_causes_modificado) as no_causes_modificado'),DB::raw('sum(no_causes_comprometido) as no_causes_comprometido'),DB::raw('sum(no_causes_devengado) as no_causes_devengado'),DB::raw('sum(no_causes_disponible) as no_causes_disponible'),
                                            DB::raw('sum(material_curacion_autorizado) as material_curacion_autorizado'),DB::raw('sum(material_curacion_modificado) as material_curacion_modificado'),DB::raw('sum(material_curacion_comprometido) as material_curacion_comprometido'),DB::raw('sum(material_curacion_devengado) as material_curacion_devengado'),DB::raw('sum(material_curacion_disponible) as material_curacion_disponible'))
                                            ->where('presupuesto_id',$presupuesto->id);
                                           // ->where('clues',$almacen->clues)
                                            //->where('proveedor_id',$almacen->proveedor_id)
                                            //->groupBy('clues');
            
            $items = Pedido::select('pedidos.*','unidades_medicas.jurisdiccion_id',DB::raw('month(fecha) as mes'))->leftjoin('unidades_medicas','unidades_medicas.clues','=','pedidos.clues');

            if (isset($parametros['q']) &&  $parametros['q'] != "") {
                $items = $items->where(function($query) use ($parametros){
                    $query
                        ->where('unidad_medica','LIKE',"%".$parametros['q']."%")
                        ->orWhere('clues','LIKE',"%".$parametros['q']."%")
                        ->orWhere('folio','LIKE',"%".$parametros['q']."%")
                        ->orWhere('descripcion','LIKE',"%".$parametros['q']."%");
                });
            } 

            $fecha_desde = isset($parametros['fecha_desde']) ? $parametros['fecha_desde'] : '';
            $fecha_hasta = isset($parametros['fecha_hasta']) ? $parametros['fecha_hasta'] : '';

            if ($fecha_desde != "" && $fecha_hasta != "" ) {
                $items = $items->whereBetween('fecha',[$fecha_desde, $fecha_hasta]);
            } 

            if ($fecha_desde != "" && $fecha_hasta == "" ) {
                $items = $items->where('fecha',">=",$fecha_desde);
            } 

            if ($fecha_desde == "" && $fecha_hasta != "" ) {
                $items = $items->where('fecha',"<=",$fecha_hasta);
            } 
            
            if(isset($parametros['status']) && $parametros['status'] != ""){
                $status = explode(',',$parametros['status']);            
                if(count($status)>0){
                    $items = $items->whereIn('status',$status);
                }              
            }

            if(isset($parametros['proveedores']) && $parametros['proveedores'] != ""){
                $proveedores = explode(',',$parametros['proveedores']);            
                if(count($proveedores)>0){
                    $items = $items->whereIn('proveedor_id',$proveedores);
                }              
            }

            if(isset($parametros['jurisdicciones']) && $parametros['jurisdicciones'] != ""){
                $jurisdicciones = explode(',',$parametros['jurisdicciones']);            
                if(count($jurisdicciones)>0){
                    $items = $items->whereIn('jurisdiccion_id',$jurisdicciones);
                }              
            }

            $items = $items->get();

            $meses = $items->lists('mes');
            $clues = $items->lists('clues');

            $presupuesto_unidad_medica = $presupuesto_unidad_medica->whereIn('mes',$meses);
            $presupuesto_unidad_medica = $presupuesto_unidad_medica->whereIn('clues',$clues);
            /*if(isset($parametros['mes'])){
                if($parametros['mes']){
                    $presupuesto_unidad_medica = $presupuesto_unidad_medica->where('mes',$parametros['mes']);
                }
            }

            if(isset($parametros['proveedores'])){
                if($parametros['proveedores']){
                    $proveedores_ids = explode(',',$parametros['proveedores']);
                    $presupuesto_unidad_medica = $presupuesto_unidad_medica->whereIn('proveedor_id',$proveedores_ids);
                }
            }*/

            $presupuesto_unidad_medica = $presupuesto_unidad_medica->first();
            return Response::json([ 'data' => $presupuesto_unidad_medica],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function lista()
    {
        $parametros = Input::only('q','status','proveedores','jurisdicciones','page','per_page', 'fecha_desde','fecha_hasta', 'ordenar_causes','ordenar_no_causes','ordenar_material_curacion');

        $items = self::getItemsQuery($parametros);
        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function recepcion($id, Request $request){
        try{
            $pedido = Pedido::with("recepciones.movimiento")
                 ->where("id",$id)->first();

            
            foreach ($pedido->recepciones as $key => $value) {
                //$pedido->recepciones->insumos = 0;
                $arreglo = array();     
                $arreglo = $value;
                
                $insumos = DB::table("stock")
                                ->whereRaw("id in (select stock_id from movimiento_insumos where movimiento_id='".$value['movimiento_id']."')")
                                ->select(DB::RAW("count(distinct(clave_insumo_medico)) as cantidad_insumos"))
                                ->first();

                $arreglo['cantidad_insumos'] = $insumos->cantidad_insumos;
                
                $pedido->recepciones[$key]  = $arreglo;
            }     
            return Response::json([ 'data' => $pedido],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
    
    public function regresarBorrador($id, Request $request){
        try{
            DB::beginTransaction();
            
            $pedido = Pedido::find($id);
            $pedido->status = "BR";
            $pedido->save();

            $almacen = Almacen::find($pedido->almacen_solicitante);

            if(!$almacen){
                return Response::json(['error' =>"No se encontró el almacen."], 500);
            }
            
            $proveedor = Proveedor::with('contratoActivo')->find($almacen->proveedor_id);

            $contrato_activo = $proveedor->contratoActivo;
            $insumos = Insumo::conDescripcionesPrecios($contrato_activo->id, $proveedor->id)->select("precio", "clave", "insumos_medicos.tipo", "es_causes", "insumos_medicos.tiene_fecha_caducidad", "contratos_precios.tipo_insumo_id", "medicamentos.cantidad_x_envase")->withTrashed()->get();
            $lista_insumos = array();
            foreach ($insumos as $key => $value) {
                $array_datos = array();
                $array_datos['precio']              = $value['precio'];
                $array_datos['clave']               = $value['clave'];
                $array_datos['tipo']                = $value['tipo'];
                $array_datos['tipo_insumo_id']      = $value['tipo_insumo_id'];
                $array_datos['es_causes']           = $value['es_causes'];
                $array_datos['caducidad']           = $value['tiene_fecha_caducidad'];
                $array_datos['cantidad_unidosis']   = $value['cantidad_x_envase'];
                $lista_insumos[$value['clave']]     = $array_datos;
            }

            $pedido = $pedido->load("insumos");

            $total_causes               = 0;
            $total_no_causes            = 0;
            $total_material_curacion    = 0;

            foreach ($pedido->insumos as $key => $value) {
                if($lista_insumos[$value['insumo_medico_clave']]['tipo'] == "ME")
                {
                    if($lista_insumos[$value['insumo_medico_clave']]['es_causes']== 1)
                    {
                        $total_causes += $value['monto_solicitado'];
                    }else
                    {
                        $total_no_causes += $value['monto_solicitado'];
                    }
                }else
                {
                    $total_material_curacion += ($value['monto_solicitado'] * 1.16);
                }
            }

            $fecha = explode("-", $pedido->fecha);

            $presupuesto = UnidadMedicaPresupuesto::where("clues", $pedido->clues)
                                                    ->where("almacen_id", $pedido->almacen_solicitante)            
                                                    ->where("mes", intVal($fecha[1]))
                                                    ->where("anio", intVal($fecha[0]))
                                                    ->where("proveedor_id", $proveedor->id)
                                                    ->first();

            $presupuesto->causes_comprometido               = ($presupuesto->causes_comprometido - $total_causes);                                         
            $presupuesto->no_causes_comprometido            = ($presupuesto->no_causes_comprometido - $total_no_causes);                                         
            $presupuesto->material_curacion_comprometido    = ($presupuesto->material_curacion_comprometido - $total_material_curacion); 

            $presupuesto->causes_disponible                 = ($presupuesto->causes_disponible + $total_causes);     
            $presupuesto->no_causes_disponible              = ($presupuesto->no_causes_disponible + $total_no_causes);                                         
            $presupuesto->material_curacion_disponible      = ($presupuesto->material_curacion_disponible + $total_material_curacion);

            $presupuesto->save();                                   
            DB::commit();

            return Response::json([ 'data' => $presupuesto],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    public function excel()
    {
        $parametros = Input::only('q','status','proveedores','jurisdicciones', 'fecha_desde','fecha_hasta', 'ordenar_causes','ordenar_no_causes','ordenar_material_curacion');

        $items = self::getItemsQuery($parametros);
        $items = $items->get();

         Excel::create("Pedidos reporte ".date('Y-m-d'), function($excel) use($items) {

            $excel->sheet('Reporte de pedidos', function($sheet) use($items) {
                $sheet->setAutoSize(true);
                
                $sheet->mergeCells('A1:F1');

                $sheet->mergeCells('G1:I1');
                $sheet->mergeCells('J1:L1');
                $sheet->mergeCells('M1:O1');
                $sheet->mergeCells('P1:R1');
                $sheet->mergeCells('S1:U1');
                $sheet->mergeCells('V1:X1');
                $sheet->mergeCells('Y1:AA1');
                $sheet->mergeCells('AB1:AD1');
                $sheet->mergeCells('AE1:AG1');
                $sheet->mergeCells('AH1:AJ1');
                $sheet->mergeCells('AK1:AM1');
                $sheet->mergeCells('AN1:AP1');

                $sheet->row(1, array('','','','','','','Total Solicitado','','','Total Recibido','','','% Recibido','','','Causes Solicitado','','','Causes Recibido','','','% Recibido','','','No Causes Solicitado','','','No Causes Recibido','','','% Recibido','','','Material de Curación Solicitado','','','Material de Curación Recibido','','','% Recibido','',''));
                
                $sheet->row(2, array(
                    'Proveedor','Folio','Nombre', 'Clues','Unidad médica','Fecha','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Claves','Cantidad','Monto','Status'
                ));
                $sheet->cells("A1:AQ2", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $sheet->row(2, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });
                $contador_filas = 2;
                foreach($items as $item){
                    $contador_filas++;
                    $status = '';
                    switch($item->status){
                        case 'BR': $status = 'Borrador'; break;
                        case 'TR': $status = 'En transito'; break;
                        case 'PS': $status = 'Por surtir'; break;
                        case 'FI': $status = 'Finalizado'; break;
                        case 'EX': $status = 'Expirado'; break;
                        default: $status = 'Otro';
                    }
                    
                    $sheet->appendRow(array(
                        $item->proveedor,
                        ($item->folio)?$item->folio:'S/F',
                        $item->descripcion,
                        $item->clues,
                        $item->unidad_medica,
                        $item->fecha,

                       $item->total_claves_solicitadas,
                       $item->total_cantidad_solicitada,
                       $item->total_monto_solicitado,

                       $item->total_claves_recibidas,
                       $item->total_cantidad_recibida,
                       $item->total_monto_recibido,

                       (!$item->total_claves_solicitadas)?"0.0":"=J$contador_filas/G$contador_filas",
                       (!$item->total_cantidad_solicitada)?"0.0":"=K$contador_filas/H$contador_filas",
                       (!round($item->total_monto_solicitado,2))?"0.0":"=L$contador_filas/I$contador_filas",

                       $item->total_claves_causes,
                       $item->total_cantidad_causes,
                       $item->total_monto_causes,

                       $item->total_claves_causes_recibidas,
                       $item->total_cantidad_causes_recibida,
                       $item->total_monto_causes_recibido,

                       (!$item->total_claves_causes)?"0.0":"=S$contador_filas/P$contador_filas",
                       (!$item->total_cantidad_causes)?"0.0":"=T$contador_filas/Q$contador_filas",
                       (!round($item->total_monto_causes,2))?"0.0":"=U$contador_filas/R$contador_filas",
                        
                       $item->total_claves_no_causes,
                       $item->total_cantidad_no_causes,
                        $item->total_monto_no_causes,

                        $item->total_claves_no_causes_recibidas,
                        $item->total_cantidad_no_causes_recibida,
                        $item->total_monto_no_causes_recibido,

                        (!$item->total_claves_no_causes)?"0.0":"=AB$contador_filas/Y$contador_filas",
                        (!$item->total_cantidad_no_causes)?"0.0":"=AC$contador_filas/Z$contador_filas",
                        (!round($item->total_monto_no_causes,2))?"0.0":"=AD$contador_filas/AA$contador_filas",

                        $item->total_claves_material_curacion,
                        $item->total_cantidad_material_curacion,
                        $item->total_monto_material_curacion,

                        $item->total_claves_material_curacion_recibidas,
                        $item->total_cantidad_material_curacion_recibida,
                        $item->total_monto_material_curacion_recibido,

                        (!$item->total_claves_material_curacion)?"0.0":"=AK$contador_filas/AH$contador_filas",
                        (!$item->total_cantidad_material_curacion)?"0.0":"=AL$contador_filas/AI$contador_filas",
                        (!round($item->total_monto_material_curacion,2))?"0.0":"=AM$contador_filas/AJ$contador_filas",

                        $status
                    )); 
                }

                $sheet->appendRow(array(
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',

/*G*/               "=SUM(G3:G$contador_filas)",
/*H*/               "=SUM(H3:H$contador_filas)",
/*I*/               "=SUM(I3:I$contador_filas)",
/*J*/               "=SUM(J3:J$contador_filas)",
/*K*/               "=SUM(K3:K$contador_filas)",
/*L*/               "=SUM(L3:L$contador_filas)",
/*M*/               "=J".($contador_filas+1)."/G".($contador_filas+1),
/*N*/               "=K".($contador_filas+1)."/H".($contador_filas+1),
/*O*/               "=L".($contador_filas+1)."/I".($contador_filas+1),
/*P*/               "=SUM(P3:P$contador_filas)",
/*Q*/               "=SUM(Q3:Q$contador_filas)",
/*R*/               "=SUM(R3:R$contador_filas)",
/*S*/               "=SUM(S3:S$contador_filas)",
/*T*/               "=SUM(T3:T$contador_filas)",
/*U*/               "=SUM(U3:U$contador_filas)",
/*V*/               "=S".($contador_filas+1)."/P".($contador_filas+1),
/*W*/               "=T".($contador_filas+1)."/Q".($contador_filas+1),
/*X*/               "=U".($contador_filas+1)."/R".($contador_filas+1),
/*Y*/               "=SUM(Y3:Y$contador_filas)",
/*Z*/               "=SUM(Z3:Z$contador_filas)",
/*AA*/              "=SUM(AA3:AA$contador_filas)",
/*AB*/              "=SUM(AB3:AB$contador_filas)",
/*AC*/              "=SUM(AC3:AC$contador_filas)",
/*AD*/              "=SUM(AD3:AD$contador_filas)",
/*AE*/              "=AB".($contador_filas+1)."/Y".($contador_filas+1),
/*AF*/              "=AC".($contador_filas+1)."/Z".($contador_filas+1),
/*AG*/              "=AD".($contador_filas+1)."/AA".($contador_filas+1),
/*AH*/              "=SUM(AH3:AH$contador_filas)",
/*AI*/              "=SUM(AI3:AI$contador_filas)",
/*AJ*/              "=SUM(AJ3:AJ$contador_filas)",
/*AK*/              "=SUM(AK3:AK$contador_filas)",
/*AL*/              "=SUM(AL3:AL$contador_filas)",
/*AM*/              "=SUM(AM3:AM$contador_filas)",
/*AN*/              "=AK".($contador_filas+1)."/AH".($contador_filas+1),
/*AO*/              "=AL".($contador_filas+1)."/AI".($contador_filas+1),
/*AP*/              "=AM".($contador_filas+1)."/AJ".($contador_filas+1),

                    ''
                ));

                $sheet->setBorder("A1:AQ$contador_filas", 'thin');

                $contador_filas += 1;
                
                $sheet->setColumnFormat(array(
                        "G3:G$contador_filas" => '#,##0',
                        "H3:H$contador_filas" => '#,##0',
                        "I3:I$contador_filas" => '"$" #,##0.00_-',
                        "J3:J$contador_filas" => '#,##0',
                        "K3:K$contador_filas" => '#,##0',
                        "L3:L$contador_filas" => '"$" #,##0.00_-',
                        "M3:M$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "N3:N$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "O3:O$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "P3:P$contador_filas" => '#,##0',
                        "Q3:Q$contador_filas" => '#,##0',
                        "R3:R$contador_filas" => '"$" #,##0.00_-',
                        "S3:S$contador_filas" => '#,##0',
                        "T3:T$contador_filas" => '#,##0',
                        "U3:U$contador_filas" => '"$" #,##0.00_-',
                        "V3:V$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "W3:W$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "X3:X$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "Y3:Y$contador_filas" => '#,##0',
                        "Z3:Z$contador_filas" => '#,##0',
                        "AA3:AA$contador_filas" => '"$" #,##0.00_-',
                        "AB3:AB$contador_filas" => '#,##0',
                        "AC3:AC$contador_filas" => '#,##0',
                        "AD3:AD$contador_filas" => '"$" #,##0.00_-',
                        "AE3:AE$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "AF3:AF$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "AG3:AG$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "AH3:AH$contador_filas" => '#,##0',
                        "AI3:AI$contador_filas" => '#,##0',
                        "AJ3:AJ$contador_filas" => '"$" #,##0.00_-',
                        "AK3:AK$contador_filas" => '#,##0',
                        "AL3:AL$contador_filas" => '#,##0',
                        "AM3:AM$contador_filas" => '"$" #,##0.00_-',
                        "AN3:AN$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "AO3:AO$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%',
                        "AP3:AP$contador_filas" => '[Green]0.00%;[Red]-0.00%;0.00%'
                    ));
            });
         })->export('xls');
    }

    private function getItemsQuery($parametros){       

        $items = DB::table(DB::raw('(
                select
                    PR.id as proveedor_id, 
                    PR.nombre as proveedor,
                    P.id as pedido_id, 
                    P.folio, 
                    P.clues, 
                    UM.nombre as unidad_medica, 
                    UM.jurisdiccion_id,
                    P.fecha, 
                    P.fecha_concluido,
                    P.fecha_expiracion,
                    P.descripcion, 
                    
                    P.total_claves_solicitadas, 
                    P.total_cantidad_solicitada, 
                    P.total_monto_solicitado,

                    IF(P.total_claves_recibidas is null, 0, P.total_claves_recibidas) AS total_claves_recibidas,
                    IF(P.total_cantidad_recibida is null, 0, P.total_cantidad_recibida) AS total_cantidad_recibida,
                    IF(P.total_monto_recibido is null, 0, P.total_monto_recibido) AS total_monto_recibido,

                    IF(IC.total_claves_causes is null, 0, IC.total_claves_causes) AS total_claves_causes, 
                    IF(IC.total_cantidad_causes is null, 0, IC.total_cantidad_causes) AS total_cantidad_causes, 
                    IF(IC.total_monto_causes is null, 0, IC.total_monto_causes) AS total_monto_causes,

                    IF(IC.total_claves_causes_recibidas is null, 0, IC.total_claves_causes_recibidas) AS total_claves_causes_recibidas, 
                    IF(IC.total_cantidad_causes_recibida is null, 0, IC.total_cantidad_causes_recibida) AS total_cantidad_causes_recibida, 
                    IF(IC.total_monto_causes_recibido is null, 0, IC.total_monto_causes_recibido) AS total_monto_causes_recibido,

                    IF(INC.total_claves_no_causes is null, 0, INC.total_claves_no_causes) AS total_claves_no_causes, 
                    IF(INC.total_cantidad_no_causes is null, 0, INC.total_cantidad_no_causes) AS total_cantidad_no_causes, 
                    IF(INC.total_monto_no_causes is null, 0, INC.total_monto_no_causes) AS total_monto_no_causes,

                    IF(INC.total_claves_no_causes_recibidas is null, 0, INC.total_claves_no_causes_recibidas) AS total_claves_no_causes_recibidas, 
                    IF(INC.total_cantidad_no_causes_recibida is null, 0, INC.total_cantidad_no_causes_recibida) AS total_cantidad_no_causes_recibida, 
                    IF(INC.total_monto_no_causes_recibido is null, 0, INC.total_monto_no_causes_recibido) AS total_monto_no_causes_recibido,

                    IF(IMC.total_claves_material_curacion is null, 0, IMC.total_claves_material_curacion) AS total_claves_material_curacion, 
                    IF(IMC.total_cantidad_material_curacion is null, 0, IMC.total_cantidad_material_curacion) AS total_cantidad_material_curacion, 
                    IF(IMC.total_monto_material_curacion is null, 0, (IMC.total_monto_material_curacion+(IMC.total_monto_material_curacion*16/100))) AS total_monto_material_curacion,

                    IF(IMC.total_claves_material_curacion_recibidas is null, 0, IMC.total_claves_material_curacion_recibidas) AS total_claves_material_curacion_recibidas, 
                    IF(IMC.total_cantidad_material_curacion_recibida is null, 0, IMC.total_cantidad_material_curacion_recibida) AS total_cantidad_material_curacion_recibida, 
                    IF(IMC.total_monto_material_curacion_recibido is null, 0, (IMC.total_monto_material_curacion_recibido+(IMC.total_monto_material_curacion_recibido*16/100))) AS total_monto_material_curacion_recibido,

                    P.status

                    from pedidos P

                    left join unidades_medicas UM on UM.clues = P.clues
                    left join proveedores PR on P.proveedor_id = PR.id

                    left join (
                        select PC.pedido_id, count(PC.insumo_medico_clave) as total_claves_causes, sum(PC.cantidad_solicitada) as total_cantidad_causes, sum(PC.monto_solicitado) as total_monto_causes,
                        sum(if(PC.cantidad_recibida>0,1,0)) as total_claves_causes_recibidas, sum(PC.cantidad_recibida) as total_cantidad_causes_recibida, sum(PC.monto_recibido) as total_monto_causes_recibido
                        from pedidos_insumos PC
                        join insumos_medicos IM on IM.clave = PC.insumo_medico_clave and IM.tipo = "ME" and IM.es_causes = 1
                        where PC.deleted_at is null
                        group by PC.pedido_id
                    ) as IC on IC.pedido_id = P.id

                    left join (
                        select PNC.pedido_id, count(PNC.insumo_medico_clave) as total_claves_no_causes, sum(PNC.cantidad_solicitada) as total_cantidad_no_causes, sum(PNC.monto_solicitado) as total_monto_no_causes,
                        sum(if(PNC.cantidad_recibida>0,1,0)) as total_claves_no_causes_recibidas, sum(PNC.cantidad_recibida) as total_cantidad_no_causes_recibida, sum(PNC.monto_recibido) as total_monto_no_causes_recibido
                        from pedidos_insumos PNC
                        join insumos_medicos IM on IM.clave = PNC.insumo_medico_clave and IM.tipo = "ME" and IM.es_causes = 0
                        where PNC.deleted_at is null
                        group by PNC.pedido_id
                    ) as INC on INC.pedido_id = P.id

                    left join (
                        select PMC.pedido_id, count(PMC.insumo_medico_clave) as total_claves_material_curacion, sum(PMC.cantidad_solicitada) as total_cantidad_material_curacion, sum(PMC.monto_solicitado) as total_monto_material_curacion,
                        sum(if(PMC.cantidad_recibida>0,1,0)) as total_claves_material_curacion_recibidas, sum(PMC.cantidad_recibida) as total_cantidad_material_curacion_recibida, sum(PMC.monto_recibido) as total_monto_material_curacion_recibido
                        from pedidos_insumos PMC
                        join insumos_medicos IM on IM.clave = PMC.insumo_medico_clave and IM.tipo = "MC"
                        where PMC.deleted_at is null
                        group by PMC.pedido_id
                    ) as IMC on IMC.pedido_id = P.id

                    where P.deleted_at is null
                
            ) as pedidos'));
            
        
        if (isset($parametros['q']) &&  $parametros['q'] != "") {
            $items = $items->where(function($query) use ($parametros){
                $query
                    ->where('unidad_medica','LIKE',"%".$parametros['q']."%")
                    ->orWhere('clues','LIKE',"%".$parametros['q']."%")
                    ->orWhere('folio','LIKE',"%".$parametros['q']."%")
                    ->orWhere('descripcion','LIKE',"%".$parametros['q']."%");
            });
        } 


        $fecha_desde = isset($parametros['fecha_desde']) ? $parametros['fecha_desde'] : '';
        $fecha_hasta = isset($parametros['fecha_hasta']) ? $parametros['fecha_hasta'] : '';


        if ($fecha_desde != "" && $fecha_hasta != "" ) {
            $items = $items->whereBetween('fecha',[$fecha_desde, $fecha_hasta]);
        } 

        if ($fecha_desde != "" && $fecha_hasta == "" ) {
            $items = $items->where('fecha',">=",$fecha_desde);
        } 

        if ($fecha_desde == "" && $fecha_hasta != "" ) {
            $items = $items->where('fecha',"<=",$fecha_hasta);
        } 
        
        if(isset($parametros['status']) && $parametros['status'] != ""){
            $status = explode(',',$parametros['status']);            
            if(count($status)>0){
                $items = $items->whereIn('status',$status);
            }              
        }

        if(isset($parametros['proveedores']) && $parametros['proveedores'] != ""){
            $proveedores = explode(',',$parametros['proveedores']);            
            if(count($proveedores)>0){
                $items = $items->whereIn('proveedor_id',$proveedores);
            }              
        }

        if(isset($parametros['jurisdicciones']) && $parametros['jurisdicciones'] != ""){
            $jurisdicciones = explode(',',$parametros['jurisdicciones']);            
            if(count($jurisdicciones)>0){
                $items = $items->whereIn('jurisdiccion_id',$jurisdicciones);
            }              
        }

        if(isset($parametros['ordenar_causes'])){
            if($parametros['ordenar_causes'] == 'ASC'){
                $items = $items->orderBy('porcentaje_causes','ASC');
            }
            if($parametros['ordenar_causes'] == 'DESC'){
                $items = $items->orderBy('porcentaje_causes','DESC');
            }
        }

        if(isset($parametros['ordenar_no_causes'])){
            if($parametros['ordenar_no_causes'] == 'ASC'){
                $items = $items->orderBy('porcentaje_no_causes','ASC');
            }
            if($parametros['ordenar_no_causes'] == 'DESC'){
                $items = $items->orderBy('porcentaje_no_causes','DESC');
            }
        }
        if(isset($parametros['ordenar_material_curacion'])){
            if($parametros['ordenar_material_curacion'] == 'ASC'){
                $items = $items->orderBy('porcentaje_material_curacion','ASC');
            }
            if($parametros['ordenar_material_curacion'] == 'DESC'){
                $items = $items->orderBy('porcentaje_material_curacion','DESC');
            }
        }
        return $items;
    }
    
}
