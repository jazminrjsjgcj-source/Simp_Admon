<?php

namespace Tests\Feature;

use App\Jobs\EstructurarRegulacionJob;
use App\Models\Regulacion;
use App\Models\User;
use Database\Seeders\AclSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cuando la construcción del articulado falla, el usuario TIENE QUE ENTERARSE.
 *
 * ── El silencio que estas pruebas rompen ─────────────────────────────
 *
 * Desde que la estructuración ocurre en segundo plano, un fallo se comportaba así:
 *
 *   1. El usuario da a "Estructurar articulado".
 *   2. Ve "la página se actualizará sola cuando termine".
 *   3. El job falla.
 *   4. Se escribe una línea en el log.
 *   5. Y ya.
 *
 * La CONVERSIÓN sí había ido bien, así que la regulación se veía perfectamente normal, con su
 * botón de "Estructurar" invitando a darle otra vez. Nada en la pantalla indicaba que algo
 * hubiera fallado.
 *
 * El usuario recargaba. Y recargaba. Y suponía que el sistema seguía trabajando.
 *
 * Es el mismo patrón que este proyecto ha repetido seis veces: el sistema falla en silencio y
 * deja a alguien esperando algo que no va a pasar nunca. Y el log —donde sí quedaba escrito—
 * no lo lee nadie que esté esperando su articulado.
 *
 * ── Lo que se prueba, y por qué son dos capas ────────────────────────
 *
 * 1. Que el JOB guarde el motivo (no basta con escribirlo en el log).
 * 2. Que la FICHA lo pinte (no basta con guardarlo en la base).
 *
 * Las dos hacen falta. Un mecanismo que funciona pero que ninguna pantalla enseña es
 * exactamente el bug que ya nos comimos con los catálogos congelados y con el costo de espera:
 * el sistema sabía algo, y la pantalla callaba.
 */
class EstructuracionFallidaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->seed(AclSeeder::class);
        $this->admin = User::factory()->create(['rol' => User::ROL_ADMIN]);
    }

    /** Una regulación convertida, con el Markdown que le pasemos en el disco falso. */
    private function regulacionCon(string $markdown): Regulacion
    {
        $regulacion = Regulacion::factory()->convertida()->create(['nombre' => 'Reglamento de Comercio']);
        Storage::disk('local')->put($regulacion->archivo_markdown, $markdown);

        return $regulacion->fresh();
    }

    private function estructurar(Regulacion $regulacion): void
    {
        // Se ejecuta el job directamente, sin cola: es su comportamiento lo que se prueba, no el
        // mecanismo de encolado (que ya prueba Laravel).
        app()->call([new EstructurarRegulacionJob($regulacion, $this->admin->id), 'handle']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El job guarda el motivo
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un documento sin ningún artículo reconocible deja un mensaje que dice QUÉ HACER.
     *
     * ── LA TRAMPA QUE ESTA PRUEBA DESTAPÓ ──
     *
     * La primera versión de esta prueba comprobaba que el job registrara el error cuando
     * `$creados === 0`. Y falló, porque la premisa era falsa.
     *
     * El estructurador vuelca CUALQUIER línea de texto suelta como un nodo "párrafo". Así que un
     * documento de texto corrido, sin un solo artículo, produce DECENAS de nodos. `$creados` sale
     * alto, y el sistema lo daba por bueno.
     *
     * El usuario vería entonces un "articulado" que no es un articulado: una lista de párrafos
     * colgando de la nada, sin ningún artículo. Y sin ningún aviso, porque técnicamente no falló
     * nada.
     *
     * "Sin artículos" y "sin nodos" no son lo mismo, y yo los había confundido.
     *
     * ── Por qué la pregunta correcta es "¿hay ARTÍCULOS?" ──
     *
     * Lo que hace útil a una regulación estructurada es poder CITARLA: "Artículo 15, fracción II".
     * Si no hay artículos, no hay nada que citar, y todo el articulado no sirve para el único
     * propósito por el que existe.
     *
     * Contar nodos habría dado verde con un articulado inservible. Contar artículos, no.
     */
    public function test_un_documento_sin_articulos_deja_un_mensaje_accionable(): void
    {
        // Texto corrido, sin un solo "Artículo N.". El estructurador SÍ creará nodos con esto
        // —los volcará como párrafos sueltos—, pero ninguno será un artículo.
        $regulacion = $this->regulacionCon(
            "Este documento no tiene artículos.\n"
            . "Solo texto corrido, sin ninguna numeración reconocible.\n"
            . "Ni títulos, ni capítulos, ni fracciones.\n"
        );

        $this->estructurar($regulacion);

        $error = $regulacion->fresh()->estructuracion_error;

        $this->assertNotNull(
            $error,
            'El estructurador creó párrafos sueltos y dio el trabajo por bueno. El usuario ve un '
            . '"articulado" sin un solo artículo, y nadie le avisa de que no puede citar nada.'
        );

        $this->assertStringContainsString('Artículo 1.', $error, 'Debe decir qué formatos reconoce.');
        $this->assertStringContainsString('editor',      $error, 'Y qué hacer: capturarlo a mano.');
    }

    /**
     * Y esta es la otra mitad, la que impide que el arreglo se pase de frenada.
     *
     * Un documento CON artículos no debe dar ningún error, aunque además tenga párrafos sueltos
     * —que los tiene siempre: los considerandos, los transitorios, el preámbulo—.
     *
     * Sin esta prueba, un job que se quejara en cuanto viera un párrafo pasaría la anterior tan
     * tranquilo, y marcaría como fallidas TODAS las regulaciones del municipio.
     */
    public function test_un_documento_con_articulos_no_da_error_aunque_tenga_parrafos_sueltos(): void
    {
        $regulacion = $this->regulacionCon(
            "Considerando que resulta necesario regular el comercio en la vía pública.\n"
            . "\n"
            . "Artículo 1. El presente reglamento es de orden público e interés social.\n"
            . "\n"
            . "Artículo 2. Corresponde a la autoridad municipal su aplicación.\n"
        );

        $this->estructurar($regulacion);

        $this->assertNull(
            $regulacion->fresh()->estructuracion_error,
            'Hay dos artículos: el articulado se construyó bien. Un párrafo suelto (el '
            . 'considerando) es normal en cualquier reglamento y no puede marcar la '
            . 'estructuración como fallida.'
        );
    }

    /**
     * Si la conversión no está lista, el mensaje apunta a la conversión — no al articulado.
     *
     * Es un fallo distinto y una acción distinta: aquí el usuario tiene que reintentar la
     * conversión, no capturar nada a mano. Un mensaje genérico ("falló la estructuración") lo
     * mandaría al sitio equivocado.
     */
    public function test_sin_conversion_lista_el_mensaje_apunta_a_la_conversion(): void
    {
        $regulacion = Regulacion::factory()->conError()->create();

        $this->estructurar($regulacion);

        $this->assertStringContainsString(
            'Reintentar conversión',
            (string) $regulacion->fresh()->estructuracion_error
        );
    }

    /**
     * Una estructuración que va BIEN limpia el error de la vez anterior.
     *
     * Sin esto, un reintento exitoso dejaría el mensaje viejo en pantalla: la regulación tendría
     * su articulado Y un aviso rojo diciendo que la estructuración falló. Contradictorio, y el
     * usuario no sabría a cuál de los dos creer.
     *
     * Es la mitad que casi nadie escribe. Sin ella, un sistema que NUNCA limpiara el error
     * pasaría las dos pruebas anteriores tan tranquilo.
     */
    public function test_una_estructuracion_exitosa_borra_el_error_anterior(): void
    {
        $regulacion = $this->regulacionCon("Artículo 1. Del objeto del presente reglamento.\n");
        $regulacion->update(['estructuracion_error' => 'Un error de un intento anterior.']);

        $this->estructurar($regulacion->fresh());

        $this->assertNull(
            $regulacion->fresh()->estructuracion_error,
            'El aviso viejo se quedó en pantalla junto al articulado nuevo. El usuario ve un '
            . 'articulado Y un mensaje diciendo que no se pudo construir.'
        );
    }

    /**
     * La CONVERSIÓN no se marca como error cuando lo que falla es la estructuración.
     *
     * Es una distinción que importa mucho. Si la estructuración falla, el texto se extrajo
     * perfectamente: lo que falló fue el parser al construir el árbol.
     *
     * Marcar la conversión como error sería MENTIR — diría que el archivo no se pudo leer, cuando
     * se leyó bien. Y el usuario tiraría el archivo y subiría otro sin ninguna necesidad.
     *
     * Un mensaje de error que apunta al problema equivocado hace perder más tiempo que no dar
     * ninguno.
     */
    public function test_un_fallo_de_estructuracion_no_marca_la_conversion_como_error(): void
    {
        $regulacion = $this->regulacionCon(
            "Este documento no tiene artículos.\nSolo texto corrido, sin numeración.\n"
        );

        $this->estructurar($regulacion);

        $this->assertSame(
            Regulacion::CONVERSION_LISTO,
            $regulacion->fresh()->conversion_estatus,
            'El texto se extrajo bien. Decir que la conversión falló mandaría al usuario a subir '
            . 'otro archivo sin ninguna necesidad.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. La ficha lo pinta
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Guardar el motivo en la base no sirve de nada si la pantalla no lo enseña.
     *
     * Este proyecto ya se comió ese fallo DOS veces: tieneCatalogosDesactualizados() funcionaba y
     * ninguna vista lo pintaba; el servicio de costo sabía que no podía calcular el costo de
     * espera y la ficha mostraba un $0.00 indistinguible del de un trámite instantáneo.
     *
     * En los dos casos, todas las pruebas estaban en verde. Porque todas probaban el servidor.
     */
    public function test_la_ficha_muestra_el_error_de_estructuracion(): void
    {
        $regulacion = $this->regulacionCon(
            "Este documento no tiene artículos.\nSolo texto corrido, sin numeración.\n"
        );
        $this->estructurar($regulacion);

        $respuesta = $this->actingAs($this->admin)->get(route('regulaciones.show', $regulacion));

        $respuesta->assertOk();
        $respuesta->assertSee('No se pudo construir el articulado.');
        $respuesta->assertSee('Artículo 1.', false); // el mensaje accionable, tal cual
    }

    /**
     * LA MITAD QUE PROTEGE DE VERDAD.
     *
     * Una regulación cuyo articulado se construyó bien NO muestra ningún aviso.
     *
     * Sin esta prueba, un blade que enseñara el error SIEMPRE pasaría la anterior tan tranquilo.
     * Y un sistema que grita en todas las regulaciones es tan inútil como uno que no grita nunca:
     * la gente aprende a ignorar el aviso, y entonces tampoco lo lee el día que sí importa.
     */
    public function test_una_regulacion_bien_estructurada_no_muestra_ningun_aviso(): void
    {
        $regulacion = $this->regulacionCon("Artículo 1. Del objeto del presente reglamento.\n");
        $this->estructurar($regulacion);

        $this->actingAs($this->admin)
            ->get(route('regulaciones.show', $regulacion->fresh()))
            ->assertDontSee('No se pudo construir el articulado.');
    }
}
