<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends BaseModel
{
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $fillable = ["id","nombre","nombre_corto","rfc","direccion","ciudad","contacto","cargo_contacto","telefono","celular","email","activo"];
    protected $table = 'proveedores'; 

    public function contratos(){
        return $this->belongsToMany('App\Models\Contrato', 'contrato_proveedor', 'proveedor_id', 'contrato_id');
    }

    public function contratoActivo(){
        return $this->belongsToMany('App\Models\Contrato', 'contrato_proveedor', 'proveedor_id', 'contrato_id')->where('contratos.activo','=',1);
    }

}

