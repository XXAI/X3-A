<?php namespace App\Models;

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventarioMetadato extends BaseModel {

	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $primaryKey = 'id';
    
    protected $table = 'inventario_metadatos'; 

	public function Inventario(){
		return $this->belongsTo('App\Models\Inventario','inventario_id','id');
    }

}
