<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class PedidoInsumo extends BaseModel
{
    //use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $fillable = [ "pedido_id", "insumo_medico_clave", "cantidad_calculada_sistema", "cantidad_solicitada_um", "cantidad_ajustada_js", "cantidad_ajustada_ca", "created_at","updated_at"];
    protected $table = 'pedidos_insumos';
}
