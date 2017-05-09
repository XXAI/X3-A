<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\MovimientoInsumos;
use App\Models\TiposMovimientos;
use App\Models\Insumo;






/** 
* Controlador Movimientos
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017-03-22
*
* Controlador `Movimientos`: Controlador  para el manejo de entradas y salidas
*
*/
class MovimientoController extends Controller
{
    /**
	 * Inicia el contructor para los permisos de visualizacion
	 *	 
	 */
     /*
    public function __construct()
    {
        $this->middleware('permisos:GET.LISTAR_USUARIOS|POST.ADMIN_USUARIOS|PUT.ADMIN_USUARIOS|DELETE.ADMIN_USUARIOS');
    }
    */
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
	 * <Li> <code>$order</code> campo de la base de data por la que se debe ordenar la información. Por Defaul es ASC, pero si se antepone el signo - es de manera DESC</ li>	 
	 * </Ul>
	 *
	 * Conceptos ordenamiento con respecto a id:
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
    public function index()
    {
        $parametros = Input::only('q','page','per_page','almacen','tipo');
        if(!$parametros['almacen']){
                        return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }                

        if ($parametros['q']) {
                 ///$data =  Movimientos::with('MovimientoInsumos')->where(function($query) use ($parametros) {
                 $data =  Movimiento::where(function($query) use ($parametros) {
                 $query->where('folio','LIKE',"%".$parametros['q']."%")->where('almacen_id',$parametros['almacen']);
             });
        } else {
            
                 if($parametros['tipo']){
                //$data =  Movimientos::with('MovimientoInsumos')->where('tipo_movimiento_id',$parametros['tipo']);
                $data =  Movimiento::where('almacen_id',$parametros['almacen'])->where('tipo_movimiento_id',$parametros['tipo']);
                }else{
                    //$data =  Movimientos::with('MovimientoInsumos');
                    $data =  Movimiento::where('almacen_id',$parametros['almacen']);
                    //dd(json_encode($data));
                }

        }

        if(isset($parametros['page'])){

            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $data = $data->paginate($resultadosPorPagina);
           // $data = \App\Movimientos::paginate($resultadosPorPagina);

        } else {

            $data = $data->get();

        }

        if(count($data) <= 0){

            return Response::json(array("status" => 404,"messages" => "No hay resultados"), 200);
        } 
        else{
            return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data, "total" => count($data)), 200);
            
        }
    }

    /**
	 * Crear un nuevo registro en la base de data con los data enviados
	 *
	 * <h4>Request</h4>
	 * Recibe un input request tipo json de los data a almacenar en la tabla correspondiente
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 201, "messages": "Creado", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
    public function store(Request $request)
    {
        $errors = array();        

        $validacion = $this->ValidarMovimiento("", NULL, Input::json()->all());
		if(is_array($validacion))
        {
			return Response::json(['error' => $validacion], HttpResponse::HTTP_CONFLICT);
		}
        $datos = (object) Input::json()->all();	
        $success = false;

        $id_tipo_movimiento = $datos->tipo_movimiento_id;
        $tipo_movimiento = TiposMovimientos::Find($datos->tipo_movimiento_id);

        $tipo = NULL;
        if($tipo_movimiento)
            $tipo = $tipo_movimiento->tipo;

        if($id_tipo_movimiento == 1){


                if(property_exists($datos, "insumos"))
                {
                    if(count($datos->insumos) > 0 )
                    {
                        $detalle = array_filter($datos->insumos, function($v){return $v !== null;});
                        foreach ($detalle as $key => $value)
                            {
                                $validacion_insumos = $this->ValidarInsumos($key, NULL, $value, $tipo);
                                if($validacion_insumos != "")
                                    {
                                        array_push($errors, $validacion_insumos);
                                    }
                            }
                    }else{
                            array_push($errors, array(array('insumos' => array('no_items_insumos'))));
                    }
                    
                }else{
                        array_push($errors, array(array('insumos' => array('no_exist_insumos'))));
                }

                if( count($errors) > 0 )
                {
                    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
                } 

                DB::beginTransaction();
                try{
                        $data = new Movimiento;
                        $success = $this->ValidarMetadatos($datos, $data);

                } catch (\Exception $e) {
                    DB::rollback();
                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 200);
                } 
                if ($success){
                    DB::commit();
                    return Response::json(array("status" => 201,"messages" => "Creado","data" => $data), 201);
                } 
                else{
                    DB::rollback();
                    return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
                }
        }//FIN IF TIPO MOVIMIENTO = 1  -> ENTRADA MANUAL

        if($id_tipo_movimiento == 2){


                if(property_exists($datos, "insumos"))
                {
                    if(count($datos->insumos) > 0 )
                    {
                        $detalle = array_filter($datos->insumos, function($v){return $v !== null;});
                        foreach ($detalle as $key => $value)
                            {
                                $validacion_insumos = $this->ValidarInsumos($key, NULL, $value, $tipo);
                                if($validacion_insumos != "")
                                    {
                                        array_push($errors, $validacion_insumos);
                                    }

                                // validar existencia en stock
                                $item_insumo = Stock::where('clave_insumo_medico', $value['clave'])->where('existencia','>',0)->sum('existencia');

                                if($value['cantidad'] > $item_insumo)
                                     array_push($errors, array(array('cantidad'.$key => array('Stock_insuficiente'))));

                            }
                    }else{
                            array_push($errors, array(array('insumos' => array('no_items_insumos'))));
                    }
                    
                }else{
                        array_push($errors, array(array('insumos' => array('no_exist_insumos'))));
                }

                if( count($errors) > 0 )
                {
                    return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
                } 

                DB::beginTransaction();
                try{
                        $data = new Movimiento;
                        $success = $this->ValidarMetadatosSalida($datos, $data);

                } catch (\Exception $e) {
                    DB::rollback();
                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 200);
                } 
                if ($success){
                    DB::commit();
                    return Response::json(array("status" => 201,"messages" => "Creado","data" => $data), 201);
                } 
                else{
                    DB::rollback();
                    return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
                }
                


        }///FIN IF TIPO MOVIMIENTO = 2 -->  SALIDA MANUAL




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
    public function show($id)
    {

        $data =  Movimiento::with('MovimientoInsumos','Almacen')->find($id);

        if(!$data){
			return Response::json(array("status" => 404,"messages" => "No se encuentra el movimiento solicitado"), 200);
		} 
		else{
			return Response::json(array("status" => 200,"messages" => "Operación realizada con exito","data" => $data), 200);
		}
        
    }

    /**
	 * Actualizar el  registro especificado en el la base de data
	 *
	 * <h4>Request</h4>
	 * Recibe un Input Request con el json de los data
	 *
	 * @param  int  $id que corresponde al identificador del dato a actualizar 	 
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 304, "messages": "No modificado"),status) </code>
	 */
    public function update(Request $request, $id)
    {
 
    }
    
     /**
	 * Elimine el registro especificado del la base de data (softdelete).
	 *
	 * @param  int  $id que corresponde al identificador del dato a eliminar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
    public function destroy($id)
    {
        
    }


///***************************************************************************************************************************
///***************************************************************************************************************************

    /**
	 * Validad los parametros recibidos, Esto no tiene ruta de acceso es un metodo privado del controlador.
	 * @param  Request  $request que corresponde a los parametros enviados por el cliente
	 * @return Response
	 * <code> Respuesta Error json con los errores encontrados </code>
	 */
	private function ValidarMovimiento($key, $id, $request)
    { 
        $mensajes = [
                        'required'      => "required",
                        'email'         => "email",
                        'unique'        => "unique",
                        'integer'       => "not_integer",
                        'in'            => 'in',
                    ];

        $reglas = [
                    'tipo_movimiento_id'  => 'required|integer|in:1,2',
                  ];
        
    $v = \Validator::make($request, $reglas, $mensajes );

        if ($v->fails())
        {
			$mensages_validacion = array();
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                    foreach ($item as $msg)
                    {
                        array_push($msg_validacion, $msg);
                    }
                    array_push($mensages_validacion, array($indice.''.$key => $msg_validacion));
			}
			return $mensages_validacion;
        }else{
                return true;
             }
	}

///**************************************************************************************************************************
///**************************************************************************************************************************
   
    private function ValidarInsumos($key, $id, $request,$tipo){ 
        $mensajes = [
                        'required'      => "required",
                        'email'         => "email",
                        'unique'        => "unique",
                        'integer'       => "integer",
                        'min'           => "min"
                    ];

        if($tipo=='E')
                {
                    $reglas = [
                                'clave'                 => 'required',
                                'cantidad'              => 'required|integer',
                                'cantidad_x_envase'     => 'required|integer',
                                'lote'                  => 'required',
                                'fecha_caducidad'       => 'required',
                                'codigo_barras'         => 'required|string',
                              ];
                }else{
                        $reglas = [
                                    'clave'                 => 'required',
                                    'cantidad'              => 'required|integer',
                                    'cantidad_x_envase'     => 'required|integer',
                                  ];
                     }
                     
    $v = \Validator::make($request, $reglas, $mensajes );

    //$item_insumo = Insumo::where('clave','>')


        if ($v->fails())
        {
			$mensages_validacion = array(); 
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                    foreach ($item as $msg)
                    {
                        array_push($msg_validacion, $msg);
                    }
                    array_push($mensages_validacion, array($indice.''.$key => $msg_validacion));
			}
			return $mensages_validacion;
        }else{
                return ;
             }
        
		 
	}

///***************************************************************************************************************************
///***************************************************************************************************************************

        /**
	 * Funcion que recive todos los campos del formulario que se envia desde el cliente
	 * @param  Request  $datos que corresponde a los datos del form enviados por el cliente
     * @param  Request  $data que corresponde a el objeto ORM
	 * @return Response
	 * <code> Respuesta Error json con los errores encontrados </code>
	 */

     
    private function ValidarMetadatos($datos, $data){
		$success = false;
        //comprobar que el servidor id no me lo envian por parametro, si no poner el servidor por default de la configuracion local, si no seleccionar el servidor del parametro
        $servidor_id = property_exists($datos, "servidor_id") ? $datos->servidor_id : env('SERVIDOR_ID');

        //agregar al modelo los datos
        $data->almacen_id                   =  $datos->almacen_id;
        $data->tipo_movimiento_id           =  $datos->tipo_movimiento_id; 
        $data->fecha_movimiento             =  property_exists($datos, "fecha_movimiento")          ? $datos->fecha_movimiento          : '';
        $data->observaciones                =  property_exists($datos, "observaciones")             ? $datos->observaciones             : '';
        $data->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
        $data->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';

        // si se guarda el maestro tratar de guardar el detalle  
        if( $data->save() )
        {
            $success = true;
            //verificar si existe contacto, en caso de que exista proceder a guardarlo
            if(property_exists($datos, "insumos")){
                 $detalle = array_filter($datos->insumos, function($v){return $v !== null;});

                MovimientoInsumos::where("movimiento_id", $data->id)->delete();

                 foreach ($detalle as $key => $value)
                {
                     if($value != null){
                         if(is_array($value))
                            $value = (object) $value;

                        $item_stock = new Stock;

                        $item_stock->almacen_id             = $data->almacen_id;
                        $item_stock->clave_insumo_medico    = $value->clave;
                        $item_stock->marca_id               = NULL;
                        $item_stock->lote                   = $value->lote;
                        $item_stock->fecha_caducidad        = $value->fecha_caducidad;
                        $item_stock->codigo_barras          = $value->codigo_barras;
                        $item_stock->existencia             = $value->cantidad;
                        $item_stock->existencia_unidosis    = ( $value->cantidad_x_envase * $value->cantidad );

                        if($item_stock->save()){

                            $item_detalles = new MovimientoInsumos;

                            $item_detalles->movimiento_id           = $data->id; 
                            $item_detalles->stock_id                = $item_stock->id; 
                            $item_detalles->cantidad                = $item_stock->existencia;
                            $item_detalles->precio_unitario         = 0;
                            $item_detalles->iva                     = 0; 
                            $item_detalles->precio_total            = 0;

                            $item_detalles->save();   
                            
                        }else{
                                return Response::json(['error' => $validacion_insumos], HttpResponse::HTTP_CONFLICT);
                             }
                    }
                }
            }               
        }
        
        return $success;
    }
///**************************************************************************************************************************
///**************************************************************************************************************************
       
    private function ValidarMetadatosSalida($datos, $data){
		$success = false;
        //comprobar que el servidor id no me lo envian por parametro, si no poner el servidor por default de la configuracion local, si no seleccionar el servidor del parametro
        $servidor_id = property_exists($datos, "servidor_id") ? $datos->servidor_id : env('SERVIDOR_ID');

        //agregar al modelo los datos
        $data->almacen_id                   =  $datos->almacen_id;
        $data->tipo_movimiento_id           =  $datos->tipo_movimiento_id; 
        $data->fecha_movimiento             =  property_exists($datos, "fecha_movimiento")          ? $datos->fecha_movimiento          : '';
        $data->observaciones                =  property_exists($datos, "observaciones")             ? $datos->observaciones             : '';
        $data->cancelado                    =  property_exists($datos, "cancelado")                 ? $datos->cancelado                 : '';
        $data->observaciones_cancelacion    =  property_exists($datos, "observaciones_cancelacion") ? $datos->observaciones_cancelacion : '';

        // si se guarda el movimiento tratar de guardar el detalle de insumos
        if( $data->save() )
        {
            $success = true;
            if(property_exists($datos, "insumos")){
                 $detalle = array_filter($datos->insumos, function($v){return $v !== null;});

                MovimientoInsumos::where("movimiento_id", $data->id)->delete();

                 foreach ($detalle as $key => $value)
                {
                     if($value != null){
                         if(is_array($value))
                            $value = (object) $value;

////*************************************************************************************************************************
                        /// ACTUALIZACION DE NUEVO STOCK
                        $stocks = array();                        
                        $stocks = Stock::where('clave_insumo_medico',$value->clave)->where('existencia','>',0)->orderBy('fecha_caducidad','ASC')->get();

                        $cantidad_surtir     = $value->cantidad;
                        $cantidad_conseguida = 0;

                        foreach($stocks as $stock)
                        {
                            $restar_item               = 0;
                            $por_completar             = $cantidad_surtir - $cantidad_conseguida;
                            $disponible_item           = $stock->existencia;
                            $disponible_unidosis_item  = $stock->existencia_unidosis;

                            $insumo_medico = Insumo::conDescripciones()->with('informacionAmpliada')->find($stock->clave_insumo_medico);
                            $insumo_medico = (object) $insumo_medico;
                            
                            $cantidad_x_envase = $insumo_medico->informacionAmpliada->cantidad_x_envase;

                            if($disponible_item >= $por_completar){
                                $cantidad_conseguida += $por_completar; // COMPLETADO LO REQUERIDO
                                $restar_item = $por_completar;   
                            }else{
                                $cantidad_conseguida += $disponible_item;
                                $restar_item = $disponible_item;
                            }

                            $stock->existencia = ($disponible_item - $restar_item);

                            $stock_update = Stock::find($stock->id);

                            $stock_update->existencia          = ($disponible_item - $restar_item);
                            $stock_update->existencia_unidosis = ($disponible_unidosis_item) - ($restar_item * $cantidad_x_envase);

 
                            if($stock_update->save()){
///*************************************************************************************************************************
                            $item_detalles = new MovimientoInsumos;

                            $item_detalles->movimiento_id           = $data->id; 
                            $item_detalles->stock_id                = $stock_update->id; 
                            $item_detalles->cantidad                = $restar_item;
                            $item_detalles->precio_unitario         = 0;
                            $item_detalles->iva                     = 0; 
                            $item_detalles->precio_total            = 0;

                            $item_detalles->save();
///*************************************************************************************************************************
                            }

                            if($cantidad_conseguida == $cantidad_surtir){
                                break;
                            }

                        }

////*************************************************************************************************************************
                       
                    }
                }
            }               
        }
        
        return $success;
    }
///**************************************************************************************************************************
///**************************************************************************************************************************
     


}
