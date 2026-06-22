<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UnidadAdministrativa extends Model
{
    protected $table   = 'unidades_administrativas';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * Reconstruido desde las migraciones de unidades_administrativas.
     */
    protected $fillable = [
        'dependencia_id',
        'codigo',
        'nombre',
        'activo',
        'siglas',
    ];

    public function dependencia() { return $this->belongsTo(Dependencia::class); }
}
