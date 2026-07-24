@extends('layouts.app')
@section('title', 'Tipos de regulación')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Tipos de regulación</h2>
      <p class="nowrap">{{ $tipos->count() }} tipos — alimentan propuestas y regulaciones.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.index') }}" class="btn btn-outline btn-sm">← Catálogos</a>
      <a href="{{ route('admin.catalogos.tipos-regulacion.crear') }}" class="btn btn-sm">+ Nuevo</a>
    </div>
  </div>


  <div class="assist-box">
    Estos tipos aparecen en los selects de <strong>Propuestas regulatorias</strong> y <strong>Regulaciones</strong>.
    Desactivar un tipo lo oculta en nuevos registros pero conserva los históricos.
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Orden</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th class="table-action-cell">Acciones</th></tr>
        </thead>
        <tbody>
          @forelse($tipos as $tipo)
            <tr>
              <td style="color:#9ca3af;font-size:12px">{{ $tipo->orden }}</td>
              <td><strong>{{ $tipo->nombre }}</strong></td>
              <td style="color:#6b7280;font-size:13px">{{ $tipo->descripcion ?? '' }}</td>
              <td><span class="badge {{ $tipo->activo ? 'success-b' : '' }}">{{ $tipo->activo ? 'Activo' : 'Inactivo' }}</span></td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.tipos-regulacion.editar', $tipo) }}" class="btn table-action-btn btn-sm">Editar</a>
                  <form method="POST" action="{{ route('admin.catalogos.tipos-regulacion.toggle', $tipo) }}" class="u-inline">
                    @csrf
                    <button type="submit" class="btn table-action-btn btn-sm btn-outline">
                      {{ $tipo->activo ? 'Desactivar' : 'Activar' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="u-text-center cal-empty-state">Sin tipos registrados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
