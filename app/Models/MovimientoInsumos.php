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

    protected $fillable = ["movimiento_id","stock_id", "cantidad", "precio_unitario", "iva", "precio_total", "tipo_insumo_id", "clave_insumo_medico"];

 

    public function stock(){
        return $this->belongsTo('App\Models\Stock','stock_id');
    }

    public function stockGrupo(){
        return $this->belongsTo('App\Models\Stock','stock_id')->groupBy('clave_insumo_medico');
    }
 

}