<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class Insumo extends BaseModel{
    
    use SoftDeletes;
    
    protected $generarID = false;
    protected $guardarIDServidor = false;
    //protected $guardarIDUsuario = false;
    //public $incrementing = false;
    
    protected $table = 'insumos_medicos';  
    protected $primaryKey = 'clave';
    public $fillable = ["clave","tipo","generico_id","es_causes","descripcion"];

    //Este scope carga los datos de catalogos que utiliza insumos_medicos
    public function scopeConDescripciones($query){
        return $query->select('insumos_medicos.*','genericos.nombre as generico_nombre','genericos.es_cuadro_basico') //,'grupos_insumos.nombre as grupo_nombre'
                ->leftjoin('genericos','genericos.id','=','insumos_medicos.generico_id');
                //->leftjoin('grupos_insumos','grupos_insumos.id','=','genericos.grupo_insumo_id'); //Se elimino relación uno a uno, se hizo de uno a muchos
    }

    public function scopeConDescripcionesPrecios($query, $contrato_id, $proveedor_id){
        return $query->select('insumos_medicos.*','genericos.nombre as generico_nombre','genericos.es_cuadro_basico','contratos_precios.precio','contratos_precios.tipo_insumo_id', 'medicamentos.cantidad_x_envase') //,'grupos_insumos.nombre as grupo_nombre'
                ->leftjoin('genericos','genericos.id','=','insumos_medicos.generico_id')
                ->leftjoin('medicamentos','medicamentos.insumo_medico_clave','=','insumos_medicos.clave')
                ->join('contratos_precios',function($join)use($contrato_id, $proveedor_id){
                    $join->on('contratos_precios.insumo_medico_clave','=','insumos_medicos.clave')->where('contratos_precios.contrato_id','=',$contrato_id)->where('contratos_precios.proveedor_id','=',$proveedor_id);
                });
                //->leftjoin('grupos_insumos','grupos_insumos.id','=','genericos.grupo_insumo_id'); //Se elimino relación uno a uno, se hizo de uno a muchos
    }
/*
    public function scopeDatosUnidosis($query){
        return $query->select('insumos_medicos.*',DB::raw('IF(insumos_medicos.tipo = "ME",medicamentos.cantidad_x_envase,material_curacion.cantidad_x_envase) as cantidad_x_envase')) 
                ->leftjoin('medicamentos','medicamentos.insumo_medico_clave','=','insumos_medicos.clave')
                ->leftjoin('material_curacion','material_curacion.insumo_medico_clave','=','insumos_medicos.clave');
    }
*/
    public function scopeDatosUnidosis($query){
        return $query->select('insumos_medicos.*',DB::raw('(CASE WHEN insumos_medicos.tipo = "ME" THEN medicamentos.cantidad_x_envase ELSE CASE WHEN insumos_medicos.tipo = "MC" THEN material_curacion.cantidad_x_envase ELSE null END END) as cantidad_x_envase')) 
                ->leftjoin('medicamentos','medicamentos.insumo_medico_clave','=','insumos_medicos.clave')
                ->leftjoin('material_curacion','material_curacion.insumo_medico_clave','=','insumos_medicos.clave');
    }

    //Relacion con el Modelo Medicamento, usando un scope para cargar los datos de los catalogos utilizados por medicamentos
    public function informacion(){
        if ($this->tipo = "ME"){
            return $this->hasOne('App\Models\Medicamento','insumo_medico_clave','clave')->conDescripciones();
        }
        return null;
    }

    //Relacion con el Modelo Medicamento, usando un scope para cargar los datos de los catalogos utilizados por medicamentos
    public function informacionAmpliada(){
        if ($this->tipo = "ME"){
            return $this->hasOne('App\Models\Medicamento','insumo_medico_clave','clave')->conInformacionImportante();
        }
        return null;
    }

    //Relacion con el Modelo Medicamento
    public function medicamento(){
        return $this->hasOne('App\Models\Medicamento','insumo_medico_clave','clave');
    }

    //Relacion con el Modelo Generico
    public function generico(){
        return $this->belongsTo('App\Models\Generico');
    }
}