<?php

namespace Tests\Feature;

use App\Exceptions\FirmaDuplicadaException;
use App\Models\Dependencia;
use App\Models\Firma;
use App\Models\Tramite;
use App\Models\User;
use App\Services\FirmaDigitalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pruebas de la firma digital: lo que una firma promete y lo que de verdad cumple.
 *
 * ── Qué promete una firma, explicado desde cero ──────────────────────
 *
 * Cuando el sujeto obligado firma un trámite, el sistema hace una FOTO de los datos
 * clave (homoclave, nombre oficial, costo, estatus), la convierte en un texto —la
 * "cadena original"— y le calcula un hash SHA-256. Ese hash queda guardado.
 *
 * La promesa es esta: si alguien cambia el trámite después de firmarlo, al volver a
 * calcular el hash saldrá un número distinto, y el sistema podrá decir "esta firma
 * ya no vale, el documento se alteró".
 *
 * Esa promesa es TODA la razón de ser del módulo. Un acuse firmado que se puede
 * editar después sin que nadie se entere no es un acuse: es un papel.
 *
 * ── Lo que estas pruebas fijan ───────────────────────────────────────
 *
 * Las tres primeras son las importantes. Las demás protegen los bordes.
 */
class FirmaIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private FirmaDigitalService $firmas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firmas = app(FirmaDigitalService::class);
    }

    /**
     * El servicio necesita un Request para guardar la IP y el navegador de quien
     * firma. En una prueba no hay petición HTTP real, así que se fabrica una.
     */
    private function request(): Request
    {
        return Request::create('/firmas', 'POST', server: [
            'REMOTE_ADDR'    => '192.168.1.50',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);
    }

    private function tramiteListoParaFirma(): Tramite
    {
        return Tramite::factory()->enFirma()->create([
            'nombre_oficial' => 'Licencia de funcionamiento comercial',
            'homoclave'      => 'LPZ-T-DGGD-DSA-1',
        ]);
    }

    /** Firma un trámite como el sujeto obligado. Es el camino normal del sistema. */
    private function firmar(Tramite $tramite, ?User $quien = null, ?string $tipo = null): Firma
    {
        return $this->firmas->firmar(
            $tramite,
            $quien ?? User::factory()->create(),
            $tipo ?? Firma::TIPO_ACEPTACION_SUJETO,
            $this->request(),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. La promesa central
    // ═══════════════════════════════════════════════════════════════════════

    public function test_una_firma_recien_hecha_verifica_correctamente(): void
    {
        $tramite = $this->tramiteListoParaFirma();

        $firma = $this->firmar($tramite);

        $this->assertTrue(
            $this->firmas->verificarIntegridad($firma),
            'Una firma que nadie ha tocado debe verificar. Si esto falla, la cadena '
            . 'original no se puede reconstruir y NINGUNA firma será verificable.'
        );
    }

    /**
     * LA PRUEBA MÁS IMPORTANTE DE TODO EL MÓDULO.
     *
     * Si esta falla, el hash SHA-256 no está probando nada: es un sello sobre un
     * documento que se puede cambiar después.
     *
     * Antes de arreglar el servicio, esta prueba fallaba. verificarIntegridad() solo
     * comparaba la fila `firmas` consigo misma —re-hasheaba la cadena guardada y la
     * comparaba con el hash guardado— y nunca volvía a mirar el trámite. Así que un
     * trámite firmado al que se le cambiara el nombre oficial seguía dando
     * "firma válida".
     */
    public function test_editar_el_tramite_despues_de_firmarlo_invalida_la_firma(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $firma   = $this->firmar($tramite);

        // Alguien cambia el nombre oficial DESPUÉS de que el sujeto firmó.
        $tramite->update(['nombre_oficial' => 'Otro nombre completamente distinto']);

        $this->assertFalse(
            $this->firmas->verificarIntegridad($firma->fresh()),
            'El trámite cambió después de firmarse y la firma sigue dando por válida. '
            . 'Eso significa que se puede alterar el contenido de un documento firmado '
            . 'sin que nadie se entere.'
        );
    }

    /**
     * Lo mismo con el costo, que es el otro dato que va impreso en el acuse.
     *
     * Se prueba aparte del nombre a propósito: los campos sellados están enumerados a
     * mano en extraerDatosClaveDelFirmable(). Si alguien quita uno de esa lista, ese
     * campo pasa a ser editable después de firmar y nadie lo nota. Una prueba por
     * campo sellado es la única forma de detectarlo.
     */
    public function test_cambiar_el_costo_despues_de_firmar_invalida_la_firma(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $firma   = $this->firmar($tramite);

        $tramite->update(['cbu_unitario' => 999.99]);

        $this->assertFalse($this->firmas->verificarIntegridad($firma->fresh()));
    }

    /**
     * La otra mitad de la verificación: detectar que alguien manipuló la tabla
     * `firmas` directamente en la base, sin pasar por el sistema.
     *
     * Esta parte YA funcionaba antes del arreglo. Se prueba para que no se pierda.
     */
    public function test_manipular_la_cadena_original_en_la_base_invalida_la_firma(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $firma   = $this->firmar($tramite);

        // Alguien entra a la base y reescribe la cadena a mano.
        $firma->update(['cadena_original' => '{"firmable_data":{"nombre_oficial":"Mentira"}}']);

        $this->assertFalse($this->firmas->verificarIntegridad($firma->fresh()));
    }

    /**
     * Un cambio en un campo que NO está sellado no invalida la firma.
     *
     * Es tan importante como lo contrario. Si cualquier edición invalidara la firma,
     * el sistema estaría gritando "documento alterado" cada vez que alguien corrige
     * una falta de ortografía en un campo que no aparece en el acuse. Nadie haría caso
     * a la alarma, y entonces la alarma no sirve para nada.
     */
    public function test_cambiar_un_campo_no_sellado_no_invalida_la_firma(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $firma   = $this->firmar($tramite);

        // 'objetivo' no está en extraerDatosClaveDelFirmable(): no va en el acuse.
        $tramite->update(['objetivo' => 'Texto del objetivo corregido.']);

        $this->assertTrue(
            $this->firmas->verificarIntegridad($firma->fresh()),
            'Un campo que no forma parte del acuse no debería invalidar la firma.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Doble firma
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un doble clic en "Firmar" no puede producir dos firmas activas del mismo tipo.
     *
     * El sistema lo impide en dos capas, y las dos hacen falta:
     *   - El servicio comprueba antes de escribir (da un mensaje claro al usuario).
     *   - La base tiene un índice único parcial (frena la carrera de verdad, cuando
     *     dos peticiones simultáneas pasan las dos la comprobación).
     */
    public function test_no_se_puede_firmar_dos_veces_el_mismo_tipo(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $sujeto  = User::factory()->create();

        $this->firmar($tramite, $sujeto);

        $this->expectException(FirmaDuplicadaException::class);
        $this->firmar($tramite, $sujeto);
    }

    public function test_el_sujeto_y_el_enlace_si_pueden_firmar_el_mismo_tramite(): void
    {
        // Son tipos distintos: las dos firmas conviven. Es el flujo normal.
        $tramite = $this->tramiteListoParaFirma();

        $this->firmar($tramite, tipo: Firma::TIPO_ACEPTACION_SUJETO);
        $this->firmar($tramite, tipo: Firma::TIPO_ACEPTACION_ENLACE);

        $this->assertCount(2, $this->firmas->firmasActivas($tramite));
    }

    /**
     * El índice único de la base solo aplica a las firmas ACTIVAS.
     *
     * Tiene que ser así: un trámite puede firmarse, revocarse la firma, volverse a
     * firmar y volverse a revocar. Eso deja varias firmas revocadas del mismo tipo, y
     * es correcto — son el rastro de lo que pasó.
     */
    public function test_tras_revocar_se_puede_volver_a_firmar_el_mismo_tipo(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $admin   = User::factory()->create();

        $primera = $this->firmar($tramite);
        $this->firmas->revocar($primera, $admin, 'Se firmó por error.');

        $segunda = $this->firmar($tramite->fresh());

        $this->assertCount(1, $this->firmas->firmasActivas($tramite->fresh()));
        $this->assertTrue($this->firmas->verificarIntegridad($segunda));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Congelar y descongelar catálogos
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Al firmar, el trámite guarda una foto de los nombres de catálogo que usó. Si
     * mañana se renombra la dependencia, el documento firmado sigue diciendo lo que
     * decía. El sistema avisa de la diferencia, pero no cambia el documento.
     */
    public function test_al_firmar_se_congelan_los_nombres_de_catalogo(): void
    {
        $dependencia = Dependencia::factory()->create([
            'nombre' => 'Dirección General de Gobierno Digital',
            'siglas' => 'DGGD',
        ]);

        $tramite = Tramite::factory()->enFirma()->create(['dependencia_id' => $dependencia->id]);

        $this->firmar($tramite);

        // La dependencia se renombra DESPUÉS de la firma.
        $dependencia->update(['nombre' => 'Dirección de Gobierno Digital']);

        $tramite = $tramite->fresh();

        $this->assertSame(
            'Dirección General de Gobierno Digital',
            $tramite->nombreDeCatalogo('dependencia'),
            'El documento firmado debe seguir mostrando el nombre que tenía al firmarse.'
        );

        $this->assertTrue(
            $tramite->tieneCatalogosDesactualizados(),
            'El sistema debe DETECTAR el cambio para poder avisar a una persona.'
        );
    }

    /**
     * EL AGUJERO QUE ARREGLAMOS EN ESTA TANDA.
     *
     * Congelar tenía sentido mientras la firma existiera. Pero revocar() no
     * descongelaba, así que un trámite cuya única firma se revocaba se quedaba con la
     * foto de una firma que ya no existía. Dos consecuencias, las dos malas:
     *
     *   - Seguía mostrando el nombre viejo de la dependencia sin ninguna firma que lo
     *     justificara.
     *   - Al volver a firmar, congelarCatalogos() veía que ya había foto y no hacía
     *     nada: la firma nueva sellaba unos nombres que no eran los del documento en
     *     ese momento.
     */
    public function test_revocar_la_ultima_firma_descongela_los_catalogos(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $admin   = User::factory()->create();

        $firma = $this->firmar($tramite);
        $this->assertTrue($tramite->fresh()->catalogosCongelados());

        $this->firmas->revocar($firma, $admin, 'Se firmó por error.');

        $this->assertFalse(
            $tramite->fresh()->catalogosCongelados(),
            'Sin ninguna firma activa, el trámite no debe seguir congelado.'
        );
    }

    /**
     * Pero si QUEDA otra firma activa, NO se descongela. El documento sigue firmado
     * por alguien, así que su contenido sigue sellado.
     */
    public function test_revocar_una_firma_de_dos_no_descongela_nada(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $admin   = User::factory()->create();

        $delSujeto = $this->firmar($tramite, tipo: Firma::TIPO_ACEPTACION_SUJETO);
        $this->firmar($tramite, tipo: Firma::TIPO_ACEPTACION_ENLACE);

        $this->firmas->revocar($delSujeto, $admin, 'El sujeto firmó por error.');

        $this->assertTrue(
            $tramite->fresh()->catalogosCongelados(),
            'Todavía queda la firma del enlace: el documento sigue sellado.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Bordes
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Una firma a la que le falta la cadena original no se puede verificar. Se
     * devuelve false, no true.
     *
     * "No verificable" y "alterada" se tratan igual, y es deliberado: en ninguno de
     * los dos casos se puede AFIRMAR que la firma sea válida. Ante la duda sobre un
     * acto jurídico, la respuesta honesta es "no lo sé", y "no lo sé" no es "sí".
     */
    public function test_una_firma_sin_cadena_original_no_verifica(): void
    {
        $firma = Firma::factory()->create(['cadena_original' => null]);

        $this->assertFalse($this->firmas->verificarIntegridad($firma));
    }

    /**
     * Una firma creada a mano (con la factory, que mete un hash de relleno) NO debe
     * verificar.
     *
     * Si verificara, significaría que el hash no está comprobando nada.
     */
    public function test_una_firma_con_hash_inventado_no_verifica(): void
    {
        $firma = Firma::factory()->create();

        $this->assertFalse(
            $this->firmas->verificarIntegridad($firma),
            'Un hash de relleno pasó la verificación: el hash no está comprobando nada.'
        );
    }

    /** Revocar dos veces la misma firma no hace nada la segunda vez. */
    public function test_revocar_una_firma_ya_revocada_no_hace_nada(): void
    {
        $tramite = $this->tramiteListoParaFirma();
        $admin   = User::factory()->create();

        $firma = $this->firmar($tramite);
        $this->firmas->revocar($firma, $admin, 'Primer motivo.');
        $this->firmas->revocar($firma->fresh(), $admin, 'Segundo motivo.');

        $this->assertSame('Primer motivo.', $firma->fresh()->motivo_revocacion);
    }
}
