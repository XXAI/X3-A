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
class ResguardosArticulosDevoluciones extends BaseModel {

	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'resguardos_articulos_devoluciones';

    public function ResguardosArticulos(){
		return $this->belongsTo('App\Models\AlmacenGeneral\ResguardosArticulos','resguardos_articulos_id','id');
    }

}