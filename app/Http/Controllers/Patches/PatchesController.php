<?php

namespace App\Http\Controllers\Patches;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use App\Http\Requests;
use  \DB, \Storage, \ZipArchive, \Hash, \Response, \Config;
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

		$items_cliente = [];
		foreach(Config::get("patches.cliente") as $item){
			$items_cliente[] = $item;
		 }
		 
		$items_api = [];
		foreach(Config::get("patches.api") as $item){
			$items_api[] = $item;
 		}
        
        $data = ['cliente' => $items_cliente, 'api' => $items_api];
       
        return Response::json([ 'data' => $data],200);
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

					if($array_filename[0] != md5($patch_name)){
						throw new \Exception("El nombre del archivo fue alterado");
					}
					
					if($array_filename[2]=="cliente"){
						$output = "ACTUALIZANDO PARCHE No. ".$array_filename[3]." AL CLIENTE\n";
						$base_path = env("PATH_CLIENTE");	
						$api_base_path = base_path();	
						$script = "
							cd $base_path
							pwd
							git apply --stat ".$api_base_path."/storage/app/patches/".$filename."
							git apply --check ".$api_base_path."/storage/app/patches/".$filename."
							git am --signoff < ".$api_base_path."/storage/app/patches/".$filename."
						";
						$output .= shell_exec($script);

						foreach(Config::get("patches.cliente") as $item){
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
						
					}

					if($array_filename[2]=="api"){
						$output = "ACTUALIZANDO PARCHE No. ".$array_filename[3]." A LA API\n";
						$base_path = base_path();	
						$script = "
							cd $base_path
							pwd
							git apply --stat storage/app/patches/".$filename."
							git apply --check storage/app/patches/".$filename."
							git am --signoff < storage/app/patches/".$filename."
						";
						$output .= shell_exec($script);

						foreach(Config::get("patches.api") as $item){
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
					}
					
					



					return Response::json([ 'data' => $output],200);

				} else {
					throw new \Exception("Archivo inválido.");
				}
			} else {
				throw new \Exception("No hay archivo.");
			}

        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } catch (\FatalErrorException $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }
	
}