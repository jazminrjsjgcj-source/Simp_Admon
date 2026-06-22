<?php

/**
 * Bug #B23 — Otorga el permiso 'regulaciones.ver' al rol 'enlace' en la BD existente.
 *
 * El cambio en config/acl.php solo aplica a instalaciones nuevas. Esta migración
 * propaga el cambio a sistemas ya instalados que tienen el rol y el permiso creados
 * pero les falta la relación.
 *
 * Es idempotente: si la relación ya existe (instalación nueva), no hace nada.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rolId     = DB::table('roles')->where('codigo', 'enlace')->value('id');
        $permisoId = DB::table('permisos')->where('codigo', 'regulaciones.ver')->value('id');

        // Defensa: si por alguna razón no existen el rol o el permiso, salir sin error.
        // Pasaría si la migración se corre antes que los seeders, o en una BD inconsistente.
        if (!$rolId || !$permisoId) {
            return;
        }

        // Idempotente: si la relación ya existe, no la duplica.
        $existe = DB::table('role_permiso')
            ->where('role_id', $rolId)
            ->where('permiso_id', $permisoId)
            ->exists();

        if (!$existe) {
            DB::table('role_permiso')->insert([
                'role_id'    => $rolId,
                'permiso_id' => $permisoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $rolId     = DB::table('roles')->where('codigo', 'enlace')->value('id');
        $permisoId = DB::table('permisos')->where('codigo', 'regulaciones.ver')->value('id');

        if (!$rolId || !$permisoId) {
            return;
        }

        DB::table('role_permiso')
            ->where('role_id', $rolId)
            ->where('permiso_id', $permisoId)
            ->delete();
    }
};
