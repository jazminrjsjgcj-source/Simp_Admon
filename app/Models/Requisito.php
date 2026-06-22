<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Requisito extends Model
{
    protected $table   = 'requisitos';

    /**
     * Columnas asignables en masa (sin id ni timestamps). Incluye las fj_*
     * (fundamento jurídico opcional) y los flags de costo que asigna
     * TramiteService::sincronizarRequisitos(). Reconstruido desde las
     * migraciones create_tramites_tables + add_fundamento + costos.
     */
    protected $fillable = [
        'tramite_id',
        'orden',
        'nombre',
        'original',
        'copia',
        'tipo_presentacion',
        'dias_estimados',
        'horas_estimadas',
        'minutos_estimados',
        'tiempo_homologado_hrs',
        'costo_requisito',
        'id_automatico',
        'observaciones',
        'es_producto_tramite',
        'tramite_origen',
        'documento_origen',
        'tiene_costo',
        'requiere_tercero',
        'costo_variable',
        'fj_norma',
        'fj_capitulo',
        'fj_articulo',
    ];

    public function tramite() { return $this->belongsTo(Tramite::class); }

    public function regulaciones() { return $this->hasMany(RequisitoRegulacion::class); }
}
