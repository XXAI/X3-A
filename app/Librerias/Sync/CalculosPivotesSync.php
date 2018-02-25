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
			//$conexion->commit();	
			return true; // Retornamos true para indicar en el proceso de sincronización que se pudo hacer el update correctamente
		}catch (\Illuminate\Database\QueryException $e){			
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
        catch (\ErrorException $e) {
			if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        } 
        catch (\Exception $e) {            
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
	}
	

	/**
     * Calcula el presupuesto disponible de las tablas considerando las cancelaciones de pedidos.
     *
     * @return Void
     */
    public static function calcularAjustePresupuestoPedidosCanceladosRemoto( $conexion){
		
		if($conexion == null){
			return false;
		}
		// Probamos que se pueda hacer conexiones remotas o locales primerp
		try{
			$conexion->beginTransaction();
        } 
        catch (\Exception $e) {   
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
		}		
		
		try{
			// Primero modificamos el presupuesto destino a partir de la cancelacion
			$statement1 = "
			UPDATE  unidad_medica_presupuesto as presupuesto
			JOIN (
				SELECT 
					clues,
					anio_destino,
					mes_destino,
					ifnull(sum(causes),0) as causes,
					ifnull(sum(no_causes),0) as no_causes,
					ifnull(sum(material_curacion),0) as material_curacion,
					ifnull(sum(insumos),0) as insumos
					
				FROM  ajuste_presupuesto_pedidos_cancelados 
				WHERE status = 'P' GROUP BY clues, anio_destino, mes_destino
			) ajuste ON  ajuste.anio_destino = presupuesto.anio AND ajuste.mes_destino = presupuesto.mes AND ajuste.clues = presupuesto.clues 
			
			SET presupuesto.causes_modificado = presupuesto.causes_modificado + ajuste.causes,
				presupuesto.causes_disponible = presupuesto.causes_modificado + ajuste.causes - presupuesto.causes_devengado - presupuesto.causes_comprometido,
				
				presupuesto.no_causes_modificado = presupuesto.no_causes_modificado + ajuste.no_causes,
				presupuesto.no_causes_disponible = presupuesto.no_causes_modificado + ajuste.no_causes - presupuesto.no_causes_devengado - presupuesto.no_causes_comprometido,
				
				presupuesto.material_curacion_modificado = presupuesto.material_curacion_modificado + ajuste.material_curacion,
				presupuesto.material_curacion_disponible = presupuesto.material_curacion_modificado + ajuste.material_curacion - presupuesto.material_curacion_devengado - presupuesto.material_curacion_comprometido,
			
				presupuesto.insumos_modificado = presupuesto.insumos_modificado + ajuste.insumos,
				presupuesto.insumos_disponible = presupuesto.insumos_modificado + ajuste.insumos - presupuesto.insumos_devengado - presupuesto.insumos_comprometido
			";

			// Segundo modificamos el presupuesto origen a partir de la cancelacion
			$statement2 = "
				UPDATE  unidad_medica_presupuesto as presupuesto
				JOIN (
					SELECT 
						clues,
						anio_origen,
						mes_origen,
						ifnull(sum(causes),0) as causes,
						ifnull(sum(no_causes),0) as no_causes,
						ifnull(sum(material_curacion),0) as material_curacion,
						ifnull(sum(insumos),0) as insumos
						
					FROM  ajuste_presupuesto_pedidos_cancelados 
					WHERE status = 'P' GROUP BY clues, anio_origen, mes_origen
				) ajuste ON  ajuste.anio_origen = presupuesto.anio AND ajuste.mes_origen = presupuesto.mes AND ajuste.clues = presupuesto.clues 
				
				SET presupuesto.causes_modificado = presupuesto.causes_modificado - ajuste.causes,
					presupuesto.causes_disponible = presupuesto.causes_modificado - ajuste.causes - presupuesto.causes_devengado - presupuesto.causes_comprometido,
					
					presupuesto.no_causes_modificado = presupuesto.no_causes_modificado - ajuste.no_causes,
					presupuesto.no_causes_disponible = presupuesto.no_causes_modificado - ajuste.no_causes - presupuesto.no_causes_devengado - presupuesto.no_causes_comprometido,
					
					presupuesto.material_curacion_modificado = presupuesto.material_curacion_modificado - ajuste.material_curacion,
					presupuesto.material_curacion_disponible = presupuesto.material_curacion_modificado - ajuste.material_curacion - presupuesto.material_curacion_devengado - presupuesto.material_curacion_comprometido,
				
					presupuesto.insumos_modificado = presupuesto.insumos_modificado - ajuste.insumos,
					presupuesto.insumos_disponible = presupuesto.insumos_modificado - ajuste.insumos - presupuesto.insumos_devengado - presupuesto.insumos_comprometido
				";

			// Actualizamos los status  pendientes a 'AR' que significa aplicado en remoto
			$statement3 = "UPDATE ajuste_presupuesto_pedidos_cancelados SET status = 'AR' WHERE status = 'P'";

			$conexion->statement($statement1);				
			$conexion->statement($statement2);		
			$conexion->statement($statement3);	
			
			//$conexion->commit();	
			return true; // Retornamos true para indicar en el proceso de sincronización que se pudo hacer el update correctamente
		}catch (\Illuminate\Database\QueryException $e){			
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
        catch (\ErrorException $e) {
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        } 
        catch (\Exception $e) {            
			if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
	}
	
	/**
     * Calcula el presupuesto disponible de las tablas considerando las cancelaciones de pedidos.
     *
     * @return Void
     */
    public static function calcularAjustePresupuestoPedidosCanceladosLocal( $conexion){
		
		if($conexion == null){
			return false;
		}
		// Probamos que se pueda hacer conexiones remotas o locales primerp
		try{
			$conexion->beginTransaction();
        } 
        catch (\Exception $e) {   
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
		}		
		
		try{
			// Primero modificamos el presupuesto destino a partir de la cancelacion
			$statement1 = "
			UPDATE  unidad_medica_presupuesto as presupuesto
			JOIN (
				SELECT 
					clues,
					anio_destino,
					mes_destino,
					ifnull(sum(causes),0) as causes,
					ifnull(sum(no_causes),0) as no_causes,
					ifnull(sum(material_curacion),0) as material_curacion,
					ifnull(sum(insumos),0) as insumos
					
				FROM  ajuste_presupuesto_pedidos_cancelados 
				WHERE status = 'AR' GROUP BY clues, anio_destino, mes_destino
			) ajuste ON  ajuste.anio_destino = presupuesto.anio AND ajuste.mes_destino = presupuesto.mes AND ajuste.clues = presupuesto.clues 
			
			SET presupuesto.causes_modificado = presupuesto.causes_modificado + ajuste.causes,
				presupuesto.causes_disponible = presupuesto.causes_modificado + ajuste.causes - presupuesto.causes_devengado - presupuesto.causes_comprometido,
				
				presupuesto.no_causes_modificado = presupuesto.no_causes_modificado + ajuste.no_causes,
				presupuesto.no_causes_disponible = presupuesto.no_causes_modificado + ajuste.no_causes - presupuesto.no_causes_devengado - presupuesto.no_causes_comprometido,
				
				presupuesto.material_curacion_modificado = presupuesto.material_curacion_modificado + ajuste.material_curacion,
				presupuesto.material_curacion_disponible = presupuesto.material_curacion_modificado + ajuste.material_curacion - presupuesto.material_curacion_devengado - presupuesto.material_curacion_comprometido,
			
				presupuesto.insumos_modificado = presupuesto.insumos_modificado + ajuste.insumos,
				presupuesto.insumos_disponible = presupuesto.insumos_modificado + ajuste.insumos - presupuesto.insumos_devengado - presupuesto.insumos_comprometido
			";

			// Segundo modificamos el presupuesto origen a partir de la cancelacion
			$statement2 = "
				UPDATE  unidad_medica_presupuesto as presupuesto
				JOIN (
					SELECT 
						clues,
						anio_origen,
						mes_origen,
						ifnull(sum(causes),0) as causes,
						ifnull(sum(no_causes),0) as no_causes,
						ifnull(sum(material_curacion),0) as material_curacion,
						ifnull(sum(insumos),0) as insumos
						
					FROM  ajuste_presupuesto_pedidos_cancelados 
					WHERE status = 'AR' GROUP BY clues, anio_origen, mes_origen
				) ajuste ON  ajuste.anio_origen = presupuesto.anio AND ajuste.mes_origen = presupuesto.mes AND ajuste.clues = presupuesto.clues 
				
				SET presupuesto.causes_modificado = presupuesto.causes_modificado - ajuste.causes,
					presupuesto.causes_disponible = presupuesto.causes_modificado - ajuste.causes - presupuesto.causes_devengado - presupuesto.causes_comprometido,
					
					presupuesto.no_causes_modificado = presupuesto.no_causes_modificado - ajuste.no_causes,
					presupuesto.no_causes_disponible = presupuesto.no_causes_modificado - ajuste.no_causes - presupuesto.no_causes_devengado - presupuesto.no_causes_comprometido,
					
					presupuesto.material_curacion_modificado = presupuesto.material_curacion_modificado - ajuste.material_curacion,
					presupuesto.material_curacion_disponible = presupuesto.material_curacion_modificado - ajuste.material_curacion - presupuesto.material_curacion_devengado - presupuesto.material_curacion_comprometido,
				
					presupuesto.insumos_modificado = presupuesto.insumos_modificado - ajuste.insumos,
					presupuesto.insumos_disponible = presupuesto.insumos_modificado - ajuste.insumos - presupuesto.insumos_devengado - presupuesto.insumos_comprometido
				";

			// Actualizamos los status ya aplicados en remoto a 'ARL' que significa aplicado en remoto y local
			$statement3 = "UPDATE ajuste_presupuesto_pedidos_cancelados SET status = 'ARL' WHERE status = 'AR'";

			$conexion->statement($statement1);				
			$conexion->statement($statement2);		
			$conexion->statement($statement3);	
			
			//$conexion->commit();	
			return true; // Retornamos true para indicar en el proceso de sincronización que se pudo hacer el update correctamente
		}catch (\Illuminate\Database\QueryException $e){			
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
        catch (\ErrorException $e) {
            if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        } 
        catch (\Exception $e) {            
			if($remoto){
				$conexion_remota->rollback();
			} else {
				DB::rollback();
			}
			return false; // Retornamos false para que se cancele lo que se tenga que cancelar en el proceso de sincronizacion
        }
    }
}