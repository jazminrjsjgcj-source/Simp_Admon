<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega acciones_agenda.activa.
 *
 * ── El problema que resuelve ──────────────────────────────────────────
 *
 * Desde la agenda se puede registrar un trámite nuevo y, en el mismo paso, la acción
 * de mejora sobre él. Pero ese trámite nace como borrador y todavía tiene que
 * recorrer su propio camino (revisión, corrección, firma) antes de existir de forma
 * oficial.
 *
 * Hasta ahora la acción se activaba de inmediato —recibía folio y entraba en la
 * agenda— aunque el trámite sobre el que se apoya aún no estuviera firmado. Se estaba
 * comprometiendo la mejora de algo que formalmente todavía no existía.
 *
 * ── Cómo se resuelve ─────────────────────────────────────────────────
 *
 * La acción queda INACTIVA mientras su trámite no esté completado:
 *
 *   - No aparece en los listados de las demás personas, ni en el calendario, ni en
 *     los indicadores. Es un "falso borrador".
 *   - Su AUTOR sí la ve, marcada como "pendiente del trámite", para que sepa que
 *     existe y por qué todavía no cuenta.
 *   - Se activa SOLA en cuanto el trámite pasa a completado.
 *
 * Las acciones sobre trámites que ya existen nacen activas (true por defecto), así
 * que este cambio no afecta al camino normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            $table->boolean('activa')
                ->default(true)
                ->comment('False mientras el trámite vinculado no esté completado.');

            // Los listados filtran por esta bandera en casi todas las consultas.
            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            $table->dropIndex(['activa']);
            $table->dropColumn('activa');
        });
    }
};
