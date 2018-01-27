<?php

namespace App\Http\Controllers\Sync;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use \DB, \Storage, \ZipArchive, \Hash, \Response, \Config;
use Illuminate\Support\Facades\Input;
use App\Models\Sincronizacion, App\Models\Servidor; 
use App\Librerias\Sync\ArchivoSync;
use Carbon\Carbon;

class SincronizacionController extends \App\Http\Controllers\Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function lista()
    {

        $parametros = Input::only('page','per_page','q');
        $items = Sincronizacion::select('sincronizaciones.*', 'servidores.nombre as servidor_nombre')->leftjoin("servidores","servidores.id","=","sincronizaciones.servidor_id")->orderBy('created_at','desc');
        
        
        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
    }
    
    /**
     * Crea un archivo comprimido para sincronización protegido con SECRET KEY 
     *
     * @return \Illuminate\Http\Response
     */
    public function manual()
    {
        ini_set('memory_limit', '-1');
        // 1. Debemos generar el link de descarga en una carpeta con una cadena aleatoria
        // 2. Cuando generemos el link en el controlador debe existir un middleware que al descargar el archivo lo borre del sistema

        $ultima_sincronizacion =  Sincronizacion::select('fecha_generacion')->where("servidor_id",env("SERVIDOR_ID"))->orderBy('fecha_generacion','desc')->first();
        $fecha_generacion = date('Y-m-d H:i:s');

        Storage::delete("sync.".env('SERVIDOR_ID').".zip");
        Storage::makeDirectory("sync");
        
        // Creamos o reseteamos archivo de respaldo
        Storage::put('sync/header.sync',"ID=".env('SERVIDOR_ID'));
        Storage::append('sync/header.sync',"SECRET_KEY=".env('SECRET_KEY'));
        Storage::append('sync/header.sync',"VERSION=".env('VERSION'));
        Storage::append('sync/header.sync',"FECHA_SYNC=".$fecha_generacion);

        Storage::put('sync/sumami.sync', "");        
        Storage::append('sync/sumami.sync', "INSERT INTO sincronizaciones (servidor_id,fecha_generacion) VALUES ('".env('SERVIDOR_ID')."','".$fecha_generacion."'); \n");
        
        try {

            // Generamos archivo de sincronización de registros actualizados o creados a la fecha de corte
         
            foreach(Config::get("sync.tablas") as $key){
                
                if ($ultima_sincronizacion) {
                    $rows = DB::table($key)->where("servidor_id",env("SERVIDOR_ID"))->whereBetween('updated_at',[$ultima_sincronizacion->fecha_generacion,$fecha_generacion])->get();
                } else {             
                    $rows = DB::table($key)->where("servidor_id",env("SERVIDOR_ID"))->get();
                }
               
                if($rows){
                    $query = "";
                    $rows_chunks = array_chunk($rows, 50);
                    $columnas = DB::getSchemaBuilder()->getColumnListing($key);

                    foreach($rows_chunks as $row_chunk){
                        //Storage::append('sync/sumami.sync', "REPLACE INTO ".$key." VALUES ");
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
                            
                            //Storage::append('sync/sumami.sync', $item);                       
                            $query .= $item;
                        }
                        $query .= "; \n";
                        //Storage::append('sync/sumami.sync', "; \n");
                    }
                    Storage::append('sync/sumami.sync', $query);

                } 
            }
           
            // Generamos archivo de catalogos para que cuando se sincronize en el servidor principal se sepa si están actualizados o no
          
            if(Config::get("sync.catalogos")){   
                     
                $contador = 0;  
                
                foreach (Config::get("sync.catalogos") as $key) {
                   
                    $ultima_actualizacion = DB::table($key)->max("updated_at"); 
                    
                    if($contador==0){
                        Storage::put('sync/catalogos.sync', $key."=".$ultima_actualizacion);
                    } else {
                        Storage::append('sync/catalogos.sync', $key."=".$ultima_actualizacion);
                    }                    
                    $contador++;
                }
            } else {
                Storage::put('sync/catalogos.sync','');
            }
            $storage_path = storage_path();
            $zip = new ZipArchive();
            $zippath = $storage_path."/app/";
            $zipname = "sync.".env('SERVIDOR_ID').".zip";
           
            exec("zip -P ".env('SECRET_KEY')." -j -r ".$zippath.$zipname." \"".$zippath."sync/\"");
            
            $zip_status = $zip->open($zippath.$zipname);

            if ($zip_status === true) {

                $zip->close();
                Storage::deleteDirectory("sync");
                
                ///Then download the zipped file.
                header('Content-Type: application/zip');
                header('Content-disposition: attachment; filename='.$zipname);
                header('Content-Length: ' . filesize($zippath.$zipname));
             
                readfile($zippath.$zipname);
                Storage::delete($zipname);
                exit();
            } else {                
                throw new \Exception("No se pudo crear el archivo");
            }
        } catch (\Exception $e) {    
            //echo " Sync Manual Excepción: ".$e->getMessage();
            Storage::append('log.sync', $fecha_generacion." Sync Manual Excepción: ".$e->getMessage());  
            Storage::deleteDirectory("sync");     
            return \Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);   
        }
        
    }

    /**
     * Importa archivo comprimido para sincronización protegido con SECRET KEY y devuelve archivo de confirmacion
     *
     * @return \Illuminate\Http\Response
     */
    public function importarSync(Request $request)
    {
       
        ini_set('memory_limit', '-1');
        DB::beginTransaction();
        try {
             
            Storage::makeDirectory("importar");
            //echo php_ini_loaded_file();
            //var_dump($request);
            if ($request->hasFile('sync')){
                
                $file = $request->file('sync');
                if ($file->isValid()) {
                     
                    Storage::put(
                        "importar/".$file->getClientOriginalName(),
                        file_get_contents($file->getRealPath())
                    );

                    $nombreArray = explode(".",$file->getClientOriginalName());

                    $servidor_id = $nombreArray[1];                    
                    $servidor = Servidor::find($servidor_id);                    
                    
                    if($servidor){
                        $storage_path = storage_path();
                        $zip = new ZipArchive();
                        $zippath = $storage_path."/app/importar/";
                        $zipname = "sync.".$servidor_id.".zip";


                        $zip_status = $zip->open($zippath.$zipname) ;
            
                         

                        if ($zip_status === true) {

                            if ($zip->setPassword($servidor->secret_key)){
                                Storage::makeDirectory("importar/".$servidor->id);
                                if ($zip->extractTo($zippath."/".$servidor->id)){
                                    $zip->close();

                                    //Borramos el ZIP y nos quedamos con los archivos extraidos
                                    Storage::delete("importar/".$file->getClientOriginalName());

                                    Storage::makeDirectory("importar/".$servidor->id."/confirmacion");
                                    
                                    //Obtenemos información del servidor que está sincronizando
                                    $contents_header = Storage::get("importar/".$servidor->id."/header.sync");
                                    $header_vars = ArchivoSync::parseVars($contents_header);

                                    // Obtenemos las fechas de actualizacion de sus catálogos
                                    $contents_catalogos = Storage::get("importar/".$servidor->id."/catalogos.sync");
                                    $catalogos_vars = ArchivoSync::parseVars($contents_catalogos);
                                

                                    // Verificamos que esta actualiacion no se haya aplicado antes                     
                                    if(Sincronizacion::where('servidor_id',$servidor->id)->where('fecha_generacion',$header_vars['FECHA_SYNC'])->count() > 0){
                                        //Storage::deleteDirectory("importar/".$servidor->id);
                                        throw new \Exception("No se puede importar más de una vez el mismo archivo de sincronización. La fecha del archivo indica que ya fue cargado previamente.");
                                    }                                   


                                   
                                    $actualizar_catalogos = "false";
                                    Storage::put("importar/".$servidor->id."/confirmacion/catalogos.sync","");
                                    foreach ($catalogos_vars as $key => $cat_ultima_actualizacion) {
                                       
                                        $principal_ultima_actualizacion = DB::table($key)->max("updated_at"); 
                                       
                                        if ($principal_ultima_actualizacion) {
                                           
                                            if ($principal_ultima_actualizacion != $cat_ultima_actualizacion) {
                                                
                                                $actualizar_catalogos = "true";
                                               
                                                $rows = DB::table($key)->whereBetween('updated_at',[$cat_ultima_actualizacion,$principal_ultima_actualizacion])->get();                                               
                                                 
                                                if($rows){
                                                    $query = "";
                                                    $rows_chunks = array_chunk($rows, 50);    
                                                    $columnas = DB::getSchemaBuilder()->getColumnListing($key);

                                                    foreach($rows_chunks as $row_chunk){
                                                        //Storage::append("importar/".$servidor->id."/confirmacion/catalogos.sync", "REPLACE INTO ".$key." VALUES ");
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
                                                            
                                                            //Storage::append("importar/".$servidor->id."/confirmacion/catalogos.sync", $item);                       
                                                            $query .= $item;
                                                        }
                                                        $query .= "; \n";
                                                        //Storage::append("importar/".$servidor->id."/confirmacion/catalogos.sync", "; \n");
                                                    }

                                                    Storage::append("importar/".$servidor->id."/confirmacion/catalogos.sync", $query);

                                                    
                                                } 

                                            }
                                        }
                                    }
                              
                                    // Registramos la version del servidor y si los catálogos estan actualizados
                                    $servidor->version = $header_vars['VERSION'];

                                    if ($actualizar_catalogos == "true") {
                                        $servidor->catalogos_actualizados = false;
                                    } else {
                                        $servidor->catalogos_actualizados = true;
                                    }
                                    $servidor->ultima_sincronizacion = $header_vars['FECHA_SYNC'];
                                    $servidor->save();

                                    // Comparamos la version del servidor principal y si es diferente le indicamos que tiene que actualizar
                                    if($servidor->version != env('VERSION')) {
                                        $actualizar_software = "true";
                                    } else {
                                        $actualizar_software = "false";
                                    }
                                   
                                    $contents = Storage::get("importar/".$servidor->id."/sumami.sync");
                                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                                    DB::connection()->getpdo()->exec($contents);
                                    DB::statement('SET FOREIGN_KEY_CHECKS=1');

                                    // Registramos la sincronización en la base de datos. 
                                    $sincronizacion = new Sincronizacion;
                                    $sincronizacion->servidor_id = $servidor->id;
                                    $sincronizacion->fecha_generacion = $header_vars['FECHA_SYNC'];
                                    $sincronizacion->save();

                                    $confirmacion_file = "importar/".$servidor->id."/confirmacion/confirmacion.sync";
                                    Storage::put($confirmacion_file,"ID=".$servidor->id);
                                    Storage::append($confirmacion_file,"FECHA_SYNC=".$header_vars['FECHA_SYNC']);                                   
                                    Storage::append($confirmacion_file,"ACTUALIZAR_SOFTWARE=".$actualizar_software);
                                    Storage::append($confirmacion_file,"VERSION_ACTUAL_SOFTWARE=".env('VERSION'));
                                    Storage::append($confirmacion_file,"ACTUALIZAR_CATALOGOS=".$actualizar_catalogos);
                                    $storage_path = storage_path();
                                    
                                    $zip = new ZipArchive();
                                    $zippath = $storage_path."/app/";
                                    $zipname = "sync.confirmacion.".$servidor->id.".zip";                                   

                                    exec("zip -P ".$servidor->secret_key." -j -r ".$zippath.$zipname." \"".$zippath."/importar/".$servidor->id."/confirmacion\"");
                                    //exec("zip  -j -r ".$zippath.$zipname." \"".$zippath."/importar/".$servidor->id."/confirmacion\"");
                                    $zip_status = $zip->open($zippath.$zipname);

                                    if ($zip_status === true) {

                                        $zip->close();  

                                        ///Then download the zipped file.
                                        header('Content-Type: application/zip');
                                        header('Content-disposition: attachment; filename='.$zipname);
                                        header('Content-Length: ' . filesize($zippath.$zipname));
                                        readfile($zippath.$zipname);
                                        Storage::delete($zipname);
                                        Storage::deleteDirectory("importar/".$servidor->id);

                                        DB::commit();
                                        exit();
                                    } else {            
                                        Storage::deleteDirectory("importar/".$servidor->id);    
                                        throw new \Exception("No se pudo crear el archivo");
                                    }

                                } else {
                                    Storage::delete("importar/".$file->getClientOriginalName());
                                    Storage::deleteDirectory("importar/".$servidor->id);
                                    throw new \Exception("No se pudo desencriptar el archivo, es posible que la llave de descriptación sea incorrecta, o que el nombre del archivo no corresponda al servidor correcto."); 
                                }

                            } else {
                                $zip->close();
                                Storage::delete("importar/".$file->getClientOriginalName());
                                throw new \Exception("Ocurrió un error al desencriptar el archivo");
                            }                            
                            exit;
                        } else {   
                            Storage::delete("importar/".$file->getClientOriginalName());             
                            throw new \Exception("No se pudo leer el archivo");
                        }

                    } else{
                        Storage::delete("importar/".$file->getClientOriginalName());
                        throw new \Exception("Archivo inválido, es posible que el nombre haya sido alterado o el servidor que desea sincronizar no se encuentra registrado.");
                    }
                }
            } else {
                 
                throw new \Exception("No hay archivo.");
            }
        } catch (\Illuminate\Database\QueryException $e){
            //echo " Sync Importación Excepción: ".$e->getMessage();
            Storage::append('log.sync', $fecha_generacion." Sync Importación Excepción: ".$e->getMessage());
            DB::rollback();
            return \Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
        catch(\Exception $e ){
            return \Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }

    /**
     * Importa archivo comprimido para sincronización protegido con SECRET KEY y devuelve archivo de confirmacion
     *
     * @return \Illuminate\Http\Response
     */
    public function confirmarSync(Request $request)
    {
        ini_set('memory_limit', '-1');
        DB::beginTransaction();
        try {
             
            Storage::makeDirectory("confirmacion");
            if ($request->hasFile('sync')){
                $file = $request->file('sync');
                if ($file->isValid()) {
                    
                    
                    $nombreArray = explode(".",$file->getClientOriginalName());
                    
                    $servidor_id = $nombreArray[2];                    
                    
                    if($servidor_id == env('SERVIDOR_ID')){

                        Storage::put(
                            "confirmacion/".$file->getClientOriginalName(),
                            file_get_contents($file->getRealPath())
                        );
                        
                        $storage_path = storage_path();
                        $zip = new ZipArchive();
                        $zippath = $storage_path."/app/confirmacion/";
                        $zipname = "sync.confirmacion.".$servidor_id.".zip";


                        $zip_status = $zip->open($zippath.$zipname) ;            
                         
                        if ($zip_status === true) {

                            if ($zip->setPassword(env('SECRET_KEY'))){
                                if ($zip->extractTo($zippath)){
                                    $zip->close();

                                    //Borramos el ZIP y nos quedamos con los archivos extraidos
                                    Storage::delete("confirmacion/".$file->getClientOriginalName());
                                   
                                    //Obtenemos información de la respuesta del  servidor remoto
                                    $contents_confirmacion = Storage::get("confirmacion/confirmacion.sync");     
                                                                    
                                    $confirmacion_vars = ArchivoSync::parseVars($contents_confirmacion);
                                    
                                    //Verificamos que el nombre del archivo en verdad corresponda al que dice el archivo de sincronizacion
                                    // A este punto creo innecesario hacer esta confirmación pues si no funciono la contraseña pues no es el servidor
                                    // pero suponiendo que alguien repita claves de servidores pues serviria para esto
                                    if ($confirmacion_vars["ID"] != env('SERVIDOR_ID')){
                                        Storage::delete("confirmacion/confirmacion.sync");
                                        Storage::delete("confirmacion/catalogos.sync");
                                        throw new Exception("El contenido del archivo de confirmación no corresponde al nombre del archivo, no se debe cambiar el nombre del archivo de confirmación que el servidor remoto genera.");
                                    }                                

                                    // Verificamos que esta actualizacion no se haya aplicado antes                     
                                    if(Sincronizacion::where('servidor_id',env('SERVIDOR_ID'))->where('fecha_generacion',$confirmacion_vars['FECHA_SYNC'])->count() > 0){
                                        Storage::delete("confirmacion/confirmacion.sync");
                                        Storage::delete("confirmacion/catalogos.sync");
                                        throw new \Exception("Este archivo ya fue utilizado previamente para confirmar la sincronización con el servidor remoto.");
                                    }          
                                    
                                    $contents = Storage::get("confirmacion/catalogos.sync");
                                    if($contents != ""){
                                        DB::statement('SET FOREIGN_KEY_CHECKS=0');
                                        DB::connection()->getpdo()->exec($contents);
                                        DB::statement('SET FOREIGN_KEY_CHECKS=1');
                                    }
                                    
                                    // Registramos la sincronización en la base de datos. 
                                    $sincronizacion = new Sincronizacion;
                                    $sincronizacion->servidor_id = env('SERVIDOR_ID');
                                    $sincronizacion->fecha_generacion = $confirmacion_vars['FECHA_SYNC'];
                                    $sincronizacion->save();

                                    $servidor = Servidor::find(env('SERVIDOR_ID'));
                                    if($servidor){
                                        $servidor->ultima_sincronizacion = $confirmacion_vars['FECHA_SYNC'];
                                        $servidor->save();
                                    }
                                    
                                    Storage::delete("confirmacion/confirmacion.sync");
                                    Storage::delete("confirmacion/catalogos.sync");

                                    DB::commit();
                                    
                                    return Response::json([ 'data' => "Sincronización con servidor remoto confirmada." ],200);

                                } else {
                                    $zip->close();
                                    Storage::delete("confirmacion/".$file->getClientOriginalName());
                                    throw new \Exception("No se pudo desencriptar el archivo, es posible que la llave de descriptación sea incorrecta, o que el nombre del archivo no corresponda al servidor correcto."); 
                                }

                            } else {
                                $zip->close();
                                Storage::delete("confirmacion/".$file->getClientOriginalName());
                                throw new \Exception("Ocurrió un error al desencriptar el archivo");
                            }

                            
                            exit;
                        } else {   
                            Storage::delete("confirmacion/".$file->getClientOriginalName());             
                            throw new \Exception("No se pudo leer el archivo");
                        }


                    } else{
                        throw new \Exception("Archivo inválido, es posible que el nombre haya sido alterado o el archivo de confirmación corresponda a otro servidor.");
                    }
                }
            } else {
                throw new \Exception("No hay archivo.");
            }
        } catch (\Illuminate\Database\QueryException $e){
            echo " Sync Confirmación Excepción: ".$e->getMessage();
            Storage::append('log.sync', $fecha_generacion." Sync Confirmación Excepción: ".$e->getMessage());
            DB::rollback();            
            return \Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
        catch(\Exception $e ){
            return \Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
    }


    /**
     * Sincroniza la base de datos con servidor remoto
     *
     */
    public function auto()
    {
        ini_set('memory_limit', '-1');
        $log = "";
        $ultima_sincronizacion =  Sincronizacion::select('fecha_generacion')->where("servidor_id",env("SERVIDOR_ID"))->orderBy('fecha_generacion','desc')->first();
        $fecha_generacion = date('Y-m-d H:i:s');
        
        try {
            $conexion_remota = DB::connection('mysql_sync');
            DB::beginTransaction();

            $conexion_remota->beginTransaction();

            //DB::statement('SET GLOBAL max_allowed_packet=134217728');//128MB
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            //$conexion_remota->statement('SET GLOBAL max_allowed_packet=134217728');//128MB
            $conexion_remota->statement('SET FOREIGN_KEY_CHECKS=0');
        } 
        catch (\Exception $e) {     
            Storage::append('log.sync', $fecha_generacion." Sync Auto Excepción: ".$e->getMessage());
            return " Sync Auto Excepción: ".$e->getMessage();            
        }
        
        try {
            $log .= "[#] Inicia Sincronización al: $fecha_generacion [#] \n\n";

            // Sincronizamos tablas de local a remoto 
            $log .= "### Tablas [local -> remoto]: ----------------------- ### \n";
            foreach(Config::get("sync.tablas")as $key){
                
                if ($ultima_sincronizacion) {
                    $rows = DB::table($key)->where("servidor_id",env("SERVIDOR_ID"))->whereBetween('updated_at',[$ultima_sincronizacion->fecha_generacion,$fecha_generacion])->get();
                } else {             
                    $rows = DB::table($key)->where("servidor_id",env("SERVIDOR_ID"))->get();
                }                

                if($rows){                    

                    // Separamos los registros porque cuando son demasiados marca un error de ejecución
                    $rows_chunks = array_chunk($rows, 50);

                    $columnas = DB::getSchemaBuilder()->getColumnListing($key);

                    foreach($rows_chunks as $row_chunk){

                        $statement = "REPLACE INTO ".$key." VALUES ";                    
                       
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
                            
                            $statement.= $item;                      
                        }
                        $statement .= ";";
                        $conexion_remota->statement($statement);
                    }
                    $log .= "Tabla: ".$key."\t=> ".count($rows)." registros sincronizados \n";
                } else {
                    $log .= "Tabla: ".$key."\t=> 0 registros sincronizados \n";
                }
            }   
            //  Sincronizamos catálogos de remoto a local
            $log .= "\n### Catálogos [remoto -> local]: -------------------- ### \n";
            foreach (Config::get("sync.catalogos") as $key) {
                   
                $ultima_actualizacion_local = DB::table($key)->max("updated_at");                 
                $ultima_actualizacion_remoto = $conexion_remota->table($key)->max("updated_at");

                if ($ultima_actualizacion_local) {
                    if ($ultima_actualizacion_local != $ultima_actualizacion_remoto) {
                        $rows = $conexion_remota->table($key)->whereBetween('updated_at',[$ultima_actualizacion_local, $ultima_actualizacion_remoto])->get();
                        
                    } else {
                        $rows = null;
                    }
                } else {
                    $rows = $conexion_remota->table($key)->get();
                }

                if ($rows) {

                    // Separamos los registros porque cuando son demasiados marca un error de ejecución
                    $rows_chunks = array_chunk($rows, 50);
                    $columnas = DB::getSchemaBuilder()->getColumnListing($key);

                    foreach($rows_chunks as $row_chunk){
                        $statement = "REPLACE INTO ".$key." VALUES ";   

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
                    
                        DB::statement($statement);
                    }
                    $log .= "Tabla: ".$key."\t=> ".count($rows)." registros sincronizados \n";
                } else {
                    $log .= "Tabla: ".$key."\t=> 0 registros sincronizados \n";
                } 

                
                         
            }
            $servidor_remoto = Servidor::on('mysql_sync')->find(env('SERVIDOR_ID'));
            $servidor_remoto->version = env('VERSION');
            $servidor_remoto->catalogos_actualizados = true;
            $servidor_remoto->ultima_sincronizacion = Carbon::now();
            $servidor_remoto->save();

            $sincronizacion_remoto = new Sincronizacion;
            $sincronizacion_remoto->setConnection("mysql_sync");
            $sincronizacion_remoto->servidor_id = env('SERVIDOR_ID');
            $sincronizacion_remoto->fecha_generacion = $fecha_generacion;
            $sincronizacion_remoto->save();

            $sincronizacion = new Sincronizacion;
            $sincronizacion->servidor_id = env('SERVIDOR_ID');
            $sincronizacion->fecha_generacion = $fecha_generacion;
            $sincronizacion->save();


            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $conexion_remota->statement('SET FOREIGN_KEY_CHECKS=1');

            DB::commit();
            $conexion_remota->commit();

            $log .= "\n[#] Fin de Sincronización [#] \n";


            return \Response::json([ 'data' => $log],200);

        } catch (\Illuminate\Database\QueryException $e){
            $log .= " Sync Auto Excepción: ".$e->getMessage();
            Storage::append('log.sync', $fecha_generacion." Sync Auto Excepción: ".$e->getMessage());
            //DB::statement('SET FOREIGN_KEY_CHECKS=1');
            //$conexion_remota->statement('SET FOREIGN_KEY_CHECKS=1');
            DB::rollback();
            $conexion_remota->rollback();
            return \Response::json([ 'data' => $log],500);
        }
        catch (\ErrorException $e) {
            $log .= " Sync Auto Excepción: ".$e->getMessage();
            Storage::append('log.sync', $fecha_generacion." Sync Auto Excepción: ".$e->getMessage());
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $conexion_remota->statement('SET FOREIGN_KEY_CHECKS=1');
            DB::rollback();
            $conexion_remota->rollback();
            return \Response::json([ 'data' => $log],500);
        } 
        catch (\Exception $e) {            
            $log .= " Sync Auto Excepción: ".$e->getMessage();
            Storage::append('log.sync', $fecha_generacion." Sync Auto Excepción: ".$e->getMessage());
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $conexion_remota->statement('SET FOREIGN_KEY_CHECKS=1');
            DB::rollback();
            $conexion_remota->rollback();
            return \Response::json([ 'data' => $log],500);
        }
    }
}