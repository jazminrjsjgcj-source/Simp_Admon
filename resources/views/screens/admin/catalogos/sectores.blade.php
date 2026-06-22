@extends('layouts.app')
@section('title', 'Sectores SCIAN')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Sectores SCIAN</h2>
      <p class="nowrap">{{ $sectores->count() }} sectores económicos — Sistema de Clasificación Industrial de América del Norte.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.index') }}" class="btn btn-outline btn-sm">← Catálogos</a>
      <a href="{{ route('admin.catalogos.sectores.crear') }}" class="btn btn-sm">+ Nuevo sector</a>
    </div>
  </div>


  <div class="assist-box">
    Los sectores y subsectores clasifican el impacto económico de trámites y propuestas.
    El catálogo base viene del <strong>SCIAN México 2018</strong> (INEGI). Solo edite si la clasificación municipal lo requiere.
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Código</th><th>Nombre</th><th>Subsectores</th><th class="table-action-cell">Acciones</th></tr>
        </thead>
        <tbody>
          @forelse($sectores as $sector)
            <tr>
              <td><code>{{ $sector->codigo }}</code></td>
              <td><strong>{{ $sector->nombre }}</strong></td>
              <td>
                <a href="{{ route('admin.catalogos.subsectores', $sector) }}" class="btn btn-outline btn-sm">
                  {{ $sector->subsectores_count }} subsectores →
                </a>
              </td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.sectores.editar', $sector) }}" class="btn table-action-btn btn-sm">Editar</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="u-text-center cal-empty-state">Sin sectores registrados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
