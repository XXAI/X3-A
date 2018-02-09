<?php
namespace App\Http\Controllers\AlmacenGeneral;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Illuminate\Support\Facades\Input;
use DB; 

use App\Models\AlmacenGeneral\Movimiento;
use App\Models\AlmacenGeneral\MovimientoArticulos;

use App\Models\AlmacenGeneral\InventarioArticulo;
use App\Models\AlmacenGeneral\InventarioArticuloMetadatos;

/**
* Controlador Movimiento
* 
* @package    Plataforma API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20
*
* Controlador `SisMovimiento`: Manejo de usuarios del sistema
*
*/
class EntradaArticuloController extends Controller {
	/**
	 * Muestra una lista de los recurso según los parametros a procesar en la petición.
	 *
	 * <h3>Lista de parametros Request:</h3>
	 * <Ul>Paginación
	 * <Li> <code>$pagina</code> numero del puntero(offset) para la sentencia limit </ li>
	 * <Li> <code>$limite</code> numero de filas a mostrar por página</ li>	 
	 * </Ul>
	 * <Ul>Busqueda
	 * <Li> <code>$valor</code> string con el valor para hacer la busqueda</ li>
	 * <Li> <code>$order</code> campo de la base de datos por la que se debe ordenar la información. Por Defaul es ASC, pero si se antepone el signo - es de manera DESC</ li>	 
	 * </Ul>
	 *
	 * Ejemplo ordenamiento con respecto a id:
	 * <code>
	 * http://url?pagina=1&limite=5&order=id ASC 
	 * </code>
	 * <code>
	 * http://url?pagina=1&limite=5&order=-id DESC
	 * </code>
	 *
	 * Todo Los parametros son opcionales, pero si existe pagina debe de existir tambien limite
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function index(){
		$datos = Request::all();
		
		// Si existe el paarametro pagina en la url devolver las filas según sea el caso
		// si no existe parametros en la url devolver todos las filas de la tabla correspondiente
		// esta opción es para devolver todos los datos cuando la tabla es de tipo catálogo
		if(array_key_exists("pagina", $datos)){
			$pagina = $datos["pagina"];
			if(isset($datos["order"])){
				$order = $datos["order"];
				if(strpos(" ".$order,"-"))
					$orden = "desc";
				else
					$orden = "asc";
				$order=str_replace("-", "", $order); 
			}
			else{
				$order = "id"; $orden = "desc";
			}
			
			if($pagina == 0){
				$pagina = 1;
			}
			if($pagina == 1)
				$datos["limite"] = $datos["limite"] - 1;
			// si existe buscar se realiza esta linea para devolver las filas que en el campo que coincidan con el valor que el usuario escribio
			// si no existe buscar devolver las filas con el limite y la pagina correspondiente a la paginación
			if(array_key_exists("buscar", $datos)){
				$columna = $datos["columna"];
				$valor   = $datos["valor"];
				$data = Movimiento::with("Usuario", "MovimientoArticulos", "TipoMovimiento", "Almacen")
				->where('tipo_movimiento_id',$datos["tipo_movimiento_id"])->orderBy($order, $orden);
				
				$search = trim($valor);
				$keyword = $search;
				$data = $data->whereNested(function($query) use ($keyword){	
						$query->Where("id", "LIKE", "%".$keyword."%")
						->orWhere("status", "LIKE", "%".$keyword."%")
						->orWhere("fecha_movimiento", "LIKE", "%".$keyword."%"); 
				});
				
				$total = $data->get();
				$data = $data->skip($pagina-1)->take($datos["limite"])->get();
			}
			else{
				$data = Movimiento::with("Usuario", "MovimientoArticulos", "TipoMovimiento", "Almacen")->where('tipo_movimiento_id',$datos["tipo_movimiento_id"])->skip($pagina-1)->take($datos["limite"])->orderBy($order, $orden)->get();
				$total =  Movimiento::where('tipo_movimiento_id',$datos["tipo_movimiento_id"])->get();
			}			
		}
		else{
			$data = Movimiento::with("Usuario", "MovimientoArticulos", "TipoMovimiento", "Almacen")->where('tipo_movimiento_id',$datos["tipo_movimiento_id"])->get();
			$total = $data;
		}

		if(!$data){
			return Response::json(array("status" => 204, "messages" => "No hay resultados"),204);
		} 
		else {				
			return Response::json(array("status" => 200, "messages" => "Operación realizada con exito", "data" => $data, "total" => count($total)), 200);			
		}
	}

	/**
	 * Crear un nuevo registro en la base de datos con los datos enviados
	 *
	 * <h4>Request</h4>
	 * Recibe un input request tipo json de los datos a almacenar en la tabla correspondiente
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 201, "messages": "Creado", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function store(){
		$this->ValidarParametros(Input::json()->all());			
		$datos = (object) Input::json()->all();	
		$success = false;

        DB::beginTransaction();
        try{
            $data = new Movimiento;
            $success = $this->campos($datos, $data);

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
        } 
        if ($success){
            DB::commit();
            $data = $this->informacion($data->id);
            return Response::json(array("status" => 201,"messages" => "Creado","data" => $data), 201);
        } 
        else{
            DB::rollback();
            return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
        }
		
	}

	
	/**
	 * Actualizar el  registro especificado en el la base de datos
	 *
	 * <h4>Request</h4>
	 * Recibe un Input Request con el json de los datos
	 *
	 * @param  int  $id que corresponde al identificador del dato a actualizar 	 
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 304, "messages": "No modificado"),status) </code>
	 */
	public function update($id){
		$this->ValidarParametros(Input::json()->all());	

		$datos = (object) Input::json()->all();		
		$success = false;
        
        DB::beginTransaction();
        try{
        	$data = Movimiento::find($id);

            if(!$data){
                return Response::json(['error' => "No se encuentra el recurso que esta buscando."], HttpResponse::HTTP_NOT_FOUND);
            }
            
            $success = $this->campos($datos, $data);

        } catch (\Exception $e) {
            DB::rollback();
            return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
        } 
        if($success){
			DB::commit();
			$data = $this->informacion($data->id);
			return Response::json(array("status" => 200, "messages" => "Operación realizada con exito", "data" => $data), 200);
		} 
		else {
			DB::rollback();
			return Response::json(array("status" => 304, "messages" => "No modificado"),200);
		}
	}

	public function campos($datos, $data){
		$success = false;
		
		$almacen_id = Request::header("X-Almacen-Id");

        $data->almacen_id 		 			= $almacen_id;
        $data->tipo_movimiento_id	 		= property_exists($datos, "tipo_movimiento_id") 		? $datos->tipo_movimiento_id  	: $data->tipo_movimiento_id;	       
        $data->status 			 			= property_exists($datos, "status") 					? $datos->status 					: $data->status;
        $data->fecha_movimiento		 	 	= property_exists($datos, "fecha_movimiento") 			? $datos->fecha_movimiento 			: $data->fecha_movimiento;		
		$data->observaciones 	 			= property_exists($datos, "observaciones") 				? $datos->observaciones 			: $data->observaciones;
		//$data->cancelado 					= property_exists($datos, "cancelado")					? $datos->cancelado					: $data->cancelado;
		$data->cancelado 					= 1;
		$data->observaciones_cancelacion	= property_exists($datos, "observaciones_cancelacion")	? $datos->observaciones_cancelacion	: $data->observaciones_cancelacion;

        if ($data->save()) {		
        	if(property_exists($datos, "movimiento_articulos")){
        		$movimiento_articulos = array_filter($datos->movimiento_articulos, function($v){return $v !== null;});
        		MovimientoArticulos::where("movimiento_id", $data->id)->delete();
        		foreach ($movimiento_articulos as $key => $value) {
        			$value = (object) $value;
        			if($value != null){

        				DB::update("update movimiento_articulos set deleted_at = null where movimiento_id = $data->id and articulo_id = '$value->articulo_id'");
						$item = MovimientoArticulos::where("movimiento_id", $data->id)->where("articulo_id", $value->articulo_id)->first();

						if(!$item)
            				$item = new MovimientoArticulos;
            			
            			$item->movimiento_id  	= $data->id;
            			$item->articulo_id    	= property_exists($value, "articulo_id")	? $value->articulo_id	  : $item->articulo_id;
            			$item->inventario_id   	= property_exists($value, "inventario_id")	? $value->inventario_id	  : $item->inventario_id;
            			$item->cantidad    		= property_exists($value, "cantidad")		? $value->cantidad		  : $item->cantidad;
            			$item->precio_unitario 	= property_exists($value, "precio_unitario")? $value->precio_unitario : $item->precio_unitario;
            			$item->iva    			= property_exists($value, "iva")			? $value->iva			  : $item->iva;
            			$item->importe 			= property_exists($value, "importe")		? $value->importe		  : $item->importe;

            			if($item->save()){
	        				if(property_exists($value, "inventarios") && count($value->inventarios) > 0) {
				        		$inventarios = array_filter($value->inventarios, function($v){return $v !== null;});
				        		InventarioArticulo::where("movimiento_articulo_id", $item->id)->delete();
				        		DB::table('inventario_movimiento_articulos')->where('movimiento_articulos_id', $item->id)->delete();
				        		foreach ($inventarios as $invk => $invv) {
				        			$invv = (object) $invv;
				        			if($invv != null){
				        				DB::update("update inventario set deleted_at = null where movimiento_articulo_id = $item->id and articulo_id = '$item->articulo_id' and numero_inventario = '$invv->numero_inventario'");
				        				$inventario = InventarioArticulo::where("movimiento_articulo_id", $item->id)->where("articulo_id", $item->articulo_id)->where("numero_inventario", $invv->numero_inventario)->first();				        									        					
			        					if(!$inventario)
			        						$inventario = new InventarioArticulo;					        			
				        				
				        				$inventario->almacen_id 	 		 = $almacen_id;
			            				$inventario->movimiento_articulo_id  = $item->id;
			            				$inventario->articulo_id 			 = $item->articulo_id;
			            				$inventario->existencia 			 = 1;
			            				$inventario->numero_inventario		 = property_exists($invv, "numero_inventario")	? $invv->numero_inventario != '' ?  $invv->numero_inventario : $almacen_id.'-'.time() : $almacen_id.'-'.time();
			            				$inventario->observaciones 			 = property_exists($invv, "observaciones")		? $invv->observaciones 		: $item->observaciones;
			            				//$inventario->baja 			 		 = property_exists($invv, "baja")				? $invv->baja		  		: $item->baja;
										$inventario->baja 			 		 = 0;

			            				if($inventario->save()){			            											        																		         
						            		DB::table('inventario_movimiento_articulos')->insert(
											    ['movimiento_articulos_id' => $item->id, 'inventario_id' => $inventario->id]
											);												            	
				        					
	        								$ma = MovimientoArticulos::find($item->id);
	        								if($ma){					            			
						            			$ma->inventario_id = $inventario->id;
						            			$ma->save();						            			
					            			}
					            			if(property_exists($invv, "inventario_metadato") && count($invv->inventario_metadato) > 0) {
								        		$inve_meta = array_filter($invv->inventario_metadato, function($v){return $v !== null;});
								        		InventarioArticuloMetadatos::where("inventario_id", $inventario->id)->delete();
								        		
								        		foreach ($inve_meta as $invmk => $invmv) 
												{
								        			$invmv = (object) $invmv;
								        			if($invmv != null)
													{	
								        				DB::update("update inventario_metadatos set deleted_at = null where inventario_id = $inventario->id and campo = '$invmv->campo'");
								        				$inventario_m = InventarioArticuloMetadatos::where("inventario_id", $inventario->id)->where("campo", $invmv->campo)->first();				        									        					
							        					if(!$inventario_m)
							        						$inventario_m = new InventarioArticuloMetadatos;

							        					$inventario_m->inventario_id = $inventario->id;
							        					$inventario_m->campo = $invmv->campo;
							        					$inventario_m->valor = $invmv->valor;

							        					if($inventario_m->save()){								        																		           
										            		$success = true;												            	
							        					}
								        			}
								        		}				            				
				            				}
				            			}
				        			}
				        		}
	        				}
	        			}
            		}
        		}
        	}        	
			$success = true;
		}  
		return $success;     						
	}

	public function informacion($id){
		$data = Movimiento::with("Usuario", "MovimientoArticulos", "TipoMovimiento", "Almacen")->find($id);					
		return $data;
	}

	/**
	 * Devuelve la información del registro especificado.
	 *
	 * @param  int  $id que corresponde al identificador del recurso a mostrar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function show($id){
		$data = Movimiento::with("Usuario", "MovimientoArticulos", "TipoMovimiento", "Almacen")->find($id);			
		
		if(!$data){
			return Response::json(array("status"=> 404,"messages" => "No hay resultados"),404);
		} 
		else {	
			return Response::json(array("status" => 200, "messages" => "Operación realizada con exito", "data" => $data), 200);
		}
	}
	
	/**
	 * Elimine el registro especificado del la base de datos (softdelete).
	 *
	 * @param  int  $id que corresponde al identificador del dato a eliminar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function destroy($id){
		$success = false;
        DB::beginTransaction();
        try {
			$data = Movimiento::find($id);
			$grupos = $data->Grupos();
			if(count($grupos)>0){
				foreach ($grupos as $grupo) {
					$data->removeGroup($grupo);				
				}
			}
			$data->delete();
			
			$success=true;
		} 
		catch (\Exception $e) {
			return Response::json($e->getMessage(), 500);
        }
        if ($success){
			DB::commit();
			return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data), 200);
		} 
		else {
			DB::rollback();
			return Response::json(array("status" => 404, "messages" => "No se encontro el registro"), 404);
		}
	}	

	/**
	 * Validad los parametros recibidos, Esto no tiene ruta de acceso es un metodo privado del controlador.
	 *
	 * @param  Request  $request que corresponde a los parametros enviados por el cliente
	 *
	 * @return Response
	 * <code> Respuesta Error json con los errores encontrados </code>
	 */
	private function ValidarParametros($request){
		$rules = [
			"total" => "required"
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails()){
			return Response::json($v->errors());
		}
	}
}