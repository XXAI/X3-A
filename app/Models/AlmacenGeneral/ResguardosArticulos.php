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

    protected $table = 'resguardo_articulos';

    public function Resguardos(){
		return $this->belongsTo('App\Models\AlmacenGeneral\Resguardos','resguardos_id','id');
    }

    public function Inventarios(){
		return $this->belongsTo('App\Models\AlmacenGeneral\Inventario','inventario_id','id')->with("Articulo","MovimientoArticulo");
    }

    public function InventarioMetadatoUnico(){
        return $this->hasmany('App\Models\AlmacenGeneral\InventarioMetadato','inventario_id','id');
    }

    public function CondicionesArticulos(){
        return $this->belongsTo('App\Models\CondicionesArticulos','condiciones_articulos_id','id');
    }
}