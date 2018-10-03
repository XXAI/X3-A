<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class PresupuestoMovimientoEjercicio extends BaseModel
{
    //use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;
    public $incrementing = true;
     
    protected $fillable = [ "presupuesto_id", "causes_saldo_anterior", "causes_cargo", "causes_abono", "causes_saldo","no_causes_saldo_anterior", "no_causes_cargo", "no_causes_abono", "no_causes_saldo"];
    protected $casts = [
        "presupuesto_id"=>"integer",
        "causes_saldo_anterior"=>"double",
        "causes_cargo"=>"double",
        "causes_abono"=>"double",
        "causes_saldo"=>"double",
        "no_causes_saldo_anterior"=>"double",
        "no_causes_cargo"=>"double",
        "no_causes_abono"=>"double",
        "no_causes_saldo"=>"double"
    ];
    protected $table = 'presupuesto_movimiento_ejercicio';


    /*
    public function presupuestoUnidadesMedicas(){
        return $this->hasMany('App\Models\PresupuestoUnidadMedica','presupuesto_id');
    }*/
    
}
