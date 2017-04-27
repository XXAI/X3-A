<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movimiento extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimientos';
    protected $fillable = ['almacen_id','tipo_movimiento_id','fecha_movimiento','observaciones'];
    

 
    // Porque esta en mayusculas deberia ser movimientosInsumos() !!!! los odio a todos!! AKira
    public function movimientoInsumos(){
        return $this->hasMany('App\Models\MovimientoInsumos','movimiento_id')->with('stock.marca');
    }

    public function movimientoPedido(){
        return $this->hasOne('App\Models\MovimientoPedido','movimiento_id');
    }

    public function almacen(){
        return $this->hasOne('App\Models\Almacen','id','almacen_id');
    }
 

}