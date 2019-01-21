<?php

namespace App\Http\Controllers\OpcionesAvanzadas;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, \DB, \Storage, \Artisan;

use App\Models\Servidor;

class MigracionServidorOfflineController extends Controller{
    /*function migrar(Request $request, $id){
        ini_set('max_execution_time', 36000);
        Storage::makeDirectory("server-migration");
        $migration_file = 'server-migration/online-to-offline-'.$id.'.mgrtn';
        try{
            $servidor = Servidor::find($id);
            $lista = [];
            if($servidor){               
                
                Storage::delete($migration_file);
                Storage::put($migration_file,"#Script para migrar base de datos del servidor principal a un servidor offline con id: ".$id);

                $tablas = DB::select(DB::raw("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('servidor_id') AND TABLE_SCHEMA='".env('DB_DATABASE') ."'
                AND TABLE_NAME IN (SELECT DISTINCT TABLE_NAME  FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('clues')  AND TABLE_SCHEMA='".env('DB_DATABASE') ."')"));

                $r = [];
                $tablas_relacionadas_ejecutadas = [];
                $tablas_padre = [];

                $sql = "";
                foreach($tablas as $tabla){ 
                    $tablas_padre[] =  $tabla->TABLE_NAME; 
                    $sql.= "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."' WHERE clues='".$servidor->clues."'; \n"; 
                }
                Storage::append($migration_file, $sql);
                $sql = "";
                $PK =[];
                foreach($tablas as $tabla){              

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
                    $PK[$tabla->TABLE_NAME] = $ids_registros_tabla;
                    
                    $tablas_relacionadas_ejecutadas = [$tabla->TABLE_NAME];
                    
                    self::procesarTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre,$lista,$tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas, $ids_registros_tabla,$migration_file,$servidor);
                }

                 ///Then download the file.
                 $storage_path = storage_path();
                 $filepath = $storage_path."/app/";
                 header('Content-Type: application/sql');
                 header('Content-disposition: attachment; filename=migracion_online_a_offline_servidor_id_'.$id);
                 header('Content-Length: ' . filesize($filepath.$migration_file));
              
                 readfile($filepath.$migration_file);
                 Storage::delete($migration_file);
 
                 exit();

               // return Response::json([ 'pk'=>$PK],200);
                //return Response::json([ 'lista_comprobar'=>$tablas, 'data' => $r, 'foreign_keys' => $lista],200);
            } else {
                throw new \Exception("No existe el servidor");
            }
        } catch (\Exception $e) {
            Storage::delete($migration_file);
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }*/

    function migrar(Request $request, $id){
        ini_set('max_execution_time', 36000);
        Storage::makeDirectory("server-migration");
        
        

        $servidor = Servidor::find($id);
        if(!$servidor){
            return Response::json(['error' => "No existe el servidor"], HttpResponse::HTTP_CONFLICT);
        }

        //$migration_file_set_servidor_id = "server-migration/change_server_id_to_".$servidor->id."_script.mgrtn";

        //Storage::put($migration_file_set_servidor_id, "");
        


        $conexion_db = DB::connection('mysql');
        $conexion_db->beginTransaction();
        try{            
            
                         
                $statements_cambio_servidor_id =  [];
                $cambio_servidor_id_exitoso = self::cambiarServidorID($servidor, $statements_cambio_servidor_id);
                if($cambio_servidor_id_exitoso == true){
                     ///Then download the file.

                    /* $storage_path = storage_path();
                    $filepath = $storage_path."/app/";
                    header('Content-Type: application/sql');
                    header('Content-disposition: attachment; filename=migracion_online_a_offline_servidor_id_'.$servidor->id);
                    header('Content-Length: ' . filesize($filepath.$migration_file_set_servidor_id));
                
                    readfile($filepath.$migration_file_set_servidor_id);
                    Storage::delete($migration_file_set_servidor_id);
                    exit();*/
                    //$sql_cambio_servidor_id = "INSERK  INTO sial.pedidos VAS sorvidor_id = '0006';";

                   // $sql_cambio_servidor_id = "SELECT * FROM sial.pedidos WHERE sorvidor_id = '0006';";
                   //$sql_cambio_servidor_id = "UPDATE actas SET servidor_id='0006' WHERE clues='CSSSA019954'; INSERK  INTO sial.pedidos VAS sorvidor_id = '0006';";
                    //self::appendLine($sql_cambio_servidor_id,"INSERK  INTO sial.pedidos VAS sorvidor_id = '0006';");

                   // $statements_cambio_servidor_id[] = "INSERK  INTO sial.pedidos VAS sorvidor_id = '0006';";
                  

                   // $contents = Storage::get($migration_file_set_servidor_id);


                    //$conexion_db->statement('SET FOREIGN_KEY_CHECKS=0');
                   
                   // $result = $conexion_db->getpdo()->exec($contents);

                    //$result = $conexion_db->unprepared($contents);
                    //$conexion_db->statement('SET FOREIGN_KEY_CHECKS=1');
                    foreach($statements_cambio_servidor_id as $statement){
                        $conexion_db->statement($statement);
                    }

                    $conexion_db->rollback();

                    //Storage::delete($migration_file_set_servidor_id);
                    return Response::json(['data' => $statements_cambio_servidor_id], 200);
                    
                } else {
                    throw new \Exception($cambio_servidor_id_exitoso);
                }                
                 
            
        } catch (\Exception $e) {
            Storage::delete($migration_file_set_servidor_id);
            $conexion_db->rollback();
            return Response::json(['error' => $e->getMessage(),'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    function cambiarServidorID($servidor, &$statements){       
        
        try{
            $lista = [];

           // $sql = "#Script para migrar base de datos del servidor principal a un servidor offline con id: ".$servidor->id;

            $tablas = DB::select(DB::raw("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('servidor_id') AND TABLE_SCHEMA='".env('DB_DATABASE') ."'
            AND TABLE_NAME IN (SELECT DISTINCT TABLE_NAME  FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('clues')  AND TABLE_SCHEMA='".env('DB_DATABASE') ."')"));

 
            $tablas_relacionadas_ejecutadas = [];
            $tablas_padre = [];

            foreach($tablas as $tabla){ 
                $tablas_padre[] =  $tabla->TABLE_NAME; 
                //Storage::append($migration_file,  "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."' WHERE clues='".$servidor->clues."';");
                $statements[] = "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."' WHERE clues='".$servidor->clues."';";
                //self::appendLine($sql,"UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."' WHERE clues='".$servidor->clues."';" );
            }
            //Storage::append($migration_file, "INSERK  INTO sial.pedidos VAS sorvidor_id = '0006';");
          
            
            foreach($tablas as $tabla){              

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
                
                self::procesarTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre,$lista,$tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas, $ids_registros_tabla,$statements,$servidor);
            }                
            
            return true;
           
        } catch (\Exception $e) {
            return $e;
        } 
    }

    

    function procesarTablasRelacionadas($referenced_table_name, $tablas_padre ,&$lista, $key_padre, $tablas_relacionadas_ejecutadas, $ids_registros_relacionados, &$statements, $servidor){      
        
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
            if( $tabla->TABLE_NAME == $referenced_table_name){
                continue;
            }

            // Esto es para evitar editar tablas padre dentro de una iteración (tablas con servidor_id y clues)
            if(in_array($tabla->TABLE_NAME,$tablas_padre)){
                continue;
            }

            // Esto es para evitar ejecutar una tabla en una misma linea del tiempo mas de una vez
            if(in_array($tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas)){
                continue;
            }

            // Esta llave se genera para evitar entrar mas de una vez a una tabla que viene del mismo padre
            $key = $key_padre.".".$tabla->TABLE_NAME.".".$tabla->COLUMN_NAME;
           //$key = $tabla->TABLE_NAME.".".$tabla->COLUMN_NAME;
            if(in_array($key,$lista)){
                continue;
            }           

            $title = "#### ".$ruta." > ".$tabla->TABLE_NAME." ####";
            //self::appendLine($sql, $title);

            $_tablas_ejecutadas = $tablas_ejecutadas;
            $_tablas_ejecutadas[] = $tabla->TABLE_NAME;
            $lista[] = $key;            

            // Revisar si tiene servidor_id
            $col_servidor_id = DB::select(DB::raw("show columns from ".$tabla->TABLE_NAME." like 'servidor_id' "));
            $tiene_servidor_id = false;
            if(count($col_servidor_id)>0 && $ids_registros_relacionados != ""){
                $tiene_servidor_id = true;

                // actualizar registros a nuevo servidor_id
                $statements[] = "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."' WHERE ".$tabla->COLUMN_NAME." IN (".$ids_registros_relacionados.");";
                //self::appendLine($sql,$line );
                
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
            self::procesarTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre, $lista, $key, $_tablas_ejecutadas, $ids_registros_tabla,$migration_file,$servidor);

           // self::appendLine($sql, "#### FIN: ".$ruta." > ".$tabla->TABLE_NAME." #####");
            
        }
        
        
        unset($tablas_ejecutadas);
    }
/*
    
    function procesarTablasRelacionadas($referenced_table_name, $tablas_padre ,&$lista, $key_padre, $tablas_relacionadas_ejecutadas, $ids_registros_relacionados, $migration_file, $servidor){      
        
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
            if( $tabla->TABLE_NAME == $referenced_table_name){
                continue;
            }

            // Esto es para evitar editar tablas padre dentro de una iteración (tablas con servidor_id y clues)
            if(in_array($tabla->TABLE_NAME,$tablas_padre)){
                continue;
            }

            // Esto es para evitar ejecutar una tabla en una misma linea del tiempo mas de una vez
            if(in_array($tabla->TABLE_NAME,$tablas_relacionadas_ejecutadas)){
                continue;
            }

            // Esta llave se genera para evitar entrar mas de una vez a una tabla que viene del mismo padre
            $key = $key_padre.".".$tabla->TABLE_NAME.".".$tabla->COLUMN_NAME;
           //$key = $tabla->TABLE_NAME.".".$tabla->COLUMN_NAME;
            if(in_array($key,$lista)){
                continue;
            }           

            //$title = "#### ".$ruta." > ".$tabla->TABLE_NAME." ####";
           // Storage::append($migration_file, $title);
            $_tablas_ejecutadas = $tablas_ejecutadas;
            $_tablas_ejecutadas[] = $tabla->TABLE_NAME;
            $lista[] = $key;            

            // Revisar si tiene servidor_id
            $col_servidor_id = DB::select(DB::raw("show columns from ".$tabla->TABLE_NAME." like 'servidor_id' "));
            $tiene_servidor_id = false;
            if(count($col_servidor_id)>0 && $ids_registros_relacionados != ""){
                $tiene_servidor_id = true;

                // actualizar registros a nuevo servidor_id
                $sql = "UPDATE ".$tabla->TABLE_NAME." SET servidor_id='".$servidor->id."' WHERE ".$tabla->COLUMN_NAME." IN (".$ids_registros_relacionados.");";
                Storage::append($migration_file, $sql);
                
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
            self::procesarTablasRelacionadas($tabla->TABLE_NAME,$tablas_padre, $lista, $key, $_tablas_ejecutadas, $ids_registros_tabla,$migration_file,$servidor);

            //Storage::append($migration_file, "#### FIN: ".$ruta." > ".$tabla->TABLE_NAME." #####");
        }
        
        
        unset($tablas_ejecutadas);
    }*/
     

    function appendLine(&$string, $newLine ){
        $string .= "\n".$newLine;
    }
}
?>