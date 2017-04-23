<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialCuracion extends BaseModel{
    
    use SoftDeletes;
    
    protected $generarID = false;
    protected $guardarIDServidor = false;
    //protected $guardarIDUsuario = false;
    //public $incrementing = false;
    
    protected $table = 'material_curacion';  
    protected $primaryKey = 'insumo_medico_clave';
    protected $fillable = ["id","insumo_medico_clave","nombre_generico_especifico","funcion","cantidad_x_envase","unidad_medida_id"];

   
}