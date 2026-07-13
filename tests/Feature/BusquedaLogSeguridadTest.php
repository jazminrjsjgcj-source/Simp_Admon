<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BusquedaLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NO SE PUEDE VOTAR NI REGISTRAR CLICS SOBRE LAS BÚSQUEDAS DE OTRA PERSONA.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ ESTO IMPORTA MÁS DE LO QUE PARECE
 * ══════════════════════════════════════════════════════════════════════
 *
 * A primera vista, la bitácora del buscador parece inofensiva: registra qué busca la gente y si
 * el resultado le sirvió. ¿Qué más da si alguien la manipula?
 *
 * Da mucho. El docblock de BusquedaLogService lo dice con todas las letras:
 *
 *     "Estos datos son los inputs directos para un futuro modelo de ranking"
 *     "busqueda_feedback → qué resultado sirvió y cuál no (training labels)"
 *
 * VAS A ENTRENAR UN MODELO CON ESTOS DATOS.
 *
 * Y un buscador que aprende de datos manipulables SE PUEDE ENVENENAR. Alguien marca su trámite
 * como "útil" en cien consultas, y el ranking empieza a favorecerlo. Como el modelo se entrena
 * solo, NADIE SABRÁ POR QUÉ el buscador se volvió raro.
 *
 * No es un bug de hoy. Es un bug de dentro de un año, cuando el modelo esté entrenado y nadie
 * recuerde de dónde salieron los datos.
 *
 * ── El agujero, en concreto ──
 *
 * El controlador validaba así:
 *
 *     $request->validate(['log_id' => 'required|integer']);
 *
 * Comprueba el TIPO. No comprueba la PROPIEDAD. Y ese log_id viene del navegador.
 *
 * Con F12:
 *
 *     for (let i = 1; i < 100000; i++) {
 *         fetch('/buscar/feedback', { body: JSON.stringify({ log_id: i, util: false }) });
 *     }
 *
 * Cien mil votos negativos sobre las búsquedas de todo el Ayuntamiento.
 *
 * ── Y quién lo encontró ──
 *
 * Nadie lo buscaba. Lo encontró ActualizacionPorIdSeguraTest, el centinela que vigila las
 * actualizaciones por id sin revisar. Se puso rojo y dijo: "esto es nuevo y nadie lo ha mirado".
 *
 * Tenía razón.
 */
class BusquedaLogSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private BusquedaLogService $log;
    private User $sonia;
    private User $marcos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log    = app(BusquedaLogService::class);
        $this->sonia  = User::factory()->create();
        $this->marcos = User::factory()->create();
    }

    /** Una búsqueda hecha por un usuario concreto. */
    private function busquedaDe(User $usuario, string $consulta = 'licencia de funcionamiento'): int
    {
        $this->actingAs($usuario);

        return $this->log->registrarBusqueda(
            consulta:        $consulta,
            regulacionIds:   null,
            tipos:           null,
            modo:            'completo',
            totalResultados: 5,
            tiempoMs:        120,
            tieneDestacada:  false,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El clic
    // ═══════════════════════════════════════════════════════════════════════

    /** Marcar un clic en la PROPIA búsqueda funciona, como siempre. */
    public function test_registrar_un_clic_en_la_propia_busqueda_funciona(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->sonia);
        $this->log->registrarClic($logId, 'articulo', 42);

        $this->assertDatabaseHas('busqueda_log', [
            'id'                       => $logId,
            'resultado_clickeado_tipo' => 'articulo',
            'resultado_clickeado_id'   => 42,
        ]);
    }

    /**
     * LA PRUEBA DEL AGUJERO.
     *
     * Marcos intenta registrar un clic en la búsqueda de Sonia. No pasa nada.
     */
    public function test_no_se_puede_registrar_un_clic_en_la_busqueda_de_otro(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->marcos);
        $this->log->registrarClic($logId, 'tramite', 999);

        // La búsqueda de Sonia sigue sin ningún clic registrado.
        $this->assertDatabaseHas('busqueda_log', [
            'id'                       => $logId,
            'resultado_clickeado_tipo' => null,
            'resultado_clickeado_id'   => null,
        ]);
    }

    /**
     * Y NO REVIENTA NADA.
     *
     * Esto es deliberado, y es distinto de lo que hicimos con el requisito ajeno.
     *
     * Allí se ABORTA el guardado: alguien manipuló un formulario, y si falseó un campo no hay
     * razón para confiar en el resto.
     *
     * Aquí NO. Esto es una bitácora PASIVA: observa, no decide nada. Reventar la navegación de un
     * ciudadano por un registro de clic sería mucho peor que el bug que estamos cerrando.
     *
     * Se ignora, se registra en el log, y la vida sigue.
     */
    public function test_un_clic_ajeno_no_revienta_la_navegacion(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->marcos);

        // No debe lanzar ninguna excepción.
        $this->log->registrarClic($logId, 'tramite', 999);
        $this->log->registrarClic(999999, 'tramite', 1); // ni siquiera existe

        $this->assertTrue(true);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. El voto
    // ═══════════════════════════════════════════════════════════════════════

    /** Votar sobre la propia búsqueda funciona. */
    public function test_votar_sobre_la_propia_busqueda_funciona(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->sonia);

        $ok = $this->log->registrarFeedback($logId, 'licencia', 'articulo', 42, 'Artículo 15', true);

        $this->assertTrue($ok);
        $this->assertDatabaseHas('busqueda_feedback', [
            'busqueda_log_id' => $logId,
            'user_id'         => $this->sonia->id,
            'util'            => true,
        ]);
    }

    /**
     * NO SE PUEDE VOTAR SOBRE LA BÚSQUEDA DE OTRO.
     *
     * Este es el agujero grande: estos votos son las "training labels" de un futuro modelo de
     * ranking. Un buscador entrenado con votos manipulados favorece lo que alguien quiso empujar,
     * y nadie sabrá por qué.
     */
    public function test_no_se_puede_votar_sobre_la_busqueda_de_otro(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->marcos);

        $ok = $this->log->registrarFeedback($logId, 'licencia', 'articulo', 42, 'Artículo 15', false);

        $this->assertFalse($ok, 'Marcos votó sobre una búsqueda que no es suya.');

        $this->assertDatabaseMissing('busqueda_feedback', [
            'busqueda_log_id' => $logId,
            'user_id'         => $this->marcos->id,
        ]);
    }

    /**
     * NO SE PUEDE VOTAR MIL VECES LO MISMO.
     *
     * El candado anterior impide votar en búsquedas ajenas. Este impide INFLAR LAS PROPIAS.
     *
     * Los dos hacen falta, y por separado no valen: sin este, basta con hacer UNA búsqueda y
     * votarla diez mil veces con un bucle de fetch(). Mil votos "útil" sobre un trámite lo
     * empujarían al primer puesto en cuanto el modelo se entrene.
     */
    public function test_un_usuario_no_puede_votar_mil_veces_el_mismo_resultado(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->sonia);

        for ($i = 0; $i < 10; $i++) {
            $this->log->registrarFeedback($logId, 'licencia', 'articulo', 42, 'Artículo 15', true);
        }

        $votos = DB::table('busqueda_feedback')
            ->where('busqueda_log_id', $logId)
            ->where('resultado_id', 42)
            ->count();

        $this->assertSame(
            1,
            $votos,
            'Un solo usuario metió diez votos sobre el mismo resultado. Con un bucle de fetch() '
            . 'podría meter diez mil, y empujar cualquier trámite al primer puesto del ranking.'
        );
    }

    /**
     * Pero SÍ puede cambiar de opinión.
     *
     * Si vota "no me sirvió" y luego "sí me sirvió", el voto se ACTUALIZA. No se duplica.
     *
     * Es lo que un usuario espera al pulsar "Sí" después de haber pulsado "No" — y sin esto, el
     * candado anterior le impediría corregirse, que sería un bug distinto y bastante molesto.
     */
    public function test_un_usuario_puede_cambiar_su_voto(): void
    {
        $logId = $this->busquedaDe($this->sonia);

        $this->actingAs($this->sonia);

        $this->log->registrarFeedback($logId, 'licencia', 'articulo', 42, 'Artículo 15', false);
        $this->log->registrarFeedback($logId, 'licencia', 'articulo', 42, 'Artículo 15', true);

        $this->assertDatabaseHas('busqueda_feedback', [
            'busqueda_log_id' => $logId,
            'resultado_id'    => 42,
            'util'            => true,
        ]);

        $this->assertSame(
            1,
            DB::table('busqueda_feedback')->where('busqueda_log_id', $logId)->count(),
            'Cambiar de voto creó una fila nueva en vez de actualizar la existente.'
        );
    }
}
