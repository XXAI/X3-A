<?php
namespace App\Librerias\Sync;
use \DB;
class CalculosPivotesSync {

    /**
     * Calcula el presupuesto disponible de las tablas.
     *
     * @return Void
     */
    public static function calcularPresupuestoDisponible( $conexion){
		
		if($conexion == null){
			return false;
		}
		// Probamos que se pueda hacer conexiones remotas o locales primerp
		try{
            //$conexion_remota = DB::connection('mysql_sync');
           // DB::beginTransaction();
            //$conexion_remota->beginTransaction();

			$conexion->beginTransaction();
        } 
        catch (\Exception $e) {     
			
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
		}

		
		
		try{
			$statement = "
							UPDATE unidad_medica_presupuesto SET 
							causes_disponible = (causes_modificado - causes_comprometido - causes_devengado),
							no_causes_disponible = (no_causes_modificado - no_causes_comprometido - no_causes_devengado),
							material_curacion_disponible = (material_curacion_modificado - material_curacion_comprometido - material_curacion_devengado),
							insumos_disponible = (insumos_modificado - insumos_comprometido - insumos_devengado)
						";

			$conexion->statement($statement);					
			$conexion->commit();	
			/*
			if($remoto){
				
				$conexion_remota->statement($statement);	
				
				$conexion_remota->commit();			
				echo "Me ejecuto despues";
			} else {
				DB::statement($statement);
				
				DB::commit();
				echo "Me ejecuto primero";
			}*/
			return true; // Retornamos true para indicar en el proceso de sincronización que se pudo hacer el update correctamente

		}catch (\Illuminate\Database\QueryException $e){
			echo $e->getMessage();
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
        catch (\ErrorException $e) {
            echo $e->getMessage();
            
            
			if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        } 
        catch (\Exception $e) {            
			echo $e->getMessage();
          
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
		


		
    }
}