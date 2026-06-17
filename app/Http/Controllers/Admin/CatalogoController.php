<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\SectorScian;
use App\Models\SubsectorScian;
use App\Models\SujetoObligado;
use App\Models\TipoRegulacion;
use App\Models\TipoTramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Fase C — Catálogos administrativos.
 *
 * CRUD para dependencias, unidades administrativas, tipos de regulación,
 * tipos de trámite y sectores/subsectores SCIAN.
 * Todos con soft-toggle (activar/desactivar sin eliminar).
 */
class CatalogoController extends Controller
{
    // ─── Index ───────────────────────────────────────────────────

    public function index()
    {
        return view('screens.admin.catalogos.index');
    }

    // ─── Dependencias ────────────────────────────────────────────

    public function dependencias()
    {
        $query = Dependencia::withCount('unidades')->orderBy('nombre');
        if (Schema::hasColumn('dependencias', 'activo')) {
            $query->reorder()->orderBy('activo', 'desc')->orderBy('nombre');
        }
        $dependencias = $query->get();

        return view('screens.admin.catalogos.dependencias', compact('dependencias'));
    }

    public function crearDependencia()
    {
        return view('screens.admin.catalogos.dependencia-form', ['dependencia' => null]);
    }

    public function guardarDependencia(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:10|unique:dependencias,codigo',
        ], [
            'codigo.unique' => 'Ese código ya está registrado.',
        ]);

        $validated['activo'] = true;
        Dependencia::create($validated);

        return redirect()->route('admin.catalogos.dependencias')
            ->with('success', 'Dependencia creada.');
    }

    public function editarDependencia(Dependencia $dependencia)
    {
        // Cargar las unidades ligadas a esta dependencia, para mostrarlas y
        // gestionarlas (agregar/activar/desactivar/eliminar) desde el formulario.
        $unidades = UnidadAdministrativa::where('dependencia_id', $dependencia->id)
            ->orderBy('nombre')
            ->get();

        return view('screens.admin.catalogos.dependencia-form', compact('dependencia', 'unidades'));
    }

    public function actualizarDependencia(Request $request, Dependencia $dependencia)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:10|unique:dependencias,codigo,' . $dependencia->id,
        ]);

        $dependencia->update($validated);

        return redirect()->route('admin.catalogos.dependencias')
            ->with('success', 'Dependencia actualizada.');
    }

    public function toggleDependencia(Dependencia $dependencia)
    {
        $dependencia->update(['activo' => !$dependencia->activo]);
        $estado = $dependencia->activo ? 'activada' : 'desactivada';

        return back()->with('success', "Dependencia {$estado}.");
    }

    // ─── Sujetos Obligados (titulares de dependencia) ───────────

    public function sujetosObligados()
    {
        $sujetos = SujetoObligado::with('dependencia')
            ->orderBy('nombre')
            ->get();

        return view('screens.admin.catalogos.sujetos-obligados', compact('sujetos'));
    }

    public function crearSujetoObligado()
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        return view('screens.admin.catalogos.sujeto-obligado-form', [
            'sujeto'       => null,
            'dependencias' => $dependencias,
        ]);
    }

    public function guardarSujetoObligado(Request $request)
    {
        $validated = $request->validate([
            'dependencia_id' => 'required|exists:dependencias,id',
            'nombre'         => 'required|string|max:255',
            'cargo'          => 'nullable|string|max:255',
        ]);

        $validated['activo'] = true;
        SujetoObligado::create($validated);

        return redirect()->route('admin.catalogos.sujetos-obligados')
            ->with('success', 'Sujeto obligado creado.');
    }

    public function editarSujetoObligado(SujetoObligado $sujeto)
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        return view('screens.admin.catalogos.sujeto-obligado-form', compact('sujeto', 'dependencias'));
    }

    public function actualizarSujetoObligado(Request $request, SujetoObligado $sujeto)
    {
        $validated = $request->validate([
            'dependencia_id' => 'required|exists:dependencias,id',
            'nombre'         => 'required|string|max:255',
            'cargo'          => 'nullable|string|max:255',
        ]);

        $sujeto->update($validated);

        return redirect()->route('admin.catalogos.sujetos-obligados')
            ->with('success', 'Sujeto obligado actualizado.');
    }

    public function toggleSujetoObligado(SujetoObligado $sujeto)
    {
        $sujeto->update(['activo' => !$sujeto->activo]);
        $estado = $sujeto->activo ? 'activado' : 'desactivado';

        return back()->with('success', "Sujeto obligado {$estado}.");
    }

    // ─── Unidades Administrativas ────────────────────────────────

    public function unidades()
    {
        $query = UnidadAdministrativa::with('dependencia')->orderBy('nombre');
        if (Schema::hasColumn('unidades_administrativas', 'activo')) {
            $query->reorder()->orderBy('activo', 'desc')->orderBy('nombre');
        }
        $unidades = $query->get();

        $dependencias = Dependencia::activas()->orderBy('nombre')->get();

        return view('screens.admin.catalogos.unidades', compact('unidades', 'dependencias'));
    }

    public function crearUnidad()
    {
        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
        return view('screens.admin.catalogos.unidad-form', ['unidad' => null, 'dependencias' => $dependencias]);
    }

    public function guardarUnidad(Request $request)
    {
        $validated = $request->validate([
            'dependencia_id' => 'required|exists:dependencias,id',
            'codigo'         => 'required|string|max:10',
            'nombre'         => 'required|string|max:255',
        ]);

        $existe = UnidadAdministrativa::where('dependencia_id', $validated['dependencia_id'])
            ->where('codigo', $validated['codigo'])
            ->exists();

        if ($existe) {
            return back()->withErrors(['codigo' => 'Ese código ya existe en esta dependencia.'])->withInput();
        }

        $validated['activo'] = true;
        UnidadAdministrativa::create($validated);

        // Si el alta vino desde el formulario de edición de una dependencia,
        // regresamos ahí para que el usuario siga viendo sus unidades.
        // Si no, mantiene el comportamiento original (lista general de unidades).
        if ($request->filled('volver_a_dependencia')) {
            return redirect()
                ->route('admin.catalogos.dependencias.editar', $request->input('volver_a_dependencia'))
                ->with('success', 'Unidad administrativa agregada.');
        }

        return redirect()->route('admin.catalogos.unidades')
            ->with('success', 'Unidad administrativa creada.');
    }

    public function editarUnidad(UnidadAdministrativa $unidad)
    {
        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
        return view('screens.admin.catalogos.unidad-form', compact('unidad', 'dependencias'));
    }

    public function actualizarUnidad(Request $request, UnidadAdministrativa $unidad)
    {
        $validated = $request->validate([
            'dependencia_id' => 'required|exists:dependencias,id',
            'codigo'         => 'required|string|max:10',
            'nombre'         => 'required|string|max:255',
        ]);

        $unidad->update($validated);

        return redirect()->route('admin.catalogos.unidades')
            ->with('success', 'Unidad actualizada.');
    }

    public function toggleUnidad(Request $request, UnidadAdministrativa $unidad)
    {
        $unidad->update(['activo' => !$unidad->activo]);
        $estado = $unidad->activo ? 'activada' : 'desactivada';

        if ($request->filled('volver_a_dependencia')) {
            return redirect()
                ->route('admin.catalogos.dependencias.editar', $request->input('volver_a_dependencia'))
                ->with('success', "Unidad {$estado}.");
        }

        return back()->with('success', "Unidad {$estado}.");
    }

    /**
     * Elimina una unidad administrativa SOLO si no tiene nada ligado
     * (ni usuarios ni trámites). Si tiene registros dependientes, no la borra
     * y avisa que solo puede desactivarse, para no romper esos registros.
     */
    public function eliminarUnidad(Request $request, UnidadAdministrativa $unidad)
    {
        $tieneUsuarios = \App\Models\User::where('unidad_id', $unidad->id)->exists();
        $tieneTramites = \App\Models\Tramite::where('unidad_id', $unidad->id)->exists();

        if ($tieneUsuarios || $tieneTramites) {
            $mensaje = 'No se puede eliminar: la unidad tiene '
                . ($tieneTramites ? 'trámites' : '')
                . ($tieneTramites && $tieneUsuarios ? ' y ' : '')
                . ($tieneUsuarios ? 'usuarios' : '')
                . ' ligados. Solo puede desactivarla.';
            return back()->with('error', $mensaje);
        }

        $dependenciaId = $unidad->dependencia_id;
        $unidad->delete();

        if ($request->filled('volver_a_dependencia')) {
            return redirect()
                ->route('admin.catalogos.dependencias.editar', $request->input('volver_a_dependencia'))
                ->with('success', 'Unidad eliminada.');
        }

        return back()->with('success', 'Unidad eliminada.');
    }

    // ─── Tipos de Regulación ─────────────────────────────────────

    public function tiposRegulacion()
    {
        $tipos = TipoRegulacion::orderBy('orden')->orderBy('nombre')->get();
        return view('screens.admin.catalogos.tipos-regulacion', compact('tipos'));
    }

    public function crearTipoRegulacion()
    {
        return view('screens.admin.catalogos.tipo-form', [
            'item'          => null,
            'titulo'        => 'Tipo de regulación',
            'ruta_guardar'  => 'admin.catalogos.tipos-regulacion.guardar',
            'ruta_cancelar' => 'admin.catalogos.tipos-regulacion',
        ]);
    }

    public function guardarTipoRegulacion(Request $request)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:tipos_regulacion,nombre',
            'descripcion' => 'nullable|string|max:500',
            'orden'       => 'nullable|integer|min:0',
        ]);
        $validated['activo'] = true;
        $validated['orden']  = $validated['orden'] ?? 0;
        TipoRegulacion::create($validated);

        return redirect()->route('admin.catalogos.tipos-regulacion')
            ->with('success', 'Tipo de regulación creado.');
    }

    public function editarTipoRegulacion(TipoRegulacion $tipo)
    {
        return view('screens.admin.catalogos.tipo-form', [
            'item'            => $tipo,
            'titulo'          => 'Tipo de regulación',
            'ruta_actualizar' => 'admin.catalogos.tipos-regulacion.actualizar',
            'ruta_cancelar'   => 'admin.catalogos.tipos-regulacion',
        ]);
    }

    public function actualizarTipoRegulacion(Request $request, TipoRegulacion $tipo)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:tipos_regulacion,nombre,' . $tipo->id,
            'descripcion' => 'nullable|string|max:500',
            'orden'       => 'nullable|integer|min:0',
        ]);
        $tipo->update($validated);

        return redirect()->route('admin.catalogos.tipos-regulacion')
            ->with('success', 'Tipo de regulación actualizado.');
    }

    public function toggleTipoRegulacion(TipoRegulacion $tipo)
    {
        $tipo->update(['activo' => !$tipo->activo]);
        $estado = $tipo->activo ? 'activado' : 'desactivado';

        return back()->with('success', "Tipo {$estado}.");
    }

    // ─── Tipos de Trámite ────────────────────────────────────────

    public function tiposTramite()
    {
        $tipos = TipoTramite::withCount('tramites')->orderBy('orden')->orderBy('nombre')->get();
        return view('screens.admin.catalogos.tipos-tramite', compact('tipos'));
    }

    public function crearTipoTramite()
    {
        return view('screens.admin.catalogos.tipo-form', [
            'item'          => null,
            'titulo'        => 'Tipo de trámite',
            'ruta_guardar'  => 'admin.catalogos.tipos-tramite.guardar',
            'ruta_cancelar' => 'admin.catalogos.tipos-tramite',
        ]);
    }

    public function guardarTipoTramite(Request $request)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:tipos_tramite,nombre',
            'descripcion' => 'nullable|string|max:500',
            'orden'       => 'nullable|integer|min:0',
        ]);
        $validated['activo'] = true;
        $validated['orden']  = $validated['orden'] ?? 0;
        TipoTramite::create($validated);

        return redirect()->route('admin.catalogos.tipos-tramite')
            ->with('success', 'Tipo de trámite creado.');
    }

    public function editarTipoTramite(TipoTramite $tipo)
    {
        return view('screens.admin.catalogos.tipo-form', [
            'item'            => $tipo,
            'titulo'          => 'Tipo de trámite',
            'ruta_actualizar' => 'admin.catalogos.tipos-tramite.actualizar',
            'ruta_cancelar'   => 'admin.catalogos.tipos-tramite',
        ]);
    }

    public function actualizarTipoTramite(Request $request, TipoTramite $tipo)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:tipos_tramite,nombre,' . $tipo->id,
            'descripcion' => 'nullable|string|max:500',
            'orden'       => 'nullable|integer|min:0',
        ]);
        $tipo->update($validated);

        return redirect()->route('admin.catalogos.tipos-tramite')
            ->with('success', 'Tipo de trámite actualizado.');
    }

    public function toggleTipoTramite(TipoTramite $tipo)
    {
        $tipo->update(['activo' => !$tipo->activo]);
        $estado = $tipo->activo ? 'activado' : 'desactivado';

        return back()->with('success', "Tipo {$estado}.");
    }

    // ─── Sectores SCIAN ──────────────────────────────────────────

    public function sectores()
    {
        $sectores = SectorScian::withCount('subsectores')->orderBy('codigo')->get();
        return view('screens.admin.catalogos.sectores', compact('sectores'));
    }

    public function subsectores(SectorScian $sector)
    {
        $subsectores = $sector->subsectores()->orderBy('codigo')->get();
        return view('screens.admin.catalogos.subsectores', compact('sector', 'subsectores'));
    }

    public function crearSector()
    {
        return view('screens.admin.catalogos.sector-form', ['sector' => null]);
    }

    public function guardarSector(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:10|unique:sectores_scian,codigo',
            'nombre' => 'required|string|max:255',
        ]);
        SectorScian::create($validated);

        return redirect()->route('admin.catalogos.sectores')
            ->with('success', 'Sector creado.');
    }

    public function editarSector(SectorScian $sector)
    {
        return view('screens.admin.catalogos.sector-form', compact('sector'));
    }

    public function actualizarSector(Request $request, SectorScian $sector)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:10|unique:sectores_scian,codigo,' . $sector->id,
            'nombre' => 'required|string|max:255',
        ]);
        $sector->update($validated);

        return redirect()->route('admin.catalogos.sectores')
            ->with('success', 'Sector actualizado.');
    }

    public function crearSubsector(SectorScian $sector)
    {
        return view('screens.admin.catalogos.subsector-form', ['sector' => $sector, 'subsector' => null]);
    }

    public function guardarSubsector(Request $request, SectorScian $sector)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:10|unique:subsectores_scian,codigo',
            'nombre' => 'required|string|max:255',
        ]);
        $validated['sector_id'] = $sector->id;
        SubsectorScian::create($validated);

        return redirect()->route('admin.catalogos.subsectores', $sector)
            ->with('success', 'Subsector creado.');
    }

    public function editarSubsector(SectorScian $sector, SubsectorScian $subsector)
    {
        return view('screens.admin.catalogos.subsector-form', compact('sector', 'subsector'));
    }

    public function actualizarSubsector(Request $request, SectorScian $sector, SubsectorScian $subsector)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:10|unique:subsectores_scian,codigo,' . $subsector->id,
            'nombre' => 'required|string|max:255',
        ]);
        $subsector->update($validated);

        return redirect()->route('admin.catalogos.subsectores', $sector)
            ->with('success', 'Subsector actualizado.');
    }
}
