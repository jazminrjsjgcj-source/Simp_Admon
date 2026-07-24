<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\Periodo;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Pruebas de la exportación a Excel de las agendas de simplificación y
 * digitalización.
 *
 * No basta con comprobar que la descarga responde: un archivo puede bajar
 * correctamente y venir vacío, que es justo el fallo que se reporta. Estas pruebas
 * ABREN el archivo generado y leen sus celdas, así que solo pasan si los datos
 * llegaron a la hoja.
 *
 * Las dos agendas son documentos oficiales distintos (artículos 22 y 24 de la
 * LNETB) y se llenan sobre plantillas del propio Ayuntamiento, así que se prueban
 * por separado aunque el código sea casi el mismo.
 */
class AgendaExportExcelTest extends TestCase
{
    use RefreshDatabase;

    /** Primera fila de datos de la plantilla; arriba van encabezados. */
    private const FILA_DATOS = 5;

    private Dependencia $dependencia;

    /**
     * Libros abiertos por hojaDe() durante la prueba en curso.
     *
     * Hay que guardarlos porque hojaDe() devuelve una HOJA, y esa hoja mantiene
     * vivo a su libro entero (~32 000 celdas). Sin cerrarlos, cada prueba deja su
     * plantilla en memoria para las siguientes y la suite acaba sin RAM.
     *
     * @var \PhpOffice\PhpSpreadsheet\Spreadsheet[]
     */
    private array $librosAbiertos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\AclSeeder::class);
        $this->dependencia = Dependencia::factory()->create();
    }

    /**
     * Cierra los libros de la prueba que acaba de terminar.
     *
     * PHPUnit conserva el objeto del caso de prueba durante toda la ejecución, así
     * que vaciar el array aquí es lo que impide que se acumulen de prueba en prueba.
     */
    protected function tearDown(): void
    {
        foreach ($this->librosAbiertos as $libro) {
            $libro->disconnectWorksheets();
        }

        $this->librosAbiertos = [];

        parent::tearDown();
    }

    /**
     * La agenda es un instrumento oficial que reporta compromisos de TODAS las
     * dependencias, así que quien la exporta es la autoridad revisora, no un enlace.
     */
    private function usuario(string $rol = User::ROL_REVISORA): User
    {
        $usuario = User::factory()->create([
            'rol'            => $rol,
            'activo'         => true,
            'dependencia_id' => $this->dependencia->id,
        ]);

        $usuario->roles()->attach(Role::where('codigo', $rol)->firstOrFail()->id);
        $usuario->olvidarPermisosCache();

        return $usuario;
    }

    /**
     * Crea una acción de agenda con su trámite.
     *
     * @param  array  $extra         Campos a sobrescribir en la acción (p. ej. periodo_id).
     * @param  array  $tramiteExtra  Campos a sobrescribir en el trámite (p. ej. homoclave),
     *                               necesarios cuando una prueba crea varias acciones y
     *                               tiene que distinguirlas por su fila en el Excel.
     */
    private function accion(string $tipo, array $extra = [], array $tramiteExtra = []): AccionAgenda
    {
        $tramite = Tramite::factory()->create(array_merge([
            'dependencia_id' => $this->dependencia->id,
            'nombre_oficial' => 'Licencia de funcionamiento',
            'homoclave'      => 'LPZ-T-TEST-1',
        ], $tramiteExtra));

        return AccionAgenda::create(array_merge([
            'dependencia_id'   => $this->dependencia->id,
            'tramite_id'       => $tramite->id,
            'tipo'             => $tipo,
            'descripcion'      => 'Reducir requisitos y habilitar el trámite en línea',
            'meta'             => 'Trámite disponible en línea',
            'indicador'        => 'Porcentaje de solicitudes en línea',
            'fecha_compromiso' => now()->addMonths(3),
            'estatus'          => 'borrador',
        ], $extra));
    }

    /** Crea un periodo de Agenda SyD. Solo puede haber uno con estatus 'activo'. */
    private function periodo(string $nombre, string $inicio, string $fin, string $estatus): Periodo
    {
        return Periodo::create([
            'nombre'       => $nombre,
            'fecha_inicio' => $inicio,
            'fecha_fin'    => $fin,
            'estatus'      => $estatus,
            'tipo'         => Periodo::TIPO_SYD,
        ]);
    }

    /**
     * Marca una acción como implementada de verdad.
     *
     * Lo que cuenta NO es el estatus de la acción —'completado' solo significa que el
     * papeleo está firmado—, sino que todos sus hitos estén en estado_aprobacion
     * 'aprobado'. Un solo hito aprobado basta para que la acción no tenga ninguno
     * pendiente.
     */
    private function marcarImplementada(AccionAgenda $accion): void
    {
        $accion->hitos()->create([
            'orden'             => 1,
            'clave'             => 'diagnostico',
            'nombre'            => 'Diagnóstico',
            'completado'        => true,
            'estado_aprobacion' => 'aprobado',
        ]);
    }

    /**
     * Descarga la exportación y devuelve la hoja de datos ya abierta.
     *
     * El libro se registra en $librosAbiertos para que tearDown() lo cierre: la
     * hoja que se devuelve lo mantiene vivo, y son ~32 000 celdas por llamada.
     */
    private function hojaDe(string $ruta, string $nombreHoja)
    {
        $respuesta = $this->actingAs($this->usuario())->get(route($ruta));
        $respuesta->assertOk();

        $archivo = tempnam(sys_get_temp_dir(), 'agenda') . '.xlsx';
        file_put_contents($archivo, $respuesta->streamedContent());

        $libro = IOFactory::load($archivo);
        @unlink($archivo);

        $this->librosAbiertos[] = $libro;

        $hoja = $libro->getSheetByName($nombreHoja);

        $this->assertNotNull($hoja, "La plantilla no tiene la hoja '{$nombreHoja}'.");

        return $hoja;
    }

    // ─────────────────────────────────────────────────────────────
    //  Las plantillas existen y tienen la hoja que el código busca
    // ─────────────────────────────────────────────────────────────

    /**
     * Si alguien renombra una hoja de la plantilla oficial, la exportación falla en
     * producción y no antes. Esta prueba lo detecta al momento.
     */
    public function test_las_plantillas_traen_la_hoja_que_el_codigo_busca(): void
    {
        $esperado = [
            'plantilla_simplificacion.xlsx' => '1. Trámites a simplificar',
            'plantilla_digitalizacion.xlsx' => '1. Trámites a digitalizar',
        ];

        foreach ($esperado as $archivo => $hoja) {
            $ruta = resource_path('templates/' . $archivo);

            $this->assertFileExists($ruta, "Falta la plantilla oficial {$archivo}.");

            // El libro se guarda en una variable a propósito, para poder cerrarlo.
            // Encadenar IOFactory::load(...)->getSheetByName(...) NO lo libera: la
            // hoja referencia al libro y el libro a la hoja, y un ciclo así no lo
            // deshace el conteo de referencias de PHP. Cada plantilla son ~32 000
            // celdas que se quedarían vivas el resto de la suite.
            $libro = IOFactory::load($ruta);

            $this->assertNotNull(
                $libro->getSheetByName($hoja),
                "La plantilla {$archivo} no contiene la hoja '{$hoja}'."
            );

            $libro->disconnectWorksheets();
            unset($libro);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Simplificación
    // ─────────────────────────────────────────────────────────────

    public function test_la_agenda_de_simplificacion_escribe_los_datos(): void
    {
        $this->accion('simplificacion');

        $hoja = $this->hojaDe('agenda.exportar.simp', '1. Trámites a simplificar');
        $f    = self::FILA_DATOS;

        $this->assertSame(1, $hoja->getCell("A{$f}")->getValue(), 'Falta el consecutivo.');
        $this->assertSame('LPZ-T-TEST-1', $hoja->getCell("C{$f}")->getValue());
        $this->assertSame('Licencia de funcionamiento', $hoja->getCell("D{$f}")->getValue());
        $this->assertSame('Trámite disponible en línea', $hoja->getCell("R{$f}")->getValue());
    }

    // ─────────────────────────────────────────────────────────────
    //  Digitalización
    // ─────────────────────────────────────────────────────────────

    /**
     * Es el caso que se reporta como que no se llena. Se comprueba igual que el
     * anterior: abriendo el archivo y leyendo las celdas.
     */
    public function test_la_agenda_de_digitalizacion_escribe_los_datos(): void
    {
        $this->accion('digitalizacion');

        $hoja = $this->hojaDe('agenda.exportar.dig', '1. Trámites a digitalizar');
        $f    = self::FILA_DATOS;

        $this->assertSame(1, $hoja->getCell("A{$f}")->getValue(), 'La hoja de digitalización salió vacía.');
        $this->assertSame('LPZ-T-TEST-1', $hoja->getCell("C{$f}")->getValue());
        $this->assertSame('Licencia de funcionamiento', $hoja->getCell("D{$f}")->getValue());
    }

    // ─────────────────────────────────────────────────────────────
    //  Qué acción entra en qué agenda
    // ─────────────────────────────────────────────────────────────

    /**
     * Una acción de tipo 'ambas' cuenta en las DOS agendas: se comprometió a
     * simplificar y a digitalizar el mismo trámite. Si se quedara fuera de una, el
     * documento oficial reportaría menos compromisos de los adquiridos.
     */
    public function test_una_accion_de_tipo_ambas_aparece_en_las_dos_agendas(): void
    {
        $this->accion('ambas');

        foreach ([['agenda.exportar.simp', '1. Trámites a simplificar'],
                  ['agenda.exportar.dig',  '1. Trámites a digitalizar']] as [$ruta, $hojaNombre]) {
            $hoja = $this->hojaDe($ruta, $hojaNombre);

            $this->assertSame(
                'LPZ-T-TEST-1',
                $hoja->getCell('C' . self::FILA_DATOS)->getValue(),
                "La acción de tipo 'ambas' no salió en {$hojaNombre}."
            );
        }
    }

    public function test_cada_agenda_excluye_las_acciones_del_otro_tipo(): void
    {
        $this->accion('digitalizacion');

        $hoja = $this->hojaDe('agenda.exportar.simp', '1. Trámites a simplificar');

        $this->assertNull(
            $hoja->getCell('C' . self::FILA_DATOS)->getValue(),
            'Una acción de digitalización no debe aparecer en la agenda de simplificación.'
        );
    }

    /**
     * La plantilla trae filas de ejemplo de fábrica. Si no se limpian, el documento
     * oficial sale con datos inventados mezclados con los reales.
     */
    public function test_no_quedan_los_datos_de_ejemplo_de_la_plantilla(): void
    {
        $this->accion('digitalizacion');

        $hoja = $this->hojaDe('agenda.exportar.dig', '1. Trámites a digitalizar');

        // Con una sola acción, la segunda fila de datos tiene que estar vacía.
        $this->assertNull($hoja->getCell('C' . (self::FILA_DATOS + 1))->getValue());
        $this->assertNull($hoja->getCell('D' . (self::FILA_DATOS + 1))->getValue());
    }

    /**
     * La agenda completa reporta los compromisos de todas las dependencias, así que
     * no puede descargarla cualquiera que tenga sesión.
     *
     * Las rutas de exportación van dentro de un grupo 'role:revisora,admin'. Antes
     * el control era solo visual —la vista escondía los botones—, lo que no impedía
     * escribir la dirección a mano.
     */
    public function test_un_enlace_no_puede_exportar_la_agenda_completa(): void
    {
        $enlace = $this->usuario(User::ROL_ENLACE);

        $this->actingAs($enlace)->get(route('agenda.exportar.simp'))->assertForbidden();
        $this->actingAs($enlace)->get(route('agenda.exportar.dig'))->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────
    //  Acarreo entre semestres (art. 22 LNETB)
    // ─────────────────────────────────────────────────────────────

    /**
     * Art. 22 LNETB: las acciones programadas que no se realizaron en su periodo
     * "se contemplarán en el siguiente semestre". Si la exportación solo mirara el
     * periodo activo, el compromiso incumplido desaparecería del documento oficial
     * en lugar de arrastrarse, que es justo lo contrario de lo que pide la ley.
     */
    public function test_arrastra_las_acciones_no_implementadas_del_periodo_anterior(): void
    {
        $anterior = $this->periodo('2025-2', '2025-07-01', '2025-12-31', Periodo::ESTATUS_CERRADO);
        $this->periodo('2026-1', '2026-01-01', '2026-06-30', Periodo::ESTATUS_ACTIVO);

        // Sin hitos aprobados: quedó pendiente al cerrar su semestre.
        $this->accion('simplificacion', ['periodo_id' => $anterior->id], ['homoclave' => 'LPZ-T-REZAGADA']);

        $hoja = $this->hojaDe('agenda.exportar.simp', '1. Trámites a simplificar');

        $this->assertSame(
            'LPZ-T-REZAGADA',
            $hoja->getCell('C' . self::FILA_DATOS)->getValue(),
            'La acción pendiente del semestre anterior no se arrastró a la agenda nueva.'
        );
    }

    /**
     * La otra mitad de la regla: lo que SÍ se implementó no se arrastra, o la agenda
     * repetiría cada semestre todo lo ya hecho.
     *
     * "Implementada" se mide por hitos aprobados, no por el estatus de la acción:
     * ESTATUS_COMPLETADO solo significa que el papeleo está firmado.
     */
    public function test_no_arrastra_las_acciones_ya_implementadas_del_periodo_anterior(): void
    {
        $anterior = $this->periodo('2025-2', '2025-07-01', '2025-12-31', Periodo::ESTATUS_CERRADO);
        $this->periodo('2026-1', '2026-01-01', '2026-06-30', Periodo::ESTATUS_ACTIVO);

        $hecha = $this->accion('simplificacion', ['periodo_id' => $anterior->id], ['homoclave' => 'LPZ-T-HECHA']);
        $this->marcarImplementada($hecha);

        $hoja = $this->hojaDe('agenda.exportar.simp', '1. Trámites a simplificar');

        $this->assertNull(
            $hoja->getCell('C' . self::FILA_DATOS)->getValue(),
            'Una acción ya implementada no debe repetirse en la agenda del semestre siguiente.'
        );
    }

    /**
     * Art. 22 LNETB: las acciones arrastradas van "teniendo prioridad". En un
     * documento que es un calendario, prioridad significa ir primero, así que la
     * rezagada tiene que ocupar la fila 5 y la del semestre en curso la 6.
     */
    public function test_las_rezagadas_van_antes_que_las_del_periodo_en_curso(): void
    {
        $anterior = $this->periodo('2025-2', '2025-07-01', '2025-12-31', Periodo::ESTATUS_CERRADO);
        $actual   = $this->periodo('2026-1', '2026-01-01', '2026-06-30', Periodo::ESTATUS_ACTIVO);

        $this->accion('simplificacion', ['periodo_id' => $anterior->id], ['homoclave' => 'LPZ-T-REZAGADA']);
        $this->accion('simplificacion', ['periodo_id' => $actual->id],   ['homoclave' => 'LPZ-T-NUEVA']);

        $hoja = $this->hojaDe('agenda.exportar.simp', '1. Trámites a simplificar');

        $this->assertSame(
            'LPZ-T-REZAGADA',
            $hoja->getCell('C' . self::FILA_DATOS)->getValue(),
            'La acción rezagada debe ir primera: art. 22 LNETB le da prioridad.'
        );
        $this->assertSame(
            'LPZ-T-NUEVA',
            $hoja->getCell('C' . (self::FILA_DATOS + 1))->getValue(),
            'La acción del semestre en curso debe ir después de las rezagadas.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Columna que sí depende del tipo
    // ─────────────────────────────────────────────────────────────

    /**
     * La columna P es lo único que se llena distinto según la agenda: lleva las
     * acciones del catálogo de simplificación o las de digitalización. Es el punto
     * donde un error de mapeo dejaría una de las dos en blanco.
     */
    public function test_cada_agenda_toma_las_acciones_de_su_propio_catalogo(): void
    {
        $this->accion('ambas', [
            'acciones_simplificacion' => ['Reducir requisitos'],
            'acciones_digitalizacion' => ['Habilitar pago en línea'],
        ]);

        $simp = $this->hojaDe('agenda.exportar.simp', '1. Trámites a simplificar');
        $dig  = $this->hojaDe('agenda.exportar.dig',  '1. Trámites a digitalizar');

        $this->assertSame('Reducir requisitos',      $simp->getCell('P' . self::FILA_DATOS)->getValue());
        $this->assertSame('Habilitar pago en línea', $dig->getCell('P' . self::FILA_DATOS)->getValue());
    }
}
