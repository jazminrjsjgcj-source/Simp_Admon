<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use App\Services\AclService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador del módulo ACL (admin/acl).
 *
 * Permite:
 *   - Ver la matriz roles × permisos
 *   - Editar permisos de un rol
 *   - Asignar/revocar roles a un usuario
 *   - Consultar la bitácora de cambios ACL
 *
 * Todas las acciones de escritura requieren confirmación en la UI
 * (el botón Guardar abre el modal estándar `confirm-modal`).
 */
class AclController extends Controller
{
    public function __construct(private AclService $acl) {}

    public function index()
    {
        $roles    = Role::withCount('usuarios')->orderBy('nombre')->get();
        $permisos = Permiso::orderBy('modulo')->orderBy('accion')->get()->groupBy('modulo');
        $matriz   = $this->acl->matrizRolesPermisos();

        return view('screens.admin.acl.index', compact('roles', 'permisos', 'matriz'));
    }

    public function editarRol(Role $role)
    {
        $permisos = Permiso::orderBy('modulo')->orderBy('accion')->get()->groupBy('modulo');
        $asignados = $role->permisos()->pluck('permisos.id')->toArray();

        return view('screens.admin.acl.editar-rol', compact('role', 'permisos', 'asignados'));
    }

    public function actualizarRol(Request $request, Role $role)
    {
        $request->validate([
            'permisos'   => 'nullable|array',
            'permisos.*' => 'integer|exists:permisos,id',
        ]);

        $this->acl->sincronizarPermisosDeRol($role, $request->input('permisos', []));

        return redirect()->route('admin.acl.index')
            ->with('success', 'Permisos del rol actualizados.');
    }

    public function usuarios()
    {
        $usuarios = User::with('roles', 'dependencia')
            ->orderBy('name')
            ->paginate(30);

        return view('screens.admin.acl.usuarios', compact('usuarios'));
    }

    public function asignarRoles(User $usuario)
    {
        $roles      = Role::orderBy('nombre')->get();
        $asignados  = $usuario->roles()->pluck('roles.id')->toArray();

        return view('screens.admin.acl.asignar-roles', compact('usuario', 'roles', 'asignados'));
    }

    public function guardarRoles(Request $request, User $usuario)
    {
        $request->validate([
            'roles'   => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $idsActuales = $usuario->roles()->pluck('roles.id')->toArray();
        $idsNuevos   = $request->input('roles', []);

        $aAsignar = array_diff($idsNuevos, $idsActuales);
        $aRemover = array_diff($idsActuales, $idsNuevos);

        foreach (Role::whereIn('id', $aAsignar)->get() as $rol) {
            $this->acl->asignarRolAUsuario($usuario, $rol);
        }
        foreach (Role::whereIn('id', $aRemover)->get() as $rol) {
            $this->acl->removerRolDeUsuario($usuario, $rol);
        }

        return redirect()->route('admin.acl.usuarios')
            ->with('success', "Roles actualizados para {$usuario->name}.");
    }

    public function bitacora(Request $request)
    {
        $movimientos = DB::table('acl_bitacora')
            ->leftJoin('users as afectado',  'acl_bitacora.usuario_afectado_id', '=', 'afectado.id')
            ->leftJoin('users as ejecutor',  'acl_bitacora.ejecutado_por',       '=', 'ejecutor.id')
            ->leftJoin('roles',              'acl_bitacora.role_id',             '=', 'roles.id')
            ->leftJoin('permisos',           'acl_bitacora.permiso_id',          '=', 'permisos.id')
            ->select(
                'acl_bitacora.*',
                'afectado.name as usuario_afectado',
                'ejecutor.name as ejecutor_nombre',
                'roles.nombre as rol_nombre',
                'permisos.codigo as permiso_codigo'
            )
            ->orderByDesc('acl_bitacora.created_at')
            ->paginate(30);

        return view('screens.admin.acl.bitacora', compact('movimientos'));
    }
}
