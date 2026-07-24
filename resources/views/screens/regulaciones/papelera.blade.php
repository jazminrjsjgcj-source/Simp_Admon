@extends('layouts.app')
@section('title', 'Papelera del articulado')
@section('content')
<div class="page-wide">
  <div class="screen-head">
    <div>
      <h2>Papelera del articulado</h2>
      <p>{{ $regulacion->nombre }} — elementos eliminados del articulado. Puedes restaurarlos o eliminarlos permanentemente.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('regulaciones.editor', $regulacion) }}" class="btn btn-outline">Volver al editor</a>
    </div>
  </div>

  <div class="card">
    {{-- Nota: solo se listan los elementos "tope" (cuyo padre NO está también
         en papelera), tal como los prepara RegulacionNodoController::papelera().
         Restaurar un tope restaura automáticamente todo lo que tenía anidado
         debajo (sus fracciones o incisos), así que no hace falta listar el
         árbol completo aquí. --}}
    <div class="table-wrap"><table class="data-table"><thead><tr>
      <th>Tipo</th>
      <th>Número</th>
      <th>Texto</th>
      <th>Eliminado el</th>
      <th class="table-action-cell">Acciones</th>
    </tr></thead><tbody>
      @forelse($topes as $nodo)
        <tr>
          <td>{{ $nodo->etiquetaTipo() }}</td>
          <td>{{ $nodo->numero ?? '' }}</td>
          <td>{{ \Illuminate\Support\Str::limit($nodo->texto, 100) ?: '(sin texto)' }}</td>
          <td>{{ $nodo->deleted_at->format('d/m/Y H:i') }}</td>
          <td class="table-action-cell">
            <div class="table-actions">
              <form method="POST" action="{{ route('regulaciones.nodos.papelera.restaurar', $nodo->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn table-action-btn btn-sm"
                  onclick="return confirm('¿Restaurar este elemento? Volverá a su lugar en el articulado junto con todo lo que tenía anidado debajo.')">
                  Restaurar
                </button>
              </form>
              <form method="POST" action="{{ route('regulaciones.nodos.papelera.eliminar', $nodo->id) }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn table-action-btn btn-sm danger btn-outline"
                  onclick="return confirm('¿Eliminar PERMANENTEMENTE este elemento y todo lo que tenía anidado debajo?\n\nEsta acción NO se puede deshacer.')">
                  Eliminar definitivo
                </button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="u-text-center cal-empty-state">La papelera de esta regulación está vacía.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</div>
@endsection
