<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Stock;


use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $parametros = Input::only('q','clave');

       if (isset($parametros['q']) && $parametros['q'] != "") {
            $stock =  Stock::with("insumo","almacen", "marca")->where(function($query) use ($parametros) {
                 $query->where('codigo_barras','=',$parametros['q'])
                 ->orWhere('lote','=',$parametros['q']);
             });
        } else {
             $stock = Stock::with("insumo","almacen", "marca");
        }


        if (isset($parametros['clave'])){
            $stock = $stock->where("clave_insumo_medico",'LIKE','%'.$parametros['clave'].'%');
        }
        $stock = $stock->where("stock.existencia",">","0")->get();
        

        return Response::json([ 'data' => $stock],200);
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
