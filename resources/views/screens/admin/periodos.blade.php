@extends('layouts.app')
@section('title', 'Periodos')
@section('content')
<div class="page-wide">
  <div class="screen-head">
    <div><h2 class="nowrap">Periodos de Captura</h2><p class="nowrap">Un periodo activo por tipo. Agenda SyD = semestral. Agenda Regulatoria = anual.</p></div>
    <div class="head-actions"><a href="{{ route('admin.periodos.crear') }}" class="btn">Nuevo Periodo</a></div>
  </div>

  <div class="card"><div class="table-wrap"><table class="data-table"><thead><tr>
    <th>Tipo</th><th>Nombre</th><th>Inicio</th><th>Fin</th><th>Duración</th><th>Estatus</th><th class="table-action-cell">Acciones</th>
  </tr></thead><tbody>
    @forelse($periodos as $p)
      <tr>
        <td>
          @if($p->tipo === 'agenda_regulatoria')
            <span class="chip chip-purple u-text-xs">Regulatoria (anual)</span>
          @else
            <span class="chip chip-info u-text-xs">SyD (semestral)</span>
          @endif
        </td>
        <td><strong>{{ $p->nombre }}</strong>@if($p->descripcion)<br><small>{{ $p->descripcion }}</small>@endif</td>
        <td>{{ \Carbon\Carbon::parse($p->fecha_inicio)->format('d/m/Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($p->fecha_fin)->format('d/m/Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($p->fecha_inicio)->diffInDays($p->fecha_fin) }} días</td>
        <td><span class="badge {{ $p->estatus==='activo'?'success-b':($p->estatus==='proximo'?'warning-b':'') }}">{{ ucfirst($p->estatus) }}</span></td>
        <td class="table-action-cell"><div class="table-actions">
          @if($p->estatus === 'proximo')
            <form method="POST" action="{{ route('admin.periodos.activar', $p->id) }}" class="d-inline">
              @csrf <button type="submit" class="btn table-action-btn btn-outline">Activar</button>
            </form>
          @endif
          <a href="{{ route('admin.periodos.editar', $p->id) }}" class="btn table-action-btn btn-outline" title="Editar" style="padding:6px 10px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </a>
          @if($p->estatus === 'cerrado')
            <button type="button" class="btn table-action-btn btn-outline danger"
              onclick="confirmDelete('{{ route('admin.periodos.eliminar', $p->id) }}','¿Eliminar este periodo?','Esta acción no se puede deshacer.')">
              Eliminar
            </button>
          @endif
        </div></td>
      </tr>
    @empty
      <tr><td colspan="7" class="u-text-center cal-empty-state">No hay periodos registrados.</td></tr>
    @endforelse
  </tbody></table></div></div>
</div>
@endsection
