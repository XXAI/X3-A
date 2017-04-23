<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovimientoPedido extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimiento_pedido';

    protected $fillable = ["movimiento_id","pedido_id","recibe", "entrega"];

    public function movimiento(){
        return $this->belongsTo('App\Models\Movimiento','movimiento_id');
    }
    public function pedido(){
        return $this->belongsTo('App\Models\Pedido','pedido_id');
    }
 

}