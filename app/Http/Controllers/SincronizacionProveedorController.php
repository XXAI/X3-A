<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


use App\Models\CluesTurno;
use App\Models\UnidadMedica;
use App\Models\Pedido;
use App\Models\PedidoInsumo;
use App\Models\Usuario;
use App\Models\Almacen;
use App\Models\Contrato;
use App\Models\Proveedor;


/** 
* Controlador 
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017
*
* Controlador 
*
*/
class SincronizacionProveedorController extends Controller
{
     
     
///***************************************************************************************************************************
///***************************************************************************************************************************
   
    public function index(Request $request)
    {
        //var_dump(json_encode($request)); die();

        $proveedor_id = $request->get('proveedor_id');

        $items = Pedido::where('proveedor_id',$proveedor_id);
        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
    
    }

 ///***************************************************************************************************************************
///***************************************************************************************************************************
 
 public function store(Request $request)
    {
        $clues = $request->get('clues');
        if(!$clues){
            return Response::json(array("status" => 404,"messages" => "Debe especificar una Unidad Médica."), 404);
        } 


        

        
    }

 ///***************************************************************************************************************************
///***************************************************************************************************************************
 
    public function show($id)
    {

        
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
 

    public function update(Request $request, $id){
 
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
      
    public function destroy($id)
    {
        
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
 
public function listarPedidos(Request $request)
{
    $proveedor_id = $request->get('proveedor_id');

    $items = Pedido::where('proveedor_id',$proveedor_id);
        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
}

///***************************************************************************************************************************
///***************************************************************************************************************************
 
public function analizarJson(Request $request)
{
    $proveedor_id = $request->get('proveedor_id');

    $input_data = (object)Input::json()->all();

    $errors                  = 0;
    $recetas_validas         = 0;
    $recetas_invalidas       = 0;
    $colectivos_validos      = 0;
    $colectivos_invalidos    = 0;

    if(property_exists($input_data, "recetas"))
    {
        foreach($input_data['recetas'] as $receta)
        {
            $folio = $receta->folio;


        }

    }else{

         }


        
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $items = $items->paginate($resultadosPorPagina);
        } else {
            $items = $items->get();
        }
       
        return Response::json([ 'data' => $items],200);
}

///***************************************************************************************************************************
///***************************************************************************************************************************
 
	private function ValidarMovimiento($key, $id, $request)
    { 
        $mensajes = [
                        'required'      => "required",
                        'email'         => "email",
                        'unique'        => "unique",
                        'integer'       => "not_integer",
                        'in'            => 'no_valido',
                    ];

        $reglas = array();
        $reglas = [
                    'tipo_movimiento_id' => 'required|integer|in:1,2,3,4,5,6,7,8',
                  ];

        if($request['movimiento_metadato'] != NULL)
        {
            if($request['tipo_movimiento_id'] == 1 )
            {
                $reglas = [
                            'tipo_movimiento_id'                 => 'required|integer|in:1,2,3,4,5,6,7,8',
                            'movimiento_metadato.persona_recibe' => 'required|string',
                          ];
            }
            if($request['tipo_movimiento_id'] == 2 )
            {
                $reglas = [
                            'tipo_movimiento_id'                 => 'required|integer|in:1,2,3,4,5,6,7,8',
                            'movimiento_metadato.servicio_id'    => 'required|integer',
                            'movimiento_metadato.persona_recibe' => 'required|string',
                            'movimiento_metadato.turno_id'       => 'required|integer',
                          ];
            }
            

        }else if($request['receta'] != NULL){
                
            $reglas = [
                        'tipo_movimiento_id'    => 'required|integer|in:5',
                        'receta.folio'          => 'required|string',
                        'receta.tipo_receta'    => 'required|string',
                        'receta.fecha_receta'   => 'required',
                        'receta.doctor'         => 'required|string',
                        'receta.paciente'       => 'required|string',
                        'receta.diagnostico'    => 'required|string',
                        'receta.imagen_receta'  => 'required|string',
                      ];
        }

    $v = \Validator::make($request, $reglas, $mensajes );

    //*********************************************************************************************************
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


///***************************************************************************************************************************
///***************************************************************************************************************************
   
   public function validarArrayRecetasColectivos(Request $request)
    {
        $errors = array(); 
        $almacen_id=$request->get('almacen_id');       

        $validacion = $this->ValidarMovimiento("", NULL, Input::json()->all(),$almacen_id);
		if(is_array($validacion))
        {
			return Response::json(['error' => $validacion], HttpResponse::HTTP_CONFLICT);
		}
        $datos = (object) Input::json()->all();	
        $success = false;

        if(property_exists($datos, "recetas"))
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
                    array_push($errors, array(array('recetas' => array('objeto_recetas_vacio'))));
                 }
        }else{
                array_push($errors, array(array('recetas' => array('no_existe_objeto_recetas'))));
             }

 

        return Response::json(array("status" => 200,"messages" => "Estatus Entrega Pedidos"), 200);
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
   public function sincronizarRecetasColectivos(Request $request)
    {
        return Response::json(array("status" => 200,"messages" => "Estatus Entrega Pedidos"), 200);
    }

///***************************************************************************************************************************
///***************************************************************************************************************************
   
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

        if($tipo=='RECETA')
                {
                    $reglas = [
                                'clave_insumo_medico'     => 'required',
                                'cantidad_recetada'       => 'required|integer',
                                'cantidad_surtida'        => 'required|integer',
                                'dosis'                   => 'required|integer',
                                'frecuencia'              => 'required|integer',
                                'duracion'                => 'required|integer'
                              ];
                }else{ // ES COLECTIVO
                        $reglas = [
                                    'clave_insumo_medico'    => 'required',
                                    'cantidad_solicitada'    => 'required|numeric',
                                    'cantidad_surtida'       => 'required|integer',
                                  ];
                     }
                     
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();

        if($tipo=='S')
        {
            foreach($request['lotes'] as $i => $lote)
            {
                $lote = (object) $lote;
                $lote_check =  Stock::where('clave_insumo_medico',$request['clave'])->find($lote->id);

                $v->after(function($v) use($lote,$lote_check,$i)
                {
                    ///****************************************************************************************
                    if($lote_check)
                    {
                        if($lote->cantidad <= 0)
                        {
                            $v->errors()->add('lote_'.$lote->id.'_', 'cantidad_invalida');
                        }

                        /// validar cantidad solicitada en req contra lo del find
                        if($lote->modo_salida=='N')
                        {
                            if($lote->cantidad <= $lote_check->existencia)
                            {
                            }else{
                                    $v->errors()->add('lote_'.$lote->id.'_', 'lote_insuficiente');
                                 }
                        }else {
                                if($lote->cantidad <= $lote_check->existencia_unidosis)
                                {
                                }else{
                                        $v->errors()->add('lote_'.$lote->id.'_', 'lote_insuficiente');
                                    }
                               }
                        

                    }else{
                            if($lote->cantidad <= 0)
                            {
                                $v->errors()->add('lote_'.$lote->id.'_', 'cantidad_invalida');
                            }

                            if(property_exists($lote, 'nuevo'))
                            {
                                // verificar si existe lote,codigo, barra y fecha cad
                            }else{
                                    $v->errors()->add('lote_'.$lote->id.'_', 'no_existe'); 
                                 }
                         }
                  ///****************************************************************************************    
                     
                  

                });
            }    
        }// FIN IF TIPO SALIDA

        if ($v->fails())
        {
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
  

}
