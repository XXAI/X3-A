<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Almacenes extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    
    public function roles(){
		return $this->belongsToMany('App\Models\Rol', 'permiso_rol', 'permiso_id', 'rol_id');
	}

     public function AlmacenUsuarios(){
        return $this->hasMany('App\Models\AlmacenUsuarios','almacen_id');
    }

    public function AlmacenTiposMovimientos(){
        return $this->hasMany('App\Models\AlmacenTiposMovimientos','almacen_id')->with('TipoMovimiento');
    }
     
}