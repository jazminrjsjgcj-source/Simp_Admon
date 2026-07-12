<?php

namespace Tests\Unit;

use App\Models\Tramite;
use PHPUnit\Framework\TestCase;

/**
 * Tramite::siglasDesdeNombre() — la función que fabrica las siglas de la homoclave.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ ESTA PRUEBA ES DE `Unit` Y NO DE `Feature`
 * ══════════════════════════════════════════════════════════════════════
 *
 * Todas las demás pruebas del proyecto son de Feature: levantan PostgreSQL, corren
 * RefreshDatabase, siembran datos. Tardan. Y hacen falta, porque prueban cosas que solo existen
 * cuando hay una base de datos debajo.
 *
 * Esta no. siglasDesdeNombre() es una FUNCIÓN PURA: recibe un texto, devuelve otro texto. No
 * toca la base, no toca el disco, no toca el reloj. Darle una base de datos sería como encender
 * el coche para ir al buzón.
 *
 * Fíjate en que hereda de PHPUnit\Framework\TestCase, no de Tests\TestCase: no arranca Laravel
 * siquiera. Por eso las 8 pruebas de este archivo corren en milisegundos, mientras que una sola
 * de Feature tarda medio segundo.
 *
 * La regla, sencilla: si la función no necesita nada del mundo exterior, la prueba tampoco.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ MERECE LA PENA PROBAR ALGO TAN PEQUEÑO
 * ══════════════════════════════════════════════════════════════════════
 *
 * Porque de aquí sale la HOMOCLAVE.
 *
 *     LPZ-T-DGGD-DSA-42
 *            ↑    ↑
 *            └────┴── esto lo fabrica esta función
 *
 * Y la homoclave es el identificador oficial que aparece impreso en el acuse firmado. No es un
 * dato interno: es lo que el ciudadano ve, lo que se cita en un oficio, lo que queda en un
 * expediente.
 *
 * Si esta función devuelve una cadena vacía, la homoclave sale con un doble guion —
 * LPZ-T--VU-5 — y eso queda impreso. Si devuelve las siglas mal, dos dependencias distintas
 * pueden acabar con el mismo prefijo.
 *
 * Nada de eso produce un error. Se imprime y ya.
 */
class SiglasDesdeNombreTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // 1. El comportamiento normal
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Toma la inicial de cada palabra, saltándose las de relleno.
     *
     *     "Dirección General de Gobierno Digital"
     *      D         G       ·  G        D          → DGGD
     *
     * El "de" se ignora. Si no se ignorara, saldría DGDGD, que no le dice nada a nadie.
     */
    public function test_toma_la_inicial_de_cada_palabra_significativa(): void
    {
        $this->assertSame('DGGD', Tramite::siglasDesdeNombre('Dirección General de Gobierno Digital'));
        $this->assertSame('DSA',  Tramite::siglasDesdeNombre('Dirección de Simplificación Administrativa'));
    }

    /**
     * Las palabras de relleno se ignoran: de, del, la, las, el, los, y, e, para, por, a.
     *
     * Y se ignoran SIN IMPORTAR LAS MAYÚSCULAS: la función compara en minúsculas. Un "De" con
     * mayúscula al principio de un nombre también se salta.
     */
    public function test_las_palabras_de_relleno_no_cuentan(): void
    {
        // Secretaría · [De] · [los] · Servicios · Públicos · [y] · [el] · Medio · Ambiente
        //     S                          S           P                    M         A
        //
        // Los corchetes son las palabras que se ignoran. Quedan CINCO iniciales: SSPMA.
        $this->assertSame(
            'SSPMA',
            Tramite::siglasDesdeNombre('Secretaría De los Servicios Públicos y el Medio Ambiente')
        );
    }

    /**
     * LAS SIGLAS NO LLEVAN ACENTOS.
     *
     *     "Ventanilla Única"  →  VU   (no VÚ)
     *     "Órgano Ñuñez"      →  ON   (no ÓÑ)
     *
     * Antes sí los llevaban, y producían identificadores como este:
     *
     *     LPZ-T-DGGD-VÚ-5
     *                 ↑ una tilde dentro del identificador oficial
     *
     * No era un carácter roto —eso solo pasaba en el folio, ver la prueba de abajo— pero sí un
     * problema. Un identificador tiene que poder escribirse en una URL, en un nombre de archivo,
     * en un CSV y en un sistema externo sin sorpresas. Los acentos dan guerra en todos esos
     * sitios, y cada uno falla de una manera distinta.
     *
     * OJO CON EL ALCANCE: esto solo afecta a las SIGLAS. El nombre de la dependencia no se toca:
     * sigue siendo "Ventanilla Única" en todas las pantallas, con su tilde. Lo único que se
     * limpia es el código corto que va dentro del identificador.
     */
    public function test_las_siglas_no_llevan_acentos(): void
    {
        $this->assertSame('ON', Tramite::siglasDesdeNombre('Órgano Ñuñez'));
        $this->assertSame('VU', Tramite::siglasDesdeNombre('Ventanilla Única'));
        $this->assertSame('SSP', Tramite::siglasDesdeNombre('Secretaría de Seguridad Pública'));
    }

    /**
     * NINGÚN CARÁCTER SALE PARTIDO POR LA MITAD.
     *
     * Esta prueba caza el bug que tenía GeneraFolio::folioSiglas():
     *
     *     strtoupper(substr($nombre, 0, 4))
     *
     * `substr()` cuenta BYTES, no caracteres. Y en UTF-8 una "Ñ" ocupa dos:
     *
     *     substr("Ñuñez", 0, 4)  →  C3 91 75 C3
     *                            →  "Ñu" + medio byte de la ñ
     *                            →  un byte suelto que NO ES NINGUNA LETRA
     *
     * Esa basura acababa impresa dentro del folio, sin ningún error que avisara.
     *
     * La comprobación: en unas siglas bien formadas, mb_strlen (que cuenta CARACTERES) y strlen
     * (que cuenta BYTES) tienen que dar LO MISMO. Si difieren, hay algo que no es ASCII ahí
     * dentro — y eso significa que la limpieza no se está aplicando, o que se aplicó después de
     * cortar en vez de antes.
     */
    public function test_ningun_caracter_sale_partido(): void
    {
        foreach (['Órgano Ñuñez', 'Ñuñez', 'Únicaísimo', 'de la', 'Ñ'] as $nombre) {
            $siglas = Tramite::siglasDesdeNombre($nombre);

            $this->assertSame(
                mb_strlen($siglas),
                strlen($siglas),
                "Con «{$nombre}» las siglas salieron «{$siglas}»: los bytes y los caracteres no "
                . 'cuadran, así que hay un carácter partido o sin limpiar dentro.'
            );

            $this->assertMatchesRegularExpression(
                '/^[A-Z0-9]+$/',
                $siglas,
                "Con «{$nombre}» las siglas salieron «{$siglas}», y un identificador oficial solo "
                . 'puede llevar letras y dígitos sin acentos.'
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Las entradas raras (aquí es donde se rompen las cosas)
    // ═══════════════════════════════════════════════════════════════════════

    /** Sin nombre, siglas genéricas. Nunca una cadena vacía. */
    public function test_sin_nombre_devuelve_gral(): void
    {
        $this->assertSame('GRAL', Tramite::siglasDesdeNombre(null));
        $this->assertSame('GRAL', Tramite::siglasDesdeNombre(''));
    }

    /**
     * EL CASO QUE MÁS ME GUSTA DE ESTE ARCHIVO.
     *
     * Un nombre formado ENTERAMENTE por palabras de relleno.
     *
     * Es absurdo, sí. Pero una dependencia se llama como se llama, y basta con que alguien
     * teclee "de la" en un campo por error para llegar aquí.
     *
     * Si la función devolviera cadena vacía, la homoclave saldría así:
     *
     *     LPZ-T--VU-5
     *            ↑↑ doble guion
     *
     * Y eso se imprimiría en el acuse. Sin ningún error. Nadie lo vería hasta que un ciudadano
     * preguntara qué significa.
     *
     * El respaldo de la función —las cuatro primeras letras del nombre— evita justo eso. Esta
     * prueba lo fija para que nadie lo borre pensando que es código muerto.
     */
    public function test_un_nombre_solo_de_palabras_de_relleno_no_devuelve_cadena_vacia(): void
    {
        $siglas = Tramite::siglasDesdeNombre('de la y el');

        $this->assertNotSame('', $siglas, 'Una cadena vacía produciría una homoclave con doble guion.');

        // El respaldo son las 4 primeras letras del nombre YA LIMPIO: "de la y el" → "DELAYEL" →
        // "DELA".
        //
        // Ojo con el espacio, que es lo que hace que esto no sea trivial. La primera versión del
        // respaldo cortaba el nombre SIN limpiarlo antes:
        //
        //     "de la"  →  "DE LA"  →  cortar 4  →  "DE L"
        //                                             ↑ un espacio dentro del identificador
        //
        // Y un espacio rompe URLs, nombres de archivo y CSV exactamente igual que una tilde. Se
        // habían limpiado los acentos y se había olvidado el resto. Lo cazó la prueba de abajo
        // (test_ningun_caracter_sale_partido), que exige que las siglas sean SOLO letras y
        // dígitos — sin excepciones y sin importar por qué camino se generaron.
        $this->assertSame('DELA', $siglas);
    }

    /** Espacios de sobra al principio, al final o entre palabras: se ignoran. */
    public function test_los_espacios_de_sobra_no_ensucian_las_siglas(): void
    {
        $this->assertSame(
            'DGGD',
            Tramite::siglasDesdeNombre('  Dirección   General  de   Gobierno Digital  ')
        );
    }

    /** Una sola palabra da una sola letra. Es correcto, y hay que dejarlo escrito. */
    public function test_una_sola_palabra_da_una_sola_inicial(): void
    {
        $this->assertSame('T', Tramite::siglasDesdeNombre('Tesorería'));
    }

    /**
     * Las siglas NUNCA salen vacías, con ninguna entrada.
     *
     * Es la única propiedad que de verdad importa: una cadena vacía aquí produce una homoclave
     * malformada, y la homoclave va impresa en un documento oficial.
     *
     * En vez de escribir una prueba por cada entrada rara imaginable, se comprueba la PROPIEDAD
     * sobre un montón de entradas de golpe. Si mañana alguien refactoriza la función y se le
     * escapa un caso que aquí no está, es probable que uno de estos lo pille.
     */
    public function test_las_siglas_nunca_salen_vacias(): void
    {
        $entradasRaras = [
            null,
            '',
            ' ',
            '   ',
            'de',
            'de la',
            'de la y el por para',
            '123',
            '...',
            '-',
            'Ñ',
            'á é í',
        ];

        foreach ($entradasRaras as $entrada) {
            $siglas = Tramite::siglasDesdeNombre($entrada);

            $this->assertNotSame(
                '',
                $siglas,
                'Con la entrada ' . var_export($entrada, true) . ' las siglas salieron VACÍAS. '
                . 'Eso produce una homoclave con doble guion (LPZ-T--VU-5), y esa homoclave se '
                . 'imprime en el acuse firmado.'
            );
        }
    }
}
