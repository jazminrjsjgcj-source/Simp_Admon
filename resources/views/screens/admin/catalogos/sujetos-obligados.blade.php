@extends('layouts.app')
@section('title', 'Sujetos obligados')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Sujetos obligados</h2>
      <p class="nowrap">{{ $sujetos->count() }} titulares registrados.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.index') }}" class="btn btn-outline btn-sm">← Catálogos</a>
      <a href="{{ route('admin.catalogos.sujetos-obligados.crear') }}" class="btn btn-sm">+ Nuevo</a>
    </div>
  </div>


  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Cargo</th>
            <th>Dependencia</th>
            <th>Estado</th>
            <th class="table-action-cell">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sujetos as $sujeto)
            <tr>
              <td><strong>{{ $sujeto->nombre }}</strong></td>
              <td>@dato($sujeto->cargo)</td>
              <td>@dato($sujeto->dependencia?->nombre)</td>
              <td>
                <span class="badge {{ $sujeto->activo ? 'success-b' : '' }}">
                  {{ $sujeto->activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.sujetos-obligados.editar', $sujeto) }}" class="btn table-action-btn btn-sm">Editar</a>
                  <form method="POST" action="{{ route('admin.catalogos.sujetos-obligados.toggle', $sujeto) }}" class="u-inline">
                    @csrf
                    <button type="submit" class="btn table-action-btn btn-sm btn-outline"
                      onclick="return confirmarAccion(this, '¿{{ $sujeto->activo ? 'Desactivar' : 'Activar' }} este sujeto obligado?')">
                      {{ $sujeto->activo ? 'Desactivar' : 'Activar' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="u-text-center cal-empty-state">Sin sujetos obligados registrados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
