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

    protected $fillable = [ 'tipo_insumo_id', 'tipo_pedido_id', 'clues','pedido_padre', 'folio', 'fecha', 'fecha_concluido', 'fecha_expiracion','descripcion','observaciones', 'almacen_solicitante', 'almacen_proveedor', 'organismo_dirigido', 'acta_id', 'status', 'usuario_validacion', 'proveedor_id', "created_at","updated_at"];
    
    public function insumos(){
        return $this->hasMany('App\Models\PedidoInsumo','pedido_id','id');
    }

    public function recepciones(){
        //return $this->hasManyThrough('App\Models\Movimiento','App\Models\MovimientoPedido');
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id');
    }

    public function director(){
        return $this->hasOne('App\Models\PersonalClues','id','director_id');
    }

    public function encargadoAlmacen(){
      return $this->hasOne('App\Models\PersonalClues','id','encargado_almacen_id');
    }
    
    public function acta(){
        return $this->belongsTo('App\Models\Acta','acta_id','id');
    }

    public function tipoInsumo(){
        return $this->belongsTo('App\Models\TipoInsumo','tipo_insumo_id','id');        
    }

    public function tipoPedido(){
        return $this->belongsTo('App\Models\TipoPedido','tipo_pedido_id','id');        
    }

    public function almacenSolicitante(){
        return $this->belongsTo('App\Models\Almacen','almacen_solicitante','id'); 
    }

    public function almacenProveedor(){
        return $this->belongsTo('App\Models\Almacen','almacen_proveedor','id'); 
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Proveedor','proveedor_id','id');
    }
}
