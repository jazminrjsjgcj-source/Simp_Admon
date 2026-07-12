<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El rescate de las regulaciones que se quedan colgadas en "procesando".
 *
 * ── Qué se está protegiendo ──────────────────────────────────────────
 *
 * Cuando alguien sube un PDF, el sistema lo marca como 'procesando', lo convierte, y lo deja
 * en 'listo' o en 'error'. El try/catch del conversor cubre cualquier excepción.
 *
 * Lo que NO cubre es que el proceso muera de golpe: nginx que corta, php-fpm que corta, el
 * kernel que mata el proceso porque LibreOffice se comió la RAM. Ahí no hay catch que valga.
 * La fila se queda en 'procesando' y nadie la saca de ahí.
 *
 * Y 'procesando' NO ES UN ESTADO DE ERROR: la regulación no sale en ninguna lista de fallos,
 * nadie recibe un aviso, y el usuario recarga la página y supone que el sistema sigue
 * trabajando. Mañana también.
 *
 * Nadie va a arreglarlo, porque nadie sabe que está roto.
 *
 * Este comando es lo único que rompe ese silencio.
 */
class RescatarRegulacionesColgadasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una regulación en 'procesando' desde hace N minutos.
     *
     * El `updated_at` se fuerza con una consulta directa, saltándose Eloquent: si se usara
     * ->update(), Eloquent lo pisaría con la hora actual y la regulación nunca parecería
     * vieja. Es el tipo de detalle que hace que una prueba pase por el motivo equivocado.
     */
    private function regulacionProcesandoDesdeHace(int $minutos): Regulacion
    {
        $regulacion = Regulacion::factory()->create([
            'conversion_estatus' => Regulacion::CONVERSION_PROCESANDO,
            'archivo_original'   => 'regulaciones/originales/reglamento.pdf',
        ]);

        \DB::table('regulaciones')
            ->where('id', $regulacion->id)
            ->update(['updated_at' => now()->subMinutes($minutos)]);

        return $regulacion->fresh();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El rescate
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Una regulación colgada desde hace horas pasa a 'error', con un mensaje que explica qué
     * hacer.
     *
     * El mensaje importa tanto como el cambio de estado. Un 'error' sin explicación deja al
     * usuario igual de perdido que un 'procesando' eterno: sabe que algo falló y no sabe qué
     * hacer. El texto le dice que pruebe a guardarlo como .docx o a dividir el documento.
     */
    public function test_una_regulacion_colgada_pasa_a_error_con_un_mensaje_util(): void
    {
        $regulacion = $this->regulacionProcesandoDesdeHace(120);

        $this->artisan('regulaciones:rescatar-colgadas')->assertSuccessful();

        $regulacion = $regulacion->fresh();

        $this->assertSame(Regulacion::CONVERSION_ERROR, $regulacion->conversion_estatus);
        $this->assertNotEmpty($regulacion->conversion_error);
        $this->assertStringContainsString('.docx', $regulacion->conversion_error);
    }

    /**
     * LA MITAD QUE PROTEGE DE VERDAD.
     *
     * Una regulación que lleva DOS minutos convirtiéndose NO se toca.
     *
     * Convertir un PDF grande con LibreOffice puede tardar varios minutos, y va bien. Si el
     * barredor fuera agresivo, mataría conversiones legítimas que estaban a punto de terminar
     * — y el usuario vería un "error" en un archivo que el sistema podía procesar
     * perfectamente.
     *
     * Sin esta prueba, un comando que marcara TODO lo que está en 'procesando' pasaría la
     * prueba anterior tan tranquilo. Y sería mucho peor que el bug que viene a arreglar.
     */
    public function test_una_conversion_reciente_no_se_toca(): void
    {
        $regulacion = $this->regulacionProcesandoDesdeHace(2);

        $this->artisan('regulaciones:rescatar-colgadas')->assertSuccessful();

        $this->assertSame(
            Regulacion::CONVERSION_PROCESANDO,
            $regulacion->fresh()->conversion_estatus,
            'El barredor mató una conversión que llevaba solo dos minutos y probablemente iba '
            . 'a terminar bien. Es peor que el bug que viene a arreglar.'
        );
    }

    /** El umbral se puede ajustar: en un servidor lento, quince minutos pueden ser pocos. */
    public function test_el_umbral_de_minutos_se_puede_ajustar(): void
    {
        $regulacion = $this->regulacionProcesandoDesdeHace(20);

        // Con el umbral por defecto (15) se rescataría. Con 60, no.
        $this->artisan('regulaciones:rescatar-colgadas', ['--minutos' => 60])->assertSuccessful();

        $this->assertSame(Regulacion::CONVERSION_PROCESANDO, $regulacion->fresh()->conversion_estatus);
    }

    /**
     * El comando NO toca las regulaciones que terminaron bien, ni las que ya fallaron.
     *
     * Solo mira las que están en 'procesando'. Si tocara las 'listo', destruiría
     * conversiones buenas; si tocara las 'error', pisaría el mensaje de error original —que
     * dice qué falló de verdad— con uno genérico.
     */
    public function test_no_toca_las_regulaciones_listas_ni_las_que_ya_fallaron(): void
    {
        $lista = Regulacion::factory()->convertida()->create();

        $fallida = Regulacion::factory()->conError()->create();
        $errorOriginal = $fallida->conversion_error;

        \DB::table('regulaciones')->update(['updated_at' => now()->subDay()]);

        $this->artisan('regulaciones:rescatar-colgadas')->assertSuccessful();

        $this->assertSame(Regulacion::CONVERSION_LISTO, $lista->fresh()->conversion_estatus);

        $this->assertSame(
            $errorOriginal,
            $fallida->fresh()->conversion_error,
            'El mensaje de error original dice qué falló de verdad. Pisarlo con uno genérico '
            . 'sería destruir la única pista que tenía el usuario.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. El ensayo en seco
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * --dry-run enseña qué haría y no toca nada.
     *
     * Existe porque la primera vez que se corre este comando en producción, nadie sabe cuántas
     * regulaciones colgadas hay. Si son tres, se rescatan. Si son doscientas, el problema no
     * son las regulaciones: es el servidor, y marcarlas todas como error solo taparía el
     * síntoma.
     */
    public function test_el_ensayo_en_seco_no_modifica_nada(): void
    {
        $regulacion = $this->regulacionProcesandoDesdeHace(120);

        $this->artisan('regulaciones:rescatar-colgadas', ['--dry-run' => true])
            ->expectsOutputToContain($regulacion->nombre)
            ->assertSuccessful();

        $this->assertSame(
            Regulacion::CONVERSION_PROCESANDO,
            $regulacion->fresh()->conversion_estatus,
            '--dry-run tocó la base de datos. Su único trabajo es NO tocarla.'
        );
    }

    /** Sin regulaciones colgadas, el comando termina bien y lo dice. */
    public function test_sin_colgadas_el_comando_no_hace_nada_y_termina_bien(): void
    {
        Regulacion::factory()->convertida()->create();

        $this->artisan('regulaciones:rescatar-colgadas')
            ->expectsOutputToContain('Ninguna regulación')
            ->assertSuccessful();
    }
}
