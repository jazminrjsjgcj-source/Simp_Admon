<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\Firma;
use App\Models\Tramite;
use App\Models\TramiteDerecho;
use App\Models\User;
use App\Services\CostoBurocraticoService;
use App\Services\FirmaDigitalService;
use Database\Seeders\AclSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Los avisos de las fichas: lo que el sistema SABE y la pantalla tiene que DECIR.
 *
 * ── Por qué estas pruebas son distintas a todas las demás ────────────
 *
 * Todo lo que hemos probado hasta ahora vive en el servidor: que el hash detecte una
 * edición, que el costo se calcule bien, que los catálogos se congelen al firmar.
 *
 * Nada de eso sirve si la pantalla no lo cuenta.
 *
 * Y ese es un fallo que ya nos ha pasado DOS veces en este proyecto:
 *
 *   1. tieneCatalogosDesactualizados() funcionaba y estaba probado. Ninguna vista lo
 *      pintaba. Un trámite firmado cuya dependencia se renombró mostraba el nombre viejo
 *      y nadie se enteraba de que había discrepancia.
 *
 *   2. El servicio de costo sabía cuándo no podía calcular el costo de espera. La ficha
 *      pintaba un $0.00 indistinguible del de un trámite que se resuelve en el acto.
 *
 * En los dos casos, TODAS las pruebas estaban en verde. Porque todas probaban el servidor.
 *
 * ── Qué prueban estas ────────────────────────────────────────────────
 *
 * Se pide la página por HTTP y se mira el HTML que sale. Es la única forma de saber que el
 * usuario ve lo que tiene que ver. Si el blade se rompe, si alguien borra el @if, si el
 * método cambia de nombre — estas pruebas se ponen rojas. Las otras no.
 *
 * ── Un detalle importante del diseño de estas pruebas ────────────────
 *
 * Cada aviso se prueba DOS veces: una para comprobar que SALE cuando debe, y otra para
 * comprobar que NO SALE cuando no debe.
 *
 * Sin la segunda, un blade que enseñara el aviso SIEMPRE pasaría la prueba tan tranquilo.
 * Y un sistema que grita "¡catálogo desactualizado!" en todos los trámites del municipio
 * es igual de inútil que uno que no lo grita nunca: la gente aprende a ignorarlo.
 */
class AvisosEnLaFichaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // El admin ve cualquier trámite de cualquier dependencia: así las pruebas se centran
        // en el AVISO y no en los permisos, que ya tienen sus propias pruebas.
        $this->seed(AclSeeder::class);
        $this->admin = User::factory()->create(['rol' => User::ROL_ADMIN]);
    }

    private function firmas(): FirmaDigitalService
    {
        return app(FirmaDigitalService::class);
    }

    private function costos(): CostoBurocraticoService
    {
        return app(CostoBurocraticoService::class);
    }

    private function request(): Request
    {
        return Request::create('/firmas', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Aviso: el costo de espera no se pudo calcular
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un trámite con derechos pero SIN parámetros económicos cargados.
     *
     * El CBD sale bien (los derechos son pesos contantes). El costo de espera no se puede
     * calcular: hacen falta el PIB, la población y la tasa libre de riesgo, y no están.
     */
    private function tramiteConCostoDeEsperaSinCalcular(): Tramite
    {
        // A propósito NO se siembran los parámetros económicos.
        $tramite = Tramite::factory()->create([
            'dirigido_a'                => 'fisica',
            'plazo_resolucion_cantidad' => 20,
            'plazo_resolucion_unidad'   => 'habiles',
            'volumen_anual'             => 100,
        ]);

        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho de licencia',
            'monto'       => 500,
            'unidad'      => 'PESOS',
            'es_variable' => false,
        ]);

        $this->costos()->recalcularYGuardar($tramite->fresh());

        return $tramite->fresh();
    }

    public function test_la_ficha_avisa_cuando_el_costo_de_espera_no_se_pudo_calcular(): void
    {
        $tramite = $this->tramiteConCostoDeEsperaSinCalcular();

        $respuesta = $this->actingAs($this->admin)->get(route('tramites.show', $tramite));

        $respuesta->assertOk();
        $respuesta->assertSee('El costo de espera no se pudo calcular.');
        $respuesta->assertSee('Estos totales están incompletos.');
    }

    /**
     * El costo de espera NO se pinta como $0.00.
     *
     * Un cero es una AFIRMACIÓN: "esperar no cuesta nada". Y eso no es lo que el sistema
     * sabe; lo que sabe es que no lo sabe. La ficha dice "Sin calcular".
     */
    public function test_el_costo_de_espera_sin_calcular_no_se_pinta_como_cero(): void
    {
        $tramite = $this->tramiteConCostoDeEsperaSinCalcular();

        $this->actingAs($this->admin)
            ->get(route('tramites.show', $tramite))
            ->assertSee('Sin calcular');
    }

    /**
     * LA MITAD QUE NADIE ESCRIBE.
     *
     * Con los parámetros cargados, el costo se calcula y el aviso NO aparece.
     *
     * Sin esta prueba, un blade que enseñara el aviso SIEMPRE pasaría la prueba anterior tan
     * tranquilo. Y un sistema que avisa de todo es igual de inútil que uno que no avisa de
     * nada: la gente aprende a ignorar el aviso.
     */
    public function test_con_los_parametros_cargados_la_ficha_no_muestra_ningun_aviso_de_costo(): void
    {
        $this->seed(\Database\Seeders\ParametrosCostoBurocraticoSeeder::class);

        $tramite = $this->tramiteConCostoDeEsperaSinCalcular(); // ahora SÍ se puede calcular

        $respuesta = $this->actingAs($this->admin)->get(route('tramites.show', $tramite));

        $respuesta->assertOk();
        $respuesta->assertDontSee('El costo de espera no se pudo calcular.');
        $respuesta->assertDontSee('Estos totales están incompletos.');
    }

    /**
     * El detalle de la ACCIÓN DE AGENDA también hereda el costo del trámite vinculado, y
     * también tiene que avisar.
     *
     * Aquí el riesgo es MAYOR que en la ficha del trámite: allí el CBT viene con su desglose,
     * su umbral y su impacto — hay contexto. Aquí aparece suelto, en una rejilla de cinco
     * cifras. Y un número sin contexto se lee como un hecho.
     *
     * Esta era, de hecho, la pantalla que se había olvidado.
     */
    public function test_la_agenda_avisa_cuando_el_costo_del_tramite_esta_incompleto(): void
    {
        $tramite = $this->tramiteConCostoDeEsperaSinCalcular();

        $accion = AccionAgenda::factory()->create([
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $tramite->dependencia_id,
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('agenda.show', $accion));

        $respuesta->assertOk();
        $respuesta->assertSee('Estas cifras están incompletas.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Aviso: un catálogo cambió después de firmar
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Firma un trámite y DESPUÉS renombra su dependencia.
     *
     * El trámite sigue mostrando el nombre viejo, y eso es lo correcto: un documento firmado
     * no puede cambiar de contenido por su cuenta. Pero el sistema tiene que AVISAR, para que
     * una persona decida si hay que rehacerlo.
     */
    private function tramiteFirmadoConDependenciaRenombrada(): Tramite
    {
        $dependencia = Dependencia::factory()->create([
            'nombre' => 'Dirección General de Gobierno Digital',
            'siglas' => 'DGGD',
        ]);

        $tramite = Tramite::factory()->enFirma()->create(['dependencia_id' => $dependencia->id]);

        $this->firmas()->firmar(
            $tramite,
            User::factory()->create(),
            Firma::TIPO_ACEPTACION_SUJETO,
            $this->request(),
        );

        // El cambio ocurre DESPUÉS de la firma.
        $dependencia->update(['nombre' => 'Dirección de Gobierno Digital']);

        return $tramite->fresh();
    }

    public function test_la_ficha_avisa_si_un_catalogo_cambio_despues_de_firmar(): void
    {
        $tramite = $this->tramiteFirmadoConDependenciaRenombrada();

        $respuesta = $this->actingAs($this->admin)->get(route('tramites.show', $tramite));

        $respuesta->assertOk();
        $respuesta->assertSee('Un catálogo cambió de nombre después de que este trámite se firmara.');

        // El aviso enseña los DOS nombres. Un aviso que dijera solo "algo cambió" obligaría a
        // ir a buscar qué, y nadie va: se ignora el aviso y se sigue.
        $respuesta->assertSee('Dirección General de Gobierno Digital'); // el que decía al firmar
        $respuesta->assertSee('Dirección de Gobierno Digital');         // el que dice ahora
    }

    /** Un trámite firmado al que nadie ha tocado nada NO muestra el aviso. */
    public function test_un_tramite_firmado_sin_cambios_no_muestra_ningun_aviso(): void
    {
        $tramite = Tramite::factory()->enFirma()->create();

        $this->firmas()->firmar(
            $tramite,
            User::factory()->create(),
            Firma::TIPO_ACEPTACION_SUJETO,
            $this->request(),
        );

        $this->actingAs($this->admin)
            ->get(route('tramites.show', $tramite->fresh()))
            ->assertDontSee('Un catálogo cambió de nombre');
    }

    /**
     * UN CASO QUE SE ESCAPA FÁCIL.
     *
     * Un trámite SIN FIRMAR cuya dependencia se renombra NO debe mostrar el aviso.
     *
     * ¿Por qué? Porque un trámite sin firmar no tiene foto de catálogos: muestra los nombres
     * vivos, que ya son los nuevos. No hay ninguna discrepancia que avisar.
     *
     * Si el aviso se disparara aquí, saldría en TODOS los borradores del municipio cada vez
     * que alguien corrigiera una falta de ortografía en el nombre de una dependencia. La gente
     * aprendería a ignorarlo, y entonces tampoco lo vería el día que sí importa.
     *
     * La condición del blade es `catalogosCongelados() && tieneCatalogosDesactualizados()`.
     * Esta prueba protege la primera mitad, que es la que se olvida.
     */
    public function test_un_tramite_sin_firmar_no_avisa_aunque_cambie_el_catalogo(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Nombre viejo']);

        $tramite = Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'estatus'        => Tramite::ESTATUS_BORRADOR, // sin firmar
        ]);

        $dependencia->update(['nombre' => 'Nombre nuevo']);

        $this->actingAs($this->admin)
            ->get(route('tramites.show', $tramite->fresh()))
            ->assertDontSee('Un catálogo cambió de nombre');
    }

    /** La acción de agenda firmada también avisa: AccionAgenda congela dependencia y unidad. */
    public function test_la_agenda_avisa_si_un_catalogo_cambio_despues_de_firmar(): void
    {
        $dependencia = Dependencia::factory()->create([
            'nombre' => 'Dirección General de Gobierno Digital',
            'siglas' => 'DGGD',
        ]);

        $tramite = Tramite::factory()->completado()->create(['dependencia_id' => $dependencia->id]);

        $accion = AccionAgenda::factory()->create([
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $dependencia->id,
            'estatus'        => AccionAgenda::ESTATUS_EN_FIRMA,
        ]);

        $this->firmas()->firmar(
            $accion,
            User::factory()->create(),
            Firma::TIPO_ACEPTACION_SUJETO,
            $this->request(),
        );

        $dependencia->update(['nombre' => 'Dirección de Gobierno Digital']);

        $respuesta = $this->actingAs($this->admin)->get(route('agenda.show', $accion->fresh()));

        $respuesta->assertOk();
        $respuesta->assertSee('Un catálogo cambió de nombre después de que esta acción se firmara.');
    }
}
