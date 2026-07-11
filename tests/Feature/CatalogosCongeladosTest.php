<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Firma;
use App\Models\User;
use App\Services\FirmaDigitalService;
use Illuminate\Http\Request;
use App\Models\Tramite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del congelado de catálogos al firmar.
 *
 * Un trámite firmado dice, por ejemplo, que lo tramita la "Dirección General de
 * Gobierno Digital". Si esa dependencia se renombra, el trámite —que lee el nombre
 * del catálogo vivo— pasaría a decir otra cosa: se estaría cambiando el contenido de
 * un documento firmado sin que nadie lo firmara de nuevo.
 *
 * Por eso, al firmar, el registro guarda una FOTO de los nombres que usó:
 *
 *   - Lo firmado se muestra siempre con esa foto.
 *   - Si el catálogo cambia después, el sistema lo detecta y puede AVISAR, para que
 *     una persona decida si el documento debe rehacerse. El sistema no cambia nada
 *     por su cuenta.
 */
class CatalogosCongeladosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_tramite_sin_firmar_muestra_el_nombre_vivo_del_catalogo(): void
    {
        // Mientras no se firma, el trámite es un borrador: refleja el catálogo actual.
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        $this->assertSame('Dirección de Gobierno', $tramite->nombreDeCatalogo('dependencia'));
        $this->assertFalse($tramite->catalogosCongelados());
    }

    public function test_al_congelar_se_guarda_el_nombre_del_momento(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        $tramite->congelarCatalogos();

        $this->assertTrue($tramite->fresh()->catalogosCongelados());
        $this->assertSame('Dirección de Gobierno', $tramite->fresh()->nombreDeCatalogo('dependencia'));
    }

    public function test_si_la_dependencia_se_renombra_el_tramite_firmado_sigue_diciendo_lo_mismo(): void
    {
        // El corazón del asunto: un documento firmado no cambia solo.
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        $tramite->congelarCatalogos(); // se firma

        // Meses después, la dependencia se reorganiza y cambia de nombre.
        $dependencia->update(['nombre' => 'Dirección de Innovación']);

        $this->assertSame(
            'Dirección de Gobierno',
            $tramite->fresh()->nombreDeCatalogo('dependencia'),
            'El trámite firmado debe seguir diciendo lo que decía cuando se firmó.'
        );
    }

    public function test_el_sistema_avisa_de_que_el_catalogo_cambio(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        $tramite->congelarCatalogos();
        $dependencia->update(['nombre' => 'Dirección de Innovación']);

        $tramite = $tramite->fresh();

        $this->assertTrue(
            $tramite->tieneCatalogosDesactualizados(),
            'Debe avisar de que algo cambió desde la firma.'
        );

        $cambios = $tramite->catalogosDesactualizados();

        $this->assertSame('Dirección de Gobierno',   $cambios['dependencia']['al_firmar']);
        $this->assertSame('Dirección de Innovación', $cambios['dependencia']['ahora']);
    }

    public function test_si_nada_cambia_no_hay_nada_que_avisar(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        $tramite->congelarCatalogos();

        $this->assertFalse($tramite->fresh()->tieneCatalogosDesactualizados());
        $this->assertSame([], $tramite->fresh()->catalogosDesactualizados());
    }

    public function test_al_FIRMAR_de_verdad_se_congelan_los_catalogos(): void
    {
        // Prueba de extremo a extremo: no se llama a congelarCatalogos() a mano, sino
        // que se firma el trámite como lo haría una persona, y se comprueba que el
        // congelado ocurre solo.
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'estatus'        => Tramite::ESTATUS_EN_FIRMA,
        ]);

        $firmante = User::factory()->create(['rol' => User::ROL_SUJETO]);

        app(FirmaDigitalService::class)->firmar(
            firmable: $tramite,
            firmante: $firmante,
            tipo:     Firma::TIPO_ACEPTACION_SUJETO,
            request:  Request::create('/', 'POST'),
        );

        $tramite = $tramite->fresh();

        $this->assertTrue($tramite->catalogosCongelados(), 'Al firmar deben congelarse los catálogos.');
        $this->assertSame('Dirección de Gobierno', $tramite->nombreDeCatalogo('dependencia'));

        // Y si después cambia el catálogo, el documento firmado no se mueve.
        $dependencia->update(['nombre' => 'Dirección de Innovación']);

        $this->assertSame('Dirección de Gobierno', $tramite->fresh()->nombreDeCatalogo('dependencia'));
        $this->assertTrue($tramite->fresh()->tieneCatalogosDesactualizados());
    }

    public function test_congelar_dos_veces_no_sobrescribe_la_primera_foto(): void
    {
        // Un documento se firma una vez. Volver a fotografiar sería justo el problema
        // que se quiere evitar: el registro pasaría a decir algo distinto de lo que se
        // firmó.
        $dependencia = Dependencia::factory()->create(['nombre' => 'Dirección de Gobierno']);
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        $tramite->congelarCatalogos();

        $dependencia->update(['nombre' => 'Dirección de Innovación']);
        $tramite->fresh()->congelarCatalogos(); // segundo intento

        $this->assertSame(
            'Dirección de Gobierno',
            $tramite->fresh()->nombreDeCatalogo('dependencia'),
            'La foto original no se toca.'
        );
    }
}
