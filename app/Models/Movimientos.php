<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movimientos extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimientos';
    
    public function roles(){
		return $this->belongsToMany('App\Models\Rol', 'permiso_rol', 'permiso_id', 'rol_id');
	}

 

     public function MovimientoInsumos(){
        return $this->hasMany('App\Models\MovimientoInsumos','movimiento_id');
    }

    public function Almacen(){
        return $this->hasOne('App\Models\Almacen','id','almacen_id');
    }
 

}