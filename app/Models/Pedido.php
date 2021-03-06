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
    protected $fillable = [ 'tipo_insumo_id', 'tipo_pedido_id', 'clues','pedido_padre', 'folio', 'fecha', 'fecha_concluido', 'fecha_expiracion','descripcion','observaciones', 'almacen_solicitante', 'almacen_proveedor', 'organismo_dirigido', 'acta_id', 'recepcion_permitida','status', 'usuario_validacion', 'proveedor_id','programa_id', 'presupuesto_id','presupuesto_ejercicio_id','clues_destino', "created_at","updated_at"];
    
    public function pedidoOrdinarioUnidadMedica(){
        return $this->hasOne('App\Models\PedidoOrdinarioUnidadMedica','pedido_id','id')->with('pedidoOrdinario');
    }

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

    public function movimientosRecepcionCompleto(){
        return $this->hasMany('App\Models\MovimientoPedido','pedido_id','id')->MovimientoCompletoEntrada()->where('status','FI')->with('insumos');
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

    public function unidadMedicaDestino(){
        return $this->belongsTo('App\Models\UnidadMedica','clues_destino','clues');
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

    public function programa(){
        return $this->hasOne('App\Models\Programa','programa_id');
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
    }

    public function usuario(){
        return $this->hasOne('App\Models\Usuario', "id", "usuario_id");
    }

    //Villa: Busqueda de insumos dentro del pedido
    public function scopeBuscarInsumoPedido($query, $insumo = ""){
        $query->join("movimiento_pedido", "movimiento_pedido.pedido_id", "=", "pedidos.id")
                ->join("movimiento_insumos", "movimiento_insumos.movimiento_id", "=", "movimiento_pedido.movimiento_id")
                ->join("insumos_medicos", "insumos_medicos.clave", "=", "movimiento_insumos.clave_insumo_medico")
                ->groupBy("pedidos.id")
                ->where(function($query) use ($insumo) {
                    $query->where('movimiento_insumos.clave_insumo_medico','LIKE',"%".$insumo."%")->orWhere('insumos_medicos.descripcion','LIKE',"%".$insumo."%")
                    ->orWhere('pedidos.id','LIKE',"%".$insumo."%")->orWhere('pedidos.descripcion','LIKE',"%".$insumo."%")->orWhere('pedidos.folio','LIKE',"%".$insumo."%");
        });
            
    }

}
