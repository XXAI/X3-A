<?php 
namespace App\Models\AlmacenGeneral;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use \DB;

/**
* Modelo Resguardos
* 
* @package    Plataforma API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20
*
* Modelo `Resguardos`: Manejo de los grupos de usuario
*
*/
class Resguardos extends BaseModel {

	use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;

    protected $table = 'movimientos';

	public function ResguardosArticulos(){
        return $this->hasMany('App\Models\AlmacenGeneral\ResguardosArticulos','movimiento_id')
        ->with("Articulos");
    }

    public function Usuarios(){
        return $this->belongsTo('App\Models\Usuario','usuario_id','id');
    }

    public function PersonaClues(){
        return $this->belongsTo('App\Models\PersonalClues','personal_clues_id','id');
    }
}