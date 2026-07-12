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

    public function test_cada_llamada_entrega_un_consecutivo_distinto_y_creciente(): void
    {
        // Esta prueba sustituye a la anterior, que afirmaba que el consecutivo se
        // "leía del último segmento de la homoclave". Eso era el MECANISMO, no la
        // regla: describía cómo estaba hecho por dentro, no qué tenía que cumplir.
        //
        // Al cambiar el mecanismo (el número ahora lo reserva el Contador, en vez
        // de deducirse leyendo el máximo de la tabla), aquella prueba se ponía roja
        // aunque el sistema siguiera siendo correcto. Señal de que probaba lo que
        // no debía.
        //
        // La regla de verdad es esta: pedir dos veces nunca devuelve lo mismo, y la
        // serie siempre avanza. Eso es lo que impide que dos trámites compartan
        // identificador oficial, y sigue siendo cierto con cualquier mecanismo.
        $primero = Tramite::siguienteConsecutivoGlobal();
        $segundo = Tramite::siguienteConsecutivoGlobal();

        $this->assertSame($primero + 1, $segundo);
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

    public function test_un_tramite_en_la_papelera_no_libera_su_numero(): void
    {
        // La regla no ha cambiado: un trámite borrado NO devuelve su número a la
        // serie. Si lo devolviera, el siguiente trámite recibiría una homoclave que
        // ya salió impresa en un acuse firmado.
        //
        // Lo que cambia es CÓMO se comprueba. Antes se escribía la homoclave a mano
        // en la base y se afirmaba sobre el número calculado; eso ataba la prueba al
        // mecanismo viejo. Ahora se comprueba el efecto observable: el trámite nuevo
        // no puede recibir la homoclave del borrado.
        $borrado = $this->tramiteCon('DGGD', 'DSA');
        $borrado->update(['homoclave' => $borrado->generarHomoclave()]);
        $homoclaveGastada = $borrado->fresh()->homoclave;
        $borrado->delete(); // soft delete

        $nuevo = $this->tramiteCon('DGGD', 'DSA');
        $nuevo->update(['homoclave' => $nuevo->generarHomoclave()]);

        $this->assertNotSame(
            $homoclaveGastada,
            $nuevo->fresh()->homoclave,
            'El trámite nuevo se llevó la homoclave de uno que está en la papelera.'
        );
    }
}
