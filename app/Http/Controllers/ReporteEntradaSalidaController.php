<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB, \Excel;

use App\Models\Almacen,
    App\Models\Movimiento;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class ReporteEntradaSalidaController extends Controller
{
    //
    public function index(Request $request)
    {
        try{
            $parametros = Input::only('desde','hasta','tipo', 'insumo', 'page', 'per_page');
            
            $obj =  JWTAuth::parseToken()->getPayload();
            $almacen = Almacen::with("unidadMedica")->find($request->get('almacen_id'));

            $consulta = DB::table("movimientos")->join('tipos_movimientos', 'tipos_movimientos.id', '=', 'movimientos.tipo_movimiento_id')
                                    ->join('movimiento_insumos', 'movimientos.id', '=', 'movimiento_insumos.movimiento_id')
                                    ->join('insumos_medicos', 'insumos_medicos.clave', '=', 'movimiento_insumos.clave_insumo_medico')
                                    ->join('almacenes', 'almacenes.id', '=', 'movimientos.almacen_id')
                                    ->where("movimientos.status", "=", 'FI')
                                    ->where("movimientos.almacen_id", "=", $almacen->id)
                                    ->whereNull("movimientos.deleted_at")
                                    ->whereNull("movimiento_insumos.deleted_at")
                                    ->orderBy("movimientos.fecha_movimiento", "asc", "insumos_medicos.clave", "insumos_medicos.clave", "tipos_movimientos.tipo")
                                    ->select("movimientos.id", "movimientos.fecha_movimiento", "tipos_movimientos.nombre", "tipos_movimientos.tipo", "movimiento_insumos.cantidad", "movimiento_insumos.cantidad_unidosis", "insumos_medicos.clave", "insumos_medicos.descripcion", "movimiento_insumos.precio_unitario", "movimiento_insumos.iva", "movimiento_insumos.precio_total" );
            

            if($parametros['desde'] != "" || $parametros['hasta'] != "")
            {
                if($parametros['desde']!="" && $parametros['hasta']=="")
                    $parametros['hasta'] = $parametros['desde'];
                    
                if($parametros['desde']=="" && $parametros['hasta']!="")
                    $parametros['desde'] = $parametros['hasta'];  
                    
                $consulta = $consulta->whereBetween("movimientos.fecha_movimiento", array($parametros['desde'], $parametros['hasta']));    
            }

            if($parametros['tipo'] != 1)
            {
                if($parametros['tipo'] == 2)
                    $tipo = 'E';
                if($parametros['tipo'] == 3)
                    $tipo = 'S';    
                $consulta = $consulta->where("tipos_movimientos.tipo", "=", $tipo);
            }
            if($parametros['insumo'] != "")
            {
                $texto = $parametros['insumo'];
                $consulta = $consulta->where(function ($query) use($parametros) {
                    $query->where('insumos_medicos.clave', 'like', '%' . $parametros['insumo'] . '%')
                          ->orWhere('insumos_medicos.descripcion', 'like', '%' . $parametros['insumo'] . '%');
                });   
            }

            if(isset($parametros['page'])){
                $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 25;
                $consulta = $consulta->paginate($resultadosPorPagina);
            } else {
                $consulta = $consulta->get();
            }
            return Response::json([ 'data' => array("datos"=>$consulta, "almacen" => $almacen)],200);                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }                                        
    }

    public function show(Request $request, $id)
    {
        try{
            $parametros = Input::only('desde','hasta','tipo', 'insumo', 'page', 'per_page', 'tipo_movimiento');
            
            $obj =  JWTAuth::parseToken()->getPayload();
            $almacen = Almacen::find($request->get('almacen_id'));

            $consulta = Movimiento::with("almacen.unidadMedica.director", "almacen.encargado", "tipoMovimiento", "insumosDetalles")->find($id); 

            return Response::json([ 'data' => $consulta],200);                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }                                        
    }

    public function catalogo(Request $request)
    {
        try{
            $consulta = Insumo::all(); 

            return Response::json([ 'data' => "hola"],200);                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }                                        
    }

    public function generarMovimientosExcel(Request $request)
    {
        $parametros = Input::only('desde', 'hasta', 'tipo', 'insumo', 'almacen');

        $items = self::getItemsQuery($parametros);
        $items = $items->get();
        
        $almacen = Almacen::with("unidadMedica")->find($parametros['almacen']);
        Excel::create("Reporte de Movimientos ".date('Y-m-d'), function($excel) use($items, $almacen, $parametros) {

            $excel->sheet('Movimientos de Insumos', function($sheet) use($items, $almacen, $parametros){
                $sheet->setAutoSize(true);
                
                $sheet->mergeCells('A1:K1');
                $sheet->mergeCells('A2:K2');
                $sheet->mergeCells('A3:K3');
                $sheet->mergeCells('A4:K4');
                $sheet->row(1, array('SECRETARÍA DE SALUD'));
                $sheet->row(2, array('INSTITUTO DE SALUD'));
                $sheet->row(3, array($almacen->unidadMedica->nombre));
                
               
                $sheet->cells("A1:K3", function($cells) {
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
                $sheet->row(3, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });

                $sheet->mergeCells('A5:B5');
                $sheet->mergeCells('C5:G5');
                $sheet->mergeCells('H5:I5');
                $sheet->mergeCells('J5:K5');
                $sheet->row(5, array('ALMACEN', '', $almacen->nombre,'','','','','No. DE LOTES','',count($items)));
                $sheet->mergeCells('A6:K6');
                $sheet->row(6, array('PARAMETROS'));
                $sheet->mergeCells('A7:B7');
                $sheet->mergeCells('C7:G7');
                $sheet->mergeCells('H7:I7');
                $sheet->mergeCells('J7:K7');

                

                $tipo_movimiento = "TODOS";
                if($parametros['tipo'] != 1)
                {
                    if($parametros['tipo'] == 2)
                        $tipo_movimiento = 'ENTRADAS';
                    if($parametros['tipo'] == 3)
                        $tipo_movimiento = 'SALIDAS';    
                }
                $sheet->row(7, array('PERIODO', '', 'DE '.$parametros['desde']." A ".$parametros['hasta'],'','','','','TIPO DE MOVIMIENTO','',$tipo_movimiento));
                
                $sheet->mergeCells('A8:B8');
                $sheet->mergeCells('C8:K8');
                $texto_insumo = "SIN DATO";
                if($parametros['insumo'] !="")
                    $texto_insumo = $parametros['insumo'];
                $sheet->row(8, array('INSUMO', '', $texto_insumo));

                $sheet->mergeCells('A9:A10');
                $sheet->mergeCells('B9:B10');
                $sheet->mergeCells('C9:C10');
                $sheet->mergeCells('D9:D10');
                $sheet->mergeCells('E9:E10');
                $sheet->mergeCells('F9:H9');
                $sheet->mergeCells('I9:K9');
                $sheet->row(9, array('NO', 'FECHA', 'TIPO', 'INSUMO', 'PRECIO U.', 'ENTRADA','','', "SALIDA",'',''));
                $sheet->row(10, array('', '', '', '', '', 'CANTIDAD','UNIDOSIS','MONTO', "CANTIDAD",'UNIDOSIS','MONTO'));
                
                $contador_filas = 10;
                $contador_linea = 0;
                $iva_entradas = 0;
                $iva_salidas = 0;
                foreach($items as $item){
                    $contador_filas++;
                    $contador_linea++;
                   
                    if($item->tipo == "E")
                    {
                        $sheet->appendRow(array(
                            $contador_linea,
                            $item->fecha_movimiento,
                            $item->nombre,
                            $item->clave." ".$item->descripcion,
                            $item->precio_unitario,
                            $item->cantidad,
                            $item->cantidad_unidosis,
                            $item->precio_total,
                            '-',
                            '-',
                            '-'
                        ));
                        $iva_entradas += $item->iva;
                    }else if($item->tipo == "S")
                    {
                        $sheet->appendRow(array(
                            $contador_linea,
                            $item->fecha_movimiento,
                            $item->nombre,
                            $item->clave." ".$item->descripcion,
                            $item->precio_unitario,
                            '-',
                            '-',
                            '-',
                            $item->cantidad,
                            $item->cantidad_unidosis,
                            $item->precio_total
                        ));
                        $iva_salidas += $item->iva;
                    }
                }
                $ultima_fila = $contador_filas;
                //$contador_filas++
                $sheet->row(++$contador_filas, array('', '', '', '', 'SUBTOTAL', '-', '-', "=SUM(H11:H$ultima_fila)", '-','-',"=SUM(K11:K$ultima_fila)"));
                $sheet->row(++$contador_filas, array('', '', '', '', 'IVA', '-', '-', $iva_entradas, '-','-',$iva_salidas));
                $sheet->row(++$contador_filas, array('', '', '', '', 'TOTAL', "=SUM(F11:F$ultima_fila)", "=SUM(G11:G$ultima_fila)", "=SUM(H11:H$ultima_fila) + $iva_entradas","=SUM(I11:I$ultima_fila)", "=SUM(J11:J$ultima_fila)","=SUM(K11:K$ultima_fila) + $iva_salidas"));

                $sheet->setBorder("A1:K$contador_filas", 'thin');

                $sheet->setColumnFormat(array(
                    "E11:E$ultima_fila" => '"$" #,##0.00_-',
                    "F11:G$contador_filas" => '#,##0',
                    "H3:H$contador_filas" => '"$" #,##0.00_-',
                    "I11:J$contador_filas" => '#,##0',
                    "K11:K$contador_filas" => '"$" #,##0.00_-'
                ));

                $sheet->cells('A5:K10', function ($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->cells('A9:K10', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('A5:B5', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('H5:I5', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('A6:K6', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('A7:B7', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('H7:I7', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('A8:B8', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
            });
         })->export('xls');
    }

    public function generarMovimientoUnicoExcel(Request $request)
    {
        $parametros = Input::only('movimiento_id', 'almacen');

        $items = self::getItemsQueryMovimiento($parametros);
        //$items = $items->get();
        
        $almacen = Almacen::with("unidadMedica")->find($parametros['almacen']);

        //print_r(count($items['insumosDetalles']));
        //print_r($items);
        
        Excel::create("Reporte de Movimientos ".date('Y-m-d'), function($excel) use($items, $almacen, $parametros) {

            $excel->sheet('Movimientos de Insumos', function($sheet) use($items, $almacen, $parametros){
                $sheet->setAutoSize(true);
                
                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->mergeCells('A3:G3');
                $sheet->mergeCells('A4:G4');
                $sheet->row(1, array('SECRETARÍA DE SALUD'));
                $sheet->row(2, array('INSTITUTO DE SALUD'));
                $sheet->row(3, array($almacen->unidadMedica->nombre));
                
               
                $sheet->cells("A1:G3", function($cells) {
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
                $sheet->row(3, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });

                $sheet->mergeCells('A5:B5');
                $sheet->mergeCells('D5:E5');
                $sheet->mergeCells('F5:G5');
                $sheet->row(5, array('FECHA MOVIMIENTO','', $items['fecha_movimiento'], 'TIPO DE MOVIMIENTO','', $items['tipoMovimiento']['nombre']));
                
                $sheet->mergeCells('A6:B6');
                $sheet->mergeCells('D6:E6');
                $sheet->mergeCells('F6:G6');
                $sheet->row(6, array('ALMACEN','', $items['almacen']['nombre'], 'No. DE LOTES','',count($items['insumosDetalles'])));
                
                $sheet->row(7, array('NO', 'CLAVE', 'INSUMOS', 'CANITDAD', 'UNIDOSIS', 'PRECIO U.','SUBTOTAL'));
                
                
                $contador_filas = 7;
                $contador_linea = 0;
                $iva = 0;
                foreach($items['insumosDetalles'] as $item){
                    $contador_filas++;
                    $contador_linea++;
                    $sheet->appendRow(array(
                        $contador_linea,
                        $item['detalles']['clave'],
                        $item['detalles']['descripcion'],
                        $item['cantidad'],
                        $item['cantidad_unidosis'],
                        $item['precio_unitario'],
                        $item['precio_total']
                        
                    ));
                    $iva += $item['iva'];
                }
                $ultima_fila = $contador_filas;
                $sheet->row(++$contador_filas, array('', '', '', '', '', 'SUBTOTAL', "=SUM(G11:G$ultima_fila)"));
                $sheet->row(++$contador_filas, array('', '', '', '', '', 'IVA', $iva));
                $sheet->row(++$contador_filas, array('', '', '', '', '', 'TOTAL', "=SUM(G11:G$ultima_fila) + $iva"));

                $sheet->setBorder("A1:G$contador_filas", 'thin');

                $sheet->setColumnFormat(array(
                    "D8:D$ultima_fila" => '#,##0',
                    "E8:E$ultima_fila" => '#,##0',
                    "F8:F$contador_filas" => '"$" #,##0.00_-',
                    "G8:G$contador_filas" => '"$" #,##0.00_-',
                ));

                $sheet->cells('A1:G7', function ($cells) {
                    $cells->setAlignment('center');
                });

                $sheet->cells('A5:B5', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('D5:E5', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
               
                $sheet->cells('A6:B6', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
                $sheet->cells('D6:E6', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
               
                $sheet->cells('A7:G7', function ($cells) {
                    $cells->setBackground('#DDDDDD');
                });
            });
         })->export('xls');
    }

    private function getItemsQuery($parametros){       

        $items = DB::table("movimientos")->join('tipos_movimientos', 'tipos_movimientos.id', '=', 'movimientos.tipo_movimiento_id')
                                ->join('movimiento_insumos', 'movimientos.id', '=', 'movimiento_insumos.movimiento_id')
                                ->join('insumos_medicos', 'insumos_medicos.clave', '=', 'movimiento_insumos.clave_insumo_medico')
                                ->join('almacenes', 'almacenes.id', '=', 'movimientos.almacen_id')
                                ->where("movimientos.status", "=", 'FI')
                                ->where("movimientos.almacen_id", "=", $parametros['almacen'])
                                ->whereNull("movimientos.deleted_at")
                                ->whereNull("movimiento_insumos.deleted_at")
                                ->orderBy("movimientos.fecha_movimiento", "asc", "insumos_medicos.clave", "insumos_medicos.clave", "tipos_movimientos.tipo")
                                ->select("movimientos.id", "movimientos.fecha_movimiento", "tipos_movimientos.nombre", "tipos_movimientos.tipo", "movimiento_insumos.cantidad", "movimiento_insumos.cantidad_unidosis", "insumos_medicos.clave", "insumos_medicos.descripcion", "movimiento_insumos.precio_unitario", "movimiento_insumos.iva", "movimiento_insumos.precio_total" );
        

        if($parametros['desde'] != "" || $parametros['hasta'] != "")
        {
            if($parametros['desde']!="" && $parametros['hasta']=="")
                $parametros['hasta'] = $parametros['desde'];
                
            if($parametros['desde']=="" && $parametros['hasta']!="")
                $parametros['desde'] = $parametros['hasta'];  
                
            $items = $items->whereBetween("movimientos.fecha_movimiento", array($parametros['desde'], $parametros['hasta']));    
        }

        if($parametros['tipo'] != 1)
        {
            if($parametros['tipo'] == 2)
                $tipo = 'E';
            if($parametros['tipo'] == 3)
                $tipo = 'S';    
            $items = $items->where("tipos_movimientos.tipo", "=", $tipo);
        }
        if($parametros['insumo'] != "")
        {
            $texto = $parametros['insumo'];
            $items = $items->where(function ($query) use($parametros) {
                $query->where('insumos_medicos.clave', 'like', '%' . $parametros['insumo'] . '%')
                      ->orWhere('insumos_medicos.descripcion', 'like', '%' . $parametros['insumo'] . '%');
            });   
        }

        return $items;
    }

    private function getItemsQueryMovimiento($parametros){       

        try{
            $parametros = Input::only('movimiento_id');
            $items = Movimiento::with("almacen.unidadMedica.director", "almacen.encargado", "tipoMovimiento", "insumosDetalles")->find($parametros['movimiento_id']); 
            return $items;                            
                                                
        }catch(Exception $e)
        {
            return Response::json(['error' => "error"], HttpResponse::HTTP_NOT_FOUND); 
        }       

        
    }
}
