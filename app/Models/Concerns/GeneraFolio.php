<?php

namespace App\Models\Concerns;

use App\Models\Contador;
use App\Support\Siglas;

/**
 * Genera folios con formato LPZ-{TIPO}-{SIGLAS}-{AÑO}-{consecutivo}.
 * Ejemplo: LPZ-PROP-DGGD-2026-001
 *
 * Lo usan los modelos que necesitan folio (propuestas, agenda, AIR, regulaciones).
 * Cada uno define su TIPO sobreescribiendo folioTipo(). El prefijo sale de
 * config('punta.prefijo_homoclave') para ser consistente con las homoclaves.
 *
 * El consecutivo se reinicia por tipo, dependencia y año.
 */
trait GeneraFolio
{
    /**
     * Prefijo de tipo del folio. Cada modelo lo sobreescribe: 'PROP', 'AGD', 'AIR'...
     */
    protected function folioTipo(): string
    {
        return 'DOC';
    }

    /**
     * Siglas de la dependencia asociada. Cada modelo decide de dónde sacarlas, que
     * puede ser una relación directa o a través de la propuesta.
     *
     * Se delega en App\Support\Siglas para que el cálculo viva en un solo sitio: es
     * la clase que garantiza que un identificador oficial no lleve acentos, espacios
     * ni caracteres partidos.
     */
    protected function folioSiglas(): string
    {
        $dep = $this->dependencia ?? null;

        if (! $dep) {
            return Siglas::GENERICAS;
        }

        // Las siglas capturadas a mano también se limpian: si alguien escribió "VÚ"
        // en la columna, la tilde entraría en el folio igual.
        return Siglas::normalizar($dep->siglas) ?: Siglas::desdeNombre($dep->nombre);
    }

    /**
     * Genera y devuelve un folio único para este registro.
     *
     * El consecutivo lo entrega Contador bajo bloqueo de fila, en vez de calcularse
     * leyendo el último folio de la tabla. Eso evita dos fallos: que dos altas
     * simultáneas se lleven el mismo número, y que el orden alfabético de una columna
     * de texto haga pasar "999" por mayor que "1000" y atasque la serie.
     *
     * El str_pad rellena a tres dígitos por estética; a partir de 999 el folio
     * simplemente crece, sin recortar.
     */
    public function generarFolio(): string
    {
        $lpz    = config('punta.prefijo_homoclave', 'LPZ');
        $tipo   = $this->folioTipo();
        $siglas = $this->folioSiglas();
        $anio   = now()->year;

        // El prefijo define la serie: cada combinación de tipo, dependencia y año
        // lleva su propia numeración, empezando en 001.
        $prefijo = "{$lpz}-{$tipo}-{$siglas}-{$anio}-";

        $siguiente = Contador::siguiente('folio:' . $prefijo);

        return $prefijo . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }
}
