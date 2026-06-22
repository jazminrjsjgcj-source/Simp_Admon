<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FichaPortal extends Model
{
    protected $table   = 'ficha_portal';

    /**
     * Columnas asignables en masa (sin id ni timestamps). La ficha se guarda
     * con updateOrCreate de un array dinámico de campos portal_*, así que
     * todas las columnas de contenido deben estar aquí. Reconstruido desde
     * las migraciones de ficha_portal.
     */
    protected $fillable = [
        'tramite_id',
        'nombre_ciudadano',
        'tipo',
        'homoclave_publica',
        'documento_obtiene',
        'descripcion',
        'casos_realizarse',
        'modalidad',
        'canal_principal',
        'requiere_cita',
        'enlace_cita',
        'costo_publico',
        'forma_pago',
        'resultado',
        'doc_resultado',
        'medio_entrega',
        'vigencia',
        'oficina',
        'horario',
        'telefono',
        'correo',
        'observaciones',
        'estatus_validacion',
        'fecha_validacion',
        'horarios_json',
        'direccion',
        'url',
    ];

    protected $casts = [
        'horarios_json'   => 'array',   // Fase F.4: estructura JSON de horarios
        'requiere_cita'   => 'boolean',
    ];

    public function tramite() { return $this->belongsTo(Tramite::class); }
}
