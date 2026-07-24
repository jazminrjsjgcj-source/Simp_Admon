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
    @php $verDependencia = auth()->user()->veVariasDependencias(); @endphp

    <form method="GET" action="{{ route('agenda-regulatoria.index') }}" class="filters" style="padding:0 16px 12px">
      <div class="field">
        <label>Buscar</label>
        <input name="q" value="{{ request('q') }}" placeholder="Nombre o folio"
          oninput="clearTimeout(window._st);window._st=setTimeout(()=>this.form.submit(),500)">
      </div>
      <div class="field">
        <label>Estatus</label>
        <select name="estatus" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach($estatuses as $e)
            <option value="{{ $e }}" {{ request('estatus')===$e?'selected':'' }}>@estatus($e)</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Determinación AIR</label>
        <select name="determinacion" onchange="this.form.submit()">
          <option value="">Todas</option>
          <option value="pendiente"    {{ request('determinacion')==='pendiente'   ?'selected':'' }}>Pendiente</option>
          <option value="requiere_air" {{ request('determinacion')==='requiere_air'?'selected':'' }}>Requiere AIR</option>
          <option value="exento"       {{ request('determinacion')==='exento'      ?'selected':'' }}>Exento</option>
        </select>
      </div>
      @if(request()->hasAny(['q', 'estatus', 'determinacion']))
        <div class="field" style="display:flex;align-items:flex-end">
          <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Limpiar</a>
        </div>
      @endif
    </form>

    <div class="table-wrap"><table class="data-table"><thead><tr>
      <th>Folio</th>
      <th>Propuesta</th>
      @if($verDependencia)<th>Dependencia</th>@endif
      <th>Fecha compromiso</th>
      <th>AIR</th>
      <th>Estatus</th>
      <th class="table-action-cell">Acciones</th>
    </tr></thead><tbody>
      @forelse($propuestas as $p)
        <tr>
          <td>@dato($p->folio)</td>
          <td><strong>{{ Str::limit($p->nombre, 60) }}</strong><br><small>{{ $p->created_at->format('d/m/Y') }}</small></td>
          @if($verDependencia)<td>@dato($p->dependencia->nombre)</td>@endif
          <td>{{ $p->fecha_tentativa ? \Carbon\Carbon::parse($p->fecha_tentativa)->format('d/m/Y') : '' }}</td>
          <td><x-badge-estatus :estatus="$p->determinacion_air" /></td>
          <td><x-badge-estatus :estatus="$p->estatus" /></td>
          <td class="table-action-cell">
            <div class="table-actions">
              <a href="{{ route('propuestas.show', $p) }}" class="btn table-action-btn">Ver</a>
              {{-- Eliminar: solo borradores propios de la dependencia del enlace
                   (la lógica vive en User::puedeEliminarPropuesta). --}}
              @if(auth()->user()->puedeEliminarPropuesta($p))
                <button type="button" class="btn table-action-btn danger btn-outline"
                  onclick="confirmDelete('{{ route('propuestas.destroy',$p) }}','¿Eliminar esta propuesta?','{{ addslashes(Str::limit($p->nombre ?? 'La propuesta', 60)) }} se eliminará de forma permanente.')">
                  Eliminar
                </button>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ $verDependencia ? 7 : 6 }}" class="u-text-center cal-empty-state">No hay propuestas regulatorias registradas.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $propuestas->links() }}</div>
  </div>
</div>
@endsection