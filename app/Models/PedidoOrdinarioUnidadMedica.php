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
        "causes_comprometido",
        "causes_devengado",
        "causes_disponible",
        "causes_capturado",
        "no_causes_autorizado",
        "no_causes_modificado",
        "no_causes_comprometido",
        "no_causes_devengado",
        "no_causes_disponible",
        "no_causes_capturado",
    ];
    
    protected $casts = [
        "pedido_id"=>"string",
        "pedido_ordinario_id"=>"integer",
        "clues"=>"string",        
        "causes_autorizado"=>"double",
        "causes_modificado"=>"double",
        "causes_comprometido"=>"double",
        "causes_devengado"=>"double",
        "causes_disponible"=>"double",
        "causes_capturado"=>"double",
        "no_causes_autorizado"=>"double",
        "no_causes_modificado"=>"double",
        "no_causes_comprometido"=>"double",
        "no_causes_devengado"=>"double",
        "no_causes_disponible"=>"double",
        "no_causes_capturado"=>"double",
    ];

    protected $table = 'pedidos_ordinarios_unidades_medicas';

    public function unidadMedica(){
        return $this->belongsTo('App\Models\UnidadMedica','clues','clues');
    }

    public function pedidoOrdinario(){
        return $this->belongsTo('App\Models\PedidoOrdinario','pedido_ordinario_id','id');
    }

    public function pedido(){
        return $this->hasOne('App\Models\Pedido','id','pedido_id');
    }
}
