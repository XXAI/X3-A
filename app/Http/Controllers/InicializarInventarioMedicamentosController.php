<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;

use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

use \Excel;

use App\Models\InicializacionInventario;
use App\Models\InicializacionInventarioDetalle;
use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\MovimientoInsumos;
use App\Models\MovimientoAjuste;
use App\Models\TiposMovimientos;
use App\Models\Insumo;
use App\Models\MovimientoMetadato;
use App\Models\MovimientoDetalle;
use App\Models\Receta;
use App\Models\RecetaDetalle;
use App\Models\RecetaMovimiento;
use App\Models\ContratoPrecio;
use App\Models\NegacionInsumo;
use App\Models\Almacen;
use App\Models\Programa;


/** 
* Controlador
* 
* @package    SIAL API
* @subpackage Controlador
* @author     Joram Roblero Pérez <joram.roblero@gmail.com>
* @created    2017-03-22
*
* Controlador `AjusteMasInventario`: Controlador  para 
*
*/
class InicializarInventarioMedicamentosController extends Controller
{
     
    public function index(Request $request)
    {
///*******************************************************************************************************************************
        $parametros = Input::only('q','page','per_page','clues','almacen');
        $parametros['almacen'] = $request->get('almacen_id');

        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }  
        $almacen     = Almacen::find($parametros['almacen']);
        $almacen_id  = $almacen->id;
    
        $inis   = InicializacionInventario::getModel();
        $inis   = $inis->with("inicializacionInventarioDetalle")->where('almacen_id',$almacen_id);

        if ($parametros['q'])
        {
            $inis =  $inis->where(function($query) use ($parametros) {
                 $query->where('id','LIKE',"%".$parametros['q']."%")
                       ->orWhere('observaciones','LIKE',"%".$parametros['q']."%");
             });
        }
 //////*********************************************************************************************************
        if(isset($parametros['page'])){
            $resultadosPorPagina = isset($parametros["per_page"])? $parametros["per_page"] : 20;
            $inis = $inis->paginate($resultadosPorPagina);
        } else {
                    $inis = $inis->get();
               }
 //////*********************************************************************************************************

        if(count($inis) <= 0){

            return Response::json(array("status" => 404,"messages" => "No se han realizado inicializaciones de inventario en este almacén","data" => $inis), 200);
        } 
        else{
                return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $inis, "total" => count($inis)), 200);
            }

 
///****************************************************************************************************************************************      
///*******************************************************************************************************************************************
 
   }


  ////                  S  T  O  R  E
 ////********************************************************************************************************************************************

    public function store(Request $request)
    {
        
        $parametros = Input::only('q','page','per_page','almacen');
        $parametros['almacen'] = $request->get('almacen_id');
        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }  
        
        $input_data = (object)Input::json()->all();
        $almacen = Almacen::find($parametros['almacen']);
        $almacen_id  = $almacen->id;
        $clues       = $request->get('clues');
        $servidor_id = property_exists($input_data, "servidor_id") ? $input_data->servidor_id : env('SERVIDOR_ID');

        $errors     = array();
        $nuevo      = 0;

        $cantidad_programas = 0;
        $cantidad_claves    = 0;
        $cantidad_insumos   = 0;
        $cantidad_lotes     = 0;
        $monto_total        = 0;
///*****************************************************************************************************************************************
///*****************************************************************************************************************************************
if($input_data->estatus=="INICIALIZADO")
{
        if(property_exists($input_data, "programas"))
        {
            $programas_x = $input_data->programas;
            if(count($programas_x) > 0 )
            {
                foreach ($programas_x as $key => $programa_validar)
                {
                    $validacion_programa = $this->validarPrograma($programa_validar);
                    $programa_validar    = (object) $programa_validar;

                    if($validacion_programa != "")
                    {   array_push($errors, $validacion_programa);  }

                    ///**********************************************************************************************************************
                            if(count($programa_validar->insumos) > 0 )
                            {
                                foreach ($programa_validar->insumos as $key => $insumo_validar)
                                {
                                    $validacion_insumo = $this->validarInsumo($insumo_validar);
                                    $insumo_validar    = (object) $insumo_validar;
                                    
                                    if($validacion_insumo != "")
                                    {   array_push($errors, $validacion_insumo);  }

                                    ///**********************************************************************************************************************
                                            if(count($insumo_validar->lotes) > 0 )
                                            {
                                                foreach ($insumo_validar->lotes as $key => $lote_validar)
                                                {
                                                    $validacion_lote = $this->validarLote($lote_validar);
                                                    $lote_validar    = (object) $lote_validar;

                                                    if($validacion_lote != "")
                                                    {   array_push($errors, $validacion_lote);  }
                                                }
                                            }else{ array_push($errors, array(array('Lotes' => array('no_items_lotes')))); }
                                    ///**********************************************************************************************************************
                                }
                            }else{ array_push($errors, array(array('Insumos' => array('no_items_insumos')))); }
                    ///**********************************************************************************************************************

                }
            }else{  array_push($errors, array(array('Programas' => array('no_items_programas'))));  }
                    
         }else{ array_push($errors, array(array('Programas' => array('no_existe_programas')))); }

}/// fin if INICIALIZADO
    

///*****************************************************************************************************************************************
///*****************************************************************************************************************************************
        if( count($errors) > 0 )
        {
            return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
        } 
////*****************************************************************************************************************************************
///*****************************************************************************************************************************************
    
        $success = false;
        DB::beginTransaction();
        try{

////****************************************************************************************************************************************
        $fecha_fin = NULL;
        if($input_data->estatus=="INICIALIZADO")
        {
          $fecha_fin =  date("Y-m-d");  
        }

        //$inicializacion = InicializacionInventario::find($input_data->id);

        $inicializacion = new InicializacionInventario;
        $inicializacion->servidor_id      = $servidor_id;
        $inicializacion->clues            = $clues;
        $inicializacion->almacen_id       = $almacen_id;
        $inicializacion->estatus          = $input_data->estatus;
        $inicializacion->fecha_inicio     = date("Y-m-d");
        $inicializacion->fecha_fin        = $fecha_fin;
        $inicializacion->observaciones    = $input_data->observaciones;
        $inicializacion->save();

        $nuevo = 1; 
            
         InicializacionInventarioDetalle::where("inicializacion_inventario_id", $inicializacion->id)->delete();

         foreach ($input_data->programas as $key => $programa)
        {
            $programa = (object) $programa;
                foreach ($programa->insumos as $key => $insumo)
                {
                     $insumo = (object) $insumo;
                     $insumo_x  = Insumo::datosUnidosis()->where('clave',$insumo->clave_insumo_medico)->first();
                     $cantidad_x_envase   = $insumo_x->cantidad_x_envase;

                     $iva_porcentaje = 0;
                     if($insumo_x->tipo == "ME")
                     { $iva_porcentaje = 0; }else{ $iva_porcentaje = 0.16; } 

                    foreach ($insumo->lotes as $key => $lote)
                    {
                        $lote = (object) $lote;
                    
                        DB::table('inicializacion_inventario_detalles')
                            ->where('inicializacion_inventario_id',$inicializacion->id)
                            ->where('almacen_id', $almacen_id)
                            ->where('programa_id',$programa->id)
                            ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                            ->where('lote',$lote->lote)
                            ->update(['deleted_at' => NULL]);

                         $iid = InicializacionInventarioDetalle::where('inicializacion_inventario_id',$inicializacion->id)
                                                               ->where('almacen_id',$almacen_id)
                                                               ->where('programa_id',$programa->id)
                                                               ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                                                               ->where('lote',$lote->lote)
                                                               ->where('fecha_caducidad',$lote->fecha_caducidad)
                                                               ->first();
                        if($lote->lote=="" && $lote->fecha_caducidad == "" && $lote->existencia == "")
                        {}else{  
                                if(!$iid){
                                            $iid = new InicializacionInventarioDetalle;
                                         } 
                            
                                    $iid->inicializacion_inventario_id = $inicializacion->id;
                                    $iid->almacen_id                   = $almacen_id;
                                    $iid->programa_id                  = $programa->id;
                                    $iid->clave_insumo_medico          = $insumo->clave_insumo_medico;
                                    $iid->lote                         = $lote->lote;
                                    $iid->exclusivo                    = $lote->exclusivo;
                                    $iid->fecha_caducidad              = $lote->fecha_caducidad;
                                    $iid->codigo_barras                = $lote->codigo_barras;
                                    $iid->existencia                   = $lote->existencia;
                                    $iid->existencia_unidosis          = ($lote->existencia * $cantidad_x_envase);
                                    $iid->precio_unitario              = $lote->precio_unitario;
                                    $iid->iva                          = ( $lote->precio_unitario * $iva_porcentaje );
                                    $iid->importe                      = ( $lote->existencia * $lote->precio_unitario );
                                    $iid->iva_importe                  = ( $lote->precio_unitario * $iva_porcentaje ) * $lote->existencia ;
                                    $iid->importe_con_iva              = ( $lote->precio_unitario + ( $lote->precio_unitario * $iva_porcentaje )) * $lote->existencia;
                                    $iid->save();
                            
                                }  

                    }
                }
        }

         $cantidad_programas  = DB::table('inicializacion_inventario_detalles')
                                ->where('inicializacion_inventario_id',$inicializacion->id)
                                ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                ->count(DB::raw('DISTINCT programa_id'));

         $cantidad_claves     = DB::table('inicializacion_inventario_detalles')
                                ->where('inicializacion_inventario_id',$inicializacion->id)
                                ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                ->count(DB::raw('DISTINCT clave_insumo_medico'));

         $cantidad_insumos    = DB::table('inicializacion_inventario_detalles')
                                    ->where('inicializacion_inventario_id',$inicializacion->id)
                                    ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                    ->sum('existencia');

         $cantidad_lotes      = DB::table('inicializacion_inventario_detalles')
                                ->where('inicializacion_inventario_id',$inicializacion->id)
                                ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                ->count('lote');
        
         $monto_total         = DB::table('inicializacion_inventario_detalles')
                                    ->where('inicializacion_inventario_id',$inicializacion->id)
                                    ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                    ->sum('importe_con_iva');

        $inicializacion->cantidad_programas = $cantidad_programas;
        $inicializacion->cantidad_claves    = $cantidad_claves;
        $inicializacion->cantidad_insumos   = $cantidad_insumos;
        $inicializacion->cantidad_lotes     = $cantidad_lotes;
        $inicializacion->monto_total        = $monto_total;
        $inicializacion->observaciones      = $input_data->observaciones;
        $inicializacion->save();

        //dd($inicializacion->id);

        if($inicializacion->estatus == "INICIALIZADO")
        {
            Stock::where("almacen_id", $almacen_id)->delete();
            
            $inis = InicializacionInventarioDetalle::where('almacen_id',$almacen_id)
                                                   ->where('inicializacion_inventario_id',$inicializacion->id)
                                                   ->get();
            $movimiento = new Movimiento;
            $movimiento->tipo_movimiento_id  = 19;
            $movimiento->almacen_id          = $almacen_id;
            $movimiento->fecha_movimiento    = date('Y-m-d');
            $movimiento->status              = "FI";
            $movimiento->observaciones       = $input_data->observaciones;
            $movimiento->save();
            
            foreach ($inis as $key => $ini)
            {           
                $new_stock = new Stock;
                $new_stock->almacen_id           = $almacen_id;
                $new_stock->clave_insumo_medico  = $ini->clave_insumo_medico;
                $new_stock->programa_id          = $ini->programa_id;
                $new_stock->marca_id             = $ini->marca_id;
                $new_stock->lote                 = $ini->lote;
                $new_stock->exclusivo            = $ini->exclusivo;
                $new_stock->fecha_caducidad      = $ini->fecha_caducidad;
                $new_stock->codigo_barras        = $ini->codigo_barras;
                $new_stock->existencia           = $ini->existencia;
                $new_stock->existencia_unidosis  = $ini->existencia_unidosis;
                $new_stock->unidosis_sueltas     = $ini->unidosis_sueltas;
                $new_stock->envases_parciales    = $ini->envases_parciales;
                $new_stock->save();

                $mi = new MovimientoInsumoS;
                $mi->movimiento_id       = $movimiento->id;
                $mi->stock_id            = $new_stock->id;
                $mi->clave_insumo_medico = $new_stock->clave_insumo_medico;
                $mi->modo_salida         = "N";
                $mi->cantidad            = $new_stock->existencia;
                $mi->cantidad_unidosis   = $new_stock->existencia_unidosis;
                $mi->precio_unitario     = $ini->precio_unitario;
                $mi->iva                 = $ini->iva;
                $mi->precio_total        = $ini->importe_con_iva;
                $mi->save();

            }

        }

////*****************************************************************************************************************************************

        $success = true;
        } catch (\Exception $e) {   
                                    $success = false;
                                    DB::rollback();
                                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                                } 
        if ($success)
        {
            DB::commit();
            return Response::json(array("status" => 201,"messages" => "Creado correctamente","data" => $inicializacion), 201);        
        }else{
                DB::rollback();
                return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
             }
////*****************************************************************************************************************************************



    }  /// FIN METODO     S   T   O   R   E 


///*************************************************************************************************************************************
///*************************************************************************************************************************************







/////                             S    H    O    W 
///*************************************************************************************************************************************
///*************************************************************************************************************************************
    public function show(Request $request,$id)
    {   
///****************************************************************************************************************************************
///****************************************************************************************************************************************

        $parametros = Input::only('q','page','per_page','almacen','tipo','fecha_desde','fecha_hasta','usuario','turno','servicio');
        $parametros['almacen'] = $request->get('almacen_id');

        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }  
        
        $almacen = Almacen::find($parametros['almacen']);
        $movimientos = NULL;
        $data = NULL;

        $inicializacion = InicializacionInventario::find($id);

        $programas = array();

        if($inicializacion)
        {
            $iids_programas = InicializacionInventarioDetalle::where('inicializacion_inventario_id',$inicializacion->id)
                                                             ->groupBy('programa_id')
                                                             ->orderBy('id','asc')
                                                             ->get();
            foreach ($iids_programas as $key => $iid_programa)
            {  
                $programa_temp   = Programa::find($iid_programa->programa_id);

                $claves = array();
                $iids_claves = InicializacionInventarioDetalle::where('inicializacion_inventario_id',$inicializacion->id)
                               ->where('programa_id',$programa_temp->id)
                               ->groupBy('clave_insumo_medico')
                               ->orderBy('id','asc')->get();
                foreach ($iids_claves as $key => $iid_clave)
                {
                    $clave_temp = Insumo::conDescripciones()->find($iid_clave->clave_insumo_medico);
                    $clave_temp->load('informacionAmpliada');
                    
                    $clave_temp->clave_insumo_medico = $clave_temp->clave;

                ///****************************************************************************************************************************
                    $data_precios =  DB::table("precios_base_detalles AS pbd")->select(DB::raw("pbd.precio as precio_unitario"),"pb.anio")
                                    ->leftJoin('precios_base AS pb', function($join){
                                                $join->on('pb.activo','=',DB::raw("1"));
                                                $join->on('pbd.precio_base_id', '=', 'pb.id');
                                              })
                                    ->where('pbd.insumo_medico_clave',$clave_temp->clave)
                                    ->where('pbd.deleted_at',NULL)->first();

                    if($data_precios)
                    {
                        $clave_temp->anio             = $data_precios->anio;
                        $clave_temp->precio_unitario  = $data_precios->precio_unitario;
                    }else{
                            $clave_temp->anio             = NULL;
                            $clave_temp->precio_unitario  = NULL;
                         }
                 ///****************************************************************************************************************************
                    $lotes = array();
                    $iids_lotes = InicializacionInventarioDetalle::where('inicializacion_inventario_id',$inicializacion->id)
                                                                  ->where('programa_id',$programa_temp->id)
                                                                  ->where('clave_insumo_medico',$clave_temp->clave_insumo_medico)
                                                                  ->orderBy('id','asc')
                                                                  ->get();

                    foreach ($iids_lotes as $key => $iid_lote)
                    {
                        array_push($lotes,$iid_lote);
                    }

                    $clave_temp->lotes = $lotes;
                    array_push($claves,$clave_temp);
                }

                $programa_temp->insumos = $claves;
                array_push($programas,$programa_temp);
            }

            $inicializacion->programas = $programas;

            return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $inicializacion), 200);

        }else{
                return Response::json(array("status" => 404,"messages" => "No se encuentra la inicialización de Inventario"), 404);
             }


///*******************************************************************************************************************************************              
///*******************************************************************************************************************************************

    }




/////                             U  P   D   A   T  E

///***************************************************************************************************************************
///***************************************************************************************************************************

    public function update(Request $request, $id)
    {
        $parametros = Input::only('q','page','per_page','almacen');
        $parametros['almacen'] = $request->get('almacen_id');
        if(!$request->get('almacen_id')){
            return Response::json(array("status" => 404,"messages" => "Debe especificar un almacen."), 200);
        }  
        
        $input_data = (object)Input::json()->all();
        $almacen = Almacen::find($parametros['almacen']);
        $almacen_id  = $almacen->id;
        $clues       = $request->get('clues');
        $servidor_id = property_exists($input_data, "servidor_id") ? $input_data->servidor_id : env('SERVIDOR_ID');

        $inicializacion = InicializacionInventario::find($input_data->id);

        if( ! $inicializacion)
        {
            return Response::json(array("status" => 404,"messages" => "No se encuentra la inicialización de Inventario"), 404);
        }

        if($inicializacion->estatus == "INICIALIZADO")
        {
            return Response::json(array("status" => 409,"messages" => "Esta inicialización ya esta cerrada !"), 409);
        }


        $errors     = array();
        $nuevo      = 0;

        $cantidad_programas = 0;
        $cantidad_claves    = 0;
        $cantidad_insumos   = 0;
        $cantidad_lotes     = 0;
        $monto_total        = 0;
///*****************************************************************************************************************************************
///*****************************************************************************************************************************************
if($input_data->estatus=="INICIALIZADO")
{
        if(property_exists($input_data, "programas"))
        {
            $programas_x = $input_data->programas;
            if(count($programas_x) > 0 )
            {
                foreach ($programas_x as $key => $programa_validar)
                {
                    $validacion_programa = $this->validarPrograma($programa_validar);
                    $programa_validar    = (object) $programa_validar;

                    if($validacion_programa != "")
                    {   array_push($errors, $validacion_programa);  }

                    ///**********************************************************************************************************************
                            if(count($programa_validar->insumos) > 0 )
                            {
                                foreach ($programa_validar->insumos as $key => $insumo_validar)
                                {
                                    $validacion_insumo = $this->validarInsumo($insumo_validar);
                                    $insumo_validar    = (object) $insumo_validar;
                                    
                                    if($validacion_insumo != "")
                                    {   array_push($errors, $validacion_insumo);  }

                                    ///**********************************************************************************************************************
                                            if(count($insumo_validar->lotes) > 0 )
                                            {
                                                foreach ($insumo_validar->lotes as $key => $lote_validar)
                                                {
                                                    $validacion_lote = $this->validarLote($lote_validar);
                                                    $lote_validar    = (object) $lote_validar;

                                                    if($validacion_lote != "")
                                                    {   array_push($errors, $validacion_lote);  }
                                                }
                                            }else{ array_push($errors, array(array('Lotes' => array('no_items_lotes')))); }
                                    ///**********************************************************************************************************************
                                }
                            }else{ array_push($errors, array(array('Insumos' => array('no_items_insumos')))); }
                    ///**********************************************************************************************************************

                }
            }else{  array_push($errors, array(array('Programas' => array('no_items_programas'))));  }
                    
         }else{ array_push($errors, array(array('Programas' => array('no_existe_programas')))); }

}/// fin if INICIALIZADO
    

///*****************************************************************************************************************************************
///*****************************************************************************************************************************************
        if( count($errors) > 0 )
        {
            return Response::json(['error' => $errors], HttpResponse::HTTP_CONFLICT);
        } 
////*****************************************************************************************************************************************
///*****************************************************************************************************************************************
    
        $success = false;
        DB::beginTransaction();
        try{

////****************************************************************************************************************************************
        $fecha_fin = null;
        if($input_data->estatus=="INICIALIZADO")
        {
          $fecha_fin =  date("Y-m-d");  
        }


        $inicializacion->servidor_id      = $servidor_id;
        $inicializacion->clues            = $clues;
        $inicializacion->almacen_id       = $almacen_id;
        $inicializacion->estatus          = $input_data->estatus;
        //$inicializacion->fecha_inicio     = date("Y-m-d");
        $inicializacion->fecha_fin        = $fecha_fin;
        $inicializacion->observaciones    = $input_data->observaciones;
        $inicializacion->save();

        $nuevo = 1; 
            
         InicializacionInventarioDetalle::where("inicializacion_inventario_id", $inicializacion->id)->delete();

         foreach ($input_data->programas as $key => $programa)
        {
            $programa = (object) $programa;
                foreach ($programa->insumos as $key => $insumo)
                {
                     $insumo = (object) $insumo;
                     $insumo_x  = Insumo::datosUnidosis()->where('clave',$insumo->clave_insumo_medico)->first();
                     $cantidad_x_envase   = $insumo_x->cantidad_x_envase;

                     $iva_porcentaje = 0;
                     if($insumo_x->tipo == "ME")
                     { $iva_porcentaje = 0; }else{ $iva_porcentaje = 0.16; } 

                    InicializacionInventarioDetalle::where('inicializacion_inventario_id',$inicializacion->id)
                                                    ->where('almacen_id', $almacen_id)
                                                    ->where('programa_id',$programa->id)
                                                    ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                                                    ->delete();

                    foreach ($insumo->lotes as $key => $lote)
                    {
                        $lote = (object) $lote;

                        //dd(json_encode($lote));

                        if($lote->lote=="" && $lote->fecha_caducidad == "" && $lote->existencia == "")
                        {}else{
                    
                        DB::table('inicializacion_inventario_detalles')
                            ->where('inicializacion_inventario_id',$inicializacion->id)
                            ->where('almacen_id', $almacen_id)
                            ->where('programa_id',$programa->id)
                            ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                            ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                            ->where('lote',$lote->lote)
                            ->where('fecha_caducidad',$lote->fecha_caducidad)
                            ->update(['deleted_at' => NULL]);
                    
                         $iid = InicializacionInventarioDetalle::where('inicializacion_inventario_id',$inicializacion->id)
                                                               ->where('almacen_id',$almacen_id)
                                                               ->where('programa_id',$programa->id)
                                                               ->where('clave_insumo_medico',$insumo->clave_insumo_medico)
                                                               ->where('lote',$lote->lote)
                                                               ->where('fecha_caducidad',$lote->fecha_caducidad)
                                                               ->first();
                        if(!$iid){
                                    $iid = new InicializacionInventarioDetalle;
                                 }

                                    $iid->inicializacion_inventario_id = $inicializacion->id;
                                    $iid->almacen_id                   = $almacen_id;
                                    $iid->programa_id                  = $programa->id;
                                    $iid->clave_insumo_medico          = $insumo->clave_insumo_medico;
                                    $iid->lote                         = $lote->lote;
                                    $iid->exclusivo                    = $lote->exclusivo;
                                    $iid->fecha_caducidad              = $lote->fecha_caducidad;
                                    $iid->codigo_barras                = $lote->codigo_barras;
                                    $iid->existencia                   = $lote->existencia;
                                    $iid->existencia_unidosis          = ($lote->existencia * $cantidad_x_envase);
                                    $iid->precio_unitario              = $lote->precio_unitario;
                                    $iid->iva                          = ( $lote->precio_unitario * $iva_porcentaje );
                                    $iid->importe                      = ( $lote->existencia * $lote->precio_unitario );
                                    $iid->iva_importe                  = ( $lote->precio_unitario * $iva_porcentaje ) * $lote->existencia ;
                                    $iid->importe_con_iva              = ( $lote->precio_unitario + ( $lote->precio_unitario * $iva_porcentaje )) * $lote->existencia;
                                    $iid->save();

                        }

                    }

                }
        }

         $cantidad_programas  = DB::table('inicializacion_inventario_detalles')
                                ->where('inicializacion_inventario_id',$inicializacion->id)
                                ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                ->count(DB::raw('DISTINCT programa_id'));

         $cantidad_claves     = DB::table('inicializacion_inventario_detalles')
                                ->where('inicializacion_inventario_id',$inicializacion->id)
                                ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                ->count(DB::raw('DISTINCT clave_insumo_medico'));

         $cantidad_insumos    = DB::table('inicializacion_inventario_detalles')
                                    ->where('inicializacion_inventario_id',$inicializacion->id)
                                    ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                    ->sum('existencia');

         $cantidad_lotes      = DB::table('inicializacion_inventario_detalles')
                                ->where('inicializacion_inventario_id',$inicializacion->id)
                                ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                ->count('lote');
        
         $monto_total         = DB::table('inicializacion_inventario_detalles')
                                    ->where('inicializacion_inventario_id',$inicializacion->id)
                                    ->where('inicializacion_inventario_detalles.deleted_at',NULL)
                                    ->sum('importe_con_iva');

        $inicializacion->cantidad_programas = $cantidad_programas;
        $inicializacion->cantidad_claves    = $cantidad_claves;
        $inicializacion->cantidad_insumos   = $cantidad_insumos;
        $inicializacion->cantidad_lotes     = $cantidad_lotes;
        $inicializacion->monto_total        = $monto_total;
        $inicializacion->observaciones      = $input_data->observaciones;
        $inicializacion->save();

        //dd($inicializacion->id);

        if($inicializacion->estatus == "INICIALIZADO")
        {
            Stock::where("almacen_id", $almacen_id)->delete();
            
            $inis = InicializacionInventarioDetalle::where('almacen_id',$almacen_id)
                                                   ->where('inicializacion_inventario_id',$inicializacion->id)
                                                   ->get();
            $movimiento = new Movimiento;
            $movimiento->tipo_movimiento_id  = 19;
            $movimiento->almacen_id          = $almacen_id;
            $movimiento->fecha_movimiento    = date('Y-m-d');
            $movimiento->status              = "FI";
            $movimiento->observaciones       = $input_data->observaciones;
            $movimiento->save();
            
            foreach ($inis as $key => $ini)
            {           
                $new_stock = new Stock;
                $new_stock->almacen_id           = $almacen_id;
                $new_stock->clave_insumo_medico  = $ini->clave_insumo_medico;
                $new_stock->programa_id          = $ini->programa_id;
                $new_stock->marca_id             = $ini->marca_id;
                $new_stock->lote                 = $ini->lote;
                $new_stock->exclusivo            = $ini->exclusivo;
                $new_stock->fecha_caducidad      = $ini->fecha_caducidad;
                $new_stock->codigo_barras        = $ini->codigo_barras;
                $new_stock->existencia           = $ini->existencia;
                $new_stock->existencia_unidosis  = $ini->existencia_unidosis;
                $new_stock->unidosis_sueltas     = $ini->unidosis_sueltas;
                $new_stock->envases_parciales    = $ini->envases_parciales;
                $new_stock->save();

                $mi = new MovimientoInsumoS;
                $mi->movimiento_id       = $movimiento->id;
                $mi->stock_id            = $new_stock->id;
                $mi->clave_insumo_medico = $new_stock->clave_insumo_medico;
                $mi->modo_salida         = "N";
                $mi->cantidad            = $new_stock->existencia;
                $mi->cantidad_unidosis   = $new_stock->existencia_unidosis;
                $mi->precio_unitario     = $ini->precio_unitario;
                $mi->iva                 = $ini->iva;
                $mi->precio_total        = $ini->importe_con_iva;
                $mi->save();

            }

        }

////*****************************************************************************************************************************************

        $success = true;
        } catch (\Exception $e) {   
                                    $success = false;
                                    DB::rollback();
                                    return Response::json(["status" => 500, 'error' => $e->getMessage()], 500);
                                } 
        if ($success)
        {
            DB::commit();
            return Response::json(array("status" => 200,"messages" => "Guardado correctamente","data" => $inicializacion), 200);
        }else{
                DB::rollback();
                return Response::json(array("status" => 409,"messages" => "Conflicto"), 200);
             }
////****************************************************************************************************************************************
    }
     



    public function destroy($id)
    {
        
    }
 

///**************************************************************************************************************************

private function validarPrograma($request)
    { 
        $mensajes = [
                        'required'      => "Debe ingresar este campo.",
                        'integer'       => "Solo cantidades enteras.",
                    ];
        $reglas = [
                        'id'            => 'required'
                  ];
                         
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item) 
            {
                $msg_validacion = array();
                array_push($mensages_validacion, $item);
			}  
			return $mensages_validacion;
        }else{
                return ;
             }
	}

///***************************************************************************************************************************

    private function validarInsumo($request)
    { 
        $mensajes = [
                        'required'      => "Debe ingresar este campo."
                    ];
        $reglas = [
                        'clave_insumo_medico'  => 'required'
                  ];
                         
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item)  // todos los mensajes de todos los campos
            {
                $msg_validacion = array();
                array_push($mensages_validacion, $item);
			}  
			return $mensages_validacion;
        }else{
                return ;
             }
	}
///***************************************************************************************************************************
private function validarLote($request)
    { 
        $mensajes = [
                        'required'      => "Debe ingresar este campo.",
                        'numeric'       => "Debe ingresar una cantidad valida para precio unitario.",
                        'email'         => "formato de email invalido",
                        'unique'        => "unique",
                        'integer'       => "Solo cantidades enteras.",
                        'min'           => "La cantidad debe ser mayor de cero.",
                        'after'         => "La fecha de caducidad debe ser mayor a hoy."
                    ]; 
        $reglas   = [
                        'lote'                  => 'required',
                        'exclusivo'             => 'required|integer',
                        'fecha_caducidad'       => 'required|date|after:today', 
                        'existencia'            => 'required|integer',
                        'precio_unitario'       => 'required|numeric'
                    ];
                         
        $v = \Validator::make($request, $reglas, $mensajes );
        $mensages_validacion = array();
 
        if ($v->fails())
        {
            foreach ($v->errors()->messages() as $indice => $item)
            {
                $msg_validacion = array();
                array_push($mensages_validacion, $item);
			}  
			return $mensages_validacion;
        }else{
                return ;
             }
	}
///***************************************************************************************************************************
    /* Importar Datos Masivos */
    public function cargarExcel(Request $request){
        ini_set('memory_limit', '-1');
        
        try{
            $arreglo = [];
            if ($request->hasFile('archivo')){
				
                $file = $request->file('archivo');
                if ($file->isValid()) {
                    
                    Excel::load($file, function($reader)  use (&$arreglo){ // Cargamos Excel para poder obtener los insumos y demas datos 
                        
                        $reader->formatDates(true);
                        $reader->formatDates(true, 'Y-m-d');
                        $objExcel = $reader->getExcel();
                        $sheet = $objExcel->getSheet(0);
                        $highestRow = $sheet->getHighestRow();
                        $highestColumn = $sheet->getHighestColumn();

                        $excel = $reader->get();
                        $cabeceras = [];
                        
                        foreach ($excel as $key => $value) {
                            $arreglo_datos = [];    
                            foreach ($excel[$key] as $key2 => $value2)
                            {
                                $arreglo_datos[] = $value2;
                            }
                            $arreglo[] = $arreglo_datos;
                        }
                        
                    });
                }                
            }

        }catch(\Exception $e)
        {
            return Response::json([ 'data' => "Ocurrio un error al leer el formato del archivo, favor de verificarlo y volverlo a intentar"],500); 
        }
        //return Response::json([ 'data' => $arreglo],500);
        $parametros = Input::only('term', 'clues', 'almacen');
        $data1 =  DB::table("insumos_medicos AS im")// Obtenemos todos los insumos para poder obtener información adicional, como el precio, tipo causes etc
        ->select(DB::raw("pbd.precio as precio_unitario_base"),DB::raw("pbd.precio as precio_unitario"),"im.clave", "im.tipo", "g.nombre",DB::raw("um.nombre AS unidad_medida"), "m.cantidad_x_envase", "im.es_causes", "im.es_unidosis", "im.descripcion", DB::raw("'' AS codigo_barras"),"pm.nombre AS presentacion_nombre")        
        ->leftJoin('stock AS s', 's.clave_insumo_medico', '=', 'im.clave')
        ->leftJoin('genericos AS g', 'g.id', '=', 'im.generico_id')
        ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
        ->leftJoin('unidades_medida AS um', 'um.id', '=', 'm.unidad_medida_id')
        ->leftJoin('presentaciones_medicamentos AS pm', 'pm.id', '=', 'm.presentacion_id')

        ->leftJoin('precios_base AS pb', 'pb.activo','=',DB::raw("1"))
        ->leftJoin('precios_base_detalles AS pbd', function($join){
                $join->on('im.clave', '=', 'pbd.insumo_medico_clave');
                $join->on('pbd.precio_base_id', '=', 'pb.id');
            })
        ->where('im.deleted_at',NULL)
        ->where(function($query1) use ($parametros) {
            $query1->where('im.tipo','ME')
            ->orWhere('im.tipo','MC');
        })->orderBy('im.descripcion', 'asc');
        

        //
        // Obtenemos todos los insumos para poder obtener información adicional, como el precio, tipo causes etc
        $data2 =  DB::table("insumos_medicos AS im")->select(DB::raw("pbd.precio as precio_unitario_base"),DB::raw("pbd.precio as precio_unitario"),"im.clave", "im.tipo", "g.nombre",DB::raw("um.nombre AS unidad_medida"), "m.cantidad_x_envase", "im.es_causes", "im.es_unidosis", "im.descripcion", DB::raw("'' AS codigo_barras"),"pm.nombre AS presentacion_nombre")
        ->leftJoin('genericos AS g', 'g.id', '=', 'im.generico_id')
        ->leftJoin('medicamentos AS m', 'm.insumo_medico_clave', '=', 'im.clave')
        ->leftJoin('unidades_medida AS um', 'um.id', '=', 'm.unidad_medida_id')
        ->leftJoin('presentaciones_medicamentos AS pm', 'pm.id', '=', 'm.presentacion_id')

        ->leftJoin('precios_base AS pb', 'pb.activo','=',DB::raw("1"))
        ->leftJoin('precios_base_detalles AS pbd', function($join){
                $join->on('im.clave', '=', 'pbd.insumo_medico_clave');
                $join->on('pbd.precio_base_id', '=', 'pb.id');
            })

        ->where('im.deleted_at',NULL)
        ->where(function($query2) use ($parametros) {
            $query2->where('im.tipo','ME')
            ->orWhere('im.tipo','MC');
        })->orderBy('im.descripcion', 'asc');
        

        $data = $data1->union($data2);
        $data = $data->groupBy("clave")->get();  
        //Este proceos de union no se bien por que lo hace, pero es la consulta que hace el modulo de incializar inventario (busqueda de insumos), por tanto supuse que estaba correcto

        $insumos = [];
        foreach ($data as $key => $value) {//Se crea el arreglo de los insumos
            $insumos[$value->clave] = $value;
        }

        $programa = Programa::all();
        $error = 0;
        $detalle_error = "";
        $exportacion = [];
        $sin_programa = [];
        $linea = 1;
        

        $arreglo_programa = [];
        
        try{
            foreach ($arreglo as $key => $value) {//Este proceso se clasificara los insumos por programa, insumo (clave) y por lote
                
                
                if($value[0] == "")
                    $value[0] = "Inicializacion Inventario";

                $insumo = null;
                $insumo = new \stdClass();
                $insumo->precio_unitario = 0;
                $insumo->clave_insumo_medico = $value[1];
                $insumo->tipo = "";
                $insumo->nombre = "";
                $insumo->unidad_medida = "";
                $insumo->cantidad_x_envase = 0;
                $insumo->es_causes = 0;
                $insumo->es_unidosis = 0;
                $insumo->descripcion = $value[2];
                if($value[1] !="")
                    if (array_key_exists((string)$value[1], $insumos)) {//Se verifica que exista el insumo en nuestro catalogo
                        $insumo->precio_unitario =$insumos[$value[1]]->precio_unitario;
                        $insumo->clave_insumo_medico =$insumos[$value[1]]->clave;
                        $insumo->tipo =$insumos[$value[1]]->tipo;
                        $insumo->nombre =$insumos[$value[1]]->nombre;
                        $insumo->unidad_medida =$insumos[$value[1]]->unidad_medida;
                        $insumo->cantidad_x_envase =$insumos[$value[1]]->cantidad_x_envase;
                        $insumo->es_causes =$insumos[$value[1]]->es_causes;
                        $insumo->es_unidosis =$insumos[$value[1]]->es_unidosis;
                        $insumo->descripcion =$insumos[$value[1]]->descripcion;
                    }else{
                        $insumo->clave_insumo_medico = null;
                    }/*else
                {
                    //$insumo->precio_unitario_base = 0;
                   
                    //$insumo->codigo_barras = "";
                    //$insumo->presentacion_nombre = "";
                }*/
                $date_now = new \DateTime("now");
                try{
                    $lotes = null;
                    $lotes = new \stdClass();
                    $lotes->no_lote = $value[3];
                    $date = new \DateTime(str_replace('/','-',$value[4]));
                    
                    $lotes->fecha = $date->format('Y-m-d');
                    $lotes->cantidad = $value[5];

                    /*if($date < $date_now)
                    {
                        $detalle_error .= "Error en Fecha <br>Artículo = ".$linea."<br>Programa:".$value[0]."<br>Clave Insumo: ".$value[1]."<br>Insumo: ".$value[2]."<br>Fecha: ".$value[4];
                        return Response::json([ 'data' => $detalle_error],500);
                    }*/
                }catch(\Exception $e)
                {
                    $detalle_error .= "Error en Fecha <br>Artículo = ".$linea."<br>Programa:".$value[0]."<br>Clave Insumo: ".$value[1]."<br>Insumo: ".$value[2]."<br>Fecha: ".$value[4];
                    return Response::json([ 'data' => $detalle_error],500);
                }

                /*if (array_key_exists($value[1], $insumos)) {//Se verifica que exista el insumo en nuestro catalogo
                    
                    
                }else{
                    $error++;
                    $detalle_error .= "Error insumo no existe<br>Línea: ".$linea."<br>programa:".$value[0]."<br>clave: ".$value[1]."<br>insumo: ".$value[2];
                    return Response::json([ 'data' => $detalle_error],500);
                }*/
                $index_programa = 0;
                $bandera_programa = 0;
                $index_catalogo_programa = 0;
                foreach($programa as $key_programa => $value_programa)//Se verifica que exista el programa en nuestro catalogo
                {
                    if($value_programa->nombre == $value[0])
                    {
                        $index_programa = $value_programa->id;
                        $bandera_programa = 1;
                        $index_catalogo_programa = $key_programa;
                    }
                }
                
                
                if($bandera_programa > 0) //En caso de encontrar el programa
                {
                    $bandera = 0;
                    
                    foreach($exportacion as $key_exportacion => $value_exportacion)// Se crea la estructura del programa. programa->insumos->lotes (cada uno como arreglo)
                    {
                        
                        if($exportacion[$key_exportacion]['programa']['id'] == $index_programa)
                        {
                            $bandera_duplicado = 0;
                            $index_duplicado = 0;
                            foreach($exportacion[$key_exportacion]['programa']['insumos'] as $key_validar => $value_validar)//Verifica si existe el programa dentro de nuestro arreglo para evitar duplicarlo
                            {
                                if($value_validar->clave_insumo_medico == $value[1] && $value_validar->descripcion == $value[2])
                                {
                                    $bandera_duplicado = 1;
                                    $index_duplicado = $key_validar;
                                }
                            }  
                            if($bandera_duplicado > 0)//En caso que exista
                            {
                                    $duplicado_lote = 0;
                                    $index_lote = 0;
                                    foreach($exportacion[$key_exportacion]['programa']['insumos'][$index_duplicado]->lote as $key_lotes => $value_lotes)// Se verifica que exista el insumos
                                    {
                                        if($value_lotes->no_lote == $lotes->no_lote && $value_lotes->fecha == $lotes->fecha)//Verifica que no sea el mismo lote y la misma fecha
                                        {
                                            $duplicado_lote = 1;
                                            $index_lote = $key_lotes;
                                        }
                                            
                                    }
                                    
                                    if($duplicado_lote == 0)//Si no existe, se agrega al arreglo sin pena :p
                                        $exportacion[$key_exportacion]['programa']['insumos'][$index_duplicado]->lote[] = $lotes;
                                    else//Si existe se agrega unicamente la cantidad
                                        $exportacion[$key_exportacion]['programa']['insumos'][$index_duplicado]->lote[$index_lote]->cantidad += $lotes->cantidad;
                                //
                                
                            }else//En caso que no exista se crea con la estructura antes mencionada
                            {
                                /*$nuevo_insumo = null;
                                $nuevo_insumo = new \stdClass();
                                $nuevo_insumo->precio_unitario_base =$insumos[$value[1]]->precio_unitario_base;
                                $nuevo_insumo->precio_unitario =$insumos[$value[1]]->precio_unitario;
                                $nuevo_insumo->clave =$insumos[$value[1]]->clave;
                                $nuevo_insumo->tipo =$insumos[$value[1]]->tipo;
                                $nuevo_insumo->nombre =$insumos[$value[1]]->nombre;
                                $nuevo_insumo->unidad_medida =$insumos[$value[1]]->unidad_medida;
                                $nuevo_insumo->cantidad_x_envase =$insumos[$value[1]]->cantidad_x_envase;
                                $nuevo_insumo->es_causes =$insumos[$value[1]]->es_causes;
                                $nuevo_insumo->es_unidosis =$insumos[$value[1]]->es_unidosis;
                                $nuevo_insumo->descripcion =$insumos[$value[1]]->descripcion;
                                $nuevo_insumo->codigo_barras =$insumos[$value[1]]->codigo_barras;
                                $nuevo_insumo->presentacion_nombre =$insumos[$value[1]]->presentacion_nombre;
                                $nuevo_insumo->lote[] = $lotes;*/
                                $insumo->lote[] = $lotes;
                                $exportacion[$key_exportacion]['programa']['insumos'][] = $insumo;
                            }
                                
                                
                            
                            $bandera = 1;
                            
                        }
                        
                    }
                
                    if($bandera == 0)//En caso que sea el primer programa se crea una estructura inicial :)
                    {
                        $index_nuevo = count($exportacion);
                        $exportacion[$index_nuevo]['programa']['id'] = $programa[$index_catalogo_programa]->id;
                        $exportacion[$index_nuevo]['programa']['clave'] = $programa[$index_catalogo_programa]->clave;
                        
                        $exportacion[$index_nuevo]['programa']['nombre'] = $programa[$index_catalogo_programa]->nombre;
                        
                        /*$nuevo_insumo = null;
                        $nuevo_insumo = new \stdClass();
                        $nuevo_insumo->precio_unitario_base =$insumos[$value[1]]->precio_unitario_base;
                        $nuevo_insumo->precio_unitario =$insumos[$value[1]]->precio_unitario;
                        $nuevo_insumo->clave =$insumos[$value[1]]->clave;
                        $nuevo_insumo->tipo =$insumos[$value[1]]->tipo;
                        $nuevo_insumo->nombre =$insumos[$value[1]]->nombre;
                        $nuevo_insumo->unidad_medida =$insumos[$value[1]]->unidad_medida;
                        $nuevo_insumo->cantidad_x_envase =$insumos[$value[1]]->cantidad_x_envase;
                        $nuevo_insumo->es_causes =$insumos[$value[1]]->es_causes;
                        $nuevo_insumo->es_unidosis =$insumos[$value[1]]->es_unidosis;
                        $nuevo_insumo->descripcion =$insumos[$value[1]]->descripcion;
                        $nuevo_insumo->codigo_barras =$insumos[$value[1]]->codigo_barras;
                        $nuevo_insumo->presentacion_nombre =$insumos[$value[1]]->presentacion_nombre;
                        $nuevo_insumo->lote[] = $lotes;*/
                        $insumo->lote[] = $lotes;
                        
                        $exportacion[$index_nuevo]['programa']['insumos'][] = $insumo;
                        
                    }
                    
                    
                }else{//En caso que no exista el programa... rayos caen muchos aca
                    $bandera_sin_programa = 0;
                    $index_sin_programa = 0;
                    $bandera = 0;
                    foreach($sin_programa as $key_sin_programa => $value_sin_programa)
                    {
                        if($sin_programa[$key_sin_programa]['programa']['nombre'] == $value[0])//verificamos si no se encuentra en el arreglo de los no encontrados (programas)
                        {
                            $bandera_duplicado = 0;
                            $index_duplicado = 0;
                            foreach($sin_programa[$key_sin_programa]['programa']['insumos'] as $key_validar => $value_validar)//Verificamos si existe el insumo
                            {
                               
                                if($value_validar->clave_insumo_medico == $value[1]  && $value_validar->descripcion == $value[2])
                                {
                                    $bandera_duplicado = 1;
                                    $index_duplicado = $key_validar;
                                }
                            } 
                            
                            if($bandera_duplicado > 0)
                            {
                                $duplicado_lote = 0;
                                $index_lote = 0;
                                foreach($sin_programa[$key_sin_programa]['programa']['insumos'][$index_duplicado]->lote as $key_lotes => $value_lotes)//verificamos si existe el lote
                                {
                                    if($value_lotes->no_lote == $lotes->no_lote && $value_lotes->fecha == $lotes->fecha)
                                    {
                                        $duplicado_lote = 1;
                                        $index_lote = $key_lotes;
                                    }
                                        
                                }
                                
                                if($duplicado_lote == 0)//si no se encuentra el lote se crea sin mas
                                    $sin_programa[$key_sin_programa]['programa']['insumos'][$index_duplicado]->lote[] = $lotes;
                                else//si se encuentra el lote se agrega nada mas la cantidad
                                    $sin_programa[$key_sin_programa]['programa']['insumos'][$index_duplicado]->lote[$index_lote]->cantidad += $lotes->cantidad;
                            //
                            
                            }else//Si no se encuentra el programa se crea el programa en el arreglo
                            {
                                /*$nuevo_insumo = null;
                                $nuevo_insumo = new \stdClass();
                                //$nuevo_insumo->precio_unitario_base =$insumos[$value[1]]->precio_unitario_base;
                                $nuevo_insumo->precio_unitario =$insumos[$value[1]]->precio_unitario;
                                $nuevo_insumo->clave_insumo_medico =$insumos[$value[1]]->clave;
                                $nuevo_insumo->tipo =$insumos[$value[1]]->tipo;
                                $nuevo_insumo->nombre =$insumos[$value[1]]->nombre;
                                $nuevo_insumo->unidad_medida =$insumos[$value[1]]->unidad_medida;
                                $nuevo_insumo->cantidad_x_envase =$insumos[$value[1]]->cantidad_x_envase;
                                $nuevo_insumo->es_causes =$insumos[$value[1]]->es_causes;
                                $nuevo_insumo->es_unidosis =$insumos[$value[1]]->es_unidosis;
                                $nuevo_insumo->descripcion =$insumos[$value[1]]->descripcion;
                                $nuevo_insumo->subtotal = '';
                                //$nuevo_insumo->codigo_barras =$insumos[$value[1]]->codigo_barras;
                                //$nuevo_insumo->presentacion_nombre =$insumos[$value[1]]->presentacion_nombre;
                                $nuevo_insumo->lote[] = $lotes;*/
                                $insumo->lote[] = $lotes;
                                $sin_programa[$key_sin_programa]['programa']['insumos'][] = $insumo;
                            }
                                
                            
                            $bandera++;
                        }
                    
                    }
                    if($bandera == 0)//Si es el primer programa se crea el programa en el arreglo
                    {
                        $nuevo = count($sin_programa);
                        $sin_programa[$nuevo]['programa']['id'] = 0;
                        $sin_programa[$nuevo]['programa']['nombre'] = $value[0];
                        $sin_programa[$nuevo]['programa']['clave'] = "";
                        /*$nuevo_insumo = null;
                        $nuevo_insumo = new \stdClass();
                        //$nuevo_insumo->precio_unitario_base =$insumos[$value[1]]->precio_unitario_base;
                        $nuevo_insumo->precio_unitario =$insumos[$value[1]]->precio_unitario;
                        $nuevo_insumo->clave_insumo_medico =$insumos[$value[1]]->clave;
                        $nuevo_insumo->tipo =$insumos[$value[1]]->tipo;
                        $nuevo_insumo->nombre =$insumos[$value[1]]->nombre;
                        $nuevo_insumo->unidad_medida =$insumos[$value[1]]->unidad_medida;
                        $nuevo_insumo->cantidad_x_envase =$insumos[$value[1]]->cantidad_x_envase;
                        $nuevo_insumo->es_causes =$insumos[$value[1]]->es_causes;
                        $nuevo_insumo->es_unidosis =$insumos[$value[1]]->es_unidosis;
                        $nuevo_insumo->descripcion =$insumos[$value[1]]->descripcion;
                        $nuevo_insumo->subtotal = "";
                        //$nuevo_insumo->codigo_barras =$insumos[$value[1]]->codigo_barras;
                        //$nuevo_insumo->presentacion_nombre =$insumos[$value[1]]->presentacion_nombre;
                        $nuevo_insumo->lote[] = $lotes;*/
                        $insumo->lote[] = $lotes;

                        $sin_programa[$nuevo]['programa']['insumos'][] = $insumo;
                        //$sin_programa[$nuevo]['programa']['insumos'][0]->lote[] = $lotes;
                    }
                
                }
                $linea++;
            }
        }catch(\Exception $e)
        {
            $detalle_error .= "Error ". $e;
            return Response::json([ 'data' => $detalle_error],500);
        }
        /*if(count($sin_programa) > 0)
        {
            $lista_programas = "";
            foreach($sin_programa as $key_programa => $value_programa)
            {
                $lista_programas .= $sin_programa[$key_programa]['programa']['nombre']."<br>";
            }
            return Response::json([ 'data' => "Error, programas no registrados: <br>".$lista_programas],500);
        }*/
        
        return Response::json([ 'data' => ["insumos"=>$exportacion, "sin_programa"=>$sin_programa, "programas"=>$programa, "insumos_medicamentos"=>$insumos]],200);
        
    }

    public function descargarFormato(Request $request){

        Excel::create("Formato de carga de Inventario Inicial SIAL", function($excel) {


            $excel->sheet('Med. y Mat. de Curacion', function($sheet)  {
                $sheet->setAutoSize(true);
                $sheet->row(1, array(
                    'Programa',
                    'Clave',
                    'Descripcion',
                    'Lote',
                    'Fecha Caducidad',
                    'Cantidad'
                ));
                $sheet->cells("A1:F1", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                });

                $sheet->appendRow(array(
                    'Inicializacion Inventario',
                    "010.000.0104.00 (EJEMPLO)",
                    'PARACETAMOL Tableta 500 mg 10 tabletas',
                    'L201801',
                    '01/01/2020',
                    '10'
                )); 

                //$sheet->setAutoFilter('A1:F1');
            });

            $excel->setActiveSheetIndex(0);
         })->export('xls');
    }

    public function descargarInsumosSinClave()
    {
        $parametros = Input::only('insumos');
        $objeto = json_decode($parametros['insumos']);
        //return Response::json([ 'data' => $objeto->insumos],500);
        Excel::create("Insumos S/Clave Inventario Inicial SIAL", function($excel) use($objeto){


            $excel->sheet('Insumos', function($sheet) use ($objeto)  {
                $sheet->setAutoSize(true);
                $sheet->row(1, array(
                    'Programa',
                    'Clave',
                    'Descripcion',
                    'Lote',
                    'Fecha Caducidad',
                    'Cantidad'
                ));
                $sheet->cells("A1:F1", function($cells) {
                    $cells->setAlignment('center');
                });
                $sheet->row(1, function($row) {
                    $row->setBackground('#DDDDDD');
                    $row->setFontWeight('bold');
                });

                foreach ($objeto->insumos as $key => $item) {
                    $sheet->appendRow(array(
                        $item->programa,
                        $item->clave_insumo_medico,
                        $item->descripcion,
                        $item->lote,
                        $item->fecha,
                        $item->cantidad                  
                    )); 
                }
                
            });

            $excel->setActiveSheetIndex(0);
         })->export('xls');
    }

////**************************************************************************************************************************************************
///****************************************************************************************************************************************************
}
