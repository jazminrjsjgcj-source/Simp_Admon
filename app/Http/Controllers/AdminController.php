<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Permiso;
use App\Models\Periodo;
use App\Models\Dependencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ========== USUARIOS ==========

    public function index()
    {
        $usuarios = User::with('dependencia', 'roles')->orderBy('name')->paginate(20);
        return view('screens.admin.usuarios', compact('usuarios'));
    }

    public function create()
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        $roles = ['enlace', 'sujeto', 'juridico', 'revisora', 'admin'];
        $permisos = Permiso::whereNotIn('modulo', ['acl', 'bitacora', 'parametros', 'umbrales', 'unidades_valor', 'usuarios'])
         ->orderBy('modulo')->orderBy('accion')->get()->groupBy('modulo');        $rolesConPermisos = $this->obtenerPermisosDeRoles();
        return view('screens.admin.nuevo-usuario', compact('dependencias', 'roles', 'permisos', 'rolesConPermisos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users',
            'password'       => 'required|string|min:8|confirmed',
            'rol'            => 'required|in:enlace,sujeto,juridico,revisora,admin',
            'cargo'          => 'nullable|string|max:255',
            'dependencia_id' => 'nullable|exists:dependencias,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['activo']   = true;
        $usuario = User::create($validated);

        // Asignar permisos directos (checkboxes)
        if ($request->has('permisos') && $usuario) {
            $usuario->permisosDirectos()->sync(
                collect($request->permisos)->mapWithKeys(fn ($id) => [$id => ['asignado_por' => auth()->id()]])->toArray()
            );
        }

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        $roles = ['enlace', 'sujeto', 'juridico', 'revisora', 'admin'];
        $permisos = Permiso::whereNotIn('modulo', ['acl', 'bitacora', 'parametros', 'umbrales', 'unidades_valor', 'usuarios'])
        ->orderBy('modulo')->orderBy('accion')->get()->groupBy('modulo');        $rolesConPermisos = $this->obtenerPermisosDeRoles();
        $permisosUsuario = $usuario->permisosDirectos()->pluck('permisos.id')->toArray();
        return view('screens.admin.editar-usuario', compact('usuario', 'dependencias', 'roles', 'permisos', 'rolesConPermisos', 'permisosUsuario'));
    }

    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email,' . $usuario->id,
            'rol'            => 'required|in:enlace,sujeto,juridico,revisora,admin',
            'cargo'          => 'nullable|string|max:255',
            'dependencia_id' => 'nullable|exists:dependencias,id',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $validated['password'] = Hash::make($request->password);
        }

        $validated['activo'] = $request->boolean('activo', true);
        $usuario->update($validated);
         $permisoIds = $request->has('permisos') ? $request->permisos : [];
        $usuario->permisosDirectos()->sync(
            collect($permisoIds)->mapWithKeys(fn ($id) => [$id => ['asignado_por' => auth()->id()]])->toArray()
        );
        cache()->forget('user_permisos_' . $usuario->id);
        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }
    private function obtenerPermisosDeRoles(): array
    {
        $catalogo = config('acl.roles_iniciales');
        $todosPermisos = array_keys(config('acl.permisos'));
        $resultado = [];
        foreach ($catalogo as $codigo => $datos) {
            $resultado[$codigo] = $datos['permisos'] === '*' ? $todosPermisos : $datos['permisos'];
        }
        return $resultado;
    }
    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->update(['activo' => false]);
        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario eliminado correctamente.');
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
        $request->validate([
            'nombre'       => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
        ]);

        $tipo = $request->tipo ?? 'agenda_syd';

        \DB::transaction(function () use ($request, $tipo) {
            // Solo 1 activo por tipo: cerrar los del mismo tipo
            if ($request->estatus === 'activo') {
                Periodo::where('estatus', 'activo')
                    ->where('tipo', $tipo)
                    ->update(['estatus' => 'cerrado']);
            }

            Periodo::create([
                'nombre'       => $request->nombre,
                'tipo'         => $tipo,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin'    => $request->fecha_fin,
                'estatus'      => $request->estatus ?? 'proximo',
                'descripcion'  => $request->descripcion,
                'created_by'   => auth()->id(),
            ]);
        });

        return redirect()->route('admin.periodos')
            ->with('success', 'Periodo creado exitosamente.');
    }

    public function editarPeriodo(Periodo $periodo)
    {
        return view('screens.admin.editar-periodo', compact('periodo'));
    }

    public function actualizarPeriodo(Request $request, Periodo $periodo)
    {
        $request->validate([
            'nombre'       => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
        ]);

        \DB::transaction(function () use ($request, $periodo) {
            // Solo 1 activo por tipo: cerrar los del mismo tipo
            if ($request->estatus === 'activo') {
                Periodo::where('estatus', 'activo')
                    ->where('tipo', $periodo->tipo)
                    ->where('id', '!=', $periodo->id)
                    ->update(['estatus' => 'cerrado']);
            }

            $periodo->update([
                'nombre'       => $request->nombre,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin'    => $request->fecha_fin,
                'estatus'      => $request->estatus ?? 'proximo',
                'descripcion'  => $request->descripcion,
            ]);
        });

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
        \DB::transaction(function () use ($periodo) {
            // Cerrar solo los activos DEL MISMO TIPO
            Periodo::where('estatus', 'activo')
                ->where('tipo', $periodo->tipo)
                ->where('id', '!=', $periodo->id)
                ->update(['estatus' => 'cerrado']);

            $periodo->update(['estatus' => 'activo']);
        });

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
