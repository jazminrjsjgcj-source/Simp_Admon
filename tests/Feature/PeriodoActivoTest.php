<?php

namespace Tests\Feature;

use App\Models\Periodo;
use App\Models\User;
use App\Services\PeriodoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * La regla central del módulo de periodos: SOLO UN PERIODO ACTIVO POR TIPO.
 *
 * ── Por qué esto importa tanto ───────────────────────────────────────
 *
 * El periodo activo determina a qué agenda se imputan las acciones que las dependencias
 * registran. Si hay dos activos del mismo tipo, el sistema no sabe a cuál pertenece lo que
 * se está capturando — y lo que es peor, ni siquiera se queja: elige uno.
 *
 * Es de los pocos datos del sistema en los que "estar mal" no produce ningún error. Produce
 * una agenda con las acciones repartidas entre dos periodos, y nadie se entera hasta que
 * alguien intenta cerrar el semestre.
 */
class PeriodoActivoTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(PeriodoService::class);
        $this->actingAs(User::factory()->create()); // el servicio usa auth()->id()
    }

    private function datos(string $nombre, ?string $estatus = null): array
    {
        return array_filter([
            'nombre'       => $nombre,
            'fecha_inicio' => now()->startOfYear()->toDateString(),
            'fecha_fin'    => now()->endOfYear()->toDateString(),
            'estatus'      => $estatus,
        ], fn ($v) => $v !== null);
    }

    private function activosDe(string $tipo)
    {
        return Periodo::where('tipo', $tipo)->where('estatus', Periodo::ESTATUS_ACTIVO)->get();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. La regla
    // ═══════════════════════════════════════════════════════════════════════

    public function test_activar_un_periodo_cierra_el_anterior_del_mismo_tipo(): void
    {
        $primero = $this->servicio->crear(
            $this->datos('Primer semestre 2026', Periodo::ESTATUS_ACTIVO),
            Periodo::TIPO_SYD
        );

        $segundo = $this->servicio->crear(
            $this->datos('Segundo semestre 2026'),
            Periodo::TIPO_SYD
        );

        $this->servicio->activar($segundo);

        $this->assertSame(Periodo::ESTATUS_CERRADO, $primero->fresh()->estatus);
        $this->assertSame(Periodo::ESTATUS_ACTIVO,  $segundo->fresh()->estatus);
        $this->assertCount(1, $this->activosDe(Periodo::TIPO_SYD));
    }

    /**
     * Los dos tipos de agenda son independientes.
     *
     * Activar un periodo de agenda SyD NO puede cerrar el de la agenda regulatoria: son dos
     * calendarios distintos (uno semestral, otro anual) que corren en paralelo.
     *
     * Es la mitad negativa de la prueba anterior, y sin ella esa no vale nada: un sistema que
     * cerrara TODOS los periodos activos, de cualquier tipo, la pasaría igual.
     */
    public function test_activar_un_periodo_no_toca_los_de_otro_tipo(): void
    {
        $regulatoria = $this->servicio->crear(
            $this->datos('Agenda Regulatoria 2026', Periodo::ESTATUS_ACTIVO),
            Periodo::TIPO_REGULATORIA
        );

        $syd = $this->servicio->crear(
            $this->datos('Agenda SyD 1er semestre', Periodo::ESTATUS_ACTIVO),
            Periodo::TIPO_SYD
        );

        $this->assertSame(
            Periodo::ESTATUS_ACTIVO,
            $regulatoria->fresh()->estatus,
            'La agenda regulatoria y la agenda SyD son calendarios distintos: no se cierran entre sí.'
        );
        $this->assertSame(Periodo::ESTATUS_ACTIVO, $syd->fresh()->estatus);
    }

    /**
     * LA GARANTÍA DE VERDAD: la base de datos.
     *
     * Una comprobación en código nunca puede impedir dos activos bajo concurrencia. Dos
     * transacciones simultáneas no se ven entre sí hasta que confirman, así que las dos
     * "cierran los activos" (no ven ninguno) y las dos activan.
     *
     * El índice único parcial de la base sí lo impide, pase lo que pase. Esta prueba lo
     * ejerce saltándose el servicio: escribe directamente en la tabla, como haría una
     * transacción que se coló.
     */
    public function test_la_base_de_datos_impide_dos_periodos_activos_del_mismo_tipo(): void
    {
        $this->servicio->crear(
            $this->datos('Primer semestre', Periodo::ESTATUS_ACTIVO),
            Periodo::TIPO_SYD
        );

        // Se salta el servicio a propósito: es lo que haría una carrera entre dos peticiones.
        $this->expectException(\Illuminate\Database\QueryException::class);

        Periodo::create([
            'nombre'       => 'Segundo semestre (colado)',
            'tipo'         => Periodo::TIPO_SYD,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin'    => now()->addMonths(6)->toDateString(),
            'estatus'      => Periodo::ESTATUS_ACTIVO,
        ]);
    }

    /** Muchos periodos CERRADOS del mismo tipo sí pueden convivir: uno por semestre. */
    public function test_puede_haber_muchos_periodos_cerrados_del_mismo_tipo(): void
    {
        foreach (['2024-1', '2024-2', '2025-1'] as $nombre) {
            $this->servicio->crear($this->datos($nombre, Periodo::ESTATUS_CERRADO), Periodo::TIPO_SYD);
        }

        $this->assertCount(
            3,
            Periodo::where('tipo', Periodo::TIPO_SYD)->where('estatus', Periodo::ESTATUS_CERRADO)->get(),
            'El índice único es PARCIAL: solo restringe los activos. Los cerrados se acumulan, '
            . 'que es lo normal: uno por semestre.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. La bitácora
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * EL BUG QUE ESTA TANDA ARREGLA.
     *
     * cerrarOtrosActivos() hacía un `->update()` sobre el query builder:
     *
     *     Periodo::where('estatus', 'activo')->where('tipo', $tipo)->update([...]);
     *
     * Ese update NO DISPARA LOS EVENTOS DE ELOQUENT. Periodo está observado por
     * AuditObserver, pero el observer escucha eventos de modelo, y ahí no había ninguno.
     *
     * Resultado: el cierre automático del periodo anterior NO APARECÍA EN LA BITÁCORA.
     *
     * El periodo activo determina a qué agenda se imputan las acciones de todo el municipio.
     * Que uno se cierre y otro se abra es de las cosas más importantes que pasan en el
     * sistema, y era justo la que no dejaba rastro de quién la hizo ni cuándo.
     */
    public function test_cerrar_el_periodo_anterior_queda_registrado_en_bitacora(): void
    {
        $anterior = $this->servicio->crear(
            $this->datos('Primer semestre', Periodo::ESTATUS_ACTIVO),
            Periodo::TIPO_SYD
        );

        $nuevo = $this->servicio->crear($this->datos('Segundo semestre'), Periodo::TIPO_SYD);

        $this->servicio->activar($nuevo);

        // La bitácora es polimórfica: auditable_type / auditable_id.
        $registro = DB::table('bitacora')
            ->where('auditable_type', Periodo::class)
            ->where('auditable_id', $anterior->id)
            ->where('tipo', 'updated')
            ->latest('created_at')
            ->first();

        $this->assertNotNull(
            $registro,
            'El cierre automático del periodo anterior no dejó rastro en la bitácora. '
            . 'Probablemente cerrarOtrosActivos() volvió a usar un ->update() sobre el QUERY '
            . 'BUILDER, que no dispara los eventos de Eloquent y por tanto no despierta al '
            . 'AuditObserver. El periodo se cierra, sí — pero nadie sabe quién lo cerró.'
        );

        // No basta con que exista una fila: tiene que decir QUÉ pasó.
        //
        // AuditObserver guarda el cambio campo a campo en `detalle`, con el formato
        // "estatus: [activo] -> [cerrado]". Comprobarlo es lo que convierte esta prueba en
        // útil: una fila de bitácora que no dijera qué cambió serviría de bien poco a quien
        // dentro de un año intente reconstruir por qué las acciones de marzo acabaron en el
        // periodo equivocado.
        $this->assertStringContainsString(
            'estatus',
            (string) $registro->detalle,
            'La bitácora registró algo, pero no dice que el estatus cambió.'
        );
        $this->assertStringContainsString('cerrado', (string) $registro->detalle);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Los valores por defecto que borran datos
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * OTRA MINA DESACTIVADA.
     *
     * actualizar() hacía `'estatus' => $datos['estatus'] ?? 'proximo'`.
     *
     * Eso significaba que cualquier guardado que NO mandara el estatus desactivaba el periodo
     * en silencio. Un editor que solo quisiera corregir una fecha de cierre podía cerrar la
     * agenda de todo el municipio sin enterarse: el periodo pasaba de 'activo' a 'proximo',
     * el scope dejaba de verlo, y las acciones nuevas se quedaban sin periodo al que
     * imputarse.
     *
     * Es la misma trampa que los valores por defecto de TramiteService::actualizar(): un
     * parámetro ausente y un parámetro vacío no pueden significar lo mismo.
     *
     * Ahora, si no llega estatus, se CONSERVA el que tenía.
     */
    public function test_actualizar_sin_mandar_estatus_no_desactiva_el_periodo(): void
    {
        $periodo = $this->servicio->crear(
            $this->datos('Primer semestre', Periodo::ESTATUS_ACTIVO),
            Periodo::TIPO_SYD
        );

        // El usuario solo corrige la fecha de cierre. No manda el estatus.
        $this->servicio->actualizar($periodo, [
            'nombre'       => 'Primer semestre',
            'fecha_inicio' => now()->startOfYear()->toDateString(),
            'fecha_fin'    => now()->addMonth()->toDateString(), // ← lo único que cambia
        ]);

        $this->assertSame(
            Periodo::ESTATUS_ACTIVO,
            $periodo->fresh()->estatus,
            'Corregir una fecha desactivó el periodo. Un estatus ausente significa "no lo '
            . 'toques", no "desactívalo".'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Las cadenas mágicas
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un estatus inventado se rechaza.
     *
     * AdminController hace `$validated['estatus'] = $request->estatus;` SIN ninguna regla de
     * validación: se guarda lo que venga del formulario.
     *
     * Un 'Activo' con mayúscula se guardaría sin queja. Y ese periodo NO estaría activo para
     * el sistema: scopeActivo() compara con === 'activo', el índice único filtra
     * WHERE estatus = 'activo', y el servicio cierra los demás solo si ve exactamente
     * 'activo'.
     *
     * El periodo existiría. Se vería en la lista. Diría "Activo" en la pantalla. Y no lo
     * estaría. Nadie sabría por qué.
     *
     * Es el mismo tipo de fallo que la tasa libre de riesgo capturada como porcentaje: un
     * valor que parece bueno y no lo es.
     */
    public function test_un_estatus_con_mayuscula_o_inventado_se_rechaza(): void
    {
        $this->expectException(RuntimeException::class);

        $this->servicio->crear($this->datos('Periodo raro', 'Activo'), Periodo::TIPO_SYD);
    }

    /** Y uno vacío o con espacios también. */
    public function test_un_estatus_con_espacios_se_rechaza(): void
    {
        $this->expectException(RuntimeException::class);

        $this->servicio->crear($this->datos('Periodo raro', 'activo '), Periodo::TIPO_SYD);
    }
}
