<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovimientoInsumos extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimiento_insumos';

    protected $fillable = ["movimiento_id","stock_id", "cantidad", "precio_unitario", "iva", "precio_total"];

    
    public function roles(){
		return $this->belongsToMany('App\Models\Rol', 'permiso_rol', 'permiso_id', 'rol_id');
	}

     public function Stock(){
        return $this->belongsTo('App\Models\Stock','stock_id');
    }
 

}