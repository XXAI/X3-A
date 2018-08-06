<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratoPrecio extends BaseModel{
    
    use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;

    protected $table = 'contratos_precios';
    public $incrementing = true;
    
    protected $fillable = ["id","proveedor_id","contrato_id","tipo_insumo_id","contrato_pedido_id","lote","insumo_medico_clave","precio"];
 

}