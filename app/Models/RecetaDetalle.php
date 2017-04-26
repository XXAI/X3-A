<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecetaDetalle extends BaseModel
{
     use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $table = 'receta_detalles';

    protected $fillable = ["recetas_id", "clave_insumo_medico","cantidad", "dosis", "frecuencia", "duracion"];
}
