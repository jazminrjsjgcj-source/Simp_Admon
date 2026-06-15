@extends('layouts.app')
@section('title', 'Bitácora')
@section('content')
<div class="page-wide">
  <div class="screen-head">
    <div><h2 class="nowrap">Bitácora del sistema</h2><p class="nowrap">Registro de todas las acciones realizadas.</p></div>

  </div>
  <div class="card">
    <form method="GET" action="{{ route('admin.bitacora') }}" class="filters">
      <div class="field"><label>Módulo</label>
        <select name="modulo" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach($modulos as $m)
            <option value="{{ $m }}" {{ request('modulo')===$m?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="field"><label>Tipo</label>
        <select name="tipo" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach($tipos as $t)
            <option value="{{ $t }}" {{ request('tipo')===$t?'selected':'' }}>{{ ucfirst($t) }}</option>
          @endforeach
        </select>
      </div>
    </form>
    <div class="table-wrap"><table class="data-table"><thead>
      <tr><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Tipo</th><th>Acción</th><th>Detalle del cambio</th><th>IP</th></tr>
    </thead><tbody>
      @forelse($movimientos as $m)
        <tr>
          <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</td>
          <td>{{ $m->usuario_nombre ?? 'Sistema' }}</td>
          <td><span class="badge">{{ ucfirst(str_replace('_',' ',$m->modulo ?? '—')) }}</span></td>
          <td><span class="badge {{ match($m->tipo??''){'created'=>'success-b','deleted'=>'danger-b','updated'=>'',default=>''} }}">{{ ucfirst($m->tipo ?? '—') }}</span></td>
          <td>{{ $m->accion ?? '—' }}</td>
          <td style="font-size:11px;color:#667085;max-width:320px">
            @if($m->detalle)
              @foreach(explode(' | ', $m->detalle) as $cambio)
                <div style="padding:2px 0;border-bottom:1px solid var(--surface-high)">{{ $cambio }}</div>
              @endforeach
            @else
              <span style="color:#ccc">—</span>
            @endif
          </td>
          <td style="font-size:11px;color:#667085">{{ $m->ip_address ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="u-text-center cal-empty-state">No hay movimientos registrados.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="u-pad-card-sm">{{ $movimientos->links() }}</div>
  </div>
</div>
@endsection
