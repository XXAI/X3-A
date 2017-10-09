<?php

namespace App\Http\Controllers\AdministradorCentral;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB;
use \Excel;

class ReporteFinancieroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function lista()
    {
        $parametros = Input::only('clues','proveedores','jurisdicciones','tipos_unidad','status_pedido','agrupado_por','mes_inicio','anio_inicio','mes_fin','anio_fin','page','per_page');

        $items = self::getItemsQuery($parametros);
       
        return Response::json([ 'data' => $items],200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function excel()
    {
        $parametros = Input::only('clues','proveedores','jurisdicciones','tipos_unidad','status_pedido','agrupado_por','mes_inicio','anio_inicio','mes_fin','anio_fin','page','per_page');

        $items = self::getItemsQuery($parametros);
       

         Excel::create("Reporte financiero al ".date('Y-m-d'), function($excel) use($items, $parametros) {

            $excel->sheet('Reporte financiero', function($sheet) use($items, $parametros) {
                $sheet->setAutoSize(true);
            
                if($parametros['agrupado_por'] == "UM"){
            
                $sheet->row(1, array(
                    'Num', 'Clues','Tipo','Nombre','Modificado','Comprometido','Devengado', 'Disponible'
                ));
                }
                if($parametros['agrupado_por'] == "P"){
                    $sheet->row(1, array(
                        'Num', 'Proveedor','Cant. Clues','Modificado','Comprometido','Devengado', 'Disponible'
                    ));
                }
                if($parametros['agrupado_por'] == "NA"){
                    $sheet->row(1, array(
                        'Num', 'Nivel de atención','Cant. Clues','Modificado','Comprometido','Devengado', 'Disponible'
                    ));
                }
                
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                    $row->setFontSize(14);
                });

                $contador_filas = 1;
                $total_modificado = 0;
                $total_comprometido = 0;
                $total_devengado = 0;
                $total_disponible = 0;
                foreach($items as $item){
                    $contador_filas++;
                    
                    $total_modificado += $item->modificado;
                    $total_comprometido += $item->comprometido;
                    $total_devengado += $item->devengado;
                    $total_disponible += $item->disponible;

                    if($parametros['agrupado_por'] == "UM"){
                        $sheet->appendRow(array(
                            $contador_filas,
                            $item->clues,
                            $item->tipo,
                            $item->nombre,
                            $item->modificado,
                            $item->comprometido,
                            $item->devengado,
                            $item->disponible
                        ));                         
                    }

                    if($parametros['agrupado_por'] == "P"){
                        $sheet->appendRow(array(
                            $contador_filas,
                            $item->nombre,
                            $item->cantidad_clues,
                            $item->modificado,
                            $item->comprometido,
                            $item->devengado,
                            $item->disponible
                        ));                         
                    }
                    if($parametros['agrupado_por'] == "NA"){
                        $sheet->appendRow(array(
                            $contador_filas,
                            $item->nivel_atencion,
                            $item->cantidad_clues,
                            $item->modificado,
                            $item->comprometido,
                            $item->devengado,
                            $item->disponible
                        ));                         
                    }
                }
                if($parametros['agrupado_por'] == "UM"){
                    $sheet->cells("A1:H1", function($cells) {
                        $cells->setAlignment('center');
                    });

                    $sheet->appendRow(array(
                        "",
                        "",
                        "",
                        "TOTAL",
                        $total_modificado,
                        $total_comprometido,
                        $total_devengado,
                        $total_disponible
                    )); 

                    $sheet->setColumnFormat(array(
                        "E2:E".($contador_filas+1) => '"$" #,##0.00_-',
                        "F2:F".($contador_filas+1) => '"$" #,##0.00_-',
                        "G2:G".($contador_filas+1) => '"$" #,##0.00_-',
                        "H2:H".($contador_filas+1) => '"$" #,##0.00_-',
                    ));

                    $sheet->setBorder("A1:H$contador_filas", 'thin');
                    $sheet->setBorder("D".($contador_filas+1).":H".($contador_filas+1), 'thin');
                    
                    $sheet->row(($contador_filas+1), function($row) {
                        $row->setBackground('#DDDDDD');
                        $row->setFontWeight('bold');
                        $row->setFontSize(14);
                    });
                    
                }
                if($parametros['agrupado_por'] != "UM"){
                                      
                    $sheet->cells("A1:G1", function($cells) {
                        $cells->setAlignment('center');
                    });
                    $sheet->appendRow(array(
                        "",
                        "",
                        "TOTAL",
                        $total_modificado,
                        $total_comprometido,
                        $total_devengado,
                        $total_disponible
                    )); 

                    $sheet->setColumnFormat(array(
                        "D2:D".($contador_filas+1) => '"$" #,##0.00_-',
                        "E2:E".($contador_filas+1) => '"$" #,##0.00_-',
                        "F2:F".($contador_filas+1) => '"$" #,##0.00_-',
                        "G2:H".($contador_filas+1) => '"$" #,##0.00_-',
                    ));

                    $sheet->setBorder("A1:G$contador_filas", 'thin'); 
                    $sheet->setBorder("C".($contador_filas+1).":G".($contador_filas+1), 'thin');  
                    $sheet->row(($contador_filas+1), function($row) {
                        $row->setBackground('#DDDDDD');
                        $row->setFontWeight('bold');
                        $row->setFontSize(14);
                    });
                    
                }
            });
         })->export('xls');
    }

    private function getItemsQuery($parametros){     
        if (!isset($parametros['agrupado_por']) ||  ($parametros['agrupado_por'] != "UM" && $parametros['agrupado_por'] != "P" && $parametros['agrupado_por'] != "NA")) {         
            return [];
        }  

        $variables = array();

        $query = "SELECT ";
        if($parametros['agrupado_por'] == "UM"){
            $query .= "unidad_medica_presupuesto.clues,  unidades_medicas.tipo,  unidades_medicas.nombre, ";
        }

        if($parametros['agrupado_por'] == "P"){
            $query .= "proveedores.nombre, COUNT(unidad_medica_presupuesto.clues) as cantidad_clues, ";
        }

        if($parametros['agrupado_por'] == "NA"){
            $query .= "nivel_atencion.nivel_atencion, COUNT(unidad_medica_presupuesto.clues) as cantidad_clues, ";
        }

        $query .= " 
		SUM(causes_modificado) as causes_modificado,
		SUM(no_causes_modificado) as no_causes_modificado,
		SUM(material_curacion_modificado) as material_curacion_modificado,
		SUM(causes_modificado + no_causes_modificado + material_curacion_modificado) as modificado,
		
		SUM(causes_comprometido) as causes_comprometido,
		SUM(no_causes_comprometido) as no_causes_comprometido,
		SUM(material_curacion_comprometido) as material_curacion_comprometido,
		SUM(causes_comprometido + no_causes_comprometido + material_curacion_comprometido) as comprometido,
		
		SUM(causes_devengado) as causes_devengado,
		SUM(no_causes_devengado) as no_causes_devengado,
		SUM(material_curacion_devengado) as material_curacion_devengado,
		SUM(causes_devengado + no_causes_devengado + material_curacion_devengado) as devengado,
		
		SUM(causes_disponible) as causes_disponible,
		SUM(no_causes_disponible) as no_causes_disponible,
		SUM(material_curacion_disponible) as material_curacion_disponible,
		SUM(causes_disponible + no_causes_disponible + material_curacion_disponible) as disponible
	
        FROM unidad_medica_presupuesto  
        
        LEFT JOIN unidades_medicas ON unidades_medicas.clues = unidad_medica_presupuesto.clues 
        LEFT JOIN proveedores ON proveedores.id = unidad_medica_presupuesto.proveedor_id
        LEFT JOIN (
            SELECT tipo, if(tipo = 'HO' OR tipo = 'HBC', '2','1') as nivel_atencion from unidades_medicas WHERE activa = 1 group by tipo
        ) AS nivel_atencion ON nivel_atencion.tipo = unidades_medicas.tipo

        WHERE unidades_medicas.activa = 1
        ";

       

        $where_insertado = true;
        if (isset($parametros['clues']) &&  $parametros['clues'] != "") {            
            $query .= " AND unidades_medicas.clues LIKE :clues";
            $where_insertado = true;
            $variables['clues']  = '%'.$parametros['clues'].'%';
        }

        if(isset($parametros['proveedores']) && $parametros['proveedores'] != ""){
            $proveedores = explode(',',$parametros['proveedores']);            
            if(count($proveedores)>0){
                if(!$where_insertado) {
                    $query .= " WHERE ";
                    $where_insertado = true;
                } else {
                    $query .= " AND ";
                }

                $contador = 0;
                $vars_items = "";
                foreach( $proveedores as $item){
                    if($contador>0){
                        $vars_items .= " , ";
                    }
                    $vars_items .= " :proveedor_".$item;
                    $variables[":proveedor_".$item] = $item;
                    $contador++;
                }

                $query .= " unidad_medica_presupuesto.proveedor_id IN ( ".$vars_items." )";              
            }              
        }

        if(isset($parametros['jurisdicciones']) && $parametros['jurisdicciones'] != ""){
            $jurisdicciones = explode(',',$parametros['jurisdicciones']);            
            if(count($jurisdicciones)>0){
                if(!$where_insertado) {
                    $query .= " WHERE ";
                    $where_insertado = true;
                } else {
                    $query .= " AND ";
                }

                $contador = 0;
                $vars_js = "";
                foreach( $jurisdicciones as $js){
                    if($contador>0){
                        $vars_js .= " , ";
                    }
                    $vars_js .= " :js_".$js;
                    $variables[":js_".$js] = $js;
                    $contador++;
                }
                $query .= " unidades_medicas.jurisdiccion_id IN ( ".$vars_js." )";            
            }              
        }

        if(isset($parametros['tipos_unidad']) && $parametros['tipos_unidad'] != ""){
            $tipos_unidad = explode(',',$parametros['tipos_unidad']);            
            if(count($tipos_unidad)>0){
                if(!$where_insertado) {
                    $query .= " WHERE ";
                    $where_insertado = true;
                } else {
                    $query .= " AND ";
                }


                $contador = 0;
                $vars_items = "";
                foreach( $tipos_unidad as $item){
                    if($contador>0){
                        $vars_items .= " , ";
                    }
                    $vars_items .= " :tipo_unidad_".$item;
                    $variables[":tipo_unidad_".$item] = $item;
                    $contador++;
                }

                $query .= " unidades_medicas.tipo IN ( ".$vars_items." )";           
            }              
        }
        if( isset($parametros['mes_inicio']) && isset($parametros['anio_inicio']) &&
            isset($parametros['mes_fin']) && isset($parametros['anio_fin'])){
            
            if(!$where_insertado) {
                $query .= " WHERE ";
                $where_insertado = true;
            } else {
                $query .= " AND ";
            }

            $query .= " STR_TO_DATE(CONCAT(anio,'-',LPAD(mes,2,'00'),'-01'),'%Y-%m-%d') BETWEEN STR_TO_DATE(CONCAT(:anio_inicio,'-',LPAD(:mes_inicio,2,'00'),'-01'),'%Y-%m-%d') AND STR_TO_DATE(CONCAT(:anio_fin,'-',LPAD(:mes_fin,2,'00'),'-01'),'%Y-%m-%d')";
            $variables['mes_inicio']  = $parametros['mes_inicio'];  
            $variables['anio_inicio']  = $parametros['anio_inicio']; 
            $variables['mes_fin']  = $parametros['mes_fin'];  
            $variables['anio_fin']  = $parametros['anio_fin'];  
        } else if(isset($parametros['mes_inicio']) && isset($parametros['anio_inicio']) && (!isset($parametros['mes_fin']) || !isset($parametros['anio_fin']))){
            if(!$where_insertado) {
                $query .= " WHERE ";
                $where_insertado = true;
            } else {
                $query .= " AND ";
            }

            $query .= " STR_TO_DATE(CONCAT(anio,'-',LPAD(mes,2,'00'),'-01'),'%Y-%m-%d') >= STR_TO_DATE(CONCAT(:anio_inicio,'-',LPAD(:mes_inicio,2,'00'),'-01'),'%Y-%m-%d') ";
            $variables['mes_inicio']  = $parametros['mes_inicio'];  
            $variables['anio_inicio']  = $parametros['anio_inicio']; 
        } else if((!isset($parametros['mes_inicio']) || !isset($parametros['anio_inicio'])) && isset($parametros['mes_fin']) && isset($parametros['anio_fin'])){
            if(!$where_insertado) {
                $query .= " WHERE ";
                $where_insertado = true;
            } else {
                $query .= " AND ";
            }

            $query .= " STR_TO_DATE(CONCAT(anio,'-',LPAD(mes,2,'00'),'-01'),'%Y-%m-%d') <= STR_TO_DATE(CONCAT(:anio_fin,'-',LPAD(:mes_fin,2,'00'),'-01'),'%Y-%m-%d') ";
            $variables['mes_fin']  = $parametros['mes_fin'];  
            $variables['anio_fin']  = $parametros['anio_fin']; 
        }
        
        if($parametros['agrupado_por'] == "UM" ){
            $query .= " GROUP BY unidad_medica_presupuesto.clues ORDER BY unidad_medica_presupuesto.clues";
        }

        if($parametros['agrupado_por'] == "NA"){
            $query .= " GROUP BY nivel_atencion  ORDER BY nivel_atencion";
        }
        if($parametros['agrupado_por'] == "P"){
            $query .= " GROUP BY proveedor_id ORDER BY proveedor_id";
        }
        
        //DB::enableQueryLog();
		$items = DB::select(DB::raw($query),$variables);
        //dd(DB::getQueryLog());
        //return [];
        return $items;
    }
    
}