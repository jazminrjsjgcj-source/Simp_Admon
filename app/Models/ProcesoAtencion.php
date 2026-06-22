<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProcesoAtencion extends Model
{
    protected $table   = 'proceso_atencion';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * Reconstruido desde create_tramites_tables + add_area + add_subpaso.
     */
    protected $fillable = [
        'tramite_id',
        'tipo',
        'paso',
        'subpaso',
        'accion',
        'detalle',
        'area',
    ];

    public function tramite() { return $this->belongsTo(Tramite::class); }
}
