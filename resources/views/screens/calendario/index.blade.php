@extends('layouts.app')
@section('title', 'Calendario')
@section('content')
@php
  $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  $mesPrev = $mes === 1 ? 12 : $mes - 1;
  $anioPrev = $mes === 1 ? $anio - 1 : $anio;
  $mesNext = $mes === 12 ? 1 : $mes + 1;
  $anioNext = $mes === 12 ? $anio + 1 : $anio;
@endphp
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Calendario de Agenda SyD</h2>
      <p class="nowrap">Compromisos de simplificación, digitalización y agenda regulatoria.</p>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="cal-kpis">
    <div class="cal-kpi cal-kpi-sim"><h3>{{ $kpis['sim'] }}</h3><p>Simplificación</p></div>
    <div class="cal-kpi cal-kpi-dig"><h3>{{ $kpis['dig'] }}</h3><p>Digitalización</p></div>
    <div class="cal-kpi cal-kpi-reg"><h3>{{ $kpis['reg'] }}</h3><p>Regulatoria</p></div>
    <div class="cal-kpi cal-kpi-cumplido"><h3>{{ $kpis['cumplidos'] }}</h3><p>Cumplidos</p></div>
  </div>

  {{-- Controles --}}
  <div class="cal-controls">
    <div class="cal-nav">
      <a href="?mes={{ $mesPrev }}&anio={{ $anioPrev }}&tipo={{ $filtro }}" class="btn btn-outline btn-sm">←</a>
      <strong>{{ $meses[$mes] }} {{ $anio }}</strong>
      <a href="?mes={{ $mesNext }}&anio={{ $anioNext }}&tipo={{ $filtro }}" class="btn btn-outline btn-sm">→</a>
    </div>
    <div class="cal-filtros">
      <a href="?mes={{ $mes }}&anio={{ $anio }}&tipo=todos" class="cal-filtro-chip {{ $filtro === 'todos' ? 'active' : '' }}">Todos</a>
      <a href="?mes={{ $mes }}&anio={{ $anio }}&tipo=simplificacion" class="cal-filtro-chip cal-chip-sim {{ $filtro === 'simplificacion' ? 'active' : '' }}">Simplificación</a>
      <a href="?mes={{ $mes }}&anio={{ $anio }}&tipo=digitalizacion" class="cal-filtro-chip cal-chip-dig {{ $filtro === 'digitalizacion' ? 'active' : '' }}">Digitalización</a>
      <a href="?mes={{ $mes }}&anio={{ $anio }}&tipo=regulatoria" class="cal-filtro-chip cal-chip-reg {{ $filtro === 'regulatoria' ? 'active' : '' }}">Regulatoria</a>
    </div>
    <div class="cal-vistas">
      <button class="cal-vista-btn {{ $vista === 'mes' ? 'active' : '' }}" onclick="setVista('mes')">Mes</button>
      <button class="cal-vista-btn {{ $vista === 'lista' ? 'active' : '' }}" onclick="setVista('lista')">Lista</button>
    </div>
  </div>

  {{-- Vista lista --}}
  <div id="vistaLista" class="card" style="{{ $vista === 'lista' ? '' : 'display:none' }}">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Título</th><th>Responsable</th><th>Avance</th><th>Estatus</th></tr></thead>
        <tbody>
          @forelse($eventos as $ev)
            <tr>
              <td>{{ \Carbon\Carbon::parse($ev->fecha)->format('d/m/Y') }}</td>
              <td>
                <span class="cal-event-chip cal-{{ substr($ev->tipo,0,3) }}">
                  {{ strtoupper(substr($ev->tipo,0,3)) }}
                </span>
              </td>
              <td><strong>{{ $ev->titulo }}</strong>@if($ev->accion)<br><small>{{ $ev->accion }}</small>@endif</td>
              <td>@dato($ev->responsable)</td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;min-width:80px">
                  <div style="flex:1;height:4px;background:#e5e7eb;border-radius:4px;overflow:hidden">
                    <div style="height:100%;width:{{ $ev->avance ?? 0 }}%;background:var(--accent);border-radius:4px"></div>
                  </div>
                  <small>{{ $ev->avance ?? 0 }}%</small>
                </div>
              </td>
              <td><span class="badge {{ $ev->estatus === 'cumplido' ? 'success-b' : ($ev->estatus === 'vencido' ? 'danger-b' : '') }}">{{ ucfirst($ev->estatus) }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="u-text-center cal-empty-state">No hay eventos en {{ $meses[$mes] }} {{ $anio }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Vista mes (grid) --}}
  <div id="vistaMes" style="{{ $vista === 'mes' ? '' : 'display:none' }}">
    @php
      $primerDia = \Carbon\Carbon::createFromDate($anio, $mes, 1);
      $diasEnMes = $primerDia->daysInMonth;
      $iniciaSemana = $primerDia->dayOfWeek; // 0=Dom
      $eventosPorDia = $eventos->groupBy(fn($e) => \Carbon\Carbon::parse($e->fecha)->day);
    @endphp
    <div class="card" style="overflow:visible">
      <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid var(--surface-high)">
        @foreach(['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'] as $dia)
          <div style="padding:8px;text-align:center;font-size:11px;font-weight:800;color:#667085;text-transform:uppercase">{{ $dia }}</div>
        @endforeach
      </div>
      <div style="display:grid;grid-template-columns:repeat(7,1fr)">
        @for($i = 0; $i < $iniciaSemana; $i++)
          <div style="min-height:80px;border-right:1px solid var(--surface-high);border-bottom:1px solid var(--surface-high)"></div>
        @endfor
        @for($d = 1; $d <= $diasEnMes; $d++)
          @php $col = ($iniciaSemana + $d - 1) % 7; $esHoy = now()->day === $d && now()->month === $mes && now()->year === $anio; @endphp
          <div style="min-height:80px;padding:6px;border-right:1px solid var(--surface-high);border-bottom:1px solid var(--surface-high);{{ $esHoy ? 'background:#fff7fb' : '' }}">
            <div style="font-size:12px;font-weight:{{ $esHoy ? '800' : '500' }};color:{{ $esHoy ? 'var(--primary)' : '#374151' }};margin-bottom:4px">{{ $d }}</div>
            @if(isset($eventosPorDia[$d]))
              @foreach($eventosPorDia[$d]->take(3) as $ev)
                <div class="cal-event-chip cal-{{ substr($ev->tipo,0,3) }}" style="display:block;margin-bottom:2px;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  {{ $ev->titulo }}
                </div>
              @endforeach
              @if($eventosPorDia[$d]->count() > 3)
                <small style="color:#667085;font-size:10px">+{{ $eventosPorDia[$d]->count() - 3 }} más</small>
              @endif
            @endif
          </div>
        @endfor
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
function setVista(v) {
  document.getElementById('vistaLista').style.display = v === 'lista' ? '' : 'none';
  document.getElementById('vistaMes').style.display   = v === 'mes'   ? '' : 'none';
  document.querySelectorAll('.cal-vista-btn').forEach(function(b) {
    b.classList.toggle('active', b.textContent.trim().toLowerCase() === (v === 'mes' ? 'mes' : 'lista'));
  });
  sessionStorage.setItem('calVista', v);
}
// Restore vista from session
var saved = sessionStorage.getItem('calVista');
if (saved) setVista(saved);
</script>
@endpush
