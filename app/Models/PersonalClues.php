<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalClues extends BaseModel
{
    use SoftDeletes;
    protected $generarID = true;
    protected $guardarIDServidor = true;
    //protected $guardarIDUsuario = false;
    protected $table = 'personal_clues';

    protected $fillable = [ 'clues', 'usuario_asignado', 'nombre','celular', 'email'];
}
