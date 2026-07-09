@extends('layouts.app')
@section('title', 'Trámites y Servicios')
@section('content')
@php
  $verDependencia = auth()->user()->veVariasDependencias();
  $esRevisora     = auth()->user()->isRol('revisora') || auth()->user()->isRol('admin');
@endphp
<div class="page-wide">
  <div class="screen-head">
    <div>
        <h2 class="nowrap">{{ auth()->user()->rol === 'enlace' ? 'Mis trámites y servicios' : 'Catálogo de trámites y servicios' }}</h2>
        <p class="nowrap">{{ auth()->user()->rol === 'enlace' ? 'Trámites y servicios registrados por tu dependencia.' : 'Consulta los trámites y servicios del sistema.' }}</p>
      </div>
    <div class="head-actions create-record-actions">
      @if(auth()->user()->rol === 'enlace')
        <a href="{{ route('tramites.create') }}" class="btn">Nuevo registro</a>
      @endif
    </div>
  </div>
  <div class="card">
    <form method="GET" action="{{ route('tramites.index') }}" class="filters">
      <div class="field">
        <label>Buscar</label>
        <input name="q" value="{{ request('q') }}" placeholder="Nombre, homoclave o palabra clave"
          oninput="clearTimeout(window._st);window._st=setTimeout(()=>this.form.submit(),500)">
      </div>
      @if($verDependencia)
      <div class="field">
        <label>Dependencia</label>
        <select name="dependencia" onchange="this.form.submit()">
          <option value="">Todas</option>
          @foreach($dependencias as $d)
            <option value="{{ $d->id }}" {{ request('dependencia')==$d->id?'selected':'' }}>{{ $d->nombre }}</option>
          @endforeach
        </select>
      </div>
      @endif
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
        <label>Naturaleza</label>
        <select name="naturaleza" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="tramite"  {{ request('naturaleza')==='tramite' ?'selected':'' }}>Trámites</option>
          <option value="servicio" {{ request('naturaleza')==='servicio'?'selected':'' }}>Servicios</option>
        </select>
      </div>
      <div class="field">
        <label>Ordenar por</label>
        <select name="orden" onchange="this.form.submit()">
          <option value="reciente" {{ request('orden','reciente')==='reciente'     ?'selected':'' }}>Más recientes</option>
          <option value="antiguo"  {{ request('orden')==='antiguo'                 ?'selected':'' }}>Más antiguos</option>
          <option value="az"       {{ request('orden')==='az'                      ?'selected':'' }}>Nombre (A–Z)</option>
          <option value="tipo"     {{ request('orden')==='tipo'                    ?'selected':'' }}>Tipo de trámite</option>
          @if($verDependencia)
          <option value="dependencia" {{ request('orden')==='dependencia'          ?'selected':'' }}>Dependencia</option>
          @endif
        </select>
      </div>
      @if(request()->hasAny(['q', 'dependencia', 'estatus', 'naturaleza']))
        <div class="field" style="display:flex;align-items:flex-end">
          <a href="{{ route('tramites.index') }}" class="btn btn-outline">Limpiar</a>
        </div>
      @endif
    </form>
    <div class="table-wrap"><table class="data-table"><thead><tr>
      <th>Homoclave</th>
      <th>Nombre</th>
      <th>Naturaleza</th>
      @if($verDependencia)<th>Dependencia</th>@endif
      <th>Estatus</th>
      <th class="table-action-cell">Acciones</th>
    </tr></thead><tbody>
      @forelse($tramites as $t)
        <tr>
          <td>{{ $t->homoclave ?? 'Sin folio' }}</td>
          <td><strong>{{ $t->nombre_oficial }}</strong><br><small>{{ $t->tipoLegible() }} · {{ $t->created_at->format('d/m/Y') }}</small></td>
          <td><span class="badge {{ $t->esServicio() ? 'badge-info' : 'badge-default' }}">{{ $t->naturalezaLegible() }}</span></td>
          @if($verDependencia)<td>{{ $t->dependencia->nombre ?? '—' }}</td>@endif
          <td><x-badge-estatus :estatus="$t->estatus" /></td>
          <td class="table-action-cell">
            <div class="table-actions">
              <a href="{{ route('tramites.show',$t) }}" class="btn table-action-btn btn-outline">Ver</a>
              {{-- Revisora/admin: botón "Observar" en trámites en observación --}}
              @if($esRevisora && $t->estaEnObservacion())
                <a href="{{ route('tramites.show',$t) }}#observaciones" class="btn table-action-btn">Observar</a>
              @endif
              @if($t->estaEnObservacion() && $t->observaciones_por_atender_count > 0 && auth()->user()->puedeEditarTramite($t))
                <form method="POST" action="{{ route('tramites.actualizar.estatus',$t) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="accion" value="atender_observaciones">
                  <button type="submit" class="btn table-action-btn"
                    onclick="return confirmarAccion(this, '¿Cerrar el periodo de observaciones y pasar a corrección?')">Atender</button>
                </form>
              @endif
              @if(auth()->user()->puedeEditarTramite($t) && $t->puedeSerEditado())
                <a href="{{ route('tramites.edit',$t) }}" class="btn table-action-btn btn-outline">Editar</a>
              @endif
              @if(auth()->user()->puedeEliminarTramite($t) && $t->estatus === 'borrador')
                <button type="button" class="btn table-action-btn danger btn-outline"
                  onclick="confirmDelete('{{ route('tramites.destroy',$t) }}','¿Eliminar este trámite?','{{ addslashes($t->nombre_oficial) }} quedará inactivo.')">
                  Eliminar
                </button>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ $verDependencia ? 5 : 4 }}" class="u-text-center cal-empty-state">No hay trámites que coincidan con los filtros.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $tramites->appends(request()->query())->links() }}</div>
  </div>
</div>
@endsection
