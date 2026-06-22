@extends('layouts.app')
@section('title', 'Agenda SyD')
@section('content')
<div class="page-body">
  <div class="screen-head">
    <div><h2 class="nowrap">Agenda de Simplificación y Digitalización</h2><p class="nowrap">Acciones, metas, indicadores, evidencias, firmas y acuses.</p></div>
    <div class="head-actions">
      @if(in_array(auth()->user()->rol, ['revisora','admin']))
        <a href="{{ route('agenda.exportar.simp') }}" class="btn btn-outline" title="Descargar acciones de simplificación (Art. 23 LNETB)">
          ⬇ Excel SIMP
        </a>
        <a href="{{ route('agenda.exportar.dig') }}" class="btn btn-outline" title="Descargar acciones de digitalización (Art. 24 LNETB)">
          ⬇ Excel DIG
        </a>
      @endif
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
    <div class="table-wrap"><table class="data-table"><thead><tr>
      <th>Folio</th>
      <th>Descripción</th>
      <th>Tipo</th>
      <th>Dirección</th>
      <th>Unidad administrativa</th>
      <th>Fecha compromiso</th>
      <th>Estatus</th>
      <th class="table-action-cell">Acciones</th>
    </tr></thead><tbody>
      @forelse($acciones as $a)
        <tr>
          {{-- Folio real si ya fue enviado a revisión; ID padded si aún es borrador --}}
          <td>{{ $a->folio ?? 'AGD-' . str_pad($a->id, 3, '0', STR_PAD_LEFT) }}</td>
          <td><strong>{{ Str::limit($a->descripcion, 50) }}</strong></td>
          <td><span class="badge">{{ ucfirst($a->tipo) }}</span></td>
          <td>@dato($a->dependencia?->nombre)</td>
          <td>@dato($a->unidad?->nombre)</td>
          <td>{{ $a->fecha_compromiso ? \Carbon\Carbon::parse($a->fecha_compromiso)->format('d/m/Y') : '—' }}</td>
          <td><x-badge-estatus :estatus="$a->estatus" /></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ route('agenda.show',$a) }}" class="btn table-action-btn btn-outline">Ver</a></div></td>
        </tr>
      @empty
        <tr><td colspan="8" class="u-text-center cal-empty-state">No hay acciones de agenda registradas.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $acciones->links() }}</div>
  </div>
</div>
@endsection
