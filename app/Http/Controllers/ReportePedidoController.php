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
use App\Models\MovimientoMetadato;
use App\Models\Receta;
use App\Models\RecetaDetalle;
use App\Models\RecetaMovimiento;


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
class ReportePedidoController extends Controller
{
     
    public function graficaEntregas(Request $request)
    {
        return Response::json(array("status" => 200,"messages" => "Grafica Entregas"), 200);
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
   public function estatusEntregaPedidos(Request $request)
    {
        return Response::json(array("status" => 200,"messages" => "Estatus Entrega Pedidos"), 200);
    }
///***************************************************************************************************************************
///***************************************************************************************************************************
   
    public function index(Request $request)
    {
        
    }

 ///***************************************************************************************************************************
///***************************************************************************************************************************
 
 public function store(Request $request)
    {
        
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
