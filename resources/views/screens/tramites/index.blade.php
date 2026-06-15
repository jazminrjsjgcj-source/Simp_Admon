@extends('layouts.app')
@section('title', 'Trámites')
@section('content')
<div class="page-wide">
  <div class="screen-head">
    <div><h2 class="nowrap">Mis trámites</h2><p class="nowrap">Consulta los trámites completos de tu dependencia.</p></div>
    <div class="head-actions create-record-actions">
      @if(auth()->user()->rol === 'enlace')
        <a href="{{ route('tramites.create') }}" class="btn">Nuevo Trámite</a>
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
      <div class="field">
        <label>Dependencia</label>
        <select name="dependencia" onchange="this.form.submit()">
          <option value="">Todas</option>
          @foreach($dependencias as $d)
            <option value="{{ $d->id }}" {{ request('dependencia')==$d->id?'selected':'' }}>{{ $d->nombre }}</option>
          @endforeach
        </select>
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
        <label>Costo unitario (CBU)</label>
        <select name="costo_unitario" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="bajo"   {{ request('costo_unitario')==='bajo'  || request('costo')==='bajo'  ?'selected':'' }}>Bajo (&lt; $1,000)</option>
          <option value="medio"  {{ request('costo_unitario')==='medio' || request('costo')==='medio' ?'selected':'' }}>Medio ($1,000–$10,000)</option>
          <option value="alto"   {{ request('costo_unitario')==='alto'  || request('costo')==='alto'  ?'selected':'' }}>Alto (&gt; $10,000)</option>
        </select>
      </div>
      <div class="field">
        <label>Impacto (vs umbral)</label>
        <select name="impacto" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="bajo"     {{ request('impacto')==='bajo'    ?'selected':'' }}>Bajo (&lt; 50%)</option>
          <option value="medio"    {{ request('impacto')==='medio'   ?'selected':'' }}>Medio (50–99%)</option>
          <option value="alto"     {{ request('impacto')==='alto'    ?'selected':'' }}>Alto (100–149%)</option>
          <option value="critico"  {{ request('impacto')==='critico' ?'selected':'' }}>Crítico (≥ 150%)</option>
          <option value="no_determinado" {{ request('impacto')==='no_determinado'?'selected':'' }}>Sin umbral</option>
        </select>
      </div>
    </form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Homoclave</th><th>Nombre del trámite</th><th>Dependencia</th><th>Estatus</th><th>CBU</th><th>Impacto</th><th class="table-action-cell">Acciones</th></tr></thead><tbody>
      @forelse($tramites as $t)
        <tr>
          <td>{{ $t->homoclave ?? 'Sin folio' }}</td>
          <td><strong>{{ $t->nombre_oficial }}</strong><br><small>{{ $t->updated_at->format('d/m/Y') }}</small></td>
          <td>{{ $t->dependencia->nombre ?? '—' }}</td>
          <td><span class="badge {{ match($t->estatus) { 'completado','en_firma'=>'success-b', 'en_correccion'=>'warning-b', default=>'' } }}">@estatus($t->estatus)</span></td>
          <td>{{ $t->cbu_unitario ? '$'.number_format($t->cbu_unitario,2) : '—' }}</td>
          <td>
            @switch($t->impacto)
              @case('critico') <span class="chip chip-red">Crítico</span> @break
              @case('alto')    <span class="chip chip-amber">Alto</span> @break
              @case('medio')   <span class="chip chip-amber">Medio</span> @break
              @case('bajo')    <span class="chip chip-success">Bajo</span> @break
              @default        <span class="chip chip-gray">—</span>
            @endswitch
          </td>
          <td class="table-action-cell">
            <div class="table-actions">
              <a href="{{ route('tramites.show',$t) }}" class="btn table-action-btn btn-outline">Ver</a>
              @if(auth()->user()->puedeEditarTramite($t))
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
        <tr><td colspan="6" class="u-text-center cal-empty-state">No hay trámites que coincidan con los filtros.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $tramites->appends(request()->query())->links() }}</div>
  </div>
</div>
@endsection
