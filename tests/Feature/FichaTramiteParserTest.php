<?php

namespace Tests\Feature;

use App\Services\FichaTramiteParserService;
use App\Services\MapeadorFichaTramiteService;
use App\Services\ResolverCatalogosFichaService;
use App\Models\Dependencia;
use App\Models\UnidadAdministrativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del IMPORTADOR DE FICHAS de trámite.
 *
 * La ficha es el "Reporte Catálogo de Trámites y Servicios" en PDF. El sistema la
 * lee y precarga con ella el formulario de alta, para no capturar a mano lo que ya
 * está escrito.
 *
 * Estas pruebas trabajan sobre el TEXTO de la ficha, no sobre el PDF: el parser
 * expone `parsearTexto()` justo para eso. Así la prueba es rápida, no depende de
 * tener pdftotext instalado y se centra en lo que de verdad puede romperse, que es
 * el reconocimiento de los campos.
 *
 * El texto de abajo reproduce la disposición real de una ficha del Ayuntamiento
 * (dos columnas, etiquetas en mayúsculas, bloques de concepto/fórmula/costo).
 */
class FichaTramiteParserTest extends TestCase
{
    use RefreshDatabase;

    /** Ficha de ejemplo, con la misma forma que produce `pdftotext -layout`. */
    private function textoFicha(): string
    {
        return <<<'TXT'
                                     DATOS DEL TRÁMITE O SERVICIO

NOMBRE CONSTANCIAS DE REGISTRO PARA SERVICIO MILITAR             HOMOCLAVE                LPZ-SG-1

TIPO         TRÁMITE     CLASE CIUDADANO       X    EMPRESARIA

DESCRIPCIÓN    CONSTANCIAS SOBRE EL ESTADO ACTUAL DEL REGISTRO AL SERVICIO MILITAR

                                               COBRO

       TIENE COBRO              SI    X            NO

CONCEPTO       LEGALIZACIÓN DE FIRMAS Y CERTIFICACIONES
DESCRIPCIÓN    EXPEDICIÓN DE CONSTANCIA DE REGISTRO
FORMULA        (UMA*2)+30%RESULTADO ANTERIOR+2
COSTO          $307.01

CONCEPTO       LEGALIZACIÓN DE FIRMAS Y CERTIFICACIONES
DESCRIPCIÓN    EXPEDICIÓN DE CONSTANCIA DE NO REGISTRO
FORMULA        (UMA*2)+30%RESULTADO ANTERIOR+2
COSTO          $307.01

 LUGAR DE PAGO                    FORMA DE PAGO
CAJAS RECAUDADORAS                EFECTIVO, TARJETA CREDITO
                                  MARCO JURIDICO

LEY            LEY DE HACIENDA PARA EL MUNICIPIO DE LA PAZ, BAJA CALIFORNIA SUR.
ARTÍCULOS      70, 94 (IV) Y 159

Vigencia     0-VARIABLE                Tiempo máximo de respuesta     2-DÍAS HÁBILES

NOMBRE DEL DOCUMENTO   ACTA DE NACIMIENTO
ORIGINAL               0                     COPIAS       1

MÓDULO         DEPARTAMENTO DE RECLUTAMIENTO
UBICACIÓN      H. AYUNTAMIENTO. BLVD LUIS DONALDO COLOSIO
HORARIOS       LUNES-VIERNES 8:00 A 3:00 PM
TELÉFONOS      6121237900 EXT. 1140
EMAILS

PROCEDIMIENTO EN MÓDULO     1. TRAER REQUISITOS COMPLETOS
                            2. PASAR A PAGAR
                            3. RECOGER CONSTANCIA
CONDICIÓN PARA REALIZAR EL
TRÁMITE                     SER MAYOR DE EDAD.

NO APLICA AFIRMATIVA FICTA
TXT;
    }

    private function ficha(): array
    {
        return app(FichaTramiteParserService::class)->parsearTexto($this->textoFicha());
    }

    // ─────────────────────────────────────────────────────────────
    //  Datos de identificación
    // ─────────────────────────────────────────────────────────────

    public function test_lee_nombre_y_homoclave_aunque_compartan_renglon(): void
    {
        $f = $this->ficha();

        $this->assertSame('CONSTANCIAS DE REGISTRO PARA SERVICIO MILITAR', $f['nombre']);
        $this->assertSame('LPZ-SG-1', $f['homoclave']);
    }

    public function test_distingue_tramite_de_servicio_y_a_quien_va_dirigido(): void
    {
        $f = $this->ficha();

        $this->assertSame('tramite', $f['naturaleza']);
        // La X está en CIUDADANO, no en EMPRESARIA.
        $this->assertSame('ciudadano', $f['dirigido_a']);
    }

    public function test_lee_la_descripcion_completa(): void
    {
        $this->assertSame(
            'CONSTANCIAS SOBRE EL ESTADO ACTUAL DEL REGISTRO AL SERVICIO MILITAR',
            $this->ficha()['descripcion']
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Bloques repetidos
    // ─────────────────────────────────────────────────────────────

    public function test_lee_todos_los_conceptos_de_cobro(): void
    {
        $f = $this->ficha();

        $this->assertTrue($f['tiene_cobro']);
        $this->assertCount(2, $f['costos']);
        $this->assertSame(307.01, $f['costos'][0]['costo']);
        $this->assertSame('EXPEDICIÓN DE CONSTANCIA DE REGISTRO', $f['costos'][0]['descripcion']);
        $this->assertSame('EXPEDICIÓN DE CONSTANCIA DE NO REGISTRO', $f['costos'][1]['descripcion']);
    }

    public function test_lee_los_requisitos_con_sus_cantidades(): void
    {
        $f = $this->ficha();

        $this->assertCount(1, $f['requisitos']);
        $this->assertSame('ACTA DE NACIMIENTO', $f['requisitos'][0]['documento']);
        $this->assertSame(0, $f['requisitos'][0]['original']);
        $this->assertSame(1, $f['requisitos'][0]['copias']);
    }

    public function test_lee_los_pasos_del_procedimiento(): void
    {
        $this->assertCount(3, $this->ficha()['procedimiento']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Campos en dos columnas y marco jurídico
    // ─────────────────────────────────────────────────────────────

    public function test_separa_lugar_de_pago_de_forma_de_pago(): void
    {
        $f = $this->ficha();

        // Van en columnas distintas del mismo renglón: si se mezclaran, el lugar
        // arrastraría el texto de la derecha.
        $this->assertSame('CAJAS RECAUDADORAS', $f['lugar_pago']);
        $this->assertStringContainsString('EFECTIVO', $f['forma_pago']);
        $this->assertStringNotContainsString('EFECTIVO', $f['lugar_pago']);
    }

    public function test_lee_la_ley_y_sus_articulos(): void
    {
        $f = $this->ficha();

        $this->assertStringContainsString('LEY DE HACIENDA', $f['ley']);
        $this->assertSame('70, 94 (IV) Y 159', $f['articulos']);
    }

    public function test_la_afirmativa_ficta_se_lee_en_negativo(): void
    {
        // La ficha dice "NO APLICA AFIRMATIVA FICTA", así que debe quedar en falso.
        $this->assertFalse($this->ficha()['afirmativa_ficta']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Mapeo al formulario
    // ─────────────────────────────────────────────────────────────

    /**
     * REGRESIÓN: la ficha escribe "2-DÍAS HÁBILES", con acento. Al comparar sin
     * quitar acentos, "hábiles" nunca casaba con "habil" y la unidad del plazo se
     * perdía: el formulario quedaba con la cantidad pero sin decir de qué.
     */
    public function test_el_plazo_con_acentos_se_traduce_a_cantidad_y_unidad(): void
    {
        $mapeo = app(MapeadorFichaTramiteService::class)->mapear(
            $this->ficha(),
            ['dependencia_id' => null, 'unidad_id' => null]
        );

        $this->assertSame(2, $mapeo['escalares']['plazo_resolucion_cantidad']);
        $this->assertSame('habiles', $mapeo['escalares']['plazo_resolucion_unidad']);
    }

    public function test_los_costos_y_pasos_viajan_como_json_para_el_formulario(): void
    {
        $mapeo = app(MapeadorFichaTramiteService::class)->mapear(
            $this->ficha(),
            ['dependencia_id' => null, 'unidad_id' => null]
        );

        $derechos = json_decode($mapeo['escalares']['derechos_json'], true);
        $pasos    = json_decode($mapeo['escalares']['pasos_json'], true);

        $this->assertCount(2, $derechos);
        $this->assertSame(307.01, $derechos[0]['monto']);
        $this->assertCount(3, $pasos);
    }

    public function test_los_campos_vacios_no_se_precargan(): void
    {
        $mapeo = app(MapeadorFichaTramiteService::class)->mapear(
            ['nombre' => 'Solo el nombre'],
            ['dependencia_id' => null, 'unidad_id' => null]
        );

        // Sin datos no se inventan claves: el formulario las deja en blanco.
        $this->assertArrayNotHasKey('homoclave', $mapeo['escalares']);
        $this->assertArrayNotHasKey('dependencia_id', $mapeo['escalares']);
        $this->assertSame('Solo el nombre', $mapeo['escalares']['nombre_oficial']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Emparejar con los catálogos
    // ─────────────────────────────────────────────────────────────

    public function test_empareja_el_modulo_con_la_dependencia_del_catalogo(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Departamento de Reclutamiento']);

        $r = app(ResolverCatalogosFichaService::class)->resolver($this->ficha());

        // Sin importar mayúsculas ni acentos.
        $this->assertSame($dependencia->id, $r['dependencia_id']);
    }

    /**
     * Si el módulo de la ficha no existe en el catálogo, el select debe quedar en
     * blanco. Adivinar una dependencia en un registro oficial sería peor que
     * dejarlo vacío para que lo elija una persona.
     */
    public function test_si_el_modulo_no_esta_en_el_catalogo_no_se_adivina(): void
    {
        Dependencia::factory()->create(['nombre' => 'Tesorería Municipal']);

        $r = app(ResolverCatalogosFichaService::class)->resolver($this->ficha());

        $this->assertNull($r['dependencia_id']);
        $this->assertNull($r['unidad_id']);
    }

    public function test_si_el_modulo_es_una_unidad_toma_tambien_su_dependencia(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Secretaría del Ayuntamiento']);
        $unidad = UnidadAdministrativa::factory()->create([
            'nombre'         => 'Departamento de Reclutamiento',
            'dependencia_id' => $dependencia->id,
        ]);

        $r = app(ResolverCatalogosFichaService::class)->resolver($this->ficha());

        $this->assertSame($unidad->id, $r['unidad_id']);
        $this->assertSame($dependencia->id, $r['dependencia_id']);
    }
}
