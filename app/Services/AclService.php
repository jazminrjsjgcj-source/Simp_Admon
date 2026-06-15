<?php

namespace App\Services;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Servicio para gestionar el ACL: asignación de roles a usuarios,
 * gestión de permisos por rol, y registro de la bitácora ACL.
 */
class AclService
{
    public function asignarRolAUsuario(User $usuario, Role $rol): void
    {
        if ($usuario->roles()->where('role_id', $rol->id)->exists()) {
            return;
        }

        $usuario->roles()->attach($rol->id, ['asignado_por' => Auth::id()]);
        $usuario->olvidarPermisosCache();

        $this->registrarBitacora('rol_asignado', $usuario, ['role_id' => $rol->id]);
    }

    public function removerRolDeUsuario(User $usuario, Role $rol): void
    {
        $usuario->roles()->detach($rol->id);
        $usuario->olvidarPermisosCache();

        $this->registrarBitacora('rol_removido', $usuario, ['role_id' => $rol->id]);
    }

    public function sincronizarPermisosDeRol(Role $rol, array $idsPermisos): void
    {
        $previos = $rol->permisos()->pluck('permisos.id')->toArray();
        $rol->permisos()->sync($idsPermisos);

        $this->invalidarCacheDeUsuariosConRol($rol);

        $agregados   = array_diff($idsPermisos, $previos);
        $removidos   = array_diff($previos, $idsPermisos);
        $ejecutorId  = Auth::id();

        foreach ($agregados as $permisoId) {
            $this->registrarCambioPermiso('permiso_dado', $rol, $permisoId, $ejecutorId);
        }
        foreach ($removidos as $permisoId) {
            $this->registrarCambioPermiso('permiso_revocado', $rol, $permisoId, $ejecutorId);
        }
    }

    public function crearRol(string $codigo, string $nombre, ?string $descripcion = null): Role
    {
        $rol = Role::create([
            'codigo'      => $codigo,
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
            'sistema'     => false,
        ]);

        $this->registrarBitacora('rol_creado', Auth::user(), ['role_id' => $rol->id]);

        return $rol;
    }

    public function eliminarRol(Role $rol): bool
    {
        if ($rol->esDeSistema()) {
            return false;
        }

        $rol->delete();
        $this->registrarBitacora('rol_eliminado', Auth::user(), ['role_id' => $rol->id]);

        return true;
    }

    /**
     * Matriz roles × permisos: para cada rol, lista los IDs de los permisos asignados.
     * Útil para renderizar la pantalla principal de ACL.
     */
    public function matrizRolesPermisos(): array
    {
        $matriz = [];
        foreach (Role::with('permisos:id')->get() as $rol) {
            $matriz[$rol->id] = $rol->permisos->pluck('id')->toArray();
        }
        return $matriz;
    }

    private function invalidarCacheDeUsuariosConRol(Role $rol): void
    {
        foreach ($rol->usuarios as $usuario) {
            $usuario->olvidarPermisosCache();
        }
    }

    private function registrarBitacora(string $accion, ?User $usuarioAfectado, array $datos = []): void
    {
        DB::table('acl_bitacora')->insert([
            'usuario_afectado_id' => $usuarioAfectado?->id ?? Auth::id(),
            'accion'              => $accion,
            'role_id'             => $datos['role_id']    ?? null,
            'permiso_id'          => $datos['permiso_id'] ?? null,
            'ejecutado_por'       => Auth::id(),
            'ip_address'          => Request::ip(),
            'created_at'          => now(),
        ]);
    }

    private function registrarCambioPermiso(string $accion, Role $rol, int $permisoId, ?int $ejecutorId): void
    {
        DB::table('acl_bitacora')->insert([
            'usuario_afectado_id' => $ejecutorId,
            'accion'              => $accion,
            'role_id'             => $rol->id,
            'permiso_id'          => $permisoId,
            'ejecutado_por'       => $ejecutorId,
            'ip_address'          => Request::ip(),
            'created_at'          => now(),
        ]);
    }
}
