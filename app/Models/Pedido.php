<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends BaseModel
{
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $table = 'pedidos';

    protected $fillable = [ 'tipo_insumo_id', 'tipo_pedido_id', 'pedido_padre', 'folio', 'almacen_solicitante', 'almacen_proveedor', 'organismo_dirigido', 'acta_id', 'status', 'usuario_validacion', 'proveedor_id', "created_at","updated_at"];
    
   public function insumos(){
        return $this->hasMany('App\Models\PedidosInsumo','pedido_id','id');        
    }

    public function acta(){
        return $this->belongsTo('App\Models\Acta','acta_id','id');        
    }

    public function TipoInsumo(){
        return $this->belongsTo('App\Models\TipoInsumo','tipo_insumo_id','id');        
    }

    public function TipoPedido(){
        return $this->belongsTo('App\Models\TipoPedido','tipo_pedido_id','id');        
    }
}
