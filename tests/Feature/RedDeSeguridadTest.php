<?php

namespace Tests\Feature;

use App\Services\BuscadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA RED DE SEGURIDAD: cuándo se vuelve a buscar con otras palabras.
 *
 * ══════════════════════════════════════════════════════════════════════
 * "ENCONTRÉ ALGO" NO ES "ENCONTRÉ LO CORRECTO". Y VAN TRES VECES.
 * ══════════════════════════════════════════════════════════════════════
 *
 * El buscador tiene una red: si la búsqueda falla, le pide a la IA otras palabras y vuelve a
 * buscar. El problema ha sido siempre CUÁNDO se despliega.
 *
 *   1ª vez:  la cascada AND→OR solo saltaba si el AND daba cero. Pero el AND encontraba el
 *            artículo EQUIVOCADO, así que el OR nunca se disparaba.
 *
 *   2ª vez:  el reformulador solo se llamaba si había CERO resultados. Y "cuánto se paga por
 *            comprar una casa" devolvía TREINTA resultados... todos basura.
 *
 *   3ª vez:  la condición pasó a ser "$destacada === null". Y el asistente, cuando no puede
 *            responder, NO devuelve null: devuelve una respuesta con confianza 'relacionada' que
 *            dice, con todas sus letras:
 *
 *                "No encontré una respuesta clara a tu pregunta. Encontré 2 documentos
 *                 relacionados, pero ninguno responde exactamente lo que preguntas."
 *
 *            Una RENDICIÓN CONFESADA. Y el sistema la trataba como un éxito.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL CASO QUE LO DESTAPÓ
 * ══════════════════════════════════════════════════════════════════════
 *
 * "cuánto cuesta la multa por extensión de la vía pública"
 *
 * La ley SÍ lo responde: artículo 88, fracción VII TER, "Extensión en la vía pública", con su
 * cuota. Pero la ley no lo llama MULTA: lo llama DERECHO. No te sanciona por extender tu negocio
 * a la banqueta — te cobra.
 *
 * El AND exigía (multa | sancion | infraccion | recargo), y la fracción VII TER no dice ninguna
 * de esas palabras. Fuera. Salieron dos artículos de relleno sobre pavimentación, el asistente se
 * rindió... y la red no se desplegó.
 *
 * Esta prueba fija la regla, para que no haya una cuarta vez.
 */
class RedDeSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private BuscadorService $buscador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buscador = app(BuscadorService::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Cuándo el sistema debe considerar que HA FALLADO
    // ═══════════════════════════════════════════════════════════════════════

    public function test_sin_respuesta_es_un_fallo(): void
    {
        $this->assertTrue(
            $this->buscador->elAsistenteNoRespondio(null),
            'Si no hay respuesta destacada, es que nadie pudo responder. Hay que volver a buscar.'
        );
    }

    /**
     * LA PRUEBA QUE IMPORTA. Sin ella, la red sigue mal tendida.
     */
    public function test_una_respuesta_relacionada_TAMBIEN_es_un_fallo(): void
    {
        $rendicion = [
            'definicion' => 'No encontré información sobre el monto a pagar. Las fuentes mencionan '
                          . 'que existe un impuesto, pero no especifican la tasa.',
            'confianza'  => 'relacionada',
        ];

        $this->assertTrue(
            $this->buscador->elAsistenteNoRespondio($rendicion),
            'Una respuesta con confianza "relacionada" es el asistente diciendo "esto NO responde '
            . "lo que preguntaste\".\n\n"
            . 'No es null, pero es un FRACASO. Si el sistema la trata como un éxito, la red de '
            . 'seguridad no se despliega y el ciudadano se va con dos artículos de relleno — que '
            . 'es exactamente lo que pasaba con "cuánto cuesta la multa por extensión de la vía '
            . 'pública".'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Y cuándo NO. La contraprueba: la red no puede dispararse siempre.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Sin esta prueba, poner `return true;` en el método pasaría las dos de arriba — y el buscador
     * llamaría a la IA en TODAS las búsquedas, incluidas las que ya funcionaban. Segundos de
     * espera y coste, para nada.
     */
    public function test_una_respuesta_generada_NO_es_un_fallo(): void
    {
        $respuesta = [
            'definicion' => 'El impuesto predial para casa habitación se calcula a razón de 2 al '
                          . 'millar anual sobre el valor catastral.',
            'confianza'  => 'generada',
        ];

        $this->assertFalse(
            $this->buscador->elAsistenteNoRespondio($respuesta),
            'El asistente RESPONDIÓ la pregunta. Volver a buscar sería tirar una llamada a la IA y '
            . 'varios segundos del ciudadano para reencontrar lo que ya está encontrado.'
        );
    }

    /**
     * Las definiciones curadas del diccionario jurídico no llevan el campo 'confianza' —las
     * escribió una persona, no una IA—. No pueden confundirse con un fallo.
     */
    public function test_una_definicion_curada_NO_es_un_fallo(): void
    {
        $definicion = [
            'termino'    => 'valor catastral',
            'definicion' => 'El valor que el Ayuntamiento asigna a un predio para efectos fiscales.',
        ];

        $this->assertFalse(
            $this->buscador->elAsistenteNoRespondio($definicion),
            'Una definición del diccionario jurídico la escribió una persona y no tiene campo '
            . '"confianza". Sin la comprobación explícita de "relacionada", un campo ausente '
            . 'podría tomarse por un fallo y disparar la IA sin motivo.'
        );
    }
}
