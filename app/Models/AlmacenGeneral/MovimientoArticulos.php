<?php 
namespace App\Models\AlmacenGeneral;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use \DB;

/**
* Modelo MovimientoArticulos
* 
* @package    Plataforma API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20
*
* Modelo `MovimientoArticulos`: Manejo de los grupos de usuario
*
*/
class MovimientoArticulos extends BaseModel {

	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimiento_articulos';

    public function Movimiento(){
		return $this->belongsTo('App\Models\AlmacenGeneral\Movimiento','movimiento_id','id');
    }

    public function Articulos(){
		return $this->belongsTo('App\Models\Articulos','articulo_id','id');
    }

    public function Inventarios(){
        return $this->belongsToMany('App\Models\AlmacenGeneral\InventarioArticulo', 'inventario_movimiento_articulos', 'movimiento_articulos_id', 'inventario_id')->with("InventarioMetadato");
    }
}