<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class PedidoOrdinarioUnidadMedica extends BaseModel
{
    //use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;
    public $incrementing = true;
    protected $fillable = [ 
        "pedido_id",
        "pedido_ordinario_id",
        "clues", 
        "causes_autorizado", 
        "causes_modificado",
        "no_causes_autorizado",
        "no_causes_modificado"
    ];
    
    protected $casts = [
        "pedido_id"=>"integer",
        "pedido_ordinario_id"=>"integer",
        "clues"=>"string",        
        "causes_autorizado"=>"double",
        "causes_modificado"=>"double",
        "no_causes_autorizado"=>"double",
        "no_causes_modificado"=>"double",
        "no_causes_comprometido"=>"double"
    ];

    protected $table = 'pedidos_ordinarios_unidades_medicas';

    public function unidadMedica(){
        return $this->belongsTo('App\Models\UnidadMedica','clues','clues');
    }
}
