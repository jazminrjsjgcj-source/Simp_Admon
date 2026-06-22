@extends('layouts.app')
@section('title', 'Dependencias')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Dependencias</h2>
      <p class="nowrap">{{ $dependencias->count() }} registros — activas e históricas.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.index') }}" class="btn btn-outline btn-sm">← Catálogos</a>
      <a href="{{ route('admin.catalogos.dependencias.crear') }}" class="btn btn-sm">+ Nueva</a>
    </div>
  </div>


  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Unidades</th>
            <th>Estado</th>
            <th class="table-action-cell">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($dependencias as $dep)
            <tr>
              <td><code>{{ $dep->codigo }}</code></td>
              <td>
                <strong>{{ $dep->nombre }}</strong>
                @if(isset($dep->activo) && !$dep->activo)
                  <span class="badge" style="margin-left:6px;opacity:.7">Inactiva</span>
                @endif
              </td>
              <td>{{ $dep->unidades_count }}</td>
              <td>
                <span class="badge {{ $dep->activo ? 'success-b' : '' }}">
                  {{ ($dep->activo ?? true) ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.dependencias.editar', $dep) }}" class="btn table-action-btn btn-sm">Editar</a>
                  <form method="POST" action="{{ route('admin.catalogos.dependencias.toggle', $dep) }}" class="u-inline">
                    @csrf
                    <button type="submit" class="btn table-action-btn btn-sm btn-outline"
                      onclick="return confirmarAccion(this, '¿{{ ($dep->activo ?? true) ? 'Desactivar' : 'Activar' }} esta dependencia?')">
                      {{ ($dep->activo ?? true) ? 'Desactivar' : 'Activar' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="u-text-center cal-empty-state">Sin dependencias registradas.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
