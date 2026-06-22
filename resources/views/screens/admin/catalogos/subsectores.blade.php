@extends('layouts.app')
@section('title', 'Subsectores — ' . $sector->nombre)

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Subsectores de {{ $sector->nombre }}</h2>
      <p class="nowrap"><code>{{ $sector->codigo }}</code> — {{ $subsectores->count() }} subsectores.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.sectores') }}" class="btn btn-outline btn-sm">← Sectores</a>
      <a href="{{ route('admin.catalogos.subsectores.crear', $sector) }}" class="btn btn-sm">+ Nuevo</a>
    </div>
  </div>


  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Código</th><th>Nombre</th><th class="table-action-cell">Acciones</th></tr>
        </thead>
        <tbody>
          @forelse($subsectores as $sub)
            <tr>
              <td><code>{{ $sub->codigo }}</code></td>
              <td>{{ $sub->nombre }}</td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.subsectores.editar', [$sector, $sub]) }}" class="btn table-action-btn btn-sm">Editar</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="3" class="u-text-center cal-empty-state">Sin subsectores.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
