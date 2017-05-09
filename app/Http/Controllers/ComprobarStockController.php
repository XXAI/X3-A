<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Stock;


use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response;

class ComprobarStockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $parametros = Input::only('almacen','clave');

        $stocks = array();                        
        $stocks = Stock::where('clave_insumo_medico',$parametros['clave'])->where('existencia','>',0)->where('almacen_id',$parametros['almacen'])->orderBy('fecha_caducidad','ASC')->get();
        $existencia = 0;
        $existencia_unidosis = 0;

        foreach($stocks as $stock)
        {
            $existencia += $stock->existencia;
            $existencia_unidosis += $stock->existencia_unidosis;
        }               

        $objeto_response = array('almacen_id' => $parametros['almacen'],
                                        'clave' => $parametros['clave'],
                                        'existencia' => $existencia,
                                        'existencia_unidosis' => $existencia_unidosis,
                                        'data' => $stocks,
                                        'status' => 200,
                                        'messages' => 'Operación realizada con exito',
                                        'total' => count($stocks));

        return Response::json($objeto_response,HttpResponse::HTTP_OK);
        //return Response::json(array("status" => 200,"messages" => "Operación realizada con exito", "data" => $data, "total" => count($data)), 200);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
