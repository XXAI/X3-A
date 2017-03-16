<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insumo extends BaseModel{
    
    use SoftDeletes;
    
    protected $generarID = false;
    protected $guardarIDServidor = false;
    //protected $guardarIDUsuario = false;
    //public $incrementing = false;
    
    protected $table = 'insumos_medicos';  
    protected $primaryKey = 'clave';
    protected $fillable = ["clave","tipo","generico_id","es_causes","descripcion"];

    //Este scope carga los datos de catalogos que utiliza insumos_medicos
    public function scopeConDetalles($query){
        return $query->select('insumos_medicos.*','genericos.nombre as generico_nombre','genericos.es_cuadro_basico','grupos_insumos.nombre as grupo_nombre')
                ->leftjoin('genericos','genericos.id','=','insumos_medicos.generico_id')
                ->leftjoin('grupos_insumos','grupos_insumos.id','=','genericos.grupo_insumo_id');
    }

    //Relacion con el Modelo Medicamento, usando un scope para cargar los datos de los catalogos utilizados por medicamentos
    public function medicamentoDetalle(){
        return $this->hasOne('App\Models\Medicamento','insumo_medico_clave','clave')->conDetalles();
    }

    //Relacion con el Modelo Medicamento
    public function medicamento(){
        return $this->hasOne('App\Models\Medicamento','insumo_medico_clave','clave');
    }
}