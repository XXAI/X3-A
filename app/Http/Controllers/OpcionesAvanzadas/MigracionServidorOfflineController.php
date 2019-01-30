<?php

namespace App\Http\Controllers\OpcionesAvanzadas;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \Storage, \Artisan;

use App\Models\Servidor, App\Models\LogMigracionServidorOffline;

class MigracionServidorOfflineController extends Controller{
    public static $ignorarTablas = [        
        'log_ejecucion_parches',   
        'repositorio',        
        'sincronizaciones',
        'transferencias_presupuesto'

    ];


    function migrar(Request $request, $id){
        ini_set('max_execution_time', 36000);
        $duration_start = microtime(true);
        Storage::makeDirectory("server-migration");
        
        
        

        $servidor = Servidor::find($id);
        if(!$servidor){
            return Response::json(['error' => "No existe el servidor"], HttpResponse::HTTP_CONFLICT);
        }


        $sql_file = "server-migration/".$servidor->id.".mgrtn";
        Storage::put($sql_file, "# Migración del servidor: ".$servidor->id.", CLUES: ".$servidor->clues.", generado el: ".date('Y-m-d H:i:s'));
        

        $conexion_db = DB::connection('mysql');
        $conexion_db->beginTransaction();
        try{    
                         
                $statements_cambio_servidor_id =  [];
                $cambio_servidor_id_exitoso = self::cambiarServidorID($servidor, $statements_cambio_servidor_id, $conexion_db);
                if($cambio_servidor_id_exitoso === true){
                     
                    foreach($statements_cambio_servidor_id as $statement){
                        $conexion_db->statement($statement);
                    }

                    //################################################################
                    self::generarSQLServidorID($servidor, $conexion_db, $sql_file);
                    //##############################################################
                    $conexion_db->commit();
                    $duration_end = microtime(true);
                    LogMigracionServidorOffline::create([
                        "servidor_migrado_id" => $servidor->id,
                        "duration" => ($duration_end - $duration_start),
                        "status" => "OK",
                        "mensaje" =>""
                    ]);

                    $storage_path = storage_path();
                    $filepath = $storage_path."/app/";
                    header('Content-Type: application/sql');
                    header('Content-disposition: attachment; filename=migracion_online_a_offline_servidor_id_'.$servidor->id);
                    header('Content-Length: ' . filesize($filepath.$sql_file));
                
                    readfile($filepath.$sql_file);
                    //Storage::delete($sql_file);

                    

                    exit();
                   // return Response::json(['data' => $statements_cambio_servidor_id], 200);
                    
                } else {
                    throw new \Exception($cambio_servidor_id_exitoso);
                }                
                 
            
        } catch (\Exception $e) {
            Storage::delete($sql_file);
            $conexion_db->rollback();
            $duration_end = microtime(true);
            LogMigracionServidorOffline::create([
                "servidor_migrado_id" => $id,
                "duration" => ($duration_end - $duration_start),
                "status" => "ERROR",
                "mensaje" => $e->getMessage()
            ]);
            return Response::json(['error' => $e->getMessage(),'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    function cambiarServidorID($servidor, &$statements, &$conexion_db){      
        try{
            $lista = [];

           // $sql = "#Script para migrar base de datos del servidor principal a un servidor offline con id: ".$servidor->id;

            $tablas = DB::select(DB::raw("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('servidor_id') AND TABLE_SCHEMA='".env('DB_DATABASE') ."'
            AND TABLE_NAME IN (SELECT DISTINCT TABLE_NAME  FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('clues')  AND TABLE_SCHEMA='".env('DB_DATABASE') ."')"));

 
            $tablas_relacionadas_ejecutadas = [];
            $tablas_padre = [];

            foreach($tablas as $tabla){ 
                if(in_array($tabla->TABLE_NAME, self::$ignorarTablas)){
                    continue;
                }
                if(substr($tabla->TABLE_NAME,0,1) == "_"){
                    continue;
                }
                
                $tablas_padre[] =  $tabla->TABLE_NAME; 
               
                  
                $columnas = $conexion_db->getSchemaBuilder()->getColumnListing($tabla->TABLE_NAME);    
                $set_incremento = "";
                $set_updated_at = "";
                if(in_array('incremento',$columnas)){
                    $set_incremento = ", incremento = 0 ";
                }
                if(in_array('updated_at',$columnas)){
                    $set_updated_at = ", updated_at=CURRENT_TIMESTAMP() ";
                }

                $statements[] = "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."'".$set_incremento.$set_updated_at." WHERE servidor_id !='".$servidor->id."' AND clues='".$servidor->clues."';";                
            }

            foreach($tablas as $tabla){          
                if(in_array($tabla->TABLE_NAME, self::$ignorarTablas)){
                    continue;
                }    
                if(substr($tabla->TABLE_NAME,0,1) == "_"){
                    continue;
                }
                // Recuperar registros relacionados para pasarlo a la funcion recursiva
                $ids_registros_tabla = "";
                $primary_key_column = DB::select(DB::raw("SHOW KEYS FROM ".$tabla->TABLE_NAME." WHERE key_name = 'PRIMARY'"));
               
                if(count($primary_key_column)> 0){
                    $primary_key_column_name = $primary_key_column[0]->Column_name;
                    $rows = DB::select(DB::raw("SELECT ".$primary_key_column_name." as id FROM ".$tabla->TABLE_NAME." WHERE clues='".$servidor->clues."'"));
                    $c = 0;
                    foreach($rows as $row){
                        if($c>0){
                            $ids_registros_tabla .= ", ";
                        }
                        $c++;
                        $ids_registros_tabla .= "'".$row->id."'";
                    }
                }
                
                $tablas_relacionadas_ejecutadas = [$tabla->TABLE_NAME];
                
                self::procesarTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre,$lista,$tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas, $ids_registros_tabla,$statements,$servidor, $conexion_db);

               
            }                
            
            return true;
           
        } catch (\Exception $e) {
            
            return $e;
        } 
    }

    function generarSQLServidorID($servidor, &$conexion_db, $sql_file){
        try{
            $tablas = DB::select(DB::raw("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('servidor_id') AND TABLE_SCHEMA='".env('DB_DATABASE') ."'"));
            Storage::append($sql_file, 'SET autocommit=0;');
            Storage::append($sql_file, 'SET FOREIGN_KEY_CHECKS=0;'); 
            Storage::append($sql_file, 'SET names utf8;');

            $tablas_relacionadas_ejecutadas = [];
            $tablas_padre = [];

            $lista = [];


            foreach($tablas as $tabla){ 
                if(in_array($tabla->TABLE_NAME, self::$ignorarTablas)){
                    continue;
                }         
                if(substr($tabla->TABLE_NAME,0,1) == "_"){
                    continue;
                }       
                $tablas_padre[] =  $tabla->TABLE_NAME;               
            }
            
            foreach($tablas as $tabla){ 
                if(in_array($tabla->TABLE_NAME, self::$ignorarTablas)){
                    continue;
                }               
                if(substr($tabla->TABLE_NAME,0,1) == "_"){
                    continue;
                }

                $columnas = $conexion_db->getSchemaBuilder()->getColumnListing($tabla->TABLE_NAME);
                
                $rows = $conexion_db->table($tabla->TABLE_NAME)->where('servidor_id',$servidor->id);
                if(in_array('incremento',$columnas)){
                    $rows = $rows->orderBy('incremento','asc');
                }

                $rows = $rows->get();

                $rows_chunks = array_chunk($rows, 50);             
                
                foreach($rows_chunks as $row_chunk){
                    $statement = "REPLACE INTO ".$tabla->TABLE_NAME." VALUES ";   
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
                                case "string": $item .= "\"".$row->$nombre."\""; break;
                                case "NULL": $item .= "NULL"; break;
                                default: $item .= $row->$nombre;
                            }
                            
                            $index_items += 1;
                        }
                        $item .= ") ";
                        $index_replace += 1;
                        
                        $statement.= $item;                      
                    }
                    $statement .= ";";
                    Storage::append($sql_file, $statement);                       
                        
                }

                // Recuperar registros relacionados para pasarlo a la funcion recursiva
                $ids_registros_tabla = "";
                $primary_key_column = DB::select(DB::raw("SHOW KEYS FROM ".$tabla->TABLE_NAME." WHERE key_name = 'PRIMARY'"));
                
                $tablas_relacionadas_ejecutadas = [$tabla->TABLE_NAME];

                if(count($primary_key_column)> 0){
                    $primary_key_column_name = $primary_key_column[0]->Column_name;
                    $rows = DB::select(DB::raw("SELECT ".$primary_key_column_name." as id FROM ".$tabla->TABLE_NAME." WHERE servidor_id='".$servidor->id."'"));
                    $c = 0;
                    foreach($rows as $row){
                        if($c>0){
                            $ids_registros_tabla .= ", ";
                        }
                        $c++;
                        $ids_registros_tabla .= "'".$row->id."'";
                    }

                    if($c > 0){
                        self::obtenerRegistrosTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre,$lista,$tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas, $ids_registros_tabla,$servidor, $conexion_db, $sql_file);
                    }
                }
                
               

                
            }
            Storage::append($sql_file, 'SET FOREIGN_KEY_CHECKS=1;');
            Storage::append($sql_file, 'COMMIT;');

        } catch (\Exception $e) {
            Storage::append($sql_file,"# ERROR: ".$e->getMessage()." - ".$e->getLine());
            Storage::append($sql_file,"ROLLBACK;");

            return $e;
        } 
    }

    function obtenerRegistrosTablasRelacionadas($referenced_table_name, $tablas_padre ,&$lista, $key_padre, $tablas_relacionadas_ejecutadas, $ids_registros_relacionados, $servidor, &$conexion_db, $sql_file){      
      
        $tablas_dependientes = DB::select(DB::raw("
                    SELECT  `TABLE_SCHEMA`, `TABLE_NAME`, `COLUMN_NAME`,`CONSTRAINT_NAME`, `REFERENCED_TABLE_SCHEMA`, `REFERENCED_TABLE_NAME`, `REFERENCED_COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE`
                    WHERE `TABLE_SCHEMA` = '".env('DB_DATABASE')."' AND REFERENCED_TABLE_NAME    = '".$referenced_table_name."'"));
        

        $tablas_ejecutadas = $tablas_relacionadas_ejecutadas;
        $ruta = $referenced_table_name;
        if(count($tablas_relacionadas_ejecutadas)> 0){
            
            $ruta = implode(" > ", $tablas_relacionadas_ejecutadas);
        }        
        
        foreach($tablas_dependientes as $tabla){            
          
            // No a mi mismo para evitar ciclar la funcion
            if( $tabla->TABLE_NAME == $referenced_table_name){ continue; }            

            // Esto es para evitar editar tablas padre dentro de una iteración (tablas con servidor_id y clues)
            if(in_array($tabla->TABLE_NAME,$tablas_padre)){ continue; }            

            // Esto es para evitar ejecutar una tabla en una misma linea del tiempo mas de una vez
            if(in_array($tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas)){ continue; }            

            // Esta llave se genera para evitar entrar mas de una vez a una tabla que viene del mismo padre
            $key = $key_padre.".".$tabla->TABLE_NAME.".".$tabla->COLUMN_NAME;           
            if(in_array($key,$lista)){ continue; }
            
            if(in_array($tabla->TABLE_NAME, self::$ignorarTablas)){ continue; }         
            
            if(substr($tabla->TABLE_NAME,0,1) == "_"){ continue; }       

            $_tablas_ejecutadas = $tablas_ejecutadas;
            $_tablas_ejecutadas[] = $tabla->TABLE_NAME;
            $lista[] = $key;            

            $columnas = $conexion_db->getSchemaBuilder()->getColumnListing($tabla->TABLE_NAME);
            
            $order_by_incremento = "";
            if(in_array('incremento',$columnas)){
                $order_by_incremento = " order by incremento asc";
            }
            
            $rows = DB::select(DB::raw("SELECT * FROM ".$tabla->TABLE_NAME." WHERE ".$tabla->COLUMN_NAME." IN (".$ids_registros_relacionados.")".$order_by_incremento));
            $rows_chunks = array_chunk($rows, 50);

            $ids_registros_tabla = "";
            $primary_key_column = DB::select(DB::raw("SHOW KEYS FROM ".$tabla->TABLE_NAME." WHERE key_name = 'PRIMARY'"));

            foreach($rows_chunks as $row_chunk){
                $statement = "REPLACE INTO ".$tabla->TABLE_NAME." VALUES ";   
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
                            case "string": $item .= "\"".$row->$nombre."\""; break;
                            case "NULL": $item .= "NULL"; break;
                            default: $item .= $row->$nombre;
                        }
                        
                        $index_items += 1;
                    }
                    $item .= ") ";
                    $index_replace += 1;
                    
                    $statement.= $item;                      
                }
                $statement .= ";";
                Storage::append($sql_file, $statement);                       
                    
            }



            // Recuperar registros y se le pasa a la funcion recursiva
            $ids_registros_tabla = "";
            $primary_key_column = DB::select(DB::raw("SHOW KEYS FROM ".$tabla->TABLE_NAME." WHERE key_name = 'PRIMARY'"));
            
            if(count($primary_key_column)> 0 && $ids_registros_relacionados != ""){
                $primary_key_column_name = $primary_key_column[0]->Column_name;
                $rows = DB::select(DB::raw("SELECT ".$primary_key_column_name." as id FROM ".$tabla->TABLE_NAME." WHERE ".$tabla->COLUMN_NAME." IN (".$ids_registros_relacionados.")"));
                $c = 0;
                foreach($rows as $row){
                    if($c>0){
                        $ids_registros_tabla .= ", ";
                    }
                    $c++;
                    $ids_registros_tabla .= "'".$row->id."'";
                }
                if($c > 0) {
                    self::obtenerRegistrosTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre, $lista, $key, $_tablas_ejecutadas, $ids_registros_tabla,$servidor, $conexion_db, $sql_file);           
                }
            }
            
            
            
            
        }
        unset($tablas_ejecutadas);       
    }


    function procesarTablasRelacionadas($referenced_table_name, $tablas_padre ,&$lista, $key_padre, $tablas_relacionadas_ejecutadas, $ids_registros_relacionados, &$statements, $servidor, &$conexion_db){      
      
        $tablas_dependientes = DB::select(DB::raw("
                    SELECT  `TABLE_SCHEMA`, `TABLE_NAME`, `COLUMN_NAME`,`CONSTRAINT_NAME`, `REFERENCED_TABLE_SCHEMA`, `REFERENCED_TABLE_NAME`, `REFERENCED_COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE`
                    WHERE `TABLE_SCHEMA` = '".env('DB_DATABASE')."' AND REFERENCED_TABLE_NAME    = '".$referenced_table_name."'"));
        

        $tablas_ejecutadas = $tablas_relacionadas_ejecutadas;
        $ruta = $referenced_table_name;
        if(count($tablas_relacionadas_ejecutadas)> 0){
            
            $ruta = implode(" > ", $tablas_relacionadas_ejecutadas);
        }        
        
        foreach($tablas_dependientes as $tabla){            
          
            // No a mi mismo para evitar ciclar la funcion
            if( $tabla->TABLE_NAME == $referenced_table_name){ continue; }            

            // Esto es para evitar editar tablas padre dentro de una iteración (tablas con servidor_id y clues)
            if(in_array($tabla->TABLE_NAME,$tablas_padre)){ continue; }            

            // Esto es para evitar ejecutar una tabla en una misma linea del tiempo mas de una vez
            if(in_array($tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas)){ continue; }            

            // Esta llave se genera para evitar entrar mas de una vez a una tabla que viene del mismo padre
            $key = $key_padre.".".$tabla->TABLE_NAME.".".$tabla->COLUMN_NAME;           
            if(in_array($key,$lista)){ continue; }
            
            if(in_array($tabla->TABLE_NAME, self::$ignorarTablas)){ continue; }     
            
            if(substr($tabla->TABLE_NAME,0,1) == "_"){ continue; }

            $_tablas_ejecutadas = $tablas_ejecutadas;
            $_tablas_ejecutadas[] = $tabla->TABLE_NAME;
            $lista[] = $key;            

            // Revisar si tiene servidor_id          
            $columnas = $conexion_db->getSchemaBuilder()->getColumnListing($tabla->TABLE_NAME);                  
            if(in_array('servidor_id',$columnas)  && $ids_registros_relacionados != ""){
            

                $set_incremento = "";
                $set_updated_at = "";
                if(in_array('incremento',$columnas)){
                    $set_incremento = ", incremento = 0 ";
                }

                if(in_array('updated_at',$columnas)){
                    $set_updated_at = ", updated_at=CURRENT_TIMESTAMP() ";
                }

                $statements[] = "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."'".$set_incremento.$set_updated_at." WHERE servidor_id !='".$servidor->id."' AND ".$tabla->COLUMN_NAME." IN (".$ids_registros_relacionados.");";                               
            }           
            
            // Recuperar registros y se le pasa a la funcion recursiva
            $ids_registros_tabla = "";
            $primary_key_column = DB::select(DB::raw("SHOW KEYS FROM ".$tabla->TABLE_NAME." WHERE key_name = 'PRIMARY'"));
            
            if(count($primary_key_column)> 0 && $ids_registros_relacionados != ""){
                $primary_key_column_name = $primary_key_column[0]->Column_name;
                $rows = DB::select(DB::raw("SELECT ".$primary_key_column_name." as id FROM ".$tabla->TABLE_NAME." WHERE ".$tabla->COLUMN_NAME." IN (".$ids_registros_relacionados.")"));
                $c = 0;
                foreach($rows as $row){
                    if($c>0){
                        $ids_registros_tabla .= ", ";
                    }
                    $c++;
                    $ids_registros_tabla .= "'".$row->id."'";
                }
            }
            self::procesarTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre, $lista, $key, $_tablas_ejecutadas, $ids_registros_tabla,$statements,$servidor, $conexion_db);            
        }
        unset($tablas_ejecutadas);       
    }


     

}
?>