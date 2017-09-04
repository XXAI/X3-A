<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class PedidoAlterno extends BaseModel
{
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = false;
    protected $fillable = [ 
		    "pedido_id",
		    "pedido_original_id",
		    "folio", 
		    "nombre_firma_1",
		    "cargo_firma_1", 
		    "nombre_firma_2",
		    "cargo_firma_2",
		    "usuario_valido_id",
		    "usuario_asigno_proveedor_id",
		    "fecha_validacion",
		    "fecha_asignacion_proveedor"
	];
    protected $table = 'pedidos_alternos';

    

    public function pedido(){
        return $this->belongsTo('App\Models\Pedido','pedido_id');
    }
    public function pedidoOriginal(){
		return $this->belongsTo('App\Models\Pedido','pedido_original_id');
	}

    

}
