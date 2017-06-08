<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;

use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response, DB;


use App\Models\CluesTurno;
use App\Models\UnidadMedica;


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
class DumpRecetaController extends Controller
{
     
     
///***************************************************************************************************************************
///***************************************************************************************************************************
   
    public function index(Request $request)
    {
         $clues = $request->get('clues');

        if(!$clues){
            return Response::json(array("status" => 404,"messages" => "Debe especificar una Unidad Médica."), 404);
        }      
 
        $data = UnidadMedica::where('clues',$clues)->first();
        if(!$data){
            return Response::json(array("status" => 404,"messages" => "No se encuentra la Unidad médica"), 200);
        } 
        else{
              
            $data->clues_turnos = DB::table("clues_turnos")
            ->join('turnos', 'turnos.id', '=' , 'clues_turnos.turno_id')
            ->where("clues", $clues)
            ->where('clues_servicios.deleted_at',NULL)
            ->get();
            return Response::json(array("status" => 200,"messages" => "Operación realizada con exito","data" => $data), 200);
        }  
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
 

    public function update(Request $request, $id)
    {
 
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
      
    public function destroy($id)
    {
        
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
   
	 

}
