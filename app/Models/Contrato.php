<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrato extends BaseModel{
    
    use SoftDeletes;
    
    protected $generarID = false;
    protected $guardarIDServidor = false;
    //protected $guardarIDUsuario = false;
    //public $incrementing = false;
    
    protected $table = 'contratos';  
    //protected $primaryKey = 'clave';
    protected $fillable = ["id","monto_minimo","monto_maximo","fecha_inicio","fecha_fin","activo"];
    
    public function proveedores(){
        return $this->belongsToMany('App\Models\Proveedor', 'contrato_proveedor', 'contrato_id', 'proveedor_id');
    }
}