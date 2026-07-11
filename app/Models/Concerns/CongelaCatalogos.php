<?php

namespace App\Models\Concerns;

/**
 * Congela los nombres de catálogo al firmar, y avisa si después cambian.
 *
 * ── Por qué ──────────────────────────────────────────────────────────
 *
 * Un trámite firmado dice que lo tramita la "Dirección General de Gobierno Digital".
 * Si esa dependencia se renombra, el trámite —que lee el nombre del catálogo vivo—
 * pasaría a decir otra cosa. Se estaría cambiando el contenido de un documento
 * firmado sin que nadie lo firmara de nuevo.
 *
 * ── Cómo ─────────────────────────────────────────────────────────────
 *
 * Al firmar, el registro guarda una FOTO de los nombres que usó. A partir de ahí:
 *
 *   - nombreDeCatalogo('dependencia') devuelve el nombre CONGELADO si ya se firmó, y
 *     el vivo si todavía no. Lo que se muestra del documento firmado es lo que decía
 *     cuando se firmó.
 *
 *   - catalogosDesactualizados() compara la foto con el catálogo actual y devuelve lo
 *     que cambió, para poder avisar: "la dependencia cambió de nombre desde que esto
 *     se firmó".
 *
 * El aviso invita a revisar; no cambia nada por su cuenta. Quien decide si el
 * documento debe rehacerse es una persona, no el sistema.
 *
 * El modelo que use este trait debe declarar qué catálogos le aplican, con
 * catalogosCongelables(): ['dependencia' => 'nombre', 'unidad' => 'nombre', ...],
 * donde la clave es la relación y el valor, el campo que se congela.
 */
trait CongelaCatalogos
{
    /**
     * Toma la foto de los catálogos y la guarda. Se llama al firmar el registro.
     *
     * Si ya había una foto, NO se sobrescribe: el documento se firmó una vez y esa es
     * la verdad. Volver a fotografiar sería justo el problema que se quiere evitar.
     */
    public function congelarCatalogos(): void
    {
        if (! empty($this->catalogos_al_firmar)) {
            return; // ya estaba firmado: su foto no se toca
        }

        $foto = [];

        foreach ($this->catalogosCongelables() as $relacion => $campo) {
            $modelo = $this->{$relacion};

            if ($modelo) {
                $foto[$relacion] = [
                    'id'    => $modelo->getKey(),
                    'valor' => $modelo->{$campo},
                ];
            }
        }

        $this->forceFill(['catalogos_al_firmar' => $foto])->saveQuietly();
    }

    /**
     * El nombre que debe mostrarse para un catálogo.
     *
     * Si el registro ya está firmado, el que tenía al firmarse (aunque el catálogo
     * haya cambiado). Si aún no se ha firmado, el actual.
     */
    public function nombreDeCatalogo(string $relacion): ?string
    {
        $foto = $this->catalogos_al_firmar[$relacion] ?? null;

        if ($foto && isset($foto['valor'])) {
            return $foto['valor'];
        }

        // Todavía no se ha firmado: se muestra el valor vivo.
        $campo  = $this->catalogosCongelables()[$relacion] ?? 'nombre';
        $modelo = $this->{$relacion};

        return $modelo?->{$campo};
    }

    /**
     * Qué catálogos han cambiado desde que se firmó el registro.
     *
     * Devuelve, por cada uno que difiera:
     *   ['dependencia' => ['al_firmar' => 'Dirección General...', 'ahora' => 'Dirección de...']]
     *
     * Un arreglo vacío significa que todo sigue igual que cuando se firmó.
     */
    public function catalogosDesactualizados(): array
    {
        if (empty($this->catalogos_al_firmar)) {
            return []; // aún no se ha firmado: no hay nada contra qué comparar
        }

        $cambios = [];

        foreach ($this->catalogosCongelables() as $relacion => $campo) {
            $foto = $this->catalogos_al_firmar[$relacion] ?? null;
            if (! $foto) {
                continue;
            }

            $modelo = $this->{$relacion};
            if (! $modelo) {
                // El catálogo desapareció (se borró la dependencia, por ejemplo).
                $cambios[$relacion] = [
                    'al_firmar' => $foto['valor'] ?? null,
                    'ahora'     => null,
                ];
                continue;
            }

            $actual = $modelo->{$campo};

            if ($actual !== ($foto['valor'] ?? null)) {
                $cambios[$relacion] = [
                    'al_firmar' => $foto['valor'],
                    'ahora'     => $actual,
                ];
            }
        }

        return $cambios;
    }

    /** ¿Hay algún catálogo que haya cambiado desde la firma? */
    public function tieneCatalogosDesactualizados(): bool
    {
        return $this->catalogosDesactualizados() !== [];
    }

    /** ¿Ya se congelaron los catálogos (es decir, ya se firmó)? */
    public function catalogosCongelados(): bool
    {
        return ! empty($this->catalogos_al_firmar);
    }
}
