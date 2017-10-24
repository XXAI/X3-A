<?php 
namespace App\Models\AlmacenGeneral;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use \DB;

/**
* Modelo ResguardosArticulos
* 
* @package    Plataforma API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20
*
* Modelo `ResguardosArticulos`: Manejo de los grupos de usuario
*
*/
class ResguardosArticulos extends BaseModel {

	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'resguardos_articulos';

    public function Resguardos(){
		return $this->belongsTo('App\Models\AlmacenGeneral\Resguardos','movimiento_id','id');
    }

    public function Articulos(){
		return $this->belongsTo('App\Models\Articulos','articulo_id','id');
    }

    public function CondicionesArticulos(){
        return $this->belongsTo('App\Models\CondicionesArticulos','condiciones_articulos_id','id');
    }
}