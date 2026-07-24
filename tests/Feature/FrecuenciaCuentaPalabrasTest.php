<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\VocabularioCorpusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * EL FILTRO CUENTA PALABRAS. LA BÚSQUEDA USA RAÍCES. Y ESO NO ES INCOHERENTE: ES A PROPÓSITO.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL BUG QUE ESTA PRUEBA IMPIDE QUE VUELVA
 * ══════════════════════════════════════════════════════════════════════
 *
 * VocabularioCorpusService contaba con to_tsquery, igual que la búsqueda. Y to_tsquery pasa la
 * palabra POR EL STEMMER, que no conserva palabras: conserva RAÍCES.
 *
 *     casa     → raíz "cas"    → contaba también CASO y CASOS
 *     comprar  → raíz "compr"  → contaba COMPRENDIDAS, COMPRENDE, COMPROBANTE
 *
 * En la Ley de Hacienda real eso daba 73 y 42. Los números verdaderos eran 16 y CERO.
 *
 * ── Lo que rompía ──
 *
 * BuscadorService descarta las palabras "demasiado comunes" (más del 5% del articulado). Con 73
 * falsos positivos, "casa" superaba el umbral y SE TIRABA. Y al quedar una sola palabra, el AND
 * desaparecía:
 *
 *     "cuánto se paga por comprar una casa"
 *      →  (comprar | adquisicion | adquirir | enajenacion)      ← un OR suelto, sin AND
 *      →  88 artículos, y el artículo 38 —el del 3%— hundido entre ellos.
 *
 * El filtro tiraba la mitad de la pregunta porque el stemmer español no distingue una CASA de un
 * CASO. Y no daba ningún error: devolvía 30 resultados con toda la pinta de estar buscando bien.
 *
 * ── Por qué NO se arregla quitando el stemmer de la búsqueda ──
 *
 * Porque la búsqueda hace bien en usar raíces: es lo que permite que "semifijo" encuentre
 * "semifijos". Lo que estaba mal era MEDIR con raíces.
 *
 *     LA BÚSQUEDA USA RAÍCES, Y HACE BIEN. EL FILTRO TIENE QUE CONTAR PALABRAS.
 */
class FrecuenciaCuentaPalabrasTest extends TestCase
{
    use RefreshDatabase;

    private VocabularioCorpusService $vocabulario;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->vocabulario = app(VocabularioCorpusService::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Las dos colisiones reales
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * "caso" NO es "casa". Aunque el stemmer español las reduzca a la misma raíz.
     */
    public function test_caso_no_cuenta_como_casa(): void
    {
        // Tres artículos que dicen "caso" o "casos". Ninguno habla de una casa.
        $this->cargarArticulos([
            'En cuyo caso, el contribuyente deberá presentar el aviso correspondiente.',
            'En los casos previstos en esta Ley, procederá la sanción.',
            'Salvo caso fortuito o fuerza mayor, el plazo será improrrogable.',
        ]);

        $this->assertSame(
            0,
            $this->vocabulario->frecuencia('casa'),
            'Ningún artículo habla de una CASA: los tres dicen CASO o CASOS.' . "\n\n"
            . 'Si esto devuelve 3, se está contando con el stemmer: "casa" y "caso" comparten la '
            . 'raíz "cas". Y con esos falsos positivos, "casa" acaba superando el umbral de '
            . '"palabra demasiado común" y el filtro la tira — dejando la búsqueda sin AND.'
        );
    }

    /**
     * "comprender" NO es "comprar". La Ley de Hacienda dice "comprendidas" muchas veces y no dice
     * "comprar" ni una.
     */
    public function test_comprendidas_no_cuenta_como_comprar(): void
    {
        $this->cargarArticulos([
            'Los aprovechamientos no comprendidos en la Ley de Ingresos.',
            'Las contribuciones de mejoras no comprendidas en otros capítulos.',
        ]);

        $this->assertSame(
            0,
            $this->vocabulario->frecuencia('comprar'),
            'La ley NO dice "comprar". Dice "comprendidas", que es otra palabra.' . "\n\n"
            . 'Si esto devuelve 2, se está contando por la raíz "compr". Y entonces el sistema '
            . 'cree que la ley SÍ usa la palabra del ciudadano — cuando el tesauro existe '
            . 'precisamente porque NO la usa.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. LA CONTRAPRUEBA: el prefijo tiene que seguir funcionando
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Sin esta prueba, el arreglo podría haber pasado a exigir COINCIDENCIA EXACTA — y entonces
     * "semifijo" dejaría de encontrar "semifijos", que es medio corpus.
     *
     * Lo que se quita es el STEMMER. El PREFIJO se queda.
     */
    public function test_el_plural_sigue_contando(): void
    {
        $this->cargarArticulos([
            'Los puestos semifijos pagarán la cuota diaria correspondiente.',
            'Las casas habitación destinadas a uso propio.',
        ]);

        $this->assertSame(
            1,
            $this->vocabulario->frecuencia('semifijo'),
            '"semifijo" tiene que encontrar "semifijos". El arreglo quita el STEMMER, no el '
            . 'PREFIJO: si exigiera coincidencia exacta, media ley dejaría de contarse.'
        );

        $this->assertSame(
            1,
            $this->vocabulario->frecuencia('casa'),
            '"casa" tiene que encontrar "casas". Sigue siendo búsqueda por prefijo.'
        );
    }

    /**
     * LA PALABRA-RUIDO DE REFERENCIA SIGUE VIÉNDOSE. Y AQUÍ ESTÁ EL COMPROMISO DEL ARREGLO.
     *
     * ══════════════════════════════════════════════════════════════════════
     * EL PRECIO DE QUITAR EL STEMMER: SE PIERDE EL GÉNERO
     * ══════════════════════════════════════════════════════════════════════
     *
     * De estos tres artículos, "publico" cuenta DOS:
     *
     *     "servicios PÚBLICOS"   →  \mpublico casa    ✓
     *     "notarios PÚBLICOS"    →  \mpublico casa    ✓
     *     "vía PÚBLICA"          →  \mpublico NO casa ✗   ← femenino
     *
     * Un prefijo no salta de género. Y no hay forma mecánica de arreglarlo sin volver al bug:
     *
     *     publico / pública  →  el MISMO concepto en dos géneros.  Habría que unirlos.
     *     casa    / caso     →  DOS PALABRAS DISTINTAS.            No se pueden unir.
     *
     * Las dos parejas se parecen exactamente igual. Solo un diccionario de verdad las separa; un
     * patrón, no. Con stemmer se unían las dos (y "casa" contaba los "caso": el bug). Sin stemmer,
     * no se une ninguna (y "publico" pierde los femeninos: este subconteo).
     *
     * ══════════════════════════════════════════════════════════════════════
     * POR QUÉ EL SUBCONTEO ES ACEPTABLE Y EL FALSO POSITIVO NO LO ERA
     * ══════════════════════════════════════════════════════════════════════
     *
     * El filtro hace DOS trabajos, y no son igual de delicados:
     *
     *   1. Ver qué palabras NO EXISTEN en la ley (frecuencia == 0).
     *      Aquí la precisión lo es TODO: un falso positivo hacía creer al sistema que la ley dice
     *      "comprar" —no lo dice ni una vez— y desactivaba el rescate del tesauro.
     *
     *   2. Ver qué palabras son DEMASIADO COMUNES (más del 5% del articulado).
     *      Aquí un subconteo no rompe nada MIENTRAS la palabra siga por encima del umbral.
     *      "publico" sale en ~113 nodos de la Ley de Hacienda; perder los femeninos lo deja en
     *      unos 60-70, y el umbral está en 48. Se sigue descartando.
     *
     * PRECISIÓN DONDE IMPORTA, RECUENTO APROXIMADO DONDE BASTA.
     *
     * ── Y lo que hay que vigilar ──
     *
     * Si algún día "publico" cayera POR DEBAJO del umbral en el corpus real, volvería a colarse en
     * las consultas sin acotar nada, y el ruido regresaría. Se comprueba así:
     *
     *     php artisan buscador:diagnosticar "servicios publicos"
     *
     * La columna "artículos" tiene que dar más de 48 (5% de 959).
     */
    public function test_la_palabra_comun_sigue_contandose(): void
    {
        $this->cargarArticulos([
            'Los servicios públicos municipales se prestarán conforme a esta Ley.',
            'El uso de la vía pública requiere autorización.',
            'Los notarios públicos darán aviso a la autoridad fiscal.',
        ]);

        $this->assertSame(
            2,
            $this->vocabulario->frecuencia('publico'),
            '"publico" tiene que contar los DOS artículos que dicen "públicos" —con tilde y en '
            . "plural—.\n\n"
            . 'El tercero dice "vía PÚBLICA", en femenino, y NO se cuenta: un prefijo no salta de '
            . 'género. Es el precio de quitar el stemmer, y se paga a sabiendas: el stemmer sí '
            . 'unía "publico" con "pública"... y también "casa" con "caso", que era el bug.'
        );
    }

    /**
     * Y la contraprueba del compromiso: el femenino NO se cuenta. Está aquí para que quede
     * ESCRITO, no como un fallo escondido.
     *
     * Si algún día alguien "arregla" esto haciendo que "publico" cuente "pública", que sepa lo que
     * está haciendo: probablemente habrá vuelto a un patrón que también une "casa" con "caso", y
     * habrá resucitado el bug que dejó la búsqueda sin AND.
     */
    public function test_el_femenino_no_se_cuenta_y_es_a_sabiendas(): void
    {
        $this->cargarArticulos([
            'El uso de la vía pública requiere autorización municipal.',
        ]);

        $this->assertSame(
            0,
            $this->vocabulario->frecuencia('publico'),
            'Documentado a propósito: "publico" NO encuentra "pública". Si esta prueba empieza a '
            . 'fallar, alguien ha cambiado la forma de contar — y conviene comprobar que no ha '
            . 'vuelto a unir "casa" con "caso" por el camino.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Utilidades
    // ═══════════════════════════════════════════════════════════════════════

    /** @param array<string> $textos */
    private function cargarArticulos(array $textos): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Ley de Hacienda']);

        foreach ($textos as $i => $texto) {
            RegulacionNodo::create([
                'regulacion_id' => $ley->id,
                'tipo'          => RegulacionNodo::TIPO_ARTICULO,
                'numero'        => (string) ($i + 1),
                'texto'         => $texto,
                'contexto'      => null,
                'orden'         => $i + 1,
                'estado'        => RegulacionNodo::ESTADO_VIGENTE,
            ]);
        }

        // Las frecuencias se cachean una hora. Sin esto, la prueba leería las de la prueba
        // anterior y fallaría por una razón que no tiene nada que ver con lo que se está probando.
        Cache::flush();
    }
}
