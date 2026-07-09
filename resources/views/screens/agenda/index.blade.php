@extends('layouts.app')
@section('title', 'Agenda SyD')
@section('content')
<div class="page-body" id="agenda">
  <div class="screen-head">
    <div><h2 class="nowrap">Agenda de Simplificación y Digitalización</h2><p class="nowrap">Acciones, metas, indicadores, evidencias, firmas y acuses.</p></div>
    <div class="head-actions">
      @if(in_array(auth()->user()->rol, ['revisora','admin']))
        <a href="{{ route('agenda.exportar.simp') }}" class="btn" title="Descargar acciones de simplificación (Art. 23 LNETB)">
          Excel SIMP
        </a>
        <a href="{{ route('agenda.exportar.dig') }}" class="btn" title="Descargar acciones de digitalización (Art. 24 LNETB)">
          Excel DIG
        </a>
      @endif
      @if(auth()->user()->rol === 'enlace')
        <a href="{{ route('agenda.create') }}" class="btn">Nueva Acción</a>
      @endif
    </div>
  </div>
  <div class="card">
    <form method="GET" action="{{ route('agenda.index') }}" class="filters">
      <div class="field">
        <label>Buscar</label>
        <input name="buscar" value="{{ request('buscar') }}" placeholder="Trámite, servicio, folio o acción"
          oninput="clearTimeout(window._st);window._st=setTimeout(()=>this.form.submit(),500)">
      </div>
      <div class="field">
        <label>Tipo</label>
        <select name="tipo" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="simplificacion" {{ request('tipo') === 'simplificacion' ? 'selected' : '' }}>Simplificación</option>
          <option value="digitalizacion" {{ request('tipo') === 'digitalizacion' ? 'selected' : '' }}>Digitalización</option>
          <option value="ambas" {{ request('tipo') === 'ambas' ? 'selected' : '' }}>Ambas</option>
        </select>
      </div>
      <div class="field">
        <label>Estatus</label>
        <select name="estatus" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach(\App\Models\AccionAgenda::ESTATUS_TODOS as $est)
            <option value="{{ $est }}" {{ request('estatus') === $est ? 'selected' : '' }}>@estatus($est)</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Unidad administrativa</label>
        <select name="unidad" onchange="this.form.submit()">
          <option value="">Todas</option>
          @foreach($unidades as $u)
            <option value="{{ $u->id }}" {{ request('unidad')==$u->id?'selected':'' }}>{{ $u->nombre }}</option>
          @endforeach
        </select>
      </div>
      @if(request()->hasAny(['buscar', 'tipo', 'estatus', 'unidad']))
        <div class="field" style="display:flex;align-items:flex-end">
          <a href="{{ route('agenda.index') }}" class="btn btn-outline">Limpiar</a>
        </div>
      @endif
    </form>
    @php $verDependencia = auth()->user()->veVariasDependencias(); @endphp
    <div class="table-wrap"><table class="data-table"><thead><tr>
      <th>Folio</th>
      <th>Descripción</th>
      @if($verDependencia)<th>Dependencia</th>@endif
      <th>Estatus</th>
      <th class="table-action-cell">Acciones</th>
    </tr></thead><tbody>
      @forelse($acciones as $a)
        <tr>
          <td>
            @if($a->folio)
              {{ $a->folio }}
            @else
              <span class="text-muted-sm">Borrador (sin folio)</span>
            @endif
          </td>
          <td><strong>{{ Str::limit($a->descripcion, 60) }}</strong><br><small>{{ $a->created_at->format('d/m/Y') }}</small></td>
          @if($verDependencia)<td>@dato($a->dependencia?->nombre)</td>@endif
          <td><x-badge-estatus :estatus="$a->estatus" /></td>
          <td class="table-action-cell">
            <div class="table-actions">
              <a href="{{ route('agenda.show',$a) }}" class="btn table-action-btn btn-outline">Ver</a>
              @if(auth()->user()->puedeEliminarAgenda($a))
                <button type="button" class="btn table-action-btn danger btn-outline"
                  onclick="confirmDelete('{{ route('agenda.destroy',$a) }}','¿Eliminar esta acción?','{{ addslashes(Str::limit($a->descripcion, 60)) }} se eliminará de forma permanente.')">
                  Eliminar
                </button>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ $verDependencia ? 5 : 4 }}" class="u-text-center cal-empty-state">No hay acciones de agenda registradas.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $acciones->links() }}</div>
  </div>
</div>
@endsection