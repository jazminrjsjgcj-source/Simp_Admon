<?php

namespace App\Http\Controllers\Admin;

use App\Models\TipoTramite;
use Illuminate\Http\Request;

/**
 * Catálogo de tipos de trámite. Hereda el CRUD común de
 * TipoCatalogoController. Su única particularidad es que el listado muestra
 * cuántos trámites usa cada tipo (withCount), por lo que sobreescribe el
 * método de listado; todo lo demás se hereda sin cambios.
 *
 * Los métodos públicos conservan los nombres originales (tiposTramite,
 * guardarTipoTramite, etc.) para que web.php solo cambie la clase destino,
 * no el método ni el nombre de ruta.
 */
class TipoTramiteController extends TipoCatalogoController
{
    protected function modelo(): string
    {
        return TipoTramite::class;
    }

    protected function tabla(): string
    {
        return 'tipos_tramite';
    }

    protected function titulo(): string
    {
        return 'Tipo de trámite';
    }

    protected function rutaBase(): string
    {
        return 'admin.catalogos.tipos-tramite';
    }

    protected function vistaLista(): string
    {
        return 'screens.admin.catalogos.tipos-tramite';
    }

    protected function variableLista(): string
    {
        return 'tipos';
    }

    // ─── Alias con los nombres de método originales ──────────────

    /**
     * El listado de tipos de trámite muestra el conteo de trámites por tipo,
     * por eso sobreescribe el listar() genérico de la clase base.
     */
    public function tiposTramite()
    {
        $tipos = TipoTramite::withCount('tramites')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('screens.admin.catalogos.tipos-tramite', compact('tipos'));
    }

    public function crearTipoTramite()
    {
        return $this->mostrarCrear();
    }

    public function guardarTipoTramite(Request $request)
    {
        return $this->guardar($request);
    }

    public function editarTipoTramite(TipoTramite $tipo)
    {
        return $this->mostrarEditar($tipo);
    }

    public function actualizarTipoTramite(Request $request, TipoTramite $tipo)
    {
        return $this->actualizar($request, $tipo);
    }

    public function toggleTipoTramite(TipoTramite $tipo)
    {
        return $this->alternar($tipo);
    }
}
