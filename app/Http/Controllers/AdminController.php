<?php

namespace App\Http\Controllers;

use App\Models\Dependencia;
use App\Models\Periodo;
use App\Models\Permiso;
use App\Models\User;
use App\Services\PeriodoService;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Panel de administración: usuarios, periodos y bitácora.
 *
 * El controlador coordina las peticiones HTTP y delega la lógica de negocio
 * en servicios dedicados:
 *   - UsuarioService: sincronización de permisos y catálogo de roles.
 *   - PeriodoService: regla "un periodo activo por tipo" y sus transacciones.
 */
class AdminController extends Controller
{
    public function __construct(
        private UsuarioService $usuarios,
        private PeriodoService $periodos,
    ) {}

    // ========== USUARIOS ==========

    public function index()
    {
        $usuarios = User::with('dependencia', 'roles')->orderBy('name')->paginate(20);
        return view('screens.admin.usuarios', compact('usuarios'));
    }

    public function create()
    {
        $dependencias     = Dependencia::orderBy('nombre')->get();
        $roles            = User::ROLES_TODOS;
        $permisos         = $this->permisosAsignables();
        $rolesConPermisos = $this->usuarios->permisosPorRol();

        return view('screens.admin.nuevo-usuario', compact('dependencias', 'roles', 'permisos', 'rolesConPermisos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users',
            'password'       => 'required|string|min:8|confirmed',
            'rol'            => 'required|in:' . UsuarioService::rolesValidacion(),
            'cargo'          => 'nullable|string|max:255',
            'dependencia_id' => 'nullable|exists:dependencias,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['activo']   = true;
        $usuario = User::create($validated);

        $this->usuarios->sincronizarPermisos($usuario, $request->input('permisos'));

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $dependencias     = Dependencia::orderBy('nombre')->get();
        $roles            = User::ROLES_TODOS;
        $permisos         = $this->permisosAsignables();
        $rolesConPermisos = $this->usuarios->permisosPorRol();
        $permisosUsuario  = $usuario->permisosDirectos()->pluck('permisos.id')->toArray();

        return view('screens.admin.editar-usuario', compact('usuario', 'dependencias', 'roles', 'permisos', 'rolesConPermisos', 'permisosUsuario'));
    }

    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email,' . $usuario->id,
            'rol'            => 'required|in:' . UsuarioService::rolesValidacion(),
            'cargo'          => 'nullable|string|max:255',
            'dependencia_id' => 'nullable|exists:dependencias,id',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $validated['password'] = Hash::make($request->password);
        }

        $validated['activo'] = $request->boolean('activo', true);
        $usuario->update($validated);

        $this->usuarios->sincronizarPermisos($usuario, $request->input('permisos'));

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->update(['activo' => false]);
        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario movido a papelera.');
    }

    /**
     * Permisos que el admin puede asignar directamente, agrupados por módulo.
     * Se excluyen los módulos internos (acl, bitácora y configuración del
     * sistema) que no se otorgan por checkbox.
     */
    private function permisosAsignables()
    {
        return Permiso::whereNotIn('modulo', ['acl', 'bitacora', 'parametros', 'umbrales', 'unidades_valor', 'usuarios'])
            ->orderBy('modulo')
            ->orderBy('accion')
            ->get()
            ->groupBy('modulo');
    }

    // ========== PERIODOS ==========

    public function configuracion()
    {
        return view('screens.admin.configuracion');
    }

    public function periodos()
    {
        $periodos = Periodo::orderByDesc('fecha_inicio')->get();
        return view('screens.admin.periodos', compact('periodos'));
    }

    public function crearPeriodo()
    {
        return view('screens.admin.nuevo-periodo');
    }

    public function guardarPeriodo(Request $request)
    {
        $validated = $request->validate([
            'nombre'       => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
        ]);

        $validated['estatus']     = $request->estatus;
        $validated['descripcion'] = $request->descripcion;
        $tipo                     = $request->tipo ?? 'agenda_syd';

        $this->periodos->crear($validated, $tipo);

        return redirect()->route('admin.periodos')
            ->with('success', 'Periodo creado exitosamente.');
    }

    public function editarPeriodo(Periodo $periodo)
    {
        return view('screens.admin.editar-periodo', compact('periodo'));
    }

    public function actualizarPeriodo(Request $request, Periodo $periodo)
    {
        $validated = $request->validate([
            'nombre'       => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
        ]);

        $validated['estatus']     = $request->estatus;
        $validated['descripcion'] = $request->descripcion;

        $this->periodos->actualizar($periodo, $validated);

        return redirect()->route('admin.periodos')
            ->with('success', 'Periodo actualizado.');
    }

    public function eliminarPeriodo(Periodo $periodo)
    {
        if ($periodo->estaActivo()) {
            return back()->with('error', 'No puedes eliminar el periodo activo.');
        }

        $periodo->delete();

        return redirect()->route('admin.periodos')
            ->with('success', 'Periodo eliminado.');
    }

    public function activarPeriodo(Periodo $periodo)
    {
        $this->periodos->activar($periodo);

        return back()->with('success', "Periodo \"{$periodo->nombre}\" activado. Los demás periodos activos fueron cerrados automáticamente.");
    }

    // ========== BITACORA ==========

    public function bitacora(Request $request)
    {
        $movimientos = DB::table('bitacora')
            ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
            ->select('bitacora.*', 'users.name as usuario_nombre')
            ->when($request->modulo, fn ($q, $v) => $q->where('bitacora.modulo', $v))
            ->when($request->tipo,   fn ($q, $v) => $q->where('bitacora.tipo', $v))
            ->orderByDesc('bitacora.created_at')
            ->paginate(30);

        $modulos = DB::table('bitacora')->distinct()->orderBy('modulo')->pluck('modulo');
        $tipos   = DB::table('bitacora')->distinct()->orderBy('tipo')->pluck('tipo');

        return view('screens.admin.bitacora', compact('movimientos', 'modulos', 'tipos'));
    }
}
