<?php

namespace App\Models\Concerns;

use App\Models\Contador;
use App\Support\Siglas;

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
     * Siglas de la dependencia asociada. Cada modelo decide de dónde sacarlas (puede tener la
     * relación directa o vía propuesta).
     *
     * ── AQUÍ HABÍA UN BUG, Y ERA DE LOS QUE NO DAN LA CARA ──
     *
     * La versión anterior hacía esto:
     *
     *     return strtoupper(substr($dep->nombre, 0, 4));
     *
     * `substr()` cuenta BYTES, no caracteres. Y en UTF-8 una "Ñ" ocupa dos bytes.
     *
     *     substr("Ñuñez", 0, 4)  →  C3 91 75 C3
     *                            →  "Ñu" + medio byte de la ñ
     *                            →  un carácter ROTO
     *
     * Ese byte suelto, que no es ninguna letra, acababa dentro del folio. Impreso. Sin ningún
     * error, sin ninguna alarma: el folio se guardaba tan tranquilo con basura dentro.
     *
     * Y `strtoupper()` tampoco pone en mayúsculas las letras acentuadas —también trabaja por
     * bytes—, así que una "ó" se quedaba minúscula en unas siglas que deberían ser mayúsculas.
     *
     * Lo curioso es que Tramite::siglasDesdeNombre() SÍ lo hacía bien, con mb_substr. Había dos
     * sitios calculando lo mismo, uno correcto y otro no, y nadie los comparó nunca. Esa
     * duplicación era la causa del bug, no un detalle de estilo.
     *
     * Ahora los dos usan App\Support\Siglas, que además quita los acentos: un identificador
     * oficial tiene que poder escribirse en una URL, en un nombre de archivo y en un CSV sin
     * sorpresas.
     */
    protected function folioSiglas(): string
    {
        $dep = $this->dependencia ?? null;

        if (! $dep) {
            return Siglas::GENERICAS;
        }

        // Las siglas capturadas a mano también se limpian: si alguien escribió "VÚ" en la
        // columna, la tilde entraría en el folio igual.
        return Siglas::normalizar($dep->siglas) ?: Siglas::desdeNombre($dep->nombre);
    }

    /**
     * Genera y devuelve un folio único para este registro.
     *
     * El consecutivo lo entrega el Contador. Ya no se calcula leyendo la tabla.
     *
     * ── Qué había antes y por qué estaba mal ──
     *
     * Antes se buscaba el último folio con orderByDesc('folio'). Como `folio` es
     * una columna de TEXTO, eso ordena como un diccionario, letra por letra, no
     * como números.
     *
     * Con ceros delante (001, 002... 999) funcionaba de casualidad, porque todos
     * los textos medían lo mismo. El registro 1000 rompía la casualidad:
     * comparando "999" contra "1000", el primer carácter es '9' contra '1', y como
     * '9' va después de '1' en el diccionario, la base concluía que "999" era el
     * mayor. Así que seguía proponiendo el 1000 una y otra vez, chocando contra el
     * índice único de `folio` en cada intento. La serie se atascaba PARA SIEMPRE a
     * partir del registro 1000, en los cuatro modelos que usan este trait.
     *
     * Y además: dos altas simultáneas leían el mismo "último" folio y se llevaban
     * el mismo número.
     *
     * ── Qué hace ahora y por qué es mejor ──
     *
     * Le pide el número al Contador, que lo entrega bajo bloqueo de fila. No hay
     * nada que ordenar (así que no hay orden alfabético que pueda equivocarse) y no
     * hay dos lecturas que puedan cruzarse (así que dos altas simultáneas reciben
     * números distintos).
     *
     * El str_pad sigue rellenando a 3 dígitos por estética (001, 042). Cuando la
     * serie pase de 999, str_pad no recorta: el folio crece a
     * LPZ-SIM-DGGD-2026-1000, y esta vez el 1001 llega sin problema.
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
