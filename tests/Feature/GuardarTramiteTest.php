<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use App\Services\TramiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del guardado de un trámite (TramiteService).
 *
 * Cubren en particular los campos que el formulario envía pero que NO son columnas
 * de la tabla `tramites`, y que hacían fallar el alta con MassAssignmentException:
 *
 *   - costo_tipo / costo_monto / costo_unidad → ayudantes del formulario.
 *   - portal_*                                → van a la ficha ciudadana, otra tabla.
 *
 * Son justo los dos errores que aparecieron al hacer obligatorios esos campos.
 */
class GuardarTramiteTest extends TestCase
{
    use RefreshDatabase;

    private TramiteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(TramiteService::class);
    }

    /** Datos mínimos de un trámite válido. */
    private function datosBase(): array
    {
        $unidad = UnidadAdministrativa::factory()->create();

        return [
            'nombre_oficial' => 'Licencia de funcionamiento',
            'naturaleza'     => 'tramite',
            'dependencia_id' => $unidad->dependencia_id,
            'unidad_id'      => $unidad->id,
            'objetivo'       => 'Autorizar la operación de un establecimiento.',
            'dirigido_a'     => 'ambas',
            'volumen_anual'  => 100,
        ];
    }

    public function test_se_guarda_un_tramite_con_los_datos_minimos(): void
    {
        $tramite = $this->servicio->crear($this->datosBase());

        $this->assertDatabaseHas('tramites', [
            'id'             => $tramite->id,
            'nombre_oficial' => 'Licencia de funcionamiento',
        ]);
    }

    public function test_un_tramite_nuevo_nace_como_borrador(): void
    {
        $tramite = $this->servicio->crear($this->datosBase(), esEnvio: false);

        $this->assertSame(Tramite::ESTATUS_BORRADOR, $tramite->estatus);
    }

    public function test_al_enviarlo_a_revision_queda_en_observacion(): void
    {
        $tramite = $this->servicio->crear($this->datosBase(), esEnvio: true);

        $this->assertSame(Tramite::ESTATUS_EN_OBSERVACION, $tramite->estatus);
    }

    public function test_los_campos_de_costo_no_rompen_el_guardado(): void
    {
        // costo_tipo, costo_monto y costo_unidad son ayudantes del formulario: sirven
        // para armar el texto del portal, pero NO son columnas de `tramites`. Si se
        // pasaran a Tramite::create() darían MassAssignmentException.
        $datos = $this->datosBase() + [
            'costo_tipo'   => 'con_costo',
            'costo_monto'  => 500,
            'costo_unidad' => 'pesos',
        ];

        $tramite = $this->servicio->crear($datos);

        $this->assertNotNull($tramite->id, 'El trámite debe guardarse pese a recibir los ayudantes del costo.');
    }

    public function test_los_campos_del_portal_no_rompen_el_guardado(): void
    {
        // Los portal_* pertenecen a la ficha ciudadana (otra tabla). Llegan aquí
        // porque el formulario los manda junto con el resto, y desde que son
        // obligatorios pasan la validación y llegan hasta el servicio.
        $datos = $this->datosBase() + [
            'portal_nombre_ciudadano' => 'Licencia para mi negocio',
            'portal_resultado'        => 'Licencia impresa',
            'portal_modalidad'        => 'Presencial',
            'portal_telefono'         => '6121234567',
            'portal_correo'           => 'atencion@lapaz.gob.mx',
        ];

        $tramite = $this->servicio->crear($datos);

        $this->assertNotNull($tramite->id, 'El trámite debe guardarse pese a recibir los campos del portal.');
    }

    public function test_se_guardan_los_requisitos_del_tramite(): void
    {
        $requisitos = [
            [
                'nombre'        => 'Identificación oficial',
                'tipo'          => ['original', 'copia'],
                'dias'          => 0,
                'horas'         => 1,
                'minutos'       => 30,
                'observaciones' => 'Vigente.',
                'fj_tiene'      => '0',
            ],
        ];

        $tramite = $this->servicio->crear($this->datosBase(), requisitos: $requisitos);

        $this->assertDatabaseHas('requisitos', [
            'tramite_id' => $tramite->id,
            'nombre'     => 'Identificación oficial',
        ]);
    }

    public function test_se_guarda_la_ficha_del_portal_en_su_propia_tabla(): void
    {
        $ficha = [
            'nombre_ciudadano' => 'Licencia para mi negocio',
            'resultado'        => 'Licencia impresa',
        ];

        $tramite = $this->servicio->crear($this->datosBase(), fichaPortal: $ficha);

        // La ficha NO vive en `tramites`: tiene su propia tabla.
        $this->assertNotNull($tramite->fichaPortal, 'La ficha del portal debe crearse junto al trámite.');
    }
}
