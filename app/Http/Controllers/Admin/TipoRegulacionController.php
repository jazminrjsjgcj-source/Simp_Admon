<?php

namespace App\Http\Controllers\Admin;

use App\Models\TipoRegulacion;
use Illuminate\Http\Request;

/**
 * Catálogo de tipos de regulación. Hereda el CRUD común de
 * TipoCatalogoController y solo declara su modelo, tabla, título y rutas.
 *
 * Los métodos públicos conservan los nombres originales (tiposRegulacion,
 * guardarTipoRegulacion, etc.) para que web.php solo cambie la clase destino,
 * no el método ni el nombre de ruta.
 */
class TipoRegulacionController extends TipoCatalogoController
{
    protected function modelo(): string
    {
        return TipoRegulacion::class;
    }

    protected function tabla(): string
    {
        return 'tipos_regulacion';
    }

    protected function titulo(): string
    {
        return 'Tipo de regulación';
    }

    protected function rutaBase(): string
    {
        return 'admin.catalogos.tipos-regulacion';
    }

    protected function vistaLista(): string
    {
        return 'screens.admin.catalogos.tipos-regulacion';
    }

    protected function variableLista(): string
    {
        return 'tipos';
    }

    // ─── Alias con los nombres de método originales ──────────────

    public function tiposRegulacion()
    {
        return $this->listar();
    }

    public function crearTipoRegulacion()
    {
        return $this->mostrarCrear();
    }

    public function guardarTipoRegulacion(Request $request)
    {
        return $this->guardar($request);
    }

    public function editarTipoRegulacion(TipoRegulacion $tipo)
    {
        return $this->mostrarEditar($tipo);
    }

    public function actualizarTipoRegulacion(Request $request, TipoRegulacion $tipo)
    {
        return $this->actualizar($request, $tipo);
    }

    public function toggleTipoRegulacion(TipoRegulacion $tipo)
    {
        return $this->alternar($tipo);
    }
}
