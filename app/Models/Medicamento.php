<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicamento extends BaseModel{
    
    use SoftDeletes;
    
    protected $generarID = false;
    protected $guardarIDServidor = false;
    //protected $guardarIDUsuario = false;
    //public $incrementing = false;
    
    //protected $table = 'medicamentos';  
    protected $primaryKey = 'insumo_medico_clave';
    protected $fillable = ["id","insumo_medico_clave","presentacion_id","es_controlado","es_surfactante","concentracion","contenido","cantidad_x_envase","unidad_medida_id","indicaciones","via_administracion_id","dosis"];

    //Este scope carga los datos de catalogos que utiliza medicamentos
    public function scopeConDescripciones($query){
        return $query->select('medicamentos.*','presentaciones_medicamentos.nombre as presentacion_nombre','unidades_medida.nombre as unidad_medida_nombre','unidades_medida.clave as unidad_medida_clave',
                            'vias_administracion.nombre as via_administracion_nombre')
                ->leftjoin('presentaciones_medicamentos','presentaciones_medicamentos.id','=','medicamentos.presentacion_id')
                ->leftjoin('unidades_medida','unidades_medida.id','=','medicamentos.unidad_medida_id')
                ->leftjoin('vias_administracion','vias_administracion.id','=','medicamentos.via_administracion_id');
    }
    public function scopeConInformacionImportante($query){
        return $query->select(
                                'medicamentos.*',
                               'presentaciones_medicamentos.nombre as presentacion_nombre',
                                'unidades_medida.nombre as unidad_medida_nombre',
                                'unidades_medida.clave as unidad_medida_clave',
                                'vias_administracion.nombre as via_administracion_nombre',
                                'informacion_importante_medicamentos.*',
                                'factores_riesgo_embarazo.descripcion as factores_riesgo_embarazo_descripcion')
                ->leftjoin('presentaciones_medicamentos','presentaciones_medicamentos.id','=','medicamentos.presentacion_id')
                ->leftjoin('unidades_medida','unidades_medida.id','=','medicamentos.unidad_medida_id')
                ->leftjoin('vias_administracion','vias_administracion.id','=','medicamentos.via_administracion_id')
                ->leftjoin('insumos_medicos','insumos_medicos.clave','=','medicamentos.insumo_medico_clave')
                ->leftjoin('informacion_importante_medicamentos','informacion_importante_medicamentos.generico_id','=','insumos_medicos.generico_id')
                ->leftjoin('factores_riesgo_embarazo','factores_riesgo_embarazo.id','=','informacion_importante_medicamentos.factor_riesgo_embarazo_id')                ;
    }
}