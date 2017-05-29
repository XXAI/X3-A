<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class TransferenciaPresupuesto extends BaseModel
{
    //use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;
    protected $fillable = [ "clues_origen", "mes_origen", "anio_origen", "clues_destino", "mes_destino", "anio_destino", "presupuesto_id","causes","no_causes","material_curacion"];
    protected $table = 'transferencias_presupuesto';

    public function unidadMedicaOrigen(){
      return $this->belongsTo('App\Models\UnidadMedica','clues_origen','clues');
    }
    public function unidadMedicaDestino(){
      return $this->belongsTo('App\Models\UnidadMedica','clues_destino','clues');
    }
}
