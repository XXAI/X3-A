<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClavesBasicas extends BaseModel
{
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    protected $table = 'claves_basicas';

    protected $fillable = [ 'nombre', 'clues','tipo'];
   
    public function detalles(){
        return $this->hasMany('App\Models\ClavesBasicasDetalle','claves_basicas_id');
    }

    
}
