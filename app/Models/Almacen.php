<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Almacen extends BaseModel
{
    //
  	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $fillable = ["created_at","updated_at"];
    protected $table = 'almacenes';  
    
    public function usuarios(){
      return $this->hasMany('App\Models\AlmacenUsuarios','almacen_id');
    }

    public function tiposMovimientos(){
      return $this->hasMany('App\Models\AlmacenTiposMovimientos','almacen_id')->with('TipoMovimiento');
    }

    public function unidadMedica(){
      return $this->belongsTo('App\Models\UnidadMedica','clues');
    }
   
}
