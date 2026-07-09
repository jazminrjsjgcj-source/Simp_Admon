<?php

namespace App\Services;

use App\Models\User;

/**
 * Lógica de negocio de la gestión de usuarios desde el panel de administración:
 * sincronización de permisos directos y lectura del catálogo de permisos por
 * rol. Antes vivía dentro del AdminController, mezclada con el CRUD HTTP.
 */
class UsuarioService
{
    /**
     * Sincroniza los permisos directos (checkboxes) de un usuario, registrando
     * quién los asignó. Acepta null o array vacío (deja al usuario sin permisos
     * directos). Centraliza el mapeo que store() y update() repetían.
     *
     * @param  array|null $permisoIds  IDs de permisos marcados en el formulario.
     */
    public function sincronizarPermisos(User $usuario, ?array $permisoIds): void
    {
        $payload = collect($permisoIds ?? [])
            ->mapWithKeys(fn ($id) => [$id => ['asignado_por' => auth()->id()]])
            ->toArray();

        $usuario->permisosDirectos()->sync($payload);

        // El menú lateral cachea los permisos del usuario; invalidar tras cambiar.
        cache()->forget('user_permisos_' . $usuario->id);
    }

    /**
     * Devuelve, para cada rol del catálogo ACL, la lista de permisos que le
     * corresponden. El comodín '*' se expande a todos los permisos. Lo usa la
     * vista de alta/edición para marcar los checkboxes según el rol elegido.
     */
    public function permisosPorRol(): array
    {
        $catalogo      = config('acl.roles_iniciales');
        $todosPermisos = array_keys(config('acl.permisos'));

        $resultado = [];
        foreach ($catalogo as $codigo => $datos) {
            $resultado[$codigo] = $datos['permisos'] === '*'
                ? $todosPermisos
                : $datos['permisos'];
        }

        return $resultado;
    }

    /**
     * Lista de roles válidos como cadena para reglas de validación Laravel
     * (formato "admin,enlace,juridico,revisora,sujeto"). Evita repetir la lista
     * hardcodeada en cada método del controlador.
     */
    public static function rolesValidacion(): string
    {
        return implode(',', User::ROLES_TODOS);
    }
}
