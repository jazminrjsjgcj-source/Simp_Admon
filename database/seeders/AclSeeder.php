<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder del Access Control List.
 *
 * 1. Crea los permisos definidos en config/acl.php
 * 2. Crea los roles iniciales y les asigna sus permisos
 * 3. Migra los usuarios existentes: copia users.rol como asignación en user_role
 *
 * Es idempotente: puede correrse varias veces sin duplicar.
 */
class AclSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = config('acl');

        DB::transaction(function () use ($catalogo) {
            $this->crearPermisos($catalogo['permisos']);
            $this->crearRolesConPermisos($catalogo['roles_iniciales'], $catalogo['permisos']);
            $this->migrarUsuariosExistentes();
        });

        $this->command?->info('ACL: permisos, roles y asignaciones aplicados correctamente.');
    }

    private function crearPermisos(array $permisos): void
    {
        foreach ($permisos as $codigo => $datos) {
            Permiso::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'modulo'      => $datos['modulo'],
                    'accion'      => $datos['accion'],
                    'descripcion' => $datos['descripcion'],
                ]
            );
        }
    }

    private function crearRolesConPermisos(array $rolesIniciales, array $todosLosPermisos): void
    {
        foreach ($rolesIniciales as $codigo => $datos) {
            $rol = Role::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre'      => $datos['nombre'],
                    'descripcion' => $datos['descripcion'],
                    'sistema'     => $datos['sistema'],
                ]
            );

            $codigosPermisos = $datos['permisos'] === '*'
                ? array_keys($todosLosPermisos)
                : $datos['permisos'];

            $idsPermisos = Permiso::whereIn('codigo', $codigosPermisos)->pluck('id');
            $rol->permisos()->sync($idsPermisos);
        }
    }

    /**
     * Migra usuarios pre-ACL: si tienen el campo `users.rol` pero ningún
     * registro en user_role, les crea la asignación correspondiente.
     */
    private function migrarUsuariosExistentes(): void
    {
        $usuariosSinRolAsignado = User::doesntHave('roles')->get();

        foreach ($usuariosSinRolAsignado as $usuario) {
            $rol = Role::where('codigo', $usuario->rol)->first();
            if ($rol) {
                $usuario->roles()->attach($rol->id);
            }
        }
    }
}
