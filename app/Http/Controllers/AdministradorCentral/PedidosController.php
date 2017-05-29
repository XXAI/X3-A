<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Proveedor, App\Models\Presupuesto, App\Models\UnidadMedicaPresupuesto;
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
            if(isset($parametros['mes'])){
                if($parametros['mes']){
                    $presupuesto_unidad_medica = $presupuesto_unidad_medica->where('mes',$parametros['mes']);
                }
            }

            if(isset($parametros['proveedores'])){
                if($parametros['proveedores']){
                    $proveedores_ids = explode(',',$parametros['proveedores']);
                    $presupuesto_unidad_medica = $presupuesto_unidad_medica->whereIn('proveedor_id',$proveedores_ids);
                }
            }

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
    public function excel()
    {
        $parametros = Input::only('q','status','proveedores','jurisdicciones', 'fecha_desde','fecha_hasta', 'ordenar_causes','ordenar_no_causes','ordenar_material_curacion');

        $items = self::getItemsQuery($parametros);
        $items = $items->get();

         Excel::create("Pedidos reporte ".date('Y-m-d'), function($excel) use($items) {

            $excel->sheet('Reporte de pedidos', function($sheet) use($items) {
                $sheet->setAutoSize(true);
                
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('G1:H1');
                $sheet->mergeCells('I1:J1');
                $sheet->mergeCells('K1:L1');
                $sheet->mergeCells('M1:N1');

                $sheet->row(1, array('','','','','','','Total','','Causes','','No Causes','','Material de curación',''));
                
                $sheet->row(2, array(
                    'Proveedor','Folio','Nombre', 'Clues','Unidad médica','Fecha','Claves','Monto','Claves','Monto','Claves','Monto','Claves','Monto','Status'
                ));
                $sheet->cells("A1:O2", function($cells) {
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
                        $item->folio,
                        $item->descripcion,
                        $item->clues,
                        $item->unidad_medica,
                        $item->fecha,

                        $item->total_claves_solicitadas,
                        $item->total_monto_solicitado,

                        $item->total_claves_causes,
                        $item->total_monto_causes,

                        $item->total_claves_no_causes,
                        $item->total_monto_no_causes,

                        $item->total_claves_material_curacion,
                        $item->total_monto_material_curacion,

                        $status
                    )); 
                }
                $sheet->setBorder("A1:O$contador_filas", 'thin');

                $sheet->setColumnFormat(array(
                        "G3:G$contador_filas" => '#,##0',
                        "H3:H$contador_filas" => '"$" #,##0.00_-',
                        "I3:I$contador_filas" => '#,##0',
                        "J3:J$contador_filas" => '"$" #,##0.00_-',
                        "K3:K$contador_filas" => '#,##0',
                        "L3:L$contador_filas" => '"$" #,##0.00_-',
                        "M3:M$contador_filas" => '#,##0',
                        "N3:N$contador_filas" => '"$" #,##0.00_-',
                        
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
                    P.descripcion, 
                    
                    P.total_claves_solicitadas, 
                    P.total_cantidad_solicitada, 
                    P.total_monto_solicitado,

                    IF(IC.total_claves_causes is null, 0, IC.total_claves_causes) AS total_claves_causes, 
                    IF(IC.total_cantidad_causes is null, 0, IC.total_cantidad_causes) AS total_cantidad_causes, 
                    IF(IC.total_monto_causes is null, 0, IC.total_monto_causes) AS total_monto_causes,
                    
                    IF(INC.total_claves_no_causes is null, 0, INC.total_claves_no_causes) AS total_claves_no_causes, 
                    IF(INC.total_cantidad_no_causes is null, 0, INC.total_cantidad_no_causes) AS total_cantidad_no_causes, 
                    IF(INC.total_monto_no_causes is null, 0, INC.total_monto_no_causes) AS total_monto_no_causes,
                    
                    IF(IMC.total_claves_material_curacion is null, 0, IMC.total_claves_material_curacion) AS total_claves_material_curacion, 
                    IF(IMC.total_cantidad_material_curacion is null, 0, IMC.total_cantidad_material_curacion) AS total_cantidad_material_curacion, 
                    IF(IMC.total_monto_material_curacion is null, 0, (IMC.total_monto_material_curacion+(IMC.total_monto_material_curacion*16/100))) AS total_monto_material_curacion,

                    P.status

                    from pedidos P

                    left join unidades_medicas UM on UM.clues = P.clues
                    left join proveedores PR on P.proveedor_id = PR.id

                    left join (
                        select PC.pedido_id, count(PC.insumo_medico_clave) as total_claves_causes, sum(PC.cantidad_solicitada) as total_cantidad_causes, sum(PC.monto_solicitado) as total_monto_causes
                        from pedidos_insumos PC
                        join insumos_medicos IM on IM.clave = PC.insumo_medico_clave and IM.tipo = "ME" and IM.es_causes = 1
                        where PC.deleted_at is null
                        group by PC.pedido_id
                    ) as IC on IC.pedido_id = P.id

                    left join (
                        select PNC.pedido_id, count(PNC.insumo_medico_clave) as total_claves_no_causes, sum(PNC.cantidad_solicitada) as total_cantidad_no_causes, sum(PNC.monto_solicitado) as total_monto_no_causes
                        from pedidos_insumos PNC
                        join insumos_medicos IM on IM.clave = PNC.insumo_medico_clave and IM.tipo = "ME" and IM.es_causes = 0
                        where PNC.deleted_at is null
                        group by PNC.pedido_id
                    ) as INC on INC.pedido_id = P.id

                    left join (
                        select PMC.pedido_id, count(PMC.insumo_medico_clave) as total_claves_material_curacion, sum(PMC.cantidad_solicitada) as total_cantidad_material_curacion, sum(PMC.monto_solicitado) as total_monto_material_curacion
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
