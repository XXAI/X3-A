<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoOrdinario extends BaseModel
{
    use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;
    public $incrementing = true;

    protected $table = 'pedidos_ordinarios';
    
    protected $fillable = [ 'descripcion', 'fecha', 'fecha_expiracion'];
    protected $casts = ["descripcion"=>"string","fecha"=>"date", "fecha_expiracion"=>"datetime"];

    public function pedidoOrdinarioUnidadesMedicas(){
        return $this->hasMany('App\Models\PedidoOrdinarioUnidadMedica','pedido_ordinario_id');
    }
    /*
    
    public function insumos(){
        return $this->hasMany('App\Models\PedidoInsumo','pedido_id','id')->with('infoInsumo');
    }

    public function pedidoAlterno(){
        return $this->hasMany('App\Models\PedidoAlterno','pedido_id', 'id');
    }

    public function recepciones(){
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id');
    }

    public function movimientos(){
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id');
    }

    public function historialTransferenciaCompleto(){
        return $this->hasMany('App\Models\HistorialMovimientoTransferencia','pedido_id')->movimientoConStatus()->with('movimiento.insumos')->orderBy('created_at','ASC');
    }

    public function movimientosTransferenciasCompleto(){
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id')->movimientoCompleto()->whereIn('tipo_movimiento_id',[3,9,1,7])->with('insumos');
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

    public function presupuesto(){
        return $this->belongsTo('App\Models\Presupuesto','presupuesto_id','id');
    }

    public function presupuestoApartado(){
      return $this->hasOne('App\Models\PedidoPresupuestoApartado','pedido_id');
    }

    public function logPedidoCancelado()
    {
        return $this->hasOne('App\Models\LogPedidoCancelado', "pedido_id");
    }

    public function metadatosSincronizaciones(){
        return $this->hasOne('App\Models\PedidoMetadatoSincronizacion','pedido_id','id');
    } 
    public function metadatoCompraConsolidada(){
        return $this->hasOne('App\Models\PedidoMetadatoCC','pedido_id','id')->with('programa');
    } 
    // para apertura de pedidos desde DAM de compra consolidada
    public function unidadesMedicas(){
        return $this->hasMany('App\Models\PedidoCcClues','pedido_id')->with('unidadMedica');
    }*/
}
