<?php

namespace App\Http\Controllers\Patches;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use App\Http\Requests;
use  App\Exceptions\PatchException as PatchException, \DB, \Storage, \ZipArchive, \Hash, \Response, \Config;
use Illuminate\Support\Facades\Input;
use App\Librerias\Patches;
use Carbon\Carbon;

class PatchesController extends \App\Http\Controllers\Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function lista()
    {

		$items = [];
		foreach(Config::get("patches") as $item){
			$items[] = $item;
		}

        return Response::json([ 'data' => $items],200);
	}

	public function ejecutar(Request $request){
		// Abrá que implementar algún tipo de seguridad para que no suban otro script que por ejemplo borre toda la bases de datos		
		ini_set('memory_limit', '-1');
        try{
			Storage::makeDirectory("patches");
			if ($request->hasFile('patch')){
				$file = $request->file('patch');

				if ($file->isValid()) {
					Storage::put(
                        "patches/".$file->getClientOriginalName(),
                        file_get_contents($file->getRealPath())
					);
					$filename =  $file->getClientOriginalName();
					$array_filename = explode(".",$filename);
					
					$output = "No se aplicó ningún parche.";

					$patch_name = "";
					for($i = 1; $i < count($array_filename); $i++){
						if($i>1){
							$patch_name .= ".";
						}
						$patch_name .= $array_filename[$i];
					}

					if(strtoupper($array_filename[0]) != strtoupper(md5($patch_name))){
						throw new \Exception("El nombre del archivo fue alterado");
					}
					
					if($array_filename[2]=="cliente"){
						$output = "Ejecutando parche No. ".$array_filename[3]." al cliente...\n\n";
						$api_base_path = base_path();	
						$base_path = env("PATH_CLIENTE");	
						
					
						$script = "
							cd $base_path	
							git am --signoff < ".$api_base_path."/storage/app/patches/".$filename."
						";

						$preout =  shell_exec($script);
						$output.= $preout;

						$patchFailed = strpos($preout,"Patch failed");
						$patchEmpty = strpos($preout,"Patch is empty");

						if($patchFailed !== false || $patchEmpty !== false ){

							$script = "
								cd $base_path	
								git am --abort
							";
							shell_exec($script);
							$output .= "\nEl parche no se pudo ejecutar :(";
							throw new PatchException($output);
						} else {
							$output .= "\n¡Parche ejecutado correctamente!";
						}

					
					}

					if($array_filename[2]=="api"){
						$output = "Ejecutando parche No. ".$array_filename[3]." a la API...\n\n";
						$base_path = base_path();	
						$script = "
							cd $base_path
							git am --signoff < storage/app/patches/".$filename."
							
						";


						$preout =  shell_exec($script);
						$output.= $preout;

						$patchFailed = strpos($preout,"Patch failed");
						$patchEmpty = strpos($preout,"Patch is empty");

						if($patchFailed !== false || $patchEmpty !== false ){

							$script = "
								cd $base_path	
								git am --abort
							";
							shell_exec($script);
							$output .= "\nEl parche no se pudo ejecutar :(";
							throw new PatchException($output);
						} 

						foreach(Config::get("patches") as $item){
							if($item['nombre'] == $filename){
								if($item['ejecutar'] != ''){
									
									$o = call_user_func($item['ejecutar']);
									if($o == false){
										$output .= "\nEl parche se aplicó, pero no se pudieron ejecutar las instrucciones posteriores, debido a un error en la función configurada.";
									} 
									$output .= "\n".$o;
									break;
								}  
							}
						}

						$output .= "\n¡Parche ejecutado correctamente!";
					}
					
					



					return Response::json([ 'data' => $output],200);

				} else {
					throw new \Exception("Archivo inválido.");
				}
			} else {
				throw new \Exception("No hay archivo.");
			}

		} 
		catch (PatchException $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        }
		catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        } catch (\FatalErrorException $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        } 
    }
	
}

