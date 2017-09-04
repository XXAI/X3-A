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

    public function pedidoAlterno(){
        return $this->hasMany('App\Models\PedidoAlterno','pedido_id', 'id');
    }

    public function recepciones(){
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id');
    }

    public function recepcionesBorrados(){
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id')->withTrashed()->orderBy("created_at");
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

    public function unidadMedica(){
        return $this->belongsTo('App\Models\UnidadMedica','clues','clues');
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

    public function presupuestoApartado(){
      return $this->hasOne('App\Models\PedidoPresupuestoApartado','pedido_id');
    }

    public function logPedidoCancelado()
    {
        return $this->hasOne('App\Models\LogPedidoCancelado', "pedido_id");
    }
}
