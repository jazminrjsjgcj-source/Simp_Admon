@extends('layouts.app')
@section('title', 'Agenda SyD')
@section('content')
<div class="page-body">
  <div class="screen-head">
    <div><h2 class="nowrap">Agenda de Simplificación y Digitalización</h2><p class="nowrap">Acciones, metas, indicadores, evidencias, firmas y acuses.</p></div>
    <div class="head-actions">
      @if(auth()->user()->rol === 'enlace')
        <a href="{{ route('agenda.create') }}" class="btn">Nueva Acción</a>
      @endif
    </div>
  </div>
  <div class="card">
    <div class="filters">
      <div class="field"><label>Buscar</label><input placeholder="Trámite, folio o acción"></div>
      <div class="field"><label>Tipo</label><select><option value="">Todos</option><option>Simplificación</option><option>Digitalización</option></select></div>
      <div class="field"><label>Estatus</label><select><option value="">Todos</option><option>Completado</option><option>Pendiente</option><option>Observado</option></select></div>
      <div class="field"><label>Responsable</label><select><option value="">Todos</option></select></div>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Trámite</th><th>Tipo</th><th>Fecha compromiso</th><th>Estatus</th><th class="table-action-cell">Acciones</th></tr></thead><tbody>
      @forelse($acciones as $a)
        <tr>
          <td>AGD-{{ str_pad($a->id,3,'0',STR_PAD_LEFT) }}</td>
          <td><strong>{{ Str::limit($a->descripcion,55) }}</strong></td>
          <td><span class="badge {{ $a->tipo==='simplificacion'?'accent-b':'info-b' }}">{{ ucfirst($a->tipo) }}</span></td>
          <td>{{ $a->fecha_compromiso ? \Carbon\Carbon::parse($a->fecha_compromiso)->format('d/m/Y') : '—' }}</td>
          <td><span class="badge">@estatus($a->estatus)</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ route('agenda.show',$a) }}" class="btn table-action-btn btn-outline">Ver</a></div></td>
        </tr>
      @empty
        <tr><td colspan="6" class="u-text-center cal-empty-state">No hay acciones de agenda registradas.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $acciones->links() }}</div>
  </div>
</div>
@endsection
