<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Señal de "ley desactualizada": con qué versión del pipeline de estructuración se
 * construyó cada regulación por última vez, y cuándo.
 *
 * ── Qué problema resuelve ────────────────────────────────────────────
 *
 * Cambiar el código del pipeline (estructurador, detector, extractor de tablas) sin
 * re-estructurar deja la base con contexto viejo, y sin ningún aviso. Esta sesión lo
 * sufrió varias veces: el art. 105 sin marcar, la inyección de catálogos muerta, la
 * tabla sin volcar. Cada vez se descubrió a mano, tarde.
 *
 * Con `pipeline_version`, cada re-estructuración estampa la versión vigente
 * (EstructurarRegulacionJob::PIPELINE_VERSION). Cuando ese número sube —porque
 * cambiaste el pipeline—, las leyes con versión menor quedan detectables como
 * "necesitan re-estructurar", en vez de fallar en silencio.
 *
 * ── El estampado inicial ─────────────────────────────────────────────
 *
 * Las leyes que YA tienen articulado (nodos) se marcan como versión 1: se
 * re-estructuraron con el pipeline vigente, así que están al día. Marcarlas como
 * desactualizadas el primer día sería una falsa alarma. Las leyes sin nodos (a medias
 * o vacías) quedan en NULL: no sabemos con qué se hicieron, y NULL cuenta como
 * "desactualizada" hasta que se estructuren bien una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('pipeline_version')->nullable()
                ->comment('Versión del pipeline con que se estructuró (EstructurarRegulacionJob::PIPELINE_VERSION). NULL = desconocida.');
            $table->timestamp('estructurado_en')->nullable()
                ->comment('Cuándo se estructuró por última vez con éxito.');
        });

        // Estampar como versión 1 SOLO las que ya tienen nodos (están estructuradas).
        DB::table('regulaciones')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('regulacion_nodos')
                    ->whereColumn('regulacion_nodos.regulacion_id', 'regulaciones.id');
            })
            ->update([
                'pipeline_version' => 1,
                'estructurado_en'  => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->dropColumn(['pipeline_version', 'estructurado_en']);
        });
    }
};
