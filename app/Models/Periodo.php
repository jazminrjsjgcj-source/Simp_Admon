<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $table  = 'periodos';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * created_by SÍ va aquí: AdminController lo asigna en Periodo::create().
     * Reconstruido desde las migraciones de periodos.
     */
    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estatus',
        'descripcion',
        'created_by',
        'tipo',
    ];

    // Tipos de periodo
    const TIPO_SYD = 'agenda_syd';
    const TIPO_REGULATORIA = 'agenda_regulatoria';

    const TIPOS = [
        self::TIPO_SYD         => 'Agenda SyD (semestral)',
        self::TIPO_REGULATORIA => 'Agenda Regulatoria (anual)',
    ];

    /**
     * ── ESTATUS DE UN PERIODO ──
     *
     * Antes estas tres palabras no existían como constantes: se escribían a mano, entre
     * comillas, en once sitios distintos del sistema (el servicio, el controlador, los
     * scopes, las vistas, los seeders).
     *
     * Eso importa más de lo que parece, porque de la palabra 'activo' cuelga todo el módulo:
     *
     *   - scopeActivo() compara con === 'activo'
     *   - PeriodoService cierra los demás cuando ve 'activo'
     *   - el índice único de la base filtra WHERE estatus = 'activo'
     *
     * Un 'Activo' con mayúscula, o un 'activo ' con un espacio de más, se guardaría sin
     * queja alguna. Y ese periodo NO estaría activo para el sistema: no lo vería el scope,
     * no cerraría a los demás, no lo protegería el índice. Nadie sabría por qué.
     *
     * Con constantes, ese error deja de ser posible: un typo en el nombre de la constante no
     * compila. La única forma de escribir mal el estatus es escribirlo a mano, y por eso
     * ya no se escribe a mano en ningún sitio.
     */
    const ESTATUS_PROXIMO = 'proximo';
    const ESTATUS_ACTIVO  = 'activo';
    const ESTATUS_CERRADO = 'cerrado';

    const ESTATUS_TODOS = [
        self::ESTATUS_PROXIMO => 'Próximo',
        self::ESTATUS_ACTIVO  => 'Activo',
        self::ESTATUS_CERRADO => 'Cerrado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estaActivo(): bool
    {
        return $this->estatus === self::ESTATUS_ACTIVO;
    }

    public function esSyd(): bool
    {
        return $this->tipo === self::TIPO_SYD;
    }

    public function esRegulatoria(): bool
    {
        return $this->tipo === self::TIPO_REGULATORIA;
    }

    public function tipoLegible(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('estatus', self::ESTATUS_ACTIVO);
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeSyd($query)
    {
        return $query->where('tipo', self::TIPO_SYD);
    }

    public function scopeRegulatoria($query)
    {
        return $query->where('tipo', self::TIPO_REGULATORIA);
    }
}
