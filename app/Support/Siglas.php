<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Fabrica las siglas que van dentro de los identificadores oficiales.
 *
 * Las siglas aparecen en la homoclave del trámite y en el folio de la agenda, las propuestas,
 * los AIR y las regulaciones:
 *
 *     LPZ-T-DGGD-DSA-42          ← homoclave
 *          ↑    ↑
 *     LPZ-SIM-DGGD-2026-001      ← folio
 *             ↑
 *
 * Son identificadores públicos: se imprimen en el acuse firmado, se citan en oficios, se
 * quedan en expedientes.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ ESTA CLASE EXISTE
 * ══════════════════════════════════════════════════════════════════════
 *
 * Antes había DOS sitios calculando siglas a partir de un nombre, y hacían cosas distintas:
 *
 *   Tramite::siglasDesdeNombre()   → iniciales de cada palabra, con mb_substr (bien)
 *   GeneraFolio::folioSiglas()     → primeras 4 letras, con substr (MAL)
 *
 * Y ese `substr` era un bug de verdad. Cuenta BYTES, no caracteres, y en UTF-8 una "Ñ" ocupa
 * dos bytes:
 *
 *     substr("Ñuñez", 0, 4)  →  C3 91 75 C3  →  "Ñu" + medio byte de la ñ
 *                            →  un carácter ROTO
 *
 * Ese byte suelto, que no es ninguna letra, acababa dentro del folio. Impreso.
 * Y `strtoupper()` tampoco pone en mayúsculas las letras acentuadas —también trabaja por
 * bytes—, así que una "ó" se quedaba minúscula en unas siglas que deberían ser mayúsculas.
 *
 * La duplicación era la causa: uno de los dos sitios estaba bien y el otro no, y nadie los
 * comparó nunca. Ahora hay uno solo.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ SE QUITAN LOS ACENTOS
 * ══════════════════════════════════════════════════════════════════════
 *
 * Antes, una unidad llamada "Ventanilla Única" producía esto:
 *
 *     LPZ-T-DGGD-VÚ-5
 *                 ↑ una tilde dentro del identificador oficial
 *
 * No era un carácter roto —eso solo pasaba en el folio— pero sí un problema. Un identificador
 * tiene que poder escribirse en una URL, en un nombre de archivo, en un CSV y en un sistema
 * externo sin sorpresas. Los acentos dan guerra en todos esos sitios, y cada uno falla de una
 * manera distinta.
 *
 * Ahora "Ventanilla Única" da VU, y "Órgano Ñuñez" da ON.
 *
 * OJO CON EL ALCANCE: esto solo cambia las SIGLAS. El nombre de la dependencia no se toca —
 * sigue siendo "Ventanilla Única" en todas las pantallas, con su tilde. Lo que se limpia es
 * únicamente el código corto que va dentro del identificador.
 */
final class Siglas
{
    /**
     * Palabras que no aportan nada a unas siglas.
     *
     * Sin esta lista, "Dirección General de Gobierno Digital" daría DGDGD en vez de DGGD.
     */
    private const IGNORADAS = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'para', 'por', 'a'];

    /** Cuántas letras se toman del nombre cuando no queda ninguna inicial utilizable. */
    private const LETRAS_DE_RESPALDO = 4;

    /** Lo que se devuelve cuando no hay nombre del que sacar nada. */
    public const GENERICAS = 'GRAL';

    /**
     * Siglas a partir de un nombre: la inicial de cada palabra significativa, sin acentos y en
     * mayúsculas.
     *
     *     "Dirección General de Gobierno Digital"  →  DGGD
     *     "Ventanilla Única"                       →  VU
     *     "Órgano Ñuñez"                           →  ON
     *
     * NUNCA devuelve una cadena vacía. Eso es lo más importante de esta función: unas siglas
     * vacías producen una homoclave con doble guion (LPZ-T--VU-5) que se imprime en el acuse
     * sin que nada avise. Si no queda ninguna inicial utilizable, se cae al respaldo: las
     * primeras cuatro letras del nombre.
     */
    public static function desdeNombre(?string $nombre): string
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return self::GENERICAS;
        }

        $iniciales = '';

        foreach (preg_split('/\s+/', $nombre) as $palabra) {
            if (in_array(mb_strtolower($palabra), self::IGNORADAS, true)) {
                continue;
            }

            // Se limpia la palabra ENTERA antes de tomar su inicial, no después.
            //
            // Dos motivos:
            //
            //   1. Si se cortara primero y se limpiara después, una inicial que se transcribe a
            //      dos letras —una "Æ" se convierte en "AE"— metería dos caracteres donde debería
            //      haber uno.
            //
            //   2. normalizar() tira todo lo que no sea letra o dígito. Así, una "palabra" como
            //      "..." o "-" no aporta ninguna inicial en vez de aportar un punto o un guion.
            //      Un identificador oficial no puede llevar signos de puntuación dentro.
            $limpia = self::normalizar($palabra);

            if ($limpia === '') {
                continue; // no quedó nada utilizable (otro alfabeto, solo puntuación...)
            }

            $iniciales .= substr($limpia, 0, 1);
        }

        if ($iniciales !== '') {
            return $iniciales;
        }

        // ── EL RESPALDO ──
        //
        // Se llega aquí cuando el nombre no dejó ninguna inicial utilizable: era todo palabras de
        // relleno ("de la"), o solo puntuación, o estaba en otro alfabeto.
        //
        // Se toman las primeras letras del nombre, PASADAS POR EL MISMO FILTRO. Esa última parte
        // es la que se me olvidó la primera vez, y una prueba la cazó:
        //
        //     "de la"  →  Str::ascii  →  "DE LA"  →  cortar 4  →  "DE L"
        //                                                            ↑ un espacio
        //
        // Un espacio dentro de un identificador es exactamente igual de malo que una tilde: rompe
        // URLs, nombres de archivo y CSV por los mismos motivos. Había limpiado los acentos y me
        // había olvidado del resto.
        //
        // normalizar() se aplica ANTES de cortar. Si se aplicara después, el corte podría partir
        // un carácter multibyte por la mitad — que es justo el bug que este arreglo vino a
        // eliminar de GeneraFolio.
        $respaldo = substr(self::normalizar($nombre), 0, self::LETRAS_DE_RESPALDO);

        return $respaldo !== '' ? $respaldo : self::GENERICAS;
    }

    /**
     * Limpia unas siglas que ya venían capturadas a mano en la base de datos.
     *
     * ── Por qué hace falta ──
     *
     * Las dependencias y las unidades tienen una columna `siglas`. Cuando está llena, se usa tal
     * cual: nadie pasaba por desdeNombre().
     *
     * Y ahí está el agujero: si alguien escribió "VÚ" en ese campo, la tilde entraba en la
     * homoclave igualmente. Limpiar solo el respaldo automático habría dejado el problema en pie
     * justo en el caso más común — el de las dependencias bien configuradas.
     *
     * Esta función es el guardián de la puerta: todo lo que va a un identificador pasa por aquí,
     * venga de donde venga.
     *
     * Se quitan también los espacios y todo lo que no sea una letra o un dígito: un identificador
     * con un espacio dentro rompe URLs y ficheros exactamente igual que una tilde.
     *
     * Devuelve cadena vacía si no queda nada. El llamador decide qué hacer entonces (típicamente,
     * caer a desdeNombre()).
     */
    public static function normalizar(?string $siglas): string
    {
        $limpias = strtoupper(Str::ascii(trim((string) $siglas)));

        return preg_replace('/[^A-Z0-9]/', '', $limpias) ?? '';
    }
}
