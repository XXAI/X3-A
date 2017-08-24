<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvanceUsuarioPrivilegio extends BaseModel
{
    use SoftDeletes;
    
    protected $generarID = true;
    protected $guardarIDServidor = true;
    protected $guardarIDUsuario = true;
    
    protected $table = 'avance_usuario_privilegio';  
    protected $fillable = ["usuario_id","avance_id", "agregar", "editar", "eliminar"];

    
}
