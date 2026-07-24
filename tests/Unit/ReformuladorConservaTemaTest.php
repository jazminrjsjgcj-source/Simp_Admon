<?php

namespace Tests\Unit;

use App\Services\ReformuladorConsultaService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * EL GUARDIÁN DE "CAMBIÓ DE TEMA" NO PUEDE MATAR UNA TRADUCCIÓN POR UNA TILDE.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ HACE ESTE GUARDIÁN, Y POR QUÉ IMPORTA QUE ACIERTE
 * ══════════════════════════════════════════════════════════════════════
 *
 * El reformulador con IA traduce la pregunta del ciudadano al vocabulario de la ley. Y su riesgo
 * grave es que CAMBIE DE TEMA:
 *
 *     "cuánto pago por la basura"  →  "impuesto predial"
 *
 * Sería una traducción plausible —los dos son cobros municipales— y produciría una respuesta
 * perfectamente citada sobre el impuesto EQUIVOCADO. El guardián conservaElTema() lo frena: exige
 * que la propuesta comparta al menos una raíz significativa con la pregunta original.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL BUG: LA TILDE
 * ══════════════════════════════════════════════════════════════════════
 *
 * El ciudadano escribe SIN acentos ("via publica"); la IA responde CON ellos ("vía pública"). El
 * guardián comparaba en crudo, y entonces:
 *
 *     "publi" (del ciudadano)  ≠  "públi" (de la IA)   →  no comparten nada  →  DESCARTA
 *
 * Con la pregunta real "cuánto cuesta la multa por extensión de la vía pública", la IA propuso
 * "ocupación vía pública" —la traducción CORRECTA, porque la ley llama a la fracción VII TER
 * "Extensión en la vía pública" y la cobra como ocupación—. Y el guardián la mató por "CAMBIÓ DE
 * TEMA", cuando la única raíz común, "publi/públi", solo se diferenciaba en una tilde.
 *
 * El arreglo: comparar sin acentos (Str::ascii), igual que hace el buscador en todo lo demás.
 */
class ReformuladorConservaTemaTest extends TestCase
{
    private function conservaElTema(string $reformulada, string $original): bool
    {
        // conservaElTema() es privado: es un detalle interno del reformulador. Se prueba por
        // reflexión porque es la pieza donde vivía el bug, y merece su red propia.
        $metodo = new ReflectionMethod(ReformuladorConsultaService::class, 'conservaElTema');
        $metodo->setAccessible(true);

        return $metodo->invoke(app(ReformuladorConsultaService::class), $reformulada, $original);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. EL CASO ROTO: una tilde no puede significar "cambió de tema"
    // ═══════════════════════════════════════════════════════════════════════

    public function test_una_tilde_no_descarta_una_reformulacion_correcta(): void
    {
        $this->assertTrue(
            $this->conservaElTema(
                'sanción ocupación vía pública',                          // la IA, con tildes
                'cuanto cuesta la multa por extension de la via publica'  // el ciudadano, sin tildes
            ),
            'La propuesta y la pregunta comparten "pública/publica" — la MISMA palabra, con y sin '
            . "tilde.\n\n"
            . 'Si el guardián las trata como distintas, descarta la única reformulación que rescata '
            . 'el caso (la ley llama "ocupación de la vía pública" a lo que el ciudadano llama '
            . '"multa por extensión"). Comparar sin acentos es lo mismo que ya hace el buscador en '
            . 'todas partes.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. LA CONTRAPRUEBA: el guardián SIGUE guardando
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Sin esta prueba, "arreglar" el guardián haciéndolo devolver siempre true pasaría la de
     * arriba — y abriría la puerta al fallo GRAVE que el guardián existe para frenar.
     */
    public function test_un_cambio_de_tema_real_si_se_descarta(): void
    {
        $this->assertFalse(
            $this->conservaElTema('impuesto predial urbano', 'cuanto pago por la basura de mi negocio'),
            'Esto SÍ cambia de tema: "basura" y "predial" no comparten ninguna raíz. Es el fallo '
            . 'grave que el guardián frena — una respuesta perfectamente citada sobre el impuesto '
            . 'equivocado. El arreglo de la tilde NO puede abrir esta puerta.'
        );
    }

    /**
     * Y una pareja que comparte tema por una palabra sin acento, para confirmar que el camino
     * normal —el 99% de los casos, sin tildes de por medio— sigue funcionando.
     */
    public function test_comparten_una_palabra_llana_y_conservan_el_tema(): void
    {
        $this->assertTrue(
            $this->conservaElTema('derecho comercio ambulante', 'permiso para ambulantes'),
            '"ambulante" está en las dos, sin acentos de por medio. El camino normal no se toca.'
        );
    }

    /**
     * LÍMITE CONOCIDO, documentado a propósito. Una traducción legítima que NO conserva ninguna
     * palabra —"basura" → "residuos sólidos"— también se descarta. El guardián es una heurística
     * de raíces: no sabe que basura y residuos son lo mismo.
     *
     * No es este bug, y no se arregla aquí: para eso está el tesauro (que sí sabe basura→residuos)
     * y el asistente, que lee los artículos. Se deja escrito para que nadie confunda este límite
     * con el de la tilde, que sí era un fallo.
     */
    public function test_limite_conocido_una_traduccion_sin_palabra_comun_se_descarta(): void
    {
        $this->assertFalse(
            $this->conservaElTema('residuos sólidos recolección', 'cuanto pago por la basura'),
            'LÍMITE CONOCIDO, no un bug: "residuos" es la traducción correcta de "basura", pero no '
            . 'comparten raíz, así que el guardián la descarta. Eso lo resuelve el tesauro '
            . '(basura → residuos sólidos), no este guardián. Aquí solo se arregló la tilde.'
        );
    }
}
