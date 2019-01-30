<?php
namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable;

class LogMigracionServidorOffline extends BaseModel{
    use SoftDeletes;
    protected $generarID = false;
    protected $guardarIDServidor = false;
    protected $guardarIDUsuario = true;
    protected $fillable = ["servidor_migrado_id","duration","status","mensaje","usuario_id", "created_at","updated_at"];
    protected $table = 'log_migracion_servidor_offline';
    
}