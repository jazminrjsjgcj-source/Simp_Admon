<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $table  = 'periodos';
    protected $guarded = ['id'];

    // Tipos de periodo
    const TIPO_SYD = 'agenda_syd';
    const TIPO_REGULATORIA = 'agenda_regulatoria';

    const TIPOS = [
        self::TIPO_SYD         => 'Agenda SyD (semestral)',
        self::TIPO_REGULATORIA => 'Agenda Regulatoria (anual)',
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
        return $this->estatus === 'activo';
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
        return $query->where('estatus', 'activo');
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
