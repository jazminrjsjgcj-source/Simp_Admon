<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prueba la semilla de jurisdicción (Fase 1b): que clasifique EXACTAMENTE las
 * dos regulaciones municipales de La Paz del corpus, y nada más.
 *
 * ── Por qué se invoca la migración a mano ────────────────────────────
 *
 * Esta semilla es una migración de DATOS: clasifica filas que ya existen. En el
 * entorno de pruebas la base nace vacía (RefreshDatabase), así que cuando corrió
 * durante el arranque no había ninguna fila que tocar. Cada prueba crea primero
 * sus filas y luego ejecuta la migración a mano (`require` devuelve el objeto;
 * se le llama `up()`). Es seguro porque la semilla es idempotente.
 *
 * ── Qué garantiza ────────────────────────────────────────────────────
 *
 * Que la semilla acierta por sus dos lados, y en particular que está BLINDADA
 * contra la ambigüedad del nombre: "Municipio de La Paz" también existe en el
 * Estado de México, y la semilla NO debe clasificar esa otra como BCS. Al
 * nombrar las dos leyes exactas, cualquier otra ley —incluida la otra La Paz—
 * se queda sin tocar.
 */
class SemillaJurisdiccionTest extends TestCase
{
    use RefreshDatabase;

    /** Ejecuta la migración de semilla contra las filas que ya estén en la base. */
    private function correrSemilla(): void
    {
        $migracion = require database_path(
            'migrations/2026_07_18_000003_clasificar_jurisdiccion_regulaciones_existentes.php'
        );
        $migracion->up();
    }

    /**
     * Crea una regulación con un nombre dado y SIN jurisdicción.
     *
     * El modelo ahora pone jurisdicción por defecto al crear (Fase 3), así que para
     * probar la semilla histórica —que clasifica filas que quedaron en NULL antes de
     * ese default— se fuerza el null con un update directo, saltando el modelo.
     */
    private function regulacionLlamada(string $nombre): Regulacion
    {
        $regulacion = Regulacion::factory()->create(['nombre' => $nombre]);

        DB::table('regulaciones')->where('id', $regulacion->id)->update([
            'ambito' => null, 'estado' => null, 'municipio' => null,
        ]);

        return $regulacion->refresh();
    }

    public function test_clasifica_las_dos_regulaciones_reales_de_la_paz(): void
    {
        // Los dos nombres oficiales, tal cual están en la base. El del Bando trae
        // "de la Paz" en minúscula: al usar el nombre exacto, esa diferencia de
        // mayúsculas no importa, porque no se compara un patrón sino el nombre real.
        $nombres = [
            'Ley de Hacienda para el Municipio de La Paz',
            'Bando de Policía, Buen Gobierno y Justicia Cívica del Municipio de la Paz',
        ];

        $regulaciones = array_map(fn ($n) => $this->regulacionLlamada($n), $nombres);

        $this->correrSemilla();

        foreach ($regulaciones as $regulacion) {
            $regulacion->refresh();
            $this->assertSame('municipal', $regulacion->ambito, "Debió clasificar: {$regulacion->nombre}");
            $this->assertSame('BCS', $regulacion->estado);
            $this->assertSame('La Paz', $regulacion->municipio);
        }
    }

    public function test_no_toca_la_otra_la_paz_ni_otras_jurisdicciones(): void
    {
        // Ninguna de estas debe ser clasificada por la semilla. Se quedan en NULL,
        // esperando su propia clasificación:
        //
        //   - LA OTRA LA PAZ (Estado de México): este es el caso del blindaje. Su
        //     nombre contiene "Municipio de La Paz", que a un patrón lo engañaría;
        //     al nombrar las leyes exactas, no la toca;
        //   - otro municipio del mismo estado (Los Cabos, BCS);
        //   - una ley estatal de BCS (es de BCS, pero no municipal de La Paz);
        //   - una ley federal;
        //   - un señuelo donde "paz" es concepto, no la ciudad.
        $ajenas = [
            'Bando de Policía y Buen Gobierno del Municipio de La Paz, Estado de México',
            'Bando de Policía y Buen Gobierno del Municipio de Los Cabos',
            'Ley de Hacienda del Estado de Baja California Sur',
            'Ley General de Salud',
            'Reglamento para la Cultura de la Paz y la Convivencia Ciudadana',
        ];

        $regulaciones = array_map(fn ($n) => $this->regulacionLlamada($n), $ajenas);

        $this->correrSemilla();

        foreach ($regulaciones as $regulacion) {
            $regulacion->refresh();
            $this->assertNull($regulacion->ambito, "No debió clasificar: {$regulacion->nombre}");
            $this->assertNull($regulacion->estado);
            $this->assertNull($regulacion->municipio);
        }
    }

    public function test_no_clasifica_una_ley_de_la_paz_que_no_es_del_corpus(): void
    {
        // Comportamiento intencional, no un olvido: la semilla es un llenado del
        // corpus CONOCIDO (dos leyes), no un clasificador general. Una ley de La Paz
        // que no sea una de esas dos NO la clasifica esta semilla —se clasificaría
        // en su alta—. Esta prueba fija esa frontera para que nadie la "corrija"
        // volviéndola un patrón y reabra la ambigüedad que acabamos de cerrar.
        $nueva = $this->regulacionLlamada('Reglamento de Comercio del Municipio de La Paz');

        $this->correrSemilla();

        $nueva->refresh();
        $this->assertNull($nueva->ambito, 'La semilla solo llena el corpus conocido, no clasifica leyes nuevas.');
    }

    public function test_no_toca_una_regulacion_sin_ciudad_en_el_nombre(): void
    {
        // El caso "sin": un nombre que no menciona ninguna ciudad. Debe quedar sin
        // ámbito, no clasificado por descarte. (Nombre aleatorio de la factory, que no
        // contiene "Municipio de La Paz".)
        $regulacion = $this->regulacionLlamada('Reglamento genérico sin municipio en el nombre');

        $this->correrSemilla();

        $regulacion->refresh();
        $this->assertNull($regulacion->ambito, 'Sin ciudad en el nombre no hay nada que clasificar.');
        $this->assertNull($regulacion->estado);
        $this->assertNull($regulacion->municipio);
    }
}
