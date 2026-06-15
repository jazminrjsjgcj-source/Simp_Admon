<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica las dependencias bajo una sola entidad "H. Ayuntamiento de La Paz, B.C.S."
 *
 * El catálogo oficial de Unidades Responsables (UR) ya contiene toda la
 * jerarquía orgánica del Ayuntamiento. Mantener 21 dependencias paralelas
 * era redundante. A partir de esta migración:
 *
 *   - Existe UNA dependencia "Ayuntamiento de La Paz" con código '000'
 *   - Las dependencias anteriores se marcan como inactivas (no se eliminan
 *     por integridad referencial con trámites históricos)
 *   - Los wizards nuevos asignan automáticamente esta dependencia única
 *   - La granularidad real vive en `unidades_responsables` (UR)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna `activo` si no existe (las dependencias viejas no la tenían)
        if (!Schema::hasColumn('dependencias', 'activo')) {
            Schema::table('dependencias', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('nombre');
            });
        }

        // 2. Insertar la dependencia única (idempotente)
        DB::table('dependencias')->updateOrInsert(
            ['codigo' => '000'],
            [
                'nombre'     => 'H. Ayuntamiento de La Paz, B.C.S.',
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Marcar las viejas como inactivas (sin eliminarlas)
        DB::table('dependencias')
            ->where('codigo', '!=', '000')
            ->update(['activo' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Reactivar las viejas
        DB::table('dependencias')
            ->where('codigo', '!=', '000')
            ->update(['activo' => true]);

        // Eliminar la unificada solo si no tiene trámites
        $idUnificada = DB::table('dependencias')->where('codigo', '000')->value('id');
        if ($idUnificada && !DB::table('tramites')->where('dependencia_id', $idUnificada)->exists()) {
            DB::table('dependencias')->where('id', $idUnificada)->delete();
        }
    }
};
