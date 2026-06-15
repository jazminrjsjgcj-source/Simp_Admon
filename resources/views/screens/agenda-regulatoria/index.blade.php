@extends('layouts.app')
@section('title', 'Agenda Regulatoria')
@section('content')
@php $umbralData = $umbral ? json_decode($umbral->metadata, true) : ['status'=>'pendiente']; @endphp
<div class="page-body">
  <div class="screen-head">
    <div><h2 class="nowrap">Agenda Regulatoria</h2><p class="nowrap">Propuestas regulatorias, AIR y exenciones del periodo vigente.</p></div>
    <div class="head-actions create-record-actions">
      @if(auth()->user()->isAnyRol(['enlace','juridico']))
        <a href="{{ route('propuestas.create') }}" class="btn">Nueva Propuesta</a>
      @endif
    </div>
  </div>
  <div class="assist-box">
    <strong>Umbral de Proporcionalidad:</strong>
    @if(($umbralData['status']??'') === 'publicado' && ($umbral->valor??null))
      ${{ number_format((float)$umbral->valor,2) }} MXN — Ejercicio {{ $umbralData['ejercicio']??now()->year }}
    @else
      Pendiente de publicación por ATDT (metodología mayo 2026).
    @endif
  </div>
  <div class="card">
    <div class="panel-head"><div><h3>Propuestas Regulatorias</h3><p>Proyectos de regulación sometidos a consideración.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Propuesta</th><th>Dependencia</th><th>Costo burocr.</th><th>Determinación</th><th>Estatus</th><th class="table-action-cell">Acciones</th></tr></thead><tbody>
      @forelse($propuestas as $p)
        <tr>
          <td>@dato($p->folio)</td>
          <td><strong>{{ Str::limit($p->nombre,50) }}</strong></td>
          <td>@dato($p->dependencia->nombre)</td>
          <td>{{ $p->costo_burocratico ? '$'.number_format($p->costo_burocratico,2) : '—' }}</td>
          <td><span class="badge">{{ ucfirst(str_replace('_',' ',$p->determinacion_air)) }}</span></td>
          <td><span class="badge">{{ ucfirst($p->estatus) }}</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ route('propuestas.show', $p) }}" class="btn table-action-btn">Ver</a></div></td>
        </tr>
      @empty
        <tr><td colspan="7" class="u-text-center cal-empty-state">No hay propuestas regulatorias registradas.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</div>
@endsection
