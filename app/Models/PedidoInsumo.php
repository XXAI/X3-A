<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class PedidoInsumo extends BaseModel
{
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $fillable = [ "pedido_id", "insumo_medico_clave", "cantidad", "cantidad_solicitada", "cantidad_recibida","precio_unitario","monto","monto_solicitado","monto_recibido", "created_at","updated_at"];
    protected $table = 'pedidos_insumos';

    public function insumosConDescripcion(){
        return $this->hasOne('App\Models\Insumo','clave','insumo_medico_clave')->conDescripciones();        
    }
}
