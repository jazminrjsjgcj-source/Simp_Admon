<?php

use App\Models\AccionAgenda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de eventos de calendario de acciones de agenda de tipo 'ambas'.
 *
 * Antes, una acción de tipo 'ambas' guardaba su evento de calendario como
 * 'simplificacion' (se aplastaba). Ahora se guarda como 'ambas' y aparece en
 * los dos filtros (simplificación y digitalización).
 *
 * Los eventos creados ANTES de ese cambio siguen guardados como 'simplificacion'
 * hasta que se edite la acción. Esta migración los corrige de una sola vez:
 * pone tipo = 'ambas' en los eventos de calendario cuya acción vinculada es de
 * tipo 'ambas' y cuyo evento quedó marcado como 'simplificacion'.
 *
 * Solo toca eventos de AccionAgenda: no roza propuestas regulatorias ni trámites.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('calendario_eventos')
            ->where('eventable_type', AccionAgenda::class)
            ->where('tipo', 'simplificacion')
            ->whereIn('eventable_id', function ($q) {
                $q->select('id')
                  ->from('acciones_agenda')
                  ->where('tipo', 'ambas');
            })
            ->update([
                'tipo'       => 'ambas',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Inverso exacto: devuelve a 'simplificacion' solo los eventos de
        // acciones 'ambas' que esta migración marcó como 'ambas'.
        DB::table('calendario_eventos')
            ->where('eventable_type', AccionAgenda::class)
            ->where('tipo', 'ambas')
            ->whereIn('eventable_id', function ($q) {
                $q->select('id')
                  ->from('acciones_agenda')
                  ->where('tipo', 'ambas');
            })
            ->update([
                'tipo'       => 'simplificacion',
                'updated_at' => now(),
            ]);
    }
};
