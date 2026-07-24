<?php

namespace App\Services;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Averigua en qué PÁGINA del PDF original aparece cada artículo de una
 * regulación, y lo guarda en la columna `pagina` de regulacion_nodos.
 *
 * ── Para qué sirve ───────────────────────────────────────────────────
 *
 * El buscador sabe QUÉ artículo arrojó un resultado, pero no en qué página del
 * PDF está. Con la página guardada, un resultado puede abrir el PDF original
 * directamente en el lugar correcto (visor del navegador con #page=N).
 *
 * ── Por qué es un paso APARTE del pipeline de texto ──────────────────
 *
 * El texto que alimenta al articulado pasa por una limpieza que, entre otras
 * cosas, BORRA los saltos de página del PDF (el conversor elimina el carácter
 * 0x0C antes de guardar el Markdown). Para cuando se construyen los nodos, ya
 * no hay forma de saber dónde terminaba cada página.
 *
 * Por eso este servicio NO se cuelga de ese pipeline: vuelve a leer el PDF
 * original por su cuenta, esta vez conservando los saltos de página, y empareja
 * cada artículo con la página donde aparece su texto. Así el pipeline de
 * conversión queda intacto (sin riesgo para el formateo, el score ni el índice).
 *
 * ── Qué NO es ────────────────────────────────────────────────────────
 *
 * Es una AYUDA de navegación, no una cita legal. El emparejamiento es una
 * aproximación por texto: si un artículo no se puede localizar con certeza,
 * hereda la página del artículo anterior y se anota en el log. En el peor caso
 * el visor abre una página cercana, nunca un error.
 */
class RegulacionPaginadorService
{
    public function __construct(
        private RegulacionConversorService $conversor,
    ) {}

    /**
     * Longitud de la "huella" (primeros caracteres normalizados del cuerpo del
     * artículo) que se busca en cada página. 60 es suficiente para ser único
     * dentro de un documento y corto para no cruzar a la página siguiente
     * cuando un artículo empieza al final de una página.
     */
    private const LARGO_HUELLA = 60;

    /**
     * Huella mínima para intentar emparejar. Por debajo de esto (artículos de
     * una línea, tablas cortas) el riesgo de coincidencia falsa es alto, así que
     * se trata como "no emparejado" (hereda + log) en vez de arriesgar una
     * página equivocada.
     */
    private const HUELLA_MINIMA = 12;

    /**
     * Detecta y guarda la página de cada artículo de la regulación.
     *
     * @return int  cuántos artículos quedaron con página EXACTA (no heredada).
     *              0 puede significar "no aplica" (no es PDF) o "no se emparejó
     *              ninguno"; el log distingue ambos casos.
     */
    public function detectarPaginas(Regulacion $regulacion): int
    {
        // Solo tiene sentido en PDFs: es el único formato con una paginación
        // oficial que respetar. Las regulaciones de Word generan su PDF al vuelo,
        // sin una numeración de página estable a la que saltar.
        if ($regulacion->extension_original !== 'pdf') {
            return 0;
        }

        if (empty($regulacion->archivo_original)
            || ! Storage::disk('local')->exists($regulacion->archivo_original)) {
            Log::warning('Paginador: sin PDF original para paginar.', [
                'regulacion_id' => $regulacion->id,
            ]);
            return 0;
        }

        $rutaPdf  = Storage::disk('local')->path($regulacion->archivo_original);
        $paginas  = $this->conversor->extraerTextoPorPagina($rutaPdf);

        if ($paginas === null || count($paginas) === 0) {
            Log::warning('Paginador: no se pudo extraer texto por página.', [
                'regulacion_id' => $regulacion->id,
            ]);
            return 0;
        }

        // Se normaliza cada página UNA sola vez (no por artículo): así el
        // emparejamiento de cientos de artículos es rápido.
        $paginasNormalizadas = array_map(fn (string $t) => $this->normalizar($t), $paginas);
        $totalPaginas        = count($paginasNormalizadas);

        // Además, una versión "plana" (minúsculas y sin acentos) que CONSERVA los
        // saltos de línea. Sirve para detectar el encabezado "Artículo N" al inicio
        // de un renglón, y así distinguirlo de una cita a mitad de frase ("...conforme
        // al artículo N...") o de texto-plantilla repetido en otros artículos.
        $paginasPlano = array_map(
            fn (string $t) => strtolower(\Illuminate\Support\Str::ascii($t)),
            $paginas
        );

        // Artículos en ORDEN DE DOCUMENTO. Se ordena por `id`, no por `orden`:
        // `orden` es la posición ENTRE HERMANOS del mismo padre (se reinicia en
        // cada capítulo), así que ordenar por él revuelve los artículos entre
        // capítulos y arruinaba el emparejamiento. El `id` es autoincremental y el
        // estructurador crea los nodos recorriendo el documento de arriba a abajo,
        // así que `id` ascendente = orden real de lectura. Este orden solo afina el
        // desempate; el emparejamiento en sí NO depende de él (se busca en todo el
        // documento), por eso un error en un artículo ya no se propaga a los demás.
        $articulos = $regulacion->nodos()
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->orderBy('id')
            ->get();

        if ($articulos->isEmpty()) {
            return 0;
        }

        $paginaPrevia = null; // última página ubicada (para desempatar y para heredar)
        $exactas      = 0;
        $heredadas    = 0;

        DB::transaction(function () use (
            $articulos, $paginasNormalizadas, $paginasPlano, $totalPaginas, $regulacion,
            &$paginaPrevia, &$exactas, &$heredadas
        ) {
            foreach ($articulos as $articulo) {
                $pagina = $this->ubicarArticulo($articulo, $paginasNormalizadas, $paginasPlano, $totalPaginas, $paginaPrevia);

                if ($pagina !== null) {
                    $articulo->pagina = $pagina;
                    $paginaPrevia     = $pagina;
                    $exactas++;
                } else {
                    // No se pudo ubicar: hereda la página del artículo anterior ya
                    // ubicado (aproximación razonable) y se anota. Si aún no hay
                    // ninguna, queda NULL y el buscador cae a la página 1.
                    $articulo->pagina = $paginaPrevia;
                    $heredadas++;

                    Log::info('Paginador: artículo sin ubicar, hereda anterior.', [
                        'regulacion_id'   => $regulacion->id,
                        'nodo_id'         => $articulo->id,
                        'numero'          => $articulo->numero,
                        'pagina_heredada' => $paginaPrevia,
                    ]);
                }

                $articulo->save();
            }
        });

        // Fracciones, incisos y párrafos heredan la página de su artículo ancestro.
        // Un resultado del buscador suele ser una fracción; sin esto abriría el PDF
        // en la página 1 aunque su artículo sí tenga página.
        $this->propagarADescendientes($regulacion);

        Log::info('Paginador: terminado.', [
            'regulacion_id' => $regulacion->id,
            'total_paginas' => $totalPaginas,
            'exactas'       => $exactas,
            'heredadas'     => $heredadas,
        ]);

        return $exactas;
    }

    /**
     * Copia la página del artículo ancestro a sus fracciones, incisos y párrafos.
     *
     * Tras paginar los artículos, el resto de nodos sigue en NULL. Como un
     * resultado del buscador puede ser una fracción o un inciso, aquí cada nodo
     * que no sea artículo sube por su cadena de padres hasta el primer ancestro
     * que ya tenga página (su artículo) y la hereda. Si no hay ancestro con
     * página (p. ej. un párrafo suelto bajo un capítulo), queda en NULL y el
     * buscador cae a la página 1.
     *
     * Se hace en memoria (un solo SELECT) y se escribe solo lo que cambió.
     */
    private function propagarADescendientes(Regulacion $regulacion): void
    {
        // Todos los nodos de la regulación, indexados por id para poder saltar
        // de un hijo a su padre sin volver a la base de datos.
        $nodos = $regulacion->nodos()
            ->get(['id', 'parent_id', 'tipo', 'pagina'])
            ->keyBy('id');

        $porActualizar = [];

        foreach ($nodos as $nodo) {
            // Los artículos ya tienen su página (o su NULL definitivo): no se tocan.
            if ($nodo->tipo === RegulacionNodo::TIPO_ARTICULO || $nodo->pagina !== null) {
                continue;
            }

            // Subir por la cadena de padres hasta el primero que tenga página.
            // El tope de 20 saltos es una red contra un árbol mal formado con un
            // ciclo: sin él, un padre que se apunta a sí mismo colgaría el proceso.
            $actual    = $nodo;
            $saltos    = 0;
            $heredada  = null;
            while ($actual !== null && $saltos < 20) {
                if ($actual->pagina !== null) {
                    $heredada = $actual->pagina;
                    break;
                }
                $actual = $actual->parent_id !== null ? ($nodos[$actual->parent_id] ?? null) : null;
                $saltos++;
            }

            if ($heredada !== null) {
                $porActualizar[$nodo->id] = $heredada;
            }
        }

        if ($porActualizar === []) {
            return;
        }

        DB::transaction(function () use ($porActualizar) {
            foreach ($porActualizar as $id => $pagina) {
                RegulacionNodo::where('id', $id)->update(['pagina' => $pagina]);
            }
        });
    }

    /**
     * Ubica en qué página del PDF aparece un artículo, buscando en TODO el
     * documento. Devuelve la página o null.
     *
     * Ancla en el ENCABEZADO "Artículo N" detectado al INICIO DE UN RENGLÓN. Ese
     * detalle es clave, visto en datos reales:
     *
     *   - Anclar en el cuerpo falla: las leyes de Hacienda repiten frases-plantilla
     *     ("causarán el equivalente a una vez el valor de la UMA..."), así que el
     *     cuerpo de un artículo casa en la página de otro (art. 86 caía en la 28,
     *     que es del art. 83).
     *   - Anclar en "artículo N" en cualquier parte también falla: aparece en las
     *     citas que otros artículos hacen ("...conforme al artículo N..."), así que
     *     mandaba a una cita ajena (art. 83 caía en una mención dentro del 77).
     *
     * El encabezado a inicio de renglón ("Artículo N.-" abre el párrafo) NO se
     * confunde con una cita (va a mitad de frase) ni con texto-plantilla. Aparece
     * en la página real y, si hay tarifario, en el anexo; se desempata por ORDEN
     * (la definición precede al anexo). El cuerpo se usa solo para confirmar cuando
     * el mismo número encabeza en más de un lugar.
     */
    private function ubicarArticulo(RegulacionNodo $articulo, array $paginasNorm, array $paginasPlano, int $total, ?int $paginaPrevia): ?int
    {
        // Páginas donde "Artículo N" abre un renglón (la definición, no una cita).
        $paginas = $this->paginasConEncabezado((string) $articulo->numero, $paginasPlano, $total);

        if ($paginas !== []) {
            // Si el encabezado abre renglón en varias páginas (real + anexo) y el
            // cuerpo confirma alguna, preferir esa; si no, decidir por orden.
            if (count($paginas) > 1) {
                $cuerpo = $this->huella($articulo->texto, self::LARGO_HUELLA);
                if (strlen($cuerpo) >= self::HUELLA_MINIMA) {
                    $confirmadas = array_values(array_filter(
                        $paginas,
                        fn (int $p) => str_contains($paginasNorm[$p - 1], $cuerpo)
                    ));
                    if ($confirmadas !== []) {
                        return min($confirmadas);
                    }
                }
            }

            return $this->elegirEnOrden($paginas, $paginaPrevia);
        }

        // Respaldo: el encabezado no se detectó como inicio de renglón (formato raro
        // del PDF). Se usa el cuerpo, que al menos apunta a una página cercana.
        $cuerpo = $this->huella($articulo->texto, self::LARGO_HUELLA);
        if (strlen($cuerpo) >= self::HUELLA_MINIMA) {
            $paginas = $this->paginasQueContienen($cuerpo, $paginasNorm, $total);
            if ($paginas !== []) {
                return min($paginas);
            }
        }

        return null;
    }

    /**
     * Páginas (1-based) donde el encabezado "Artículo N" ABRE UN RENGLÓN. Se busca
     * en el texto que conserva saltos de línea; el ancla `^` (con la bandera `m`)
     * exige inicio de renglón, lo que descarta las citas a mitad de frase.
     *
     * Se admiten dígitos/espacios iniciales en el renglón por si pdftotext dejó el
     * número de página pegado antes ("28 Artículo 83"). El lookahead `(?![0-9])`
     * evita que el 8 case con el 80, o el 86 con el 860.
     *
     * @return list<int>
     */
    private function paginasConEncabezado(string $numero, array $paginasPlano, int $total): array
    {
        $num = trim(strtolower(\Illuminate\Support\Str::ascii($numero)));
        if ($num === '') {
            return [];
        }

        // El número puede tener varias palabras ("88 bis", "decimo primero"): se
        // permite espacio flexible entre ellas.
        $patronNum = preg_replace('/\s+/', '\\s+', preg_quote($num, '/'));
        $regex     = '/^[\s\d]*articulo\s+0*' . $patronNum . '(?![0-9])/m';

        $paginas = [];
        for ($p = 1; $p <= $total; $p++) {
            if (preg_match($regex, $paginasPlano[$p - 1]) === 1) {
                $paginas[] = $p;
            }
        }
        return $paginas;
    }

    /**
     * Todas las páginas (1-based) cuyo texto normalizado contiene la aguja.
     *
     * @return list<int>
     */
    private function paginasQueContienen(string $aguja, array $paginasNorm, int $total): array
    {
        $encontradas = [];
        for ($p = 1; $p <= $total; $p++) {
            // El arreglo es 0-based; la página es 1-based.
            if (str_contains($paginasNorm[$p - 1], $aguja)) {
                $encontradas[] = $p;
            }
        }
        return $encontradas;
    }

    /**
     * De varias páginas candidatas, la MENOR que sea >= la página del artículo
     * anterior. Respeta el orden del documento: la definición real de un artículo
     * va antes que cualquier reimpresión en un tarifario/anexo, así que la primera
     * candidata a partir de la previa es la correcta. Si ninguna cumple (o no hay
     * artículo previo aún), la menor de todas.
     *
     * @param list<int> $paginas  no vacío
     */
    private function elegirEnOrden(array $paginas, ?int $paginaPrevia): int
    {
        sort($paginas);

        if ($paginaPrevia !== null) {
            foreach ($paginas as $p) {
                if ($p >= $paginaPrevia) {
                    return $p;
                }
            }
        }

        return $paginas[0];
    }

    /**
     * "Huella" de un artículo: los primeros caracteres normalizados de su cuerpo.
     * Se usa un fragmento y no el texto completo porque el texto del nodo y el del
     * PDF no coinciden exactamente (limpieza previa, cortes de línea), pero su
     * ARRANQUE sí es estable.
     */
    private function huella(?string $texto, int $largo): string
    {
        return substr($this->normalizar($texto ?? ''), 0, $largo);
    }

    /**
     * Normaliza texto para comparar sin depender de acentos, mayúsculas,
     * espacios ni signos: pasa a ASCII, minúsculas, y deja solo letras y
     * números. Robusto frente a los saltos de línea y guiones de corte que mete
     * pdftotext y a las diferencias con el texto ya limpiado del nodo.
     */
    private function normalizar(string $texto): string
    {
        $texto = Str::ascii($texto);
        $texto = strtolower($texto);
        return preg_replace('/[^a-z0-9]/', '', $texto) ?? '';
    }
}
