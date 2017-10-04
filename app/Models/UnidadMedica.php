<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnidadMedica extends BaseModel{
    
    use SoftDeletes;
    
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = false;
    public $incrementing = false;

    protected $primaryKey = 'clues';
    
    protected $table = 'unidades_medicas';  
    protected $fillable = ["clues","nombre","activa","jurisdiccion_id"];

    public function almacenes(){
      return $this->hasMany('App\Models\Almacen','clues');
    }

    public function director(){
      return $this->hasOne('App\Models\PersonalClues','id','director_id');
    }

    public function jurisdiccion(){
      return $this->hasOne('App\Models\Jurisdiccion','id','jurisdiccion_id');
    }
}