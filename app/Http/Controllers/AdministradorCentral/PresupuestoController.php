<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\PresupuestoEjercicio, App\Models\PresupuestoUnidadMedica;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class PresupuestoController extends Controller
{
    public function ejercicios(){
        $items = PresupuestoEjercicio::orderBy('ejercicio','desc')->get();
        return Response::json([ 'data' => $items],200);
    }

    public function presupuestoUnidadesMedicas(Request $request, $id){
        $items = PresupuestoUnidadMedica::where('presupuesto_id',$id)->with('unidadMedica')->orderBy('clues','asc')->get();
        return Response::json([ 'data' => $items],200);

    }
    public function exportarExcel(Request $request, $id){
        $presupuesto = PresupuestoEjercicio::find($id);
        
        if(!$presupuesto){
            return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
        }

        $presupuesto_unidades_medicas = PresupuestoUnidadMedica::where('presupuesto_id',$id)->with('unidadMedica')->orderBy('clues','asc')->get();
        
        Excel::create("Presupuesto ".$presupuesto->ejercicio." - Generado el ".date('Y-m-d'), function($excel) use($presupuesto, $presupuesto_unidades_medicas) {

            $excel->sheet("Presupuesto ".$presupuesto->ejercicio, function($sheet) use($presupuesto, $presupuesto_unidades_medicas) {
                $sheet->setAutoSize(true);
                
                $sheet->mergeCells('A1:B4');
                $sheet->mergeCells('C1:F1');
                $sheet->mergeCells('G1:H3');
                $sheet->mergeCells('I1:L1');
                $sheet->row(1, array('PRESUPUESTO '.$presupuesto->ejercicio,'','CAUSES','','','','','','NO CAUSES'));
                
                $sheet->mergeCells('C2:D2');
                $sheet->mergeCells('E2:F2');
                $sheet->mergeCells('I2:J2');
                $sheet->mergeCells('K2:L2');
                $sheet->row(2, array('','','Autorizado/Modificado','','Disponible','','','','Autorizado/Modificado','','Disponible'));

                $sheet->mergeCells('C3:D3');
                $sheet->mergeCells('E3:F3');
                $sheet->mergeCells('I3:J3');
                $sheet->mergeCells('K3:L3');
                
                //$sheet->mergeCells('A4:A5');
                //$sheet->mergeCells('B4:B5');
                $sheet->mergeCells('C4:D4');
                $sheet->mergeCells('E4:F4');
                $sheet->mergeCells('G4:H4');
                $sheet->mergeCells('I4:J4');
                $sheet->mergeCells('K4:L4');

                $sheet->row(4, array('','','Autorizado','','Modificado','','Comprometido','','Devengado','','Disponible'));
                $sheet->row(5, array('Clues','Nombre'   ,'CAUSES','NO CAUSES','CAUSES','NO CAUSES','CAUSES','NO CAUSES','CAUSES','NO CAUSES','CAUSES','NO CAUSES'));

                $sheet->freezePane('A6');

                $sheet->cells("A1:L5", function($cells) {
                    $cells->setAlignment('center');
                    $cells->setBackground('#DDDDDD');
                    $cells->setFontWeight('bold');
                });

                $sheet->getStyle('A1:L5')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $sheet->cells("A1:B4", function($cells) {
                    $cells->setFontSize(16);
                });

                $sheet->cells("C3:L3", function($cells) {
                    $cells->setBackground('#FFFFFF');
                });

                $contador_filas = 5;

                foreach($presupuesto_unidades_medicas as $item){
                    $contador_filas++;
                    $sheet->appendRow(array(
                        $item->clues,
                        $item->unidadMedica->nombre,
                        $item->causes_autorizado,
                        $item->no_causes_autorizado,
                        $item->causes_modificado,
                        $item->no_causes_modificado,
                        $item->causes_comprometido,
                        $item->no_causes_comprometido,
                        $item->causes_devengado,
                        $item->no_causes_devengado,
                        $item->causes_disponible,
                        $item->no_causes_disponible
                    ));
                }               

                $last_row = $contador_filas;
                $contador_filas++;
                $subtotal_row = $contador_filas;
                $sheet->mergeCells("A$subtotal_row:B".($subtotal_row + 1));                
                $sheet->row($subtotal_row, array('TOTAL','',"=SUM(C6:C$last_row)","=SUM(D6:D$last_row)","=SUM(E6:E$last_row)","=SUM(F6:F$last_row)","=SUM(G6:G$last_row)","=SUM(H6:H$last_row)","=SUM(I6:I$last_row)","=SUM(J6:J$last_row)","=SUM(K6:K$last_row)","=SUM(L6:L$last_row)"));
                
                $contador_filas++;                
                $sheet->mergeCells("C$contador_filas:D$contador_filas");
                $sheet->mergeCells("E$contador_filas:F$contador_filas");
                $sheet->mergeCells("G$contador_filas:H$contador_filas");
                $sheet->mergeCells("I$contador_filas:J$contador_filas");
                $sheet->mergeCells("K$contador_filas:L$contador_filas");
                $sheet->row($contador_filas, array('','',"=SUM(C$subtotal_row:D$subtotal_row)",'',"=SUM(E$subtotal_row:F$subtotal_row)",'',"=SUM(G$subtotal_row:H$subtotal_row)",'',"=SUM(I$subtotal_row:J$subtotal_row)",'',"=SUM(K$subtotal_row:L$subtotal_row)"));


                $sheet->row(3, array('','',$presupuesto->causes,'',"=I$subtotal_row",'','','',$presupuesto->no_causes,'',"=K$subtotal_row"));

                $sheet->cells("A$subtotal_row:L$contador_filas", function($cells) {
                    $cells->setAlignment('center');
                    $cells->setBackground('#DDDDDD');
                    $cells->setFontWeight('bold');
                });

                $sheet->cells("C6:L$subtotal_row", function($cells) {
                    $cells->setAlignment('right');
                });

                $sheet->setBorder("A1:L$contador_filas", 'thin');

                $sheet->setColumnFormat(array(
                    "C6:L$contador_filas" => '"$" #,##0.00_-',
                    "C3:L3" => '"$" #,##0.00_-',
                ));

                $sheet->setAutoFilter("A5:L5");

                /*
                $sheet->row(1, array(
                    'Clave', 'Tipo','Causes','Unidosis','Tiene Fecha Caducidad','Controlado','Surfactante','Bloquear en Pedidos','Descontinuado','Descripción',
                    'Presentación','Concentración','Contenido','Cantidad X Envase','Unidad de medida', 'Vía de Administración','Dosis','Indicaciones'
                ));
                $sheet->cells("A1:R1", function($cells) {
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
                        $item->medicamento->es_controlado?1:0,
                        $item->medicamento->es_surfactante?1:0,                        
                        $item->no_disponible_pedidos?1:0,
                        $item->descontinuado?1:0,
                        $item->descripcion,
                        $item->medicamento->PresentacionMedicamento != null ? $item->medicamento->PresentacionMedicamento->nombre: "",                        
                        $item->medicamento->concentracion,
                        $item->medicamento->contenido,
                        $item->medicamento->cantidad_x_envase,
                        $item->medicamento->UnidadMedida != null ? $item->medicamento->unidadMedida->nombre ." (".$item->medicamento->unidadMedida->clave.")": "",
                        $item->medicamento->ViaAdministracion != null ? $item->medicamento->ViaAdministracion->nombre: "",
                        $item->medicamento->dosis,
                        $item->medicamento->indicaciones
                    )); 
                }

                $sheet->setAutoFilter('A1:R1');
                $sheet->setBorder("A1:R$contador_filas", 'thin');*/
            });           
           
           $excel->setActiveSheetIndex(0);

           


         })->export('xls');
    }
}