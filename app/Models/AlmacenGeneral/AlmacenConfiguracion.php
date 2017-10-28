<?php 
namespace App\Models\AlmacenGeneral;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
* Modelo AlmacenConfiguracion
* 
* @package    Plataforma API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20
*
* Modelo `AlmacenConfiguracion`: Configuracion general del sistema
*
*/
class AlmacenConfiguracion extends Model {	
	protected $table = 'almacen_configuracion';
}