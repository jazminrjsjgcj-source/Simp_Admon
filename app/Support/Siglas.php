<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Fabrica las siglas que van dentro de los identificadores oficiales.
 *
 * Aparecen en la homoclave de un trámite y en el folio de la agenda, las propuestas,
 * los AIR y las regulaciones:
 *
 *     LPZ-T-DGGD-DSA-42        LPZ-SIM-DGGD-2026-001
 *
 * Son identificadores públicos: se imprimen en el acuse firmado y se citan en
 * oficios, así que tienen que poder escribirse en una URL, un nombre de archivo o un
 * CSV sin sorpresas. De ahí que se quiten acentos, espacios y puntuación: solo
 * afecta a las siglas, el nombre de la dependencia conserva su tilde en pantalla.
 *
 * Existe como clase única porque antes había dos implementaciones distintas —una por
 * caracteres y otra por bytes— y la segunda partía caracteres multibyte por la mitad,
 * dejando bytes sueltos dentro de folios impresos.
 */
final class Siglas
{
    /**
     * Palabras que no aportan a unas siglas. Sin esta lista, "Dirección General de
     * Gobierno Digital" daría DGDGD en vez de DGGD.
     */
    private const IGNORADAS = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'para', 'por', 'a'];

    /** Letras que se toman del nombre cuando no queda ninguna inicial utilizable. */
    private const LETRAS_DE_RESPALDO = 4;

    /** Lo que se devuelve cuando no hay nombre del que sacar nada. */
    public const GENERICAS = 'GRAL';

    /**
     * Siglas a partir de un nombre: la inicial de cada palabra significativa, sin
     * acentos y en mayúsculas.
     *
     *     "Dirección General de Gobierno Digital"  →  DGGD
     *     "Ventanilla Única"                       →  VU
     *
     * NUNCA devuelve cadena vacía: unas siglas vacías producen una homoclave con
     * doble guion (LPZ-T--VU-5) que se imprime en el acuse sin que nada avise.
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

            // Se limpia la palabra entera antes de tomar su inicial: si se cortara
            // primero, una letra que se transcribe a dos ("Æ" → "AE") metería dos
            // caracteres donde debe haber uno. Además así una "palabra" que solo es
            // puntuación no aporta ningún signo al identificador.
            $limpia = self::normalizar($palabra);

            if ($limpia === '') {
                continue;
            }

            $iniciales .= substr($limpia, 0, 1);
        }

        if ($iniciales !== '') {
            return $iniciales;
        }

        // Respaldo: el nombre no dejó ninguna inicial utilizable (todo palabras de
        // relleno, puntuación u otro alfabeto). Se normaliza ANTES de cortar, para no
        // partir un carácter multibyte y para que no se cuele un espacio.
        $respaldo = substr(self::normalizar($nombre), 0, self::LETRAS_DE_RESPALDO);

        return $respaldo !== '' ? $respaldo : self::GENERICAS;
    }

    /**
     * Limpia unas siglas capturadas a mano en la base de datos.
     *
     * Las dependencias y unidades tienen columna `siglas`, y cuando está llena se usa
     * tal cual sin pasar por desdeNombre(). Si alguien escribió "VÚ", la tilde
     * llegaría igual al identificador: esta función es el punto único por el que pasa
     * todo lo que acaba dentro de uno.
     *
     * Devuelve cadena vacía si no queda nada; el llamador decide qué hacer entonces.
     */
    public static function normalizar(?string $siglas): string
    {
        $limpias = strtoupper(Str::ascii(trim((string) $siglas)));

        return preg_replace('/[^A-Z0-9]/', '', $limpias) ?? '';
    }
}
