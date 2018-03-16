<?php
namespace App\Librerias\Patches;
use \DB, \Config;
use Carbon\Carbon;
use App\Models\LogEjecucionParche;

 /*
 Clase de ejemplo para aplicación en parches
 */
class Parches {

    /**
     * Se ejecuta despues del parche
     *
     * @return Void
     */
    public static function ejecutar(){
       try{
            \Artisan::call('migrate');

            $parches = Config::get("patches");
            $ultimo_parche = $parches[0];

            $parche_log = LogEjecucionParche::where('nombre_parche',$ultimo_parche['nombre'])->first();

            if(!$parche_log){
                $parche_log = LogEjecucionParche::create(['clues'=>env('CLUES'),'nombre_parche'=>$ultimo_parche['nombre'],'tipo_parche'=>'api','fecha_liberacion'=>$ultimo_parche['fecha']]);
            }

            $parche_log->fecha_ejecucion = Carbon::now();
            $parche_log->save();
            
            return "Parche Ejecutado: ".$ultimo_parche['nombre'];
       } catch(\Exception $e){
            return false;
       }
	}
}