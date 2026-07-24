<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clasifica la jurisdicción de las regulaciones que ya existían cuando se
 * añadió el campo `ambito` (semilla, una sola vez).
 *
 * ── Qué clasifica ────────────────────────────────────────────────────
 *
 * Al escribir esta semilla, el corpus son exactamente dos regulaciones, ambas
 * municipales de La Paz, BCS. Sus nombres oficiales, verificados directamente
 * contra la base:
 *
 *   - "Ley de Hacienda para el Municipio de La Paz"
 *   - "Bando de Policía, Buen Gobierno y Justicia Cívica del Municipio de la Paz"
 *
 * ── Por qué por nombre EXACTO y no por un patrón "%La Paz%" ───────────
 *
 * "Municipio de La Paz" NO es un texto único en México: existe otro Municipio
 * de La Paz en el Estado de México. Un patrón que busque ese texto clasificaría
 * como BCS una ley de aquel otro La Paz. Y cualquier intento de arreglarlo con
 * más texto ("...pero no si dice México") sigue siendo adivinar.
 *
 * Como el corpus está enumerado y verificado, se nombran las dos leyes EXACTAS.
 * Así la semilla toca solo esas dos filas y le resulta imposible clasificar de
 * más: no hay ambigüedad que resolver.
 *
 * Esto la vuelve un llenado del corpus CONOCIDO, no un clasificador general.
 * Una ley de La Paz que se cargue en el futuro no la clasifica esta semilla
 * (ya cumplió su única tarea): se clasifica en su alta, como el resto.
 *
 * ── Por qué es segura de re-ejecutar ─────────────────────────────────
 *
 * El `whereNull('ambito')` hace que solo toque lo que aún NO está clasificado.
 * Correrla dos veces no cambia nada; y en la base vacía de pruebas es un no-op.
 */
return new class extends Migration
{
    /**
     * Los nombres oficiales de las dos regulaciones municipales de La Paz.
     * Definidos una vez para que up() y down() no puedan desincronizarse.
     */
    private function nombresMunicipalesDeLaPaz(): array
    {
        return [
            'Ley de Hacienda para el Municipio de La Paz',
            'Bando de Policía, Buen Gobierno y Justicia Cívica del Municipio de la Paz',
        ];
    }

    public function up(): void
    {
        DB::table('regulaciones')
            ->whereIn('nombre', $this->nombresMunicipalesDeLaPaz())
            ->whereNull('ambito')
            ->update([
                'ambito'    => 'municipal',
                'estado'    => 'BCS',
                'municipio' => 'La Paz',
            ]);
    }

    public function down(): void
    {
        // Revierte solo lo que esta semilla clasificó: esas dos leyes vuelven a
        // quedar sin ámbito (NULL), su estado antes de la semilla.
        DB::table('regulaciones')
            ->whereIn('nombre', $this->nombresMunicipalesDeLaPaz())
            ->update([
                'ambito'    => null,
                'estado'    => null,
                'municipio' => null,
            ]);
    }
};
