<?php 
namespace App\Models\AlmacenGeneral;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use \DB;

/**
* Modelo Movimiento
* 
* @package    Plataforma API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20
*
* Modelo `Movimiento`: Manejo de los grupos de usuario
*
*/
class Movimiento extends BaseModel {

	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimientos';

	public function MovimientoArticulos(){
        return $this->hasMany('App\Models\AlmacenGeneral\MovimientoArticulos','movimiento_id')
        ->with("Articulos","Inventarios");
    }


    public function TipoMovimiento(){
		return $this->belongsTo('App\Models\TiposMovimientos','tipo_movimiento_id','id');
    }

    public function Usuario(){
        return $this->belongsTo('App\Models\Usuario','usuario_id','id');
    }

    public function Almacen(){
        return $this->belongsTo('App\Models\Almacen','almacen_id','id');
    }
}