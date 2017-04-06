<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedores extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;
    
    public function roles(){
		return $this->belongsToMany('App\Models\Rol', 'permiso_rol', 'permiso_id', 'rol_id');
	}

  public function Contactos(){
        return $this->hasMany('App\Models\Contactos','proveedor_id');
    }
    public function ComunicacionContacto(){
        return $this->hasMany('App\Models\ComunicacionContacto','proveedor_id');
    }
   

}