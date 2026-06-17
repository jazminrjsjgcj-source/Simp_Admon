@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<style>
  /* Barra compacta de totales del sistema */
  .sistema-totales { display: flex; gap: 12px; flex-wrap: wrap; background: white; border: 1px solid var(--surface-high); border-radius: var(--radius-lg); padding: 14px 20px; box-shadow: var(--shadow); }
  .sistema-total-item { display: flex; align-items: center; gap: 10px; padding: 0 16px 0 0; border-right: 1px solid var(--surface-high); }
  .sistema-total-item:last-child { border-right: none; padding-right: 0; }
  .sistema-total-item strong { font-size: 22px; font-weight: 800; color: var(--primary-container); }
  .sistema-total-item span { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; line-height: 1.2; }

  /* Acordeón de módulos */
  .panorama-modulo { border: 1px solid var(--surface-high); border-radius: var(--radius-lg); background: white; box-shadow: var(--shadow); overflow: hidden; }
  .panorama-modulo-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; cursor: pointer; user-select: none; }
  .panorama-modulo-head:hover { background: var(--surface-low); }
  .panorama-modulo-titulo { font-size: 13px; font-weight: 700; color: var(--text); margin: 0; text-transform: uppercase; letter-spacing: .04em; }
  .panorama-modulo-chevron { font-size: 12px; color: var(--muted); transition: transform .2s ease; }
  .panorama-modulo-chevron.abierto { transform: rotate(180deg); }
  .panorama-modulo-body { padding: 0 16px 16px; display: none; }
  .panorama-modulo-body.abierto { display: block; }
</style>
<div class="page-wide">

  {{-- Saludo --}}
  <div class="screen-head">
    <div>
      <h2>Hola, {{ ucfirst($rol) }}</h2>
      <p>@php
        echo [
          'enlace'  => 'Carga trámites, solicita regulaciones, registra acciones de agenda y atiende observaciones de tu dependencia.',
          'sujeto'  => 'Revisa, observa y firma los trámites de tu dependencia.',
          'revisora'=> 'Revisa los trámites enviados por los enlaces y registra observaciones.',
          'juridico'=> 'Registra y gestiona las normativas del sistema PUNTA.',
          'admin'   => 'Gestiona los usuarios, roles y accesos del sistema PUNTA.',
        ][$rol] ?? 'Bienvenido al sistema PUNTA.';
      @endphp</p>
    </div>
  </div>

  {{-- KPIs — el filtro de cada tarjeta viene de $kpiTipos (definido en el controlador) --}}
  @if($rol === 'admin' && !empty($panorama))
    {{-- Barra compacta con totales del sistema --}}
    <div class="sistema-totales">
      @foreach($sistemaTotales as $st)
        <a href="{{ route($st['ruta']) }}" class="sistema-total-item" style="text-decoration:none">
          <strong>{{ $st['value'] }}</strong>
          <span>{{ $st['label'] }}</span>
        </a>
      @endforeach
    </div>

    {{-- Módulos en acordeón: el primero abierto, los demás cerrados --}}
    @foreach($panorama as $fila)
      @php $esElPrimero = $loop->first; $idModulo = 'pan-mod-' . $fila['modulo']; @endphp
      <div class="panorama-modulo">
        <div class="panorama-modulo-head" onclick="togglePanorama('{{ $idModulo }}')">
          <p class="panorama-modulo-titulo">{{ $fila['etiqueta'] }}</p>
          <span class="panorama-modulo-chevron {{ $esElPrimero ? 'abierto' : '' }}" id="{{ $idModulo }}-chev">▼</span>
        </div>
        <div class="panorama-modulo-body {{ $esElPrimero ? 'abierto' : '' }}" id="{{ $idModulo }}">
          <div class="grid kpis" style="padding-top:4px">
            @foreach($fila['cifras'] as $c)
              <div class="card stat kpi-link" style="cursor:pointer" id="kpi-pan-{{ $fila['modulo'] }}-{{ $loop->index }}"
                onclick="dashFiltrar('', 'pan-{{ $fila['modulo'] }}-{{ $loop->index }}', '{{ $c['filtro'] }}')">
                <div class="stat-value"><h3>{{ $c['value'] }}</h3><p>{{ $c['label'] }}</p></div>
                <span class="btn btn-outline btn-sm">Filtrar</span>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach
  @else
  <div class="grid kpis">
    @foreach($kpis as $i => $kpi)
      @php
        $par    = $kpiTipos[$i] ?? [null, null];
        $tipo   = $par[0] ?? null;
        $filtro = $par[1] ?? null;
        $puedeFiltrar = ($tipo || $filtro);
      @endphp
      @if($puedeFiltrar)
        <div class="card stat kpi-link" style="cursor:pointer" id="kpi-{{ $i }}"
          onclick="dashFiltrar('{{ $tipo }}', {{ $i }}, '{{ $filtro }}')">
          <div class="stat-value">
            <h3>{{ $kpi['value'] }}</h3>
            <p>{{ $kpi['label'] }}</p>
          </div>
          <span class="btn btn-outline btn-sm">Filtrar</span>
        </div>
      @else
        <a href="{{ route($kpiRoutes[$i] ?? 'dashboard') }}" class="card stat kpi-link" id="kpi-{{ $i }}">
          <div class="stat-value">
            <h3>{{ $kpi['value'] }}</h3>
            <p>{{ $kpi['label'] }}</p>
          </div>
          <span class="btn btn-outline btn-sm">Ver</span>
        </a>
      @endif
    @endforeach
  </div>
  @endif

  {{-- Fase H.2: Panel de filtro inline --}}
  <div id="dashFilterPanel" style="display:none">
    <div class="card">
      <div class="panel-head">
        <div><h3 id="dashFilterTitle" class="nowrap">Resultados</h3><p id="dashFilterSub" class="nowrap">—</p></div>
        <button type="button" class="btn btn-outline btn-sm" onclick="dashLimpiar()">Ver todos</button>
      </div>
      <div id="dashFilterLoading" style="padding:24px;text-align:center;color:#6b7280;display:none">Cargando…</div>
      <div class="table-wrap" id="dashFilterTable">
        <table class="data-table">
          <thead>
            <tr>
              <th>Folio</th>
              <th>Nombre</th>
              <th>Estatus</th>
              <th>Última actualización</th>
              <th class="table-action-cell">Acción</th>
            </tr>
          </thead>
          <tbody id="dashFilterBody">
            <tr><td colspan="5" class="u-text-center cal-empty-state">Seleccione un KPI para filtrar.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Accesos rápidos --}}
  @if($rol === 'enlace')
    <div class="grid quick">
      <a href="{{ route('tramites.create') }}" class="card quick-card kpi-link">
        <strong>Nuevo Trámite</strong>
        <span>Registrar procedimiento</span>
      </a>
      <a href="{{ route('agenda.create') }}" class="card quick-card kpi-link">
        <strong>Registrar Acción</strong>
        <span>Simplificación y digitalización</span>
      </a>
      <a href="{{ route('propuestas.create') }}" class="card quick-card kpi-link">
        <strong>Nueva Propuesta</strong>
        <span>Agenda regulatoria</span>
      </a>
    </div>
  @endif

  {{-- Pendientes: Trámites --}}
  @if($pendientesTramites->count())
  <div class="card">
    <div class="panel-head">
      <div><h3 class="nowrap">Trámites pendientes</h3><p class="nowrap">Trámites que requieren atención.</p></div>
      <a href="{{ route('tramites.index') }}" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Nombre</th><th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesTramites as $t)
        <tr>
          <td>{{ $t->homoclave ?? 'Sin folio' }}</td>
          <td><strong>{{ $t->nombre_oficial }}</strong></td>
          <td><span class="badge {{ match($t->estatus){'en_observacion','en_correccion'=>'warning-b','en_firma'=>'info-b','completado'=>'success-b',default=>''} }}">@estatus($t->estatus)</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ route('tramites.show',$t) }}" class="btn table-action-btn">Atender</a></div></td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif

  {{-- Pendientes: Agenda SyD --}}
  @if($pendientesAgenda->count())
  <div class="card">
    <div class="panel-head">
      <div><h3 class="nowrap">Agenda SyD pendiente</h3><p class="nowrap">Acciones de simplificación y digitalización.</p></div>
      <a href="{{ route('agenda.index') }}" class="btn btn-outline btn-sm">Ver todas</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Descripción</th><th>Tipo</th><th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesAgenda as $a)
        <tr>
          <td>AGD-{{ str_pad($a->id,3,'0',STR_PAD_LEFT) }}</td>
          <td><strong>{{ Str::limit($a->descripcion,50) }}</strong></td>
          <td><span class="badge {{ $a->tipo==='simplificacion'?'accent-b':'info-b' }}">{{ ucfirst($a->tipo) }}</span></td>
          <td><span class="badge">@estatus($a->estatus)</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ route('agenda.show',$a) }}" class="btn table-action-btn">Atender</a></div></td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif

  {{-- Pendientes: Agenda Regulatoria --}}
  @if($pendientesPropu->count())
  <div class="card">
    <div class="panel-head">
      <div><h3 class="nowrap">Agenda Regulatoria pendiente</h3><p class="nowrap">Propuestas con acciones pendientes.</p></div>
      <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline btn-sm">Ver todas</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Propuesta</th><th>Tipo</th><th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesPropu as $p)
        <tr>
          <td>REG-{{ str_pad($p->id,3,'0',STR_PAD_LEFT) }}</td>
          <td><strong>{{ Str::limit($p->nombre,50) }}</strong></td>
          <td>{{ $p->tipo_regulacion ?? '—' }}</td>
          <td><span class="badge">@estatus($p->estatus ?? 'borrador')</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ route('propuestas.show',$p) }}" class="btn table-action-btn">Atender</a></div></td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif

  {{-- Pendientes: Dictámenes AIR (solo revisora/admin) --}}
  @if($pendientesAir->count())
  <div class="card">
    <div class="panel-head">
      <div><h3 class="nowrap">Dictámenes AIR pendientes</h3><p class="nowrap">Análisis de impacto esperando su dictamen.</p></div>
      <a href="{{ route('dictamenes-air.index') }}" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Propuesta</th><th>Dependencia</th><th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesAir as $air)
        <tr>
          <td><strong>{{ Str::limit($air->propuesta?->nombre ?? 'Sin propuesta', 50) }}</strong></td>
          <td>@dato($air->propuesta?->dependencia?->nombre)</td>
          <td><span class="badge info-b">Esperando dictamen</span></td>
          <td class="table-action-cell"><div class="table-actions">
            @if($air->propuesta)
              <a href="{{ route('air.formulario', $air->propuesta) }}" class="btn table-action-btn">Dictaminar</a>
            @endif
          </div></td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif

  {{-- Requiere tu firma (sujeto y enlace) --}}
  @if(isset($pendientesFirma) && $pendientesFirma->count())
  <div class="card" style="border-left: 4px solid var(--primary-container)">
    <div class="panel-head">
      <div>
        <h3 class="nowrap" style="color:var(--primary-container)">✍ Requiere tu firma</h3>
        <p class="nowrap">Estos registros están esperando que los firmes.</p>
      </div>
      <a href="{{ route('firmas.index') }}" class="btn btn-sm">Ver firmas</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Nombre</th><th>Tipo</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesFirma as $f)
        <tr>
          <td>{{ $f['folio'] }}</td>
          <td><strong>{{ $f['nombre'] }}</strong></td>
          <td><span class="badge info-b">{{ $f['tipo'] }}</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ $f['url_firma'] }}" class="btn table-action-btn">Firmar</a></div></td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif

  {{-- Estado vacío --}}
  @if(!$pendientesTramites->count() && !$pendientesAgenda->count() && !$pendientesPropu->count() && !$pendientesAir->count())
  <div class="card">
    <div class="panel-head"><div><h3 class="nowrap">Pendientes inmediatos</h3><p class="nowrap">Acciones que requieren atención.</p></div></div>
    <div class="table-wrap"><table class="data-table"><tbody>
      <tr><td colspan="5" class="u-text-center cal-empty-state">No hay pendientes. ¡Todo al día!</td></tr>
    </tbody></table></div>
  </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
(function () {
  var activoKpi = null;

  // Badges de estatus → clase
  var estadoBadge = {
    'borrador': '', 'en_observacion': 'warning-b', 'en_correccion': 'warning-b',
    'en_firma': 'info-b', 'completado': 'success-b', 'consulta': '', 'determinada': 'info-b',
    'dictaminada': 'success-b',
    'vigente': 'success-b', 'en_revision': 'warning-b', 'derogada': '',
    'pendiente': 'warning-b', 'en_atencion': 'warning-b', 'atendida': 'success-b',
    'reabierta': 'warning-b', 'validada': 'success-b'
  };

  window.dashFiltrar = function (tipo, kpiIdx, filtro) {
    // Limpiar activo anterior
    if (activoKpi !== null) {
      var prev = document.getElementById('kpi-' + activoKpi);
      if (prev) prev.classList.remove('kpi-active');
    }

    // Si hace clic al mismo KPI activo → limpiar filtro
    if (activoKpi === kpiIdx) {
      activoKpi = null;
      dashLimpiar();
      return;
    }

    activoKpi = kpiIdx;
    var kpiEl = document.getElementById('kpi-' + kpiIdx);
    if (kpiEl) kpiEl.classList.add('kpi-active');

    var panel  = document.getElementById('dashFilterPanel');
    var loading = document.getElementById('dashFilterLoading');
    var table   = document.getElementById('dashFilterTable');
    var body    = document.getElementById('dashFilterBody');
    var title   = document.getElementById('dashFilterTitle');
    var sub     = document.getElementById('dashFilterSub');

    var labels = { tramites: 'Trámites', agenda: 'Agenda SyD', propuestas: 'Propuestas regulatorias' };
    var labelsFiltro = { pendientes: 'Pendientes', por_revisar: 'Por revisar', por_aprobar: 'Por aprobar', completados: 'Completados', por_corregir: 'Por corregir', por_firmar: 'Por firmar', en_tramite: 'En trámite', cerrados: 'Completados', regulaciones_por_revisar: 'Regulaciones por revisar', regulaciones_vigentes: 'Regulaciones vigentes', mis_observaciones: 'Mis observaciones', en_revision: 'En revisión', en_correccion: 'Por corregir',
      tramites_total: 'Trámites — todos', tramites_proceso: 'Trámites — en proceso', tramites_cierre: 'Trámites — completados',
      agenda_total: 'Agenda — todos', agenda_proceso: 'Agenda — en proceso', agenda_cierre: 'Agenda — completados',
      propuestas_total: 'Propuestas — todas', propuestas_proceso: 'Propuestas — en proceso', propuestas_cierre: 'Propuestas — publicadas',
      regulaciones_total: 'Regulaciones — todas', regulaciones_proceso: 'Regulaciones — en revisión', regulaciones_cierre: 'Regulaciones — vigentes' };
    title.textContent = labelsFiltro[filtro] || labels[tipo] || 'Resultados';
    sub.textContent   = 'Los más recientes';

    panel.style.display = '';
    loading.style.display = '';
    table.style.display   = 'none';
    body.innerHTML = '';

    // Scroll suave al panel
    // Scroll suave al panel de resultados, mantiene los KPIs visibles arriba
    setTimeout(function() { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 100);

    fetch('/api/dashboard/filtrar?tipo=' + encodeURIComponent(tipo || '') + '&filtro=' + encodeURIComponent(filtro || ''), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      loading.style.display = 'none';
      table.style.display   = '';

      if (!data.rows || !data.rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="u-text-center cal-empty-state">Sin registros.</td></tr>';
        return;
      }

      body.innerHTML = data.rows.map(function (row) {
        var badge = estadoBadge[row.estatus] || '';
        return '<tr>'
          + '<td>' + (row.folio || '—') + '</td>'
          + '<td><strong>' + (row.nombre || '—') + '</strong></td>'
          + '<td><span class="badge ' + badge + '">' + (row.estatus || '—').replace('_',' ') + '</span></td>'
          + '<td>' + (row.fecha || '—') + '</td>'
          + '<td class="table-action-cell"><div class="table-actions"><a href="' + row.url + '" class="btn table-action-btn btn-sm">Ver</a></div></td>'
          + '</tr>';
      }).join('');
    })
    .catch(function () {
      loading.style.display = 'none';
      table.style.display = '';
      body.innerHTML = '<tr><td colspan="5" class="u-text-center cal-empty-state">Error al cargar datos.</td></tr>';
    });
  };

  window.dashLimpiar = function () {
    if (activoKpi !== null) {
      var prev = document.getElementById('kpi-' + activoKpi);
      if (prev) prev.classList.remove('kpi-active');
      activoKpi = null;
    }
    document.getElementById('dashFilterPanel').style.display = 'none';
  };

  // Autoridad Revisora: "Pendientes" (tarjeta 0) seleccionada por defecto al ingresar.
  @if($rol === 'revisora')
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof window.dashFiltrar === 'function') {
        window.dashFiltrar(null, 0, 'pendientes');
      }
    });
  @endif
})();

  window.togglePanorama = function(id) {
    var body = document.getElementById(id);
    var chev = document.getElementById(id + '-chev');
    if (!body) return;
    var abierto = body.classList.toggle('abierto');
    body.style.display = abierto ? 'block' : 'none';
    if (chev) chev.classList.toggle('abierto', abierto);
  };
</script>
@endpush
