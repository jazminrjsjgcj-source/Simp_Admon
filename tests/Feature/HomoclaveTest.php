<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la homoclave: el identificador oficial de un trámite o servicio.
 *
 * Formato: LPZ-{T|S}-{siglas dependencia}-{siglas unidad}-{consecutivo}
 *   Ej.: LPZ-T-DGGD-DSA-7
 *
 * Se prueba con especial cuidado porque el cálculo del consecutivo se reescribió
 * al migrar a PostgreSQL: antes usaba SUBSTRING_INDEX y CAST(... AS UNSIGNED), que
 * son exclusivos de MySQL, y ahora se resuelve en PHP.
 */
class HomoclaveTest extends TestCase
{
    use RefreshDatabase;

    /** Crea un trámite listo para generar homoclave (con dependencia y unidad con siglas). */
    private function tramiteCon(string $siglasDep, string $siglasUnidad): Tramite
    {
        $dependencia = Dependencia::factory()->create(['siglas' => $siglasDep]);
        $unidad      = UnidadAdministrativa::factory()->create([
            'dependencia_id' => $dependencia->id,
            'siglas'         => $siglasUnidad,
        ]);

        return Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'unidad_id'      => $unidad->id,
        ]);
    }

    public function test_un_tramite_genera_homoclave_con_la_letra_T(): void
    {
        $tramite = $this->tramiteCon('DGGD', 'DSA');

        $this->assertSame('LPZ-T-DGGD-DSA-1', $tramite->generarHomoclave());
    }

    public function test_un_servicio_genera_homoclave_con_la_letra_S(): void
    {
        $dependencia = Dependencia::factory()->create(['siglas' => 'DGGD']);
        $unidad      = UnidadAdministrativa::factory()->create([
            'dependencia_id' => $dependencia->id,
            'siglas'         => 'DSA',
        ]);

        $servicio = Tramite::factory()->servicio()->create([
            'dependencia_id' => $dependencia->id,
            'unidad_id'      => $unidad->id,
        ]);

        $this->assertSame('LPZ-S-DGGD-DSA-1', $servicio->generarHomoclave());
    }

    public function test_el_consecutivo_avanza_con_cada_homoclave_asignada(): void
    {
        // El consecutivo es GLOBAL: no se reinicia por dependencia ni por unidad.
        $primero = $this->tramiteCon('AAA', 'BBB');
        $primero->update(['homoclave' => $primero->generarHomoclave()]);

        $segundo = $this->tramiteCon('CCC', 'DDD');
        $segundo->update(['homoclave' => $segundo->generarHomoclave()]);

        $this->assertSame('LPZ-T-AAA-BBB-1', $primero->fresh()->homoclave);
        $this->assertSame('LPZ-T-CCC-DDD-2', $segundo->fresh()->homoclave, 'El segundo debe tomar el consecutivo 2.');
    }

    public function test_el_consecutivo_se_lee_del_ultimo_segmento_aunque_las_siglas_tengan_guiones(): void
    {
        // Esta es la prueba clave del cambio de MySQL a PostgreSQL: el consecutivo
        // sale del ÚLTIMO segmento de la homoclave. Antes se extraía con
        // SUBSTRING_INDEX; ahora se hace en PHP y debe dar el mismo resultado.
        $tramite = $this->tramiteCon('DGGD', 'DSA');
        $tramite->update(['homoclave' => 'LPZ-T-DGGD-DSA-41']);

        $this->assertSame(42, Tramite::siguienteConsecutivoGlobal(), 'Debe leer el 41 y devolver 42.');
    }

    public function test_sin_siglas_capturadas_se_derivan_del_nombre(): void
    {
        // El sistema no se queda sin homoclave por un catálogo incompleto: si a la
        // dependencia o a la unidad les faltan las siglas, se derivan de su nombre
        // (las iniciales de las palabras significativas). Es una salvaguarda
        // deliberada del modelo, no un descuido.
        $dependencia = Dependencia::factory()->sinSiglas()->create([
            'nombre' => 'Dirección General de Gobierno Digital',
        ]);
        $unidad = UnidadAdministrativa::factory()->sinSiglas()->create([
            'dependencia_id' => $dependencia->id,
            'nombre'         => 'Dirección de Simplificación Administrativa',
        ]);

        $tramite = Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'unidad_id'      => $unidad->id,
        ]);

        // "Dirección General de Gobierno Digital"      → DGGD
        // "Dirección de Simplificación Administrativa" → DSA
        $this->assertSame('LPZ-T-DGGD-DSA-1', $tramite->generarHomoclave());
    }

    public function test_sin_unidad_no_se_puede_generar_la_homoclave(): void
    {
        $tramite = Tramite::factory()->create(['unidad_id' => null]);

        $this->assertNull(
            $tramite->generarHomoclave(),
            'La homoclave incluye las siglas de la unidad: sin unidad no hay homoclave.'
        );
    }

    public function test_los_tramites_en_papelera_tambien_cuentan_para_el_consecutivo(): void
    {
        // Un trámite borrado no libera su número: si lo hiciera, dos trámites
        // distintos podrían acabar con la misma homoclave.
        $tramite = $this->tramiteCon('DGGD', 'DSA');
        $tramite->update(['homoclave' => 'LPZ-T-DGGD-DSA-10']);
        $tramite->delete(); // soft delete

        $this->assertSame(11, Tramite::siguienteConsecutivoGlobal());
    }
}
