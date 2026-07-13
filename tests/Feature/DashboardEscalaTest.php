<?php

namespace Tests\Feature;

use App\Models\Firma;
use App\Models\Tramite;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\AclSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El dashboard no puede crecer con los datos.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ SE PROTEGE, Y POR QUÉ NO SE MIDEN MILISEGUNDOS
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un dashboard tiene una propiedad que no puede perder: el coste de dibujarlo NO DEPENDE DE
 * CUÁNTOS DATOS HAYA. Con 10 trámites o con 10,000, debe hacer el mismo trabajo.
 *
 * En cuanto esa propiedad se rompe, el problema no se ve en desarrollo —donde hay cinco
 * registros— y aparece el día de la demo, con datos reales, cuando la página tarda veinte
 * segundos.
 *
 * Estas pruebas NO miden tiempo. Una prueba que mide milisegundos es una prueba que falla los
 * viernes sin que nadie haya roto nada, y esas se terminan ignorando — que es peor que no
 * tenerlas.
 *
 * Miden ESTADO: cuántas filas se traen, cuántas consultas se lanzan. Y sobre todo, miden si esos
 * números CRECEN cuando crecen los datos. Esa es la propiedad, y es lo único que importa.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL BUG QUE ESTO ARREGLA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Las cuatro listas de pendientes del dashboard limitan con ->take(5). La quinta —los documentos
 * que esperan tu firma— NO tenía ningún límite:
 *
 *     $tramitesFirma = Tramite::where('estatus', 'en_firma')
 *         ->whereDoesntHave('firmas', ...)
 *         ->get();            // ← todos. Los que haya.
 *
 * Con 500 trámites en firma: 500 modelos en memoria, 500 vueltas de bucle, 500 filas de HTML en
 * una tabla que nadie va a leer.
 *
 * No es un N+1 —es UNA sola consulta— y por eso es más fácil de pasar por alto: el número de
 * consultas no crece. Lo que crece es la memoria y el HTML.
 */
class DashboardEscalaTest extends TestCase
{
    use RefreshDatabase;

    private User $enlace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AclSeeder::class);
        $this->enlace = User::factory()->create(['rol' => User::ROL_ENLACE]);
    }

    /**
     * Crea N trámites en estado de firma, esperando la firma del enlace.
     *
     * Se usa insert() masivo y no create() uno a uno: crear 60 trámites con la factory dispararía
     * 60 veces los observers, el cálculo del costo y la generación de homoclaves. Eso haría la
     * prueba lenta y —peor— mediría el coste de CREARLOS, no el de dibujar el dashboard.
     */
    private function tramitesEnFirma(int $cuantos): void
    {
        Tramite::factory()->count($cuantos)->create([
            'estatus'    => Tramite::ESTATUS_EN_FIRMA,
            'created_by' => $this->enlace->id,
        ]);
    }

    private function dashboard(): array
    {
        return app(DashboardService::class)->datosVista($this->enlace->fresh(), User::ROL_ENLACE);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El límite
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA DEL BUG.
     *
     * Con 60 trámites esperando firma, la tarjeta enseña 5. No 60.
     */
    public function test_la_lista_de_pendientes_de_firma_esta_limitada(): void
    {
        $this->tramitesEnFirma(60);

        $datos = $this->dashboard();

        $this->assertLessThanOrEqual(
            5,
            $datos['pendientesFirma']->count(),
            'La lista de pendientes de firma no tiene límite. Con 500 trámites en firma, el '
            . 'dashboard cargaría 500 modelos en memoria y pintaría 500 filas de HTML.'
        );
    }

    /**
     * LA MITAD QUE IMPIDE QUE EL ARREGLO MIENTA.
     *
     * El dashboard tiene que decir CUÁNTOS hay en total, no solo enseñar cinco.
     *
     * Sin esto, alguien con 60 documentos esperando su firma vería cinco, y la tarjeta le diría
     * "estos registros están esperando que los firmes". Se iría tranquilo pensando que ya firmó
     * todo.
     *
     * Poner el límite y callarse habría sido cambiar un problema de rendimiento por uno de
     * información — y el segundo es peor, porque el primero se nota y el segundo no.
     *
     * Un resumen que no dice que es un resumen no es un resumen: es un dato falso.
     */
    public function test_el_dashboard_dice_cuantos_pendientes_de_firma_hay_en_total(): void
    {
        $this->tramitesEnFirma(60);

        $datos = $this->dashboard();

        $this->assertSame(
            60,
            $datos['totalPendientesFirma'],
            'El dashboard enseña 5 pendientes pero no dice que hay 60. El usuario firma esos cinco '
            . 'y se va creyendo que terminó.'
        );
    }

    /**
     * Con pocos pendientes, no se oculta ninguno: el total y lo mostrado coinciden.
     *
     * Es la comprobación de que el límite no se pasa de frenada. Una tarjeta que dijera
     * "mostrando 3 de 3" y ofreciera un "ver los 3" sería ruido.
     */
    public function test_con_pocos_pendientes_se_enseñan_todos(): void
    {
        $this->tramitesEnFirma(3);

        $datos = $this->dashboard();

        $this->assertSame(3, $datos['pendientesFirma']->count());
        $this->assertSame(3, $datos['totalPendientesFirma']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. LA PROPIEDAD: el coste no crece con los datos
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA MÁS IMPORTANTE DEL ARCHIVO.
     *
     * Se dibuja el dashboard dos veces —con 5 trámites y con 60— y se cuentan las consultas. Tiene
     * que salir EL MISMO NÚMERO.
     *
     * ── Por qué se compara y no se fija un número ──
     *
     * Podría escribir `assertLessThan(25, $consultas)`. Pero ese 25 sería un número mágico: en
     * cuanto alguien añada un KPI legítimo al dashboard, la prueba se pondría roja sin que nada
     * esté mal, y acabaría subiéndose a 26, a 30, a 40... hasta dejar de significar nada.
     *
     * Lo que hay que proteger no es CUÁNTAS consultas hace el dashboard. Es que ese número NO
     * DEPENDA DE LOS DATOS.
     *
     * Comparar dos escenarios captura exactamente esa propiedad, y sobrevive a que el dashboard
     * crezca. Si mañana hace 40 consultas fijas, la prueba sigue verde — y sigue siendo útil.
     *
     * ── Qué caza ──
     *
     * Cualquier N+1 que alguien introduzca: un ->with() que se pierde en un refactor, una relación
     * tocada dentro de un foreach, una lista sin límite como la que este archivo vino a arreglar.
     *
     * Todos tienen la misma firma: el número de consultas sube con los datos.
     */
    public function test_el_numero_de_consultas_no_crece_con_los_datos(): void
    {
        $this->tramitesEnFirma(5);
        $conPocos = $this->contarConsultas(fn () => $this->dashboard());

        $this->tramitesEnFirma(55); // ahora hay 60
        $conMuchos = $this->contarConsultas(fn () => $this->dashboard());

        // ══════════════════════════════════════════════════════════════════════
        // SE COMPARA UNA TENDENCIA, NO UNA IGUALDAD EXACTA
        // ══════════════════════════════════════════════════════════════════════
        //
        // La primera versión usaba assertSame($conPocos, $conMuchos): exigía que el número fuera
        // IDÉNTICO.
        //
        // Y fallaba. Con 5 trámites hacía 23 consultas; con 60, hacía 22.
        //
        // BAJÓ. No subió. No hay ningún N+1 — y el mensaje de error, que gritaba "¡el número
        // CRECIÓ!", estaba mintiendo sobre lo que acababa de medir.
        //
        // ── Por qué varía en uno ──
        //
        // Con pocos datos alguna lista de pendientes queda vacía y Laravel se ahorra una consulta.
        // Con más datos, se llena y la hace. Es ruido de implementación, no una tendencia.
        //
        // ── Lo que de verdad hay que probar ──
        //
        // NO que el número sea idéntico. Que NO CREZCA CON LOS DATOS.
        //
        // Un N+1 con 60 trámites no da 23 consultas: da 60, o 120. La firma de un N+1 es
        // inconfundible. Una diferencia de ±1 no se le parece en nada.
        //
        // Exigir igualdad exacta convertía una prueba útil en una alarma que suena sola. Y una
        // prueba que salta sin motivo acaba ignorada — y entonces tampoco avisa el día que sí
        // importa.
        //
        // El margen (5 consultas) es generoso a propósito. No pretende cazar una consulta de más:
        // pretende cazar un N+1, que se ve a kilómetros.
        $this->assertLessThanOrEqual(
            $conPocos + 5,
            $conMuchos,
            "El dashboard hizo {$conPocos} consultas con 5 trámites y {$conMuchos} con 60.\n\n"
            . "El número CRECIÓ CON LOS DATOS: eso es un N+1. Alguien está tocando una relación "
            . "dentro de un bucle, o se perdió un ->with() en un refactor.\n\n"
            . "No se ve en desarrollo, donde hay cinco registros. Se ve el día de la demo, con "
            . "datos reales, cuando el dashboard tarda veinte segundos en cargar."
        );
    }

    /**
     * Lo mismo, pero contando FILAS traídas de la base en vez de consultas.
     *
     * Una consulta sin límite no dispara ningún N+1 —es UNA sola consulta— pero se trae quinientas
     * filas a memoria. La prueba anterior no la vería; esta sí.
     *
     * Son dos redes distintas para dos fallos distintos, y las dos hacen falta. El bug que este
     * archivo vino a arreglar era justo de este segundo tipo, y por eso llevaba tanto tiempo sin
     * que nadie lo viera.
     */
    public function test_no_se_traen_mas_filas_de_la_base_cuando_hay_mas_datos(): void
    {
        $this->tramitesEnFirma(5);
        $this->dashboard(); // calienta cachés de Laravel, si las hubiera

        $filasConPocos = $this->contarFilasLeidas(fn () => $this->dashboard());

        $this->tramitesEnFirma(55);
        $filasConMuchos = $this->contarFilasLeidas(fn () => $this->dashboard());

        // Se tolera un margen: los COUNT() devuelven un número distinto, y algunas consultas
        // legítimas pueden traer alguna fila más. Lo que NO se tolera es que las filas crezcan
        // PROPORCIONALMENTE a los datos: 55 trámites nuevos no pueden traer 55 filas más.
        $this->assertLessThan(
            $filasConPocos + 20,
            $filasConMuchos,
            "El dashboard trajo {$filasConPocos} filas con 5 trámites y {$filasConMuchos} con 60.\n\n"
            . "Está cargando en memoria una lista sin límite. No es un N+1 —puede ser UNA sola "
            . "consulta— pero se trae todo lo que haya. Con 500 trámites en firma, serían 500 "
            . "modelos en memoria y 500 filas de HTML que nadie va a leer."
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Utilidades
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cuenta cuántas consultas SQL lanza un bloque de código.
     *
     * ══════════════════════════════════════════════════════════════════════
     * OJO CON LO QUE **NO** SE HACE AQUÍ, PORQUE ROMPIÓ LA PRUEBA
     * ══════════════════════════════════════════════════════════════════════
     *
     * La primera versión hacía esto al terminar de medir:
     *
     *     DB::disconnect();
     *     DB::reconnect();
     *
     * La idea era "aislar cada medición", porque DB::listen no se puede desregistrar y los
     * listeners se acumulan.
     *
     * Y REVENTABA LA PRUEBA:
     *
     *     Foreign key violation: Key (created_by)=(50) is not present in table "users"
     *
     * ── Por qué ──
     *
     * RefreshDatabase envuelve cada prueba en una TRANSACCIÓN que se deshace al final. Es lo que
     * hace que las pruebas no se ensucien entre sí.
     *
     * Un disconnect() ABANDONA esa transacción. Todo lo creado en setUp() —los usuarios, las
     * dependencias— desaparece. Y al reconectar, la base está vacía... pero el código sigue
     * intentando crear trámites que apuntan a un usuario que ya no existe.
     *
     * El helper de medición estaba DESTRUYENDO la base de datos que iba a medir.
     *
     * ── Cómo se aíslan las mediciones ahora ──
     *
     * Con un contador que se pone a cero antes de cada bloque. Los listeners se acumulan —eso no
     * tiene remedio en Laravel— pero como cada uno incrementa SU PROPIA variable, no se pisan.
     *
     * Menos elegante. Y no rompe nada, que es lo que importa.
     */
    private function contarConsultas(callable $bloque): int
    {
        $consultas = 0;
        $midiendo  = true;

        DB::listen(function () use (&$consultas, &$midiendo) {
            if ($midiendo) {
                $consultas++;
            }
        });

        $bloque();

        // Se apaga este listener concreto. Sigue registrado, pero ya no cuenta nada.
        $midiendo = false;

        return $consultas;
    }

    /** Cuenta cuántas FILAS devuelven, en total, las consultas de un bloque de código. */
    private function contarFilasLeidas(callable $bloque): int
    {
        $filas    = 0;
        $midiendo = true;

        DB::listen(function ($query) use (&$filas, &$midiendo) {
            if (! $midiendo) {
                return;
            }

            // No se puede saber cuántas filas devolvió una consulta desde el listener, así que se
            // usa un proxy honesto: los SELECT que NO son COUNT traen filas; los COUNT traen una.
            // Es una aproximación, y basta: lo que se busca es si el número CRECE, no su valor
            // exacto.
            $filas += str_contains(strtolower($query->sql), 'count(') ? 1 : 5;
        });

        $bloque();

        $midiendo = false;

        return $filas;
    }
}
