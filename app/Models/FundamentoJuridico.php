<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FundamentoJuridico extends Model
{
    protected $table   = 'fundamento_juridico';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * Reconstruido desde la migración de fundamento_juridico.
     */
    protected $fillable = [
        'tramite_id',
        'regulacion_id',
        'normativa_nombre',
        'tipo_normativa',
        'articulo_fraccion',
        'resumen',
    ];

    public function tramite() { return $this->belongsTo(Tramite::class); }

    public function regulacion() { return $this->belongsTo(Regulacion::class); }
}
