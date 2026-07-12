<?php

namespace Tests\Feature;

use App\Exceptions\RequisitoAjenoException;
use App\Models\Dependencia;
use App\Models\Requisito;
use App\Models\Tramite;
use App\Services\TramiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NO SE PUEDE MODIFICAR UN REQUISITO DE OTRO TRÁMITE.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL ATAQUE, CONTADO CON NOMBRES
 * ══════════════════════════════════════════════════════════════════════
 *
 * Sonia es enlace de la Dirección de COMERCIO. Está editando su trámite "Licencia de
 * funcionamiento".
 *
 * Al lado, sin que ella tenga nada que ver, existe el "Permiso de construcción" de DESARROLLO
 * URBANO, con un requisito llamado "Dictamen estructural" — una revisión de seguridad de quince
 * días.
 *
 * El formulario de edición de Sonia trae sus requisitos con el id en un campo oculto:
 *
 *     <input type="hidden" name="requisitos[1][id]" value="41">
 *
 * Sonia abre F12, cambia ese 41 por el 88 —el id del Dictamen estructural—, escribe "Ninguno,
 * trámite exprés" en el nombre, y guarda. Un guardado normal, de su propio trámite.
 *
 * Y el servicio hacía esto:
 *
 *     Requisito::where('id', $req['id'])->update($datos);
 *
 * Buscaba el requisito 88 POR SU ID Y NADA MÁS. Lo encontraba. Lo sobrescribía.
 *
 * Resultado: una revisión estructural de quince días, en un trámite de otra dirección, convertida
 * en "trámite exprés" y cero días. Desde una sesión legítima. Sin permisos especiales. Sin dejar
 * rastro en ningún sitio.
 *
 * Y nadie se enteraría: Desarrollo Urbano no recibe ningún aviso, y el requisito no aparece en
 * ningún log. Solo lo descubrirían si alguien mirara ese requisito y notara que dice una tontería.
 *
 * Es una vulnerabilidad clásica: referencia directa a objeto sin control de propiedad.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ SE ABORTA Y NO SE IGNORA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Ignorar el id intruso también cerraría el agujero, y era más simple: el `update` con el candado
 * puesto no encontraría nada, y el guardado seguiría.
 *
 * Pero eso confunde dos cosas que no son iguales:
 *
 *     UN ID QUE NO CUADRA NO ES UN DATO RARO. ES UN FORMULARIO MANIPULADO.
 *
 * El formulario de PUNTA nunca manda el id de un requisito ajeno. Si llega uno, alguien lo puso
 * ahí a mano. Tratarlo como "un dato que ignoramos" es tratar un intento de manipulación como si
 * fuera una errata.
 *
 * Y si alguien manipuló UN campo del formulario, no hay ninguna razón para confiar en el resto.
 */
class RequisitoAjenoTest extends TestCase
{
    use RefreshDatabase;

    private TramiteService $tramites;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tramites = app(TramiteService::class);
    }

    /** Un trámite de una dependencia concreta, con sus propios requisitos. */
    private function tramiteDe(string $dependencia, array $requisitos = []): Tramite
    {
        $dep = Dependencia::factory()->create(['nombre' => $dependencia]);

        $tramite = Tramite::factory()->create([
            'dependencia_id' => $dep->id,
            'nombre_oficial' => "Trámite de {$dependencia}",
        ]);

        foreach ($requisitos as $i => $nombre) {
            Requisito::create([
                'tramite_id'      => $tramite->id,
                'nombre'          => $nombre,
                'orden'           => $i + 1,
                'dias_estimados'  => 15,
                'tiene_costo'     => false,
                'costo_variable'  => false,
                'costo_requisito' => 0,
            ]);
        }

        return $tramite->fresh();
    }

    /** Un requisito tal y como lo manda el formulario de edición: con su id oculto. */
    private function comoLoMandaElFormulario(int $id, string $nombre, int $dias = 0): array
    {
        return [
            'id'      => $id,
            'nombre'  => $nombre,
            'tipo'    => ['documento'],
            'dias'    => $dias,
            'horas'   => 0,
            'minutos' => 0,
        ];
    }

    private function guardar(Tramite $tramite, array $requisitos): void
    {
        $this->tramites->actualizar(
            tramite:     $tramite,
            datos:       ['nombre_oficial' => $tramite->nombre_oficial],
            derechos:    [],
            requisitos:  $requisitos,
            fichaPortal: [],
            procesos:    [],
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El ataque
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA QUE JUSTIFICA TODO ESTE ARCHIVO.
     *
     * Sonia manda el id de un requisito de otra dependencia. El guardado se aborta.
     */
    public function test_no_se_puede_modificar_el_requisito_de_otro_tramite(): void
    {
        $comercio = $this->tramiteDe('Comercio', ['Identificación oficial']);
        $urbano   = $this->tramiteDe('Desarrollo Urbano', ['Dictamen estructural']);

        $ajeno = Requisito::where('tramite_id', $urbano->id)->first();

        $this->expectException(RequisitoAjenoException::class);

        // Sonia guarda SU trámite, pero mete el id del requisito de Desarrollo Urbano.
        $this->guardar($comercio, [
            $this->comoLoMandaElFormulario($ajeno->id, 'Ninguno, trámite exprés', 0),
        ]);
    }

    /**
     * Y el requisito ajeno NO se toca. Ni un campo.
     *
     * Es la otra mitad, y es la que de verdad importa: que se lance una excepción está muy bien,
     * pero lo que hay que garantizar es que el dato de la otra dependencia siga intacto.
     *
     * Una excepción lanzada DESPUÉS de haber escrito no protege nada.
     */
    public function test_el_requisito_ajeno_no_se_modifica(): void
    {
        $comercio = $this->tramiteDe('Comercio', ['Identificación oficial']);
        $urbano   = $this->tramiteDe('Desarrollo Urbano', ['Dictamen estructural']);

        $ajeno = Requisito::where('tramite_id', $urbano->id)->first();

        try {
            $this->guardar($comercio, [
                $this->comoLoMandaElFormulario($ajeno->id, 'Ninguno, trámite exprés', 0),
            ]);
        } catch (RequisitoAjenoException $e) {
            // Esperada.
        }

        $ajeno = $ajeno->fresh();

        $this->assertSame('Dictamen estructural', $ajeno->nombre, 'El requisito ajeno se sobrescribió.');
        $this->assertSame(15, (int) $ajeno->dias_estimados, 'Le cambiaron los días estimados.');
        $this->assertSame($urbano->id, $ajeno->tramite_id, 'Y sigue siendo de su trámite.');
    }

    /**
     * EL GUARDADO ENTERO SE DESHACE, no solo la fila intrusa.
     *
     * Sonia manda dos requisitos: uno suyo, renombrado legítimamente, y uno ajeno. El servicio
     * corre dentro de una transacción, así que al abortar se deshace TODO — incluido el cambio
     * legítimo.
     *
     * Es deliberado y no es un daño colateral: si alguien manipuló UN campo del formulario, no hay
     * ninguna razón para confiar en el resto. Guardar "la parte buena" de un envío manipulado es
     * decidir por tu cuenta qué partes de un ataque son inofensivas.
     */
    public function test_el_guardado_entero_se_deshace_no_solo_la_fila_intrusa(): void
    {
        $comercio = $this->tramiteDe('Comercio', ['Identificación oficial']);
        $urbano   = $this->tramiteDe('Desarrollo Urbano', ['Dictamen estructural']);

        $propio = Requisito::where('tramite_id', $comercio->id)->first();
        $ajeno  = Requisito::where('tramite_id', $urbano->id)->first();

        try {
            $this->guardar($comercio, [
                $this->comoLoMandaElFormulario($propio->id, 'Identificación oficial vigente'), // legítimo
                $this->comoLoMandaElFormulario($ajeno->id,  'Ninguno, trámite exprés'),        // intruso
            ]);
        } catch (RequisitoAjenoException $e) {
            // Esperada.
        }

        $this->assertSame(
            'Identificación oficial',
            $propio->fresh()->nombre,
            'El cambio legítimo se guardó pese a que el envío venía manipulado. La transacción '
            . 'debería haberlo deshecho todo.'
        );
    }

    /**
     * Un id que NO EXISTE se trata igual que uno ajeno.
     *
     * Y tiene que ser así, porque desde fuera son indistinguibles: el formulario de PUNTA no manda
     * ids inventados. Si llega uno, alguien está tanteando —probando números a ver cuáles existen
     * y de quién son—, y eso es exactamente lo que hay que detectar.
     *
     * Distinguir "no existe" de "es de otro" solo le diría al atacante cuáles ha acertado.
     */
    public function test_un_id_inventado_tambien_aborta_el_guardado(): void
    {
        $comercio = $this->tramiteDe('Comercio', ['Identificación oficial']);

        $this->expectException(RequisitoAjenoException::class);

        $this->guardar($comercio, [
            $this->comoLoMandaElFormulario(999999, 'Requisito fantasma'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. El uso normal sigue funcionando
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA MITAD QUE PROTEGE DE VERDAD.
     *
     * Un enlace editando SUS PROPIOS requisitos sigue pudiendo hacerlo, exactamente igual que
     * antes.
     *
     * Sin esta prueba, un candado que rechazara TODOS los ids —o que se hubiera puesto en el sitio
     * equivocado— pasaría las cuatro pruebas anteriores tan tranquilo… y dejaría a todo el
     * municipio sin poder editar un solo requisito.
     *
     * Un arreglo de seguridad que rompe el uso normal no es un arreglo: es un fallo distinto, y
     * probablemente peor.
     */
    public function test_editar_los_requisitos_propios_sigue_funcionando(): void
    {
        $comercio = $this->tramiteDe('Comercio', ['Identificación oficial', 'Comprobante de domicilio']);

        $propios = Requisito::where('tramite_id', $comercio->id)->orderBy('id')->get();

        $this->guardar($comercio, [
            $this->comoLoMandaElFormulario($propios[0]->id, 'Identificación oficial vigente'),
            $this->comoLoMandaElFormulario($propios[1]->id, 'Comprobante de domicilio'),
        ]);

        $despues = Requisito::where('tramite_id', $comercio->id)->orderBy('id')->get();

        $this->assertSame('Identificación oficial vigente', $despues[0]->nombre);
        $this->assertSame($propios[0]->id, $despues[0]->id, 'Debe ser el MISMO requisito, renombrado.');
        $this->assertCount(2, $despues);
    }

    /** Y añadir requisitos nuevos (sin id) también sigue funcionando. */
    public function test_agregar_requisitos_nuevos_sigue_funcionando(): void
    {
        $comercio = $this->tramiteDe('Comercio', ['Identificación oficial']);
        $propio   = Requisito::where('tramite_id', $comercio->id)->first();

        $this->guardar($comercio, [
            $this->comoLoMandaElFormulario($propio->id, 'Identificación oficial'),
            ['nombre' => 'Acta constitutiva', 'tipo' => ['documento'], 'dias' => 0, 'horas' => 1, 'minutos' => 0],
        ]);

        $this->assertCount(2, Requisito::where('tramite_id', $comercio->id)->get());
    }
}
