@extends('layouts.app')
@section('title', 'Tipos de trámite')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Tipos de trámite</h2>
      <p class="nowrap">{{ $tipos->count() }} tipos — clasifican cada trámite registrado.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.index') }}" class="btn btn-outline btn-sm">← Catálogos</a>
      <a href="{{ route('admin.catalogos.tipos-tramite.crear') }}" class="btn btn-sm">+ Nuevo</a>
    </div>
  </div>


  <div class="assist-box">
    Aparecen en el formulario de <strong>nuevo trámite</strong> (paso Identificación).
    Ejemplos: Licencia, Permiso, Registro, Certificado, Constancia.
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Orden</th><th>Nombre</th><th>Descripción</th><th>Trámites</th><th>Estado</th><th class="table-action-cell">Acciones</th></tr>
        </thead>
        <tbody>
          @forelse($tipos as $tipo)
            <tr>
              <td style="color:#9ca3af;font-size:12px">{{ $tipo->orden }}</td>
              <td><strong>{{ $tipo->nombre }}</strong></td>
              <td style="color:#6b7280;font-size:13px">{{ $tipo->descripcion ?? '—' }}</td>
              <td>{{ $tipo->tramites_count ?? '—' }}</td>
              <td><span class="badge {{ $tipo->activo ? 'success-b' : '' }}">{{ $tipo->activo ? 'Activo' : 'Inactivo' }}</span></td>
              <td class="table-action-cell">
                <div class="table-actions">
                  <a href="{{ route('admin.catalogos.tipos-tramite.editar', $tipo) }}" class="btn table-action-btn btn-sm">Editar</a>
                  <form method="POST" action="{{ route('admin.catalogos.tipos-tramite.toggle', $tipo) }}" class="u-inline">
                    @csrf
                    <button type="submit" class="btn table-action-btn btn-sm btn-outline">
                      {{ $tipo->activo ? 'Desactivar' : 'Activar' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="u-text-center cal-empty-state">Sin tipos registrados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
