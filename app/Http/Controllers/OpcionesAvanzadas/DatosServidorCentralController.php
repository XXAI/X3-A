<?php

namespace App\Http\Controllers\OpcionesAvanzadas;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \Storage, \Artisan, \Config, \ZipArchive;
use App\Models\Usuario, App\Models\UnidadMedica, App\Models\Servidor;
use Carbon\Carbon;

class DatosServidorCentralController extends Controller{

    public function exportar(Request $request){
        ini_set('memory_limit', '-1');
        // 1. Debemos generar el link de descarga en una carpeta con una cadena aleatoria
        // 2. Cuando generemos el link en el controlador debe existir un middleware que al descargar el archivo lo borre del sistema

        $usuario = Usuario::find($request->get('usuario_id'));
        $servidor = Servidor::find($usuario->servidor_id);
        $unidad_medica = UnidadMedica::where('clues',$servidor->clues)->first();

        if($servidor->id == '0001'){
            return Response::json(['error' => 'Opción no valida para uusarios del servidor central'], HttpResponse::HTTP_CONFLICT);
        }

        if(!$unidad_medica->es_offline){
            return Response::json(['error' => 'La unidad medica no esta configurada como offline'], HttpResponse::HTTP_CONFLICT);
        }

        $servidor_id = $usuario->servidor_id;
        $clues = $servidor->clues;

        Storage::deleteDirectory("datos-central");

        Storage::delete("datos.".$servidor_id.".zip");
        Storage::makeDirectory("datos-central");
        
        // Creamos o reseteamos archivo de respaldo
        Storage::put('datos-central/header.sync',"ID=".$servidor_id);
        Storage::put('datos-central/header.sync',"CLUES=".$clues);
        Storage::append('datos-central/header.sync',"SECRET_KEY=".$servidor->sercret_key);
        Storage::append('datos-central/header.sync',"VERSION=".Config::get("sync.api_version"));
        Storage::append('datos-central/header.sync',"FECHA_DESCARGA=".Carbon::now());

        Storage::put('datos-central/datos.sync', "");        
        //Storage::append('datos-central/datos.sync', "INSERT INTO sincronizaciones (servidor_id,fecha_generacion) VALUES ('".$servidor_id."','".$fecha_generacion."'); \n");
        
        try {

            // Generamos archivo de sincronización de registros actualizados o creados a la fecha de corte
         
            
            $tablas_datos = [
                    'pedidos',
                    'pedidos_insumos',
                    /*
                    'actas',
                    'pedido_metadatos_sincronizaciones',
                    'pedido_proveedor_insumos',
                    'pedidos_alternos',
                    'pedidos_insumos_clues',
                    'consumos_promedios',
                    'cuadros_distribucion',
                    'insumos_maximos_minimos',
                    */
                    'movimientos',
                    'movimiento_insumos',
                    'movimiento_insumos_borrador',
                    'movimiento_ajustes',
                    'movimiento_detalles',
                    'movimiento_metadatos',
                    'movimiento_pedido',
                    'log_pedido_borrador',
                    'log_recepcion_borrador',
                    'log_transferencias_canceladas',
                    'historial_movimientos_transferencias',
                    'negaciones_insumos',
                    'recetas',
                    //'recetas_digitales',
                    'receta_detalles',
                    //'receta_digital_detalles',
                    //'receta_movimientos',
                    'stock',
                    'stock_borrador'
                ];
            
                $pedidos_ids = [];
            foreach($tablas_datos as $key){

                $rows = DB::table($key)->where('servidor_id',$servidor_id)->get();
                
                if($rows){
                    $query = "";
                    $rows_chunks = array_chunk($rows, 50);
                    $columnas = DB::getSchemaBuilder()->getColumnListing($key);

                    foreach($rows_chunks as $row_chunk){
                        
                        $query .= "REPLACE INTO ".$key." VALUES ";

                        $index_replace = 0;
                        
                        foreach($row_chunk as $row){
                            if ($index_replace!=0){
                                $item = ", (";
                            } else {
                                $item = "(";
                            }
                            
                            $index_items = 0;
                            foreach($columnas as $nombre){
                                if ($index_items!=0){
                                    $item .= ",";
                                }

                                $tipo  = gettype($row->$nombre);
                                
                                switch($tipo){
                                    case "string": $item .= "\"".addslashes($row->$nombre)."\""; break;
                                    case "NULL": $item .= "NULL"; break;
                                    default: $item .= addslashes($row->$nombre);
                                }
                                
                                $index_items += 1;
                            }
                            $item .= ") ";
                            $index_replace += 1;
                            
                            Storage::append('datos-central/sumami.sync', $item);                       
                            $query .= $item;
                        }
                        $query .= "; \n";
                        Storage::append('datos-central/sumami.sync', "; \n");
                    }
                    Storage::append('datos-central/datos.sync', $query);
                }else{
                    Storage::append('datos-central/datos.sync', 'sin rows');
                }
            }
            //return \Response::json(['message' => 'exito'], 200);   
            
            $storage_path = storage_path();
            $zip = new ZipArchive();
            $zippath = $storage_path."/app/";
            $zipname = "datos-central.".$servidor->id.".zip";
           
            exec("zip -P ".$servidor->secret_key." -j -r ".$zippath.$zipname." \"".$zippath."datos-central/\"");
            
            $zip_status = $zip->open($zippath.$zipname);

            if ($zip_status === true) {
                
                $zip->close();
                Storage::deleteDirectory("datos-central");
                
                ///Then download the zipped file.
                header('Content-Type: application/zip');
                header('Content-disposition: attachment; filename='.$zipname);
                header('Content-Length: ' . filesize($zippath.$zipname));
             
                readfile($zippath.$zipname);
                Storage::delete($zipname);

                /*LogSync::create([
                    "clues" => env("CLUES"),
                    "descripcion" => "Generó archivo de sincronización manual."
                ]);*/
                
                exit();
            } else {                
                throw new \Exception("No se pudo crear el archivo");
            }
            
        } catch (\Exception $e) {    
            /*LogSync::create([
                "clues" => env("CLUES"),
                "descripcion" => "Intentó generar archivo de sincronización manual pero hubo un error."
            ]);*/
            //echo " Sync Manual Excepción: ".$e->getMessage();
            //Storage::append('log.sync', $fecha_generacion." Sync Manual Excepción: ".$e->getMessage());  
            //Storage::deleteDirectory("sync");     
            return \Response::json(['error' => $e->getMessage(), 'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);   
        }
	}
	
	public function importar(Request $request){
		// Abrá que implementar algún tipo de seguridad para que no suban otro script que por ejemplo borre toda la bases de datos		
		ini_set('memory_limit', '-1');
        try{
            //return Response::json([ 'data' => $output],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
}
