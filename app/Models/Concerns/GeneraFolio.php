<?php

namespace App\Models\Concerns;

/**
 * Genera folios con formato LPZ-{TIPO}-{SIGLAS}-{AÑO}-{consecutivo}.
 * Ejemplo: LPZ-PROP-DGGD-2026-001
 *
 * Lo usan los modelos que necesitan folio (propuestas, agenda, AIR,
 * regulaciones). Cada modelo define su propio TIPO sobreescribiendo
 * el método folioTipo(). El LPZ sale de config('punta.prefijo_homoclave')
 * para ser consistente con las homoclaves de trámites.
 *
 * El consecutivo se reinicia por tipo, dependencia y año.
 */
trait GeneraFolio
{
    /**
     * Prefijo de tipo del folio. Cada modelo lo sobreescribe.
     * Ej. 'PROP', 'AGD', 'AIR', 'REG'.
     */
    protected function folioTipo(): string
    {
        return 'DOC';
    }

    /**
     * Siglas de la dependencia asociada. Cada modelo decide de dónde
     * sacarlas (puede tener la relación directa o vía propuesta).
     */
    protected function folioSiglas(): string
    {
        $dep = $this->dependencia ?? null;
        if ($dep && !empty($dep->siglas)) {
            return $dep->siglas;
        }
        if ($dep && !empty($dep->nombre)) {
            return strtoupper(substr($dep->nombre, 0, 4));
        }
        return 'GRAL';
    }

    /**
     * Genera y devuelve un folio único para este registro.
     */
    public function generarFolio(): string
    {
        $lpz    = config('punta.prefijo_homoclave', 'LPZ');
        $tipo   = $this->folioTipo();
        $siglas = $this->folioSiglas();
        $anio   = now()->year;

        $prefijo = "{$lpz}-{$tipo}-{$siglas}-{$anio}-";

        // Último consecutivo usado con este prefijo (mismo tipo, dependencia y año).
        $ultimo = static::where('folio', 'like', $prefijo . '%')
            ->orderByDesc('folio')
            ->value('folio');

        $siguiente = 1;
        if ($ultimo) {
            $numero = (int) substr($ultimo, strlen($prefijo));
            $siguiente = $numero + 1;
        }

        return $prefijo . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }
}
