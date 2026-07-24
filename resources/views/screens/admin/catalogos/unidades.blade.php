@extends('layouts.app')
@section('title', 'Unidades administrativas')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Unidades administrativas</h2>
      <p class="nowrap">{{ $unidades->count() }} unidades registradas.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.index') }}" class="btn btn-outline btn-sm">← Catálogos</a>
      <a href="{{ route('admin.catalogos.unidades.crear') }}" class="btn btn-sm">+ Nueva</a>
    </div>
  </div>


  {{-- Filtro por dependencia --}}
  <div class="card" style="padding:12px 16px">
    <div style="display:flex;gap:12px;align-items:center">
      <label for="filtroDep" style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap">Dependencia:</label>
      <select id="filtroDep" onchange="filtrarDep(this.value)" style="flex:1;max-width:420px">
        <option value="">Todas las dependencias</option>
        @foreach($dependencias as $dep)
          <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
        @endforeach
      </select>
      <span id="filtroContador" style="font-size:12px;color:#6b7280"></span>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="data-table" id="tablaUnidades">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Dependencia</th>
            <th>Estado</th>
            <th class="table-action-cell">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($unidades as $uni)
            <tr data-dep="{{ $uni->dependencia_id }}">
              <td><code>{{ $uni->codigo }}</code></td>
              <td>
                <strong>{{ $uni->nombre }}</strong>
                @if(isset($uni->activo) && !$uni->activo)
                  <span class="badge" style="margin-left:6px;opacity:.7">Inactiva</span>
                @endif
              </td>
              <td>{{ $uni->dependencia?->nombre ?? '' }}</td>
              <td>
                <span class="badge {{ $uni->activo ? 'success-b' : '' }}">
                  {{ ($uni->activo ?? true) ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.unidades.editar', $uni) }}" class="btn table-action-btn btn-sm">Editar</a>
                  <form method="POST" action="{{ route('admin.catalogos.unidades.toggle', $uni) }}" class="u-inline">
                    @csrf
                    <button type="submit" class="btn table-action-btn btn-sm btn-outline"
                      onclick="return confirmarAccion(this, '¿{{ ($uni->activo ?? true) ? 'Desactivar' : 'Activar' }} esta unidad?')">
                      {{ ($uni->activo ?? true) ? 'Desactivar' : 'Activar' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="u-text-center cal-empty-state">Sin unidades registradas.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
function filtrarDep(depId) {
  var visibles = 0;
  document.querySelectorAll('#tablaUnidades tbody tr').forEach(function (tr) {
    var mostrar = !depId || tr.dataset.dep === depId;
    tr.style.display = mostrar ? '' : 'none';
    if (mostrar && tr.dataset.dep) visibles++;
  });
  var contador = document.getElementById('filtroContador');
  if (contador) {
    contador.textContent = depId ? visibles + ' unidad' + (visibles !== 1 ? 'es' : '') : '';
  }
}
</script>
@endpush
