<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Contador;
use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de los IDENTIFICADORES OFICIALES: la homoclave del trámite y el folio
 * de la agenda, las propuestas, los AIR y las regulaciones.
 *
 * ── Por qué estas pruebas son distintas a HomoclaveTest y AgendaFolioTest ──
 *
 * Las que ya existen prueban el camino feliz: un trámite, un folio, un número
 * correcto. Estas prueban lo que pasa cuando el sistema se usa DE VERDAD:
 *
 *   1. Dos personas dan de alta un trámite en el mismo segundo.
 *   2. El municipio llega a la propuesta número 1000.
 *
 * Ninguno de esos dos escenarios ocurre en una demo con cinco registros. Los dos
 * ocurren en producción, y los dos producen un identificador oficial duplicado o
 * un error 500 en la cara del usuario. Un identificador duplicado no es un
 * problema técnico: es un problema legal, porque la homoclave es lo que aparece
 * en el acuse firmado.
 *
 * ── ATENCIÓN: varias de estas pruebas FALLAN HOY ──
 *
 * Están escritas en ROJO a propósito. Afirman el comportamiento que el sistema
 * DEBE tener, no el que tiene. Cada una lleva marcado el bug que documenta.
 * Ese es el orden correcto: la prueba fija la regla, y luego el código la cumple.
 */
class IdentificadoresUnicosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un trámite listo para generar homoclave, reutilizando SIEMPRE la misma
     * dependencia y unidad. Es importante que sean las mismas: así, si dos trámites
     * calculan el mismo consecutivo, la homoclave completa colisiona de verdad
     * (mismas siglas + mismo número), que es justo lo que queremos detectar.
     */
    private function tramiteEnLaMismaUnidad(): Tramite
    {
        $dependencia = Dependencia::firstOrCreate(
            ['codigo' => '110'],
            ['nombre' => 'Dirección General de Gobierno Digital', 'siglas' => 'DGGD']
        );

        $unidad = UnidadAdministrativa::firstOrCreate(
            ['dependencia_id' => $dependencia->id, 'codigo' => '01'],
            ['nombre' => 'Dirección de Simplificación Administrativa', 'siglas' => 'DSA']
        );

        return Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'unidad_id'      => $unidad->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Concurrencia en la homoclave
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * ROJO — documenta el bug de condición de carrera en la homoclave.
     *
     * Qué simula: dos enlaces dan de alta un trámite a la vez. Los dos formularios
     * se envían con medio segundo de diferencia, así que el segundo calcula su
     * homoclave ANTES de que el primero haya guardado la suya.
     *
     * Cómo lo reproduce sin hilos: llamando a generarHomoclave() en los dos
     * trámites SIN guardar entre medias. Eso es exactamente lo que ve el servidor
     * cuando atiende dos peticiones en paralelo: ninguna de las dos ve a la otra.
     *
     * Qué hace hoy siguienteConsecutivoGlobal(): lee el máximo de las homoclaves
     * existentes y le suma 1. Sin bloqueo, sin secuencia, sin reserva. Las dos
     * peticiones leen el mismo máximo y las dos se llevan el mismo número.
     *
     * Qué debería hacer: darles números distintos. Cómo se arregla es otra
     * conversación (secuencia de PostgreSQL, lockForUpdate, o capturar el choque
     * de clave única y reintentar). Esta prueba solo fija la regla.
     */
    public function test_dos_altas_simultaneas_no_pueden_recibir_la_misma_homoclave(): void
    {
        $primero = $this->tramiteEnLaMismaUnidad();
        $segundo = $this->tramiteEnLaMismaUnidad();

        // Ninguno ha guardado su homoclave todavía: es el escenario de dos
        // peticiones concurrentes que se cruzan.
        $homoclavePrimero = $primero->generarHomoclave();
        $homoclaveSegundo = $segundo->generarHomoclave();

        $this->assertNotSame(
            $homoclavePrimero,
            $homoclaveSegundo,
            'Dos trámites distintos no pueden compartir homoclave: es el identificador '
            . 'oficial que aparece en el acuse firmado. Hoy los dos reciben el mismo '
            . 'número porque el consecutivo se calcula leyendo el máximo, sin reservarlo.'
        );
    }

    /**
     * ROJO — muestra la CONSECUENCIA visible del bug anterior.
     *
     * La columna `homoclave` tiene índice único en la migración
     * (2026_06_08_000002_create_tramites_tables.php, línea 20). Así que cuando los
     * dos trámites concurrentes intentan guardar la misma homoclave, el segundo
     * choca contra la base de datos.
     *
     * El resultado para el usuario NO es un mensaje de "inténtalo de nuevo": es una
     * QueryException sin capturar, es decir, una pantalla de error 500 después de
     * haber llenado un formulario de siete pasos.
     *
     * Esta prueba afirma que los DOS trámites se guardan bien. Hoy revienta.
     */
    public function test_dos_altas_simultaneas_se_guardan_las_dos_sin_reventar(): void
    {
        $primero = $this->tramiteEnLaMismaUnidad();
        $segundo = $this->tramiteEnLaMismaUnidad();

        $homoclavePrimero = $primero->generarHomoclave();
        $homoclaveSegundo = $segundo->generarHomoclave();

        try {
            $primero->update(['homoclave' => $homoclavePrimero]);
            $segundo->update(['homoclave' => $homoclaveSegundo]);
        } catch (QueryException $e) {
            $this->fail(
                'El segundo trámite chocó contra el índice único de `homoclave`. '
                . 'En producción esto es un error 500 en la cara del usuario. '
                . 'Mensaje de la base: ' . $e->getMessage()
            );
        }

        $this->assertNotSame($primero->fresh()->homoclave, $segundo->fresh()->homoclave);
    }

    /**
     * VERDE — protege el comportamiento correcto que YA existe.
     *
     * No busca ningún bug. Existe para que el arreglo del bug anterior no rompa
     * esto sin querer: cuando alguien meta un lockForUpdate o una secuencia, esta
     * prueba avisará si de paso se cargó el conteo secuencial normal.
     */
    public function test_las_altas_una_tras_otra_si_avanzan_el_consecutivo(): void
    {
        $primero = $this->tramiteEnLaMismaUnidad();
        $primero->update(['homoclave' => $primero->generarHomoclave()]);

        // El segundo se calcula DESPUÉS de que el primero guardó: sí lo ve.
        $segundo = $this->tramiteEnLaMismaUnidad();
        $segundo->update(['homoclave' => $segundo->generarHomoclave()]);

        $this->assertSame('LPZ-T-DGGD-DSA-1', $primero->fresh()->homoclave);
        $this->assertSame('LPZ-T-DGGD-DSA-2', $segundo->fresh()->homoclave);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. La serie del folio al pasar de 999
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Crea una acción de simplificación lista para pedir folio, sin folio asignado.
     *
     * La dependencia se fija con siglas 'DGGD' porque el folio las lleva dentro
     * (LPZ-SIM-DGGD-2026-NNN) y cada combinación de tipo, dependencia y año es una
     * serie independiente.
     */
    private function accionDeSimplificacion(): AccionAgenda
    {
        $dependencia = Dependencia::firstOrCreate(
            ['codigo' => '110'],
            ['nombre' => 'Dirección General de Gobierno Digital', 'siglas' => 'DGGD']
        );

        $tramite = Tramite::factory()->completado()->create([
            'dependencia_id' => $dependencia->id,
        ]);

        return AccionAgenda::factory()->create([
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $dependencia->id,
            'tipo'           => 'simplificacion', // → prefijo SIM
            'folio'          => null,             // el folio se pide, no viene puesto
        ]);
    }

    /** El prefijo que comparten todos los folios de simplificación de esta dependencia este año. */
    private function prefijoFolio(): string
    {
        return 'LPZ-SIM-DGGD-' . now()->year . '-';
    }

    /**
     * Coloca la serie de folios en un punto concreto, como si el municipio llevara
     * años usando el sistema y ya hubiera gastado ese número de folios.
     *
     * ── Por qué se prepara así y no escribiendo un folio en la tabla ──
     *
     * La primera versión de estas pruebas creaba una acción con el folio
     * 'LPZ-SIM-DGGD-2026-999' escrito a mano en su columna, y esperaba que el
     * sistema lo leyera. Eso funcionaba con el mecanismo viejo, que averiguaba el
     * siguiente número mirando la tabla.
     *
     * El mecanismo nuevo no mira la tabla: le pide el número al Contador. Así que
     * escribir un folio en la columna ya no significa nada; la serie vive en
     * `contadores`. Preparar el escenario de la otra forma probaba las TRIPAS del
     * código viejo, no la regla.
     *
     * La regla no ha cambiado: la serie tiene que seguir avanzando al pasar de 999.
     * Lo que cambia es cómo se monta el escenario.
     */
    private function laSerieVaPorEl(int $numero): void
    {
        Contador::updateOrCreate(
            ['clave' => 'folio:' . $this->prefijoFolio()],
            ['valor' => $numero]
        );
    }

    /**
     * Con la serie en el 998, el siguiente folio es el 999 y se rellena a 3 dígitos.
     *
     * Protege el formato de toda la vida: LPZ-SIM-DGGD-2026-999.
     */
    public function test_dentro_de_los_tres_digitos_el_folio_se_rellena_con_ceros(): void
    {
        $this->laSerieVaPorEl(0);

        $this->assertSame(
            $this->prefijoFolio() . '001',
            $this->accionDeSimplificacion()->generarFolio(),
            'El primer folio de la serie debe salir como 001, no como 1.'
        );
    }

    /**
     * Con la serie en el 999, el siguiente folio es el 1000.
     *
     * str_pad rellena hasta 3 dígitos, pero NO recorta: un número de 4 dígitos sale
     * entero. El folio simplemente crece.
     */
    public function test_del_folio_999_se_pasa_correctamente_al_1000(): void
    {
        $this->laSerieVaPorEl(999);

        $this->assertSame(
            $this->prefijoFolio() . '1000',
            $this->accionDeSimplificacion()->generarFolio()
        );
    }

    /**
     * Y del 1000 se pasa al 1001. Esta es LA prueba del bug.
     *
     * ── Qué se rompía antes ──
     *
     * generarFolio() buscaba el último folio con orderByDesc('folio'). Como `folio`
     * es una columna de TEXTO, la base ordenaba como un diccionario, letra por
     * letra. Comparando "999" contra "1000": el primer carácter es '9' contra '1', y
     * '9' va después de '1', así que la base concluía que "999" era el mayor.
     *
     * Resultado: aunque el folio 1000 ya existiera, la consulta seguía devolviendo
     * el 999 como "el último". El sistema calculaba 999 + 1 = 1000 otra vez. Y otra.
     * Y otra. Para siempre. Y como `folio` tiene índice único, cada intento de
     * guardar reventaba con QueryException: la agenda quedaba inutilizable.
     *
     * ── Por qué ya no puede pasar ──
     *
     * El número lo entrega el Contador. No hay nada que ordenar, así que no hay
     * orden alfabético que pueda equivocarse. El 1001 llega igual que llegó el 2.
     */
    public function test_despues_del_folio_1000_la_serie_sigue_avanzando(): void
    {
        $this->laSerieVaPorEl(1000);

        $this->assertSame(
            $this->prefijoFolio() . '1001',
            $this->accionDeSimplificacion()->generarFolio(),
            'La serie se atascó al pasar de los 3 dígitos.'
        );
    }

    /**
     * La avería, vista desde el usuario: guardar el folio 1001 no debe chocar contra
     * el índice único de `folio`. Antes, el 1000 repetido reventaba con un error 500.
     */
    public function test_guardar_el_folio_1001_no_revienta_contra_el_indice_unico(): void
    {
        $this->laSerieVaPorEl(999);

        $mil = $this->accionDeSimplificacion();
        $mil->update(['folio' => $mil->generarFolio()]); // → ...-1000

        $milUno = $this->accionDeSimplificacion();

        try {
            $milUno->update(['folio' => $milUno->generarFolio()]); // → ...-1001
        } catch (QueryException $e) {
            $this->fail(
                'Al pasar de 1000 el folio se repitió y chocó contra su índice único. '
                . 'La agenda queda inutilizable. Mensaje de la base: ' . $e->getMessage()
            );
        }

        $this->assertSame($this->prefijoFolio() . '1000', $mil->fresh()->folio);
        $this->assertSame($this->prefijoFolio() . '1001', $milUno->fresh()->folio);
    }

    /**
     * Dos acciones que piden folio a la vez no pueden llevarse el mismo número.
     *
     * Es el mismo escenario de concurrencia que el de la homoclave, aplicado a la
     * agenda. Antes, las dos leían el mismo "último folio" de la tabla y calculaban
     * lo mismo.
     */
    public function test_dos_acciones_simultaneas_no_reciben_el_mismo_folio(): void
    {
        $primera = $this->accionDeSimplificacion();
        $segunda = $this->accionDeSimplificacion();

        // Ninguna ha guardado su folio: es el escenario de dos peticiones que se cruzan.
        $folioPrimera = $primera->generarFolio();
        $folioSegunda = $segunda->generarFolio();

        $this->assertNotSame(
            $folioPrimera,
            $folioSegunda,
            'Dos acciones distintas no pueden compartir folio: es su identificador oficial.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Aislamiento de la serie
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * La serie del folio se reinicia por tipo, dependencia y año. Está bien así y es
     * deliberado (lo dice el docblock del trait).
     *
     * Esta prueba existe para que nadie "simplifique" la clave del contador y
     * convierta el consecutivo en global sin querer: una acción de simplificación
     * (SIM) y una de digitalización (DIG) de la misma dependencia llevan series
     * independientes, y las dos empiezan en 001.
     */
    public function test_cada_tipo_de_accion_lleva_su_propia_serie_de_folios(): void
    {
        // La serie SIM ya va por el 7.
        $this->laSerieVaPorEl(7);

        $digitalizacion = AccionAgenda::factory()->create([
            'dependencia_id' => $this->accionDeSimplificacion()->dependencia_id,
            'tipo'           => 'digitalizacion', // → prefijo DIG, serie distinta
            'folio'          => null,
        ]);

        $this->assertSame(
            'LPZ-DIG-DGGD-' . now()->year . '-001',
            $digitalizacion->generarFolio(),
            'La serie DIG no debe verse afectada por lo que haya gastado la serie SIM.'
        );
    }
}
