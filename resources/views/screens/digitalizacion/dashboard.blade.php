@extends('layouts.app')
@section('title', 'Dashboard de Digitalización')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2>Dashboard de Digitalización</h2>
      <p>Panorama del avance de digitalización de trámites y servicios.</p>
    </div>
    <a href="{{ route('digitalizacion.index') }}" class="btn btn-outline" style="font-size:12px">Ver biblioteca completa</a>
  </div>

  {{-- MÉTRICAS PRINCIPALES --}}
  <div class="dig-metricas">
    <div class="dig-metrica">
      <strong>{{ $metricas['total'] }}</strong>
      <span>Trámites totales</span>
    </div>
    <div class="dig-metrica dig-metrica-accent">
      <strong>{{ $metricas['digitalizados'] }}</strong>
      <span>Digitalizados</span>
    </div>
    <div class="dig-metrica">
      <strong>{{ $metricas['en_digitalizacion'] }}</strong>
      <span>En digitalización</span>
    </div>
    <div class="dig-metrica">
      <strong>{{ $metricas['firmadas'] }}</strong>
      <span>Reingenierías firmadas</span>
    </div>
    <div class="dig-metrica {{ $metricas['con_cambios'] > 0 ? 'dig-metrica-alerta' : '' }}">
      <strong>{{ $metricas['con_cambios'] }}</strong>
      <span>Con cambios pendientes</span>
    </div>
  </div>

  {{-- PROGRESO VISUAL --}}
  @php
    $total = max($metricas['total'], 1);
    $pctDigitalizado = round($metricas['digitalizados'] / $total * 100);
    $pctEnProceso = round($metricas['en_digitalizacion'] / $total * 100);
    $pctFirmadas = round($metricas['firmadas'] / $total * 100);
  @endphp
  <div class="dig-progreso-card">
    <h3>Avance general</h3>
    <div class="dig-progreso-barra">
      <div class="dig-progreso-fill dig-fill-completado" style="width:{{ $pctDigitalizado }}%"></div>
      <div class="dig-progreso-fill dig-fill-proceso" style="width:{{ $pctEnProceso }}%"></div>
    </div>
    <div class="dig-progreso-leyenda">
      <span><i class="dig-dot dig-dot-completado"></i> Digitalizados {{ $pctDigitalizado }}%</span>
      <span><i class="dig-dot dig-dot-proceso"></i> En proceso {{ $pctEnProceso }}%</span>
      <span><i class="dig-dot dig-dot-pendiente"></i> Pendientes {{ 100 - $pctDigitalizado - $pctEnProceso }}%</span>
    </div>
  </div>

  <div class="dig-grid-2">
    {{-- ORIGEN DE DIGITALIZACIÓN --}}
    <div class="dig-panel">
      <h3>Origen</h3>
      <div class="dig-origen-stats">
        <div class="dig-origen-item">
          <div class="dig-origen-num">{{ $metricas['desde_agenda'] }}</div>
          <div class="dig-origen-label">Desde Agenda</div>
          <div class="dig-origen-bar" style="--pct:{{ $total > 0 ? round($metricas['desde_agenda']/$total*100) : 0 }}%"></div>
        </div>
        <div class="dig-origen-item">
          <div class="dig-origen-num">{{ $metricas['directas'] }}</div>
          <div class="dig-origen-label">Reingeniería directa</div>
          <div class="dig-origen-bar dig-origen-bar-amber" style="--pct:{{ $total > 0 ? round($metricas['directas']/$total*100) : 0 }}%"></div>
        </div>
        <div class="dig-origen-item">
          <div class="dig-origen-num">{{ $metricas['total'] - $metricas['desde_agenda'] - $metricas['directas'] }}</div>
          <div class="dig-origen-label">Sin asignar</div>
          <div class="dig-origen-bar dig-origen-bar-gray" style="--pct:{{ $total > 0 ? round(($metricas['total'] - $metricas['desde_agenda'] - $metricas['directas'])/$total*100) : 0 }}%"></div>
        </div>
      </div>
    </div>

    {{-- EMBUDO DE ESTADOS --}}
    <div class="dig-panel">
      <h3>Embudo</h3>
      <div class="dig-embudo">
        <div class="dig-embudo-row">
          <span class="dig-embudo-label">Sin flujo</span>
          <div class="dig-embudo-bar" style="--pct:{{ round($metricas['sin_flujo']/$total*100) }}%"></div>
          <span class="dig-embudo-val">{{ $metricas['sin_flujo'] }}</span>
        </div>
        <div class="dig-embudo-row">
          <span class="dig-embudo-label">Flujo aprobado</span>
          <div class="dig-embudo-bar dig-bar-blue" style="--pct:{{ round($metricas['flujo_aprobado']/$total*100) }}%"></div>
          <span class="dig-embudo-val">{{ $metricas['flujo_aprobado'] }}</span>
        </div>
        <div class="dig-embudo-row">
          <span class="dig-embudo-label">Con reingeniería</span>
          <div class="dig-embudo-bar dig-bar-amber" style="--pct:{{ round($metricas['con_reingenieria']/$total*100) }}%"></div>
          <span class="dig-embudo-val">{{ $metricas['con_reingenieria'] }}</span>
        </div>
        <div class="dig-embudo-row">
          <span class="dig-embudo-label">Firmadas</span>
          <div class="dig-embudo-bar dig-bar-teal" style="--pct:{{ round($metricas['firmadas']/$total*100) }}%"></div>
          <span class="dig-embudo-val">{{ $metricas['firmadas'] }}</span>
        </div>
        <div class="dig-embudo-row">
          <span class="dig-embudo-label">En digitalización</span>
          <div class="dig-embudo-bar dig-bar-purple" style="--pct:{{ round($metricas['en_digitalizacion']/$total*100) }}%"></div>
          <span class="dig-embudo-val">{{ $metricas['en_digitalizacion'] }}</span>
        </div>
        <div class="dig-embudo-row">
          <span class="dig-embudo-label">Digitalizados</span>
          <div class="dig-embudo-bar dig-bar-green" style="--pct:{{ round($metricas['digitalizados']/$total*100) }}%"></div>
          <span class="dig-embudo-val">{{ $metricas['digitalizados'] }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- ALERTAS DE CAMBIO --}}
  @if($conCambios->isNotEmpty())
    <div class="dig-panel dig-panel-alerta">
      <h3><i class="ti ti-alert-triangle" style="color:#f59e0b"></i> Trámites con cambios post-firma ({{ $conCambios->count() }})</h3>
      <div class="dig-alertas-lista">
        @foreach($conCambios as $t)
          <a href="{{ route('digitalizacion.show', [$t, 'tab' => 'reingenieria']) }}" class="dig-alerta-item">
            <strong>{{ $t->nombre_oficial }}</strong>
            <span>{{ $t->dependencia->nombre ?? '' }}</span>
          </a>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ACTIVIDAD RECIENTE --}}
  @if($recientes->isNotEmpty())
    <div class="dig-panel">
      <h3>Actividad reciente</h3>
      <div class="dig-recientes">
        @foreach($recientes as $t)
          <a href="{{ route('digitalizacion.show', $t) }}" class="dig-reciente-item">
            <div>
              <strong>{{ $t->nombre_oficial }}</strong>
              <span>{{ $t->dependencia->nombre ?? '' }} · {{ $t->updated_at->diffForHumans() }}</span>
            </div>
            <span class="chip chip-dig-{{ $t->digitalizacion_estado }}">{{ $t->digitalizacionEstadoLegible() }}</span>
          </a>
        @endforeach
      </div>
    </div>
  @endif

</div>

<style>
  /* Métricas principales */
  .dig-metricas { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
  .dig-metrica {
    flex: 1;
    min-width: 120px;
    padding: 16px;
    background: var(--surface);
    border: 1px solid var(--surface-high);
    border-radius: var(--radius-lg);
    text-align: center;
  }
  .dig-metrica strong { display: block; font-size: 28px; font-weight: 800; color: var(--text); }
  .dig-metrica span { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
  .dig-metrica-accent { border-color: #065f46; background: #ecfdf5; }
  .dig-metrica-accent strong { color: #065f46; }
  .dig-metrica-alerta { border-color: #f59e0b; background: #fffbeb; }
  .dig-metrica-alerta strong { color: #92400e; }

  /* Progreso */
  .dig-progreso-card { background: var(--surface); border: 1px solid var(--surface-high); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px; }
  .dig-progreso-card h3 { margin: 0 0 12px; font-size: 14px; color: var(--text); }
  .dig-progreso-barra { height: 20px; background: var(--surface-low); border-radius: var(--radius-pill); overflow: hidden; display: flex; }
  .dig-progreso-fill { height: 100%; transition: width .5s ease; }
  .dig-fill-completado { background: #065f46; }
  .dig-fill-proceso { background: #f59e0b; }
  .dig-progreso-leyenda { display: flex; gap: 20px; margin-top: 10px; font-size: 12px; color: var(--muted); }
  .dig-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
  .dig-dot-completado { background: #065f46; }
  .dig-dot-proceso { background: #f59e0b; }
  .dig-dot-pendiente { background: var(--surface-high); }

  /* Grid 2 columnas */
  .dig-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }

  /* Panel genérico */
  .dig-panel { background: var(--surface); border: 1px solid var(--surface-high); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 16px; }
  .dig-panel h3 { margin: 0 0 14px; font-size: 14px; color: var(--text); display: flex; align-items: center; gap: 6px; }
  .dig-panel-alerta { border-color: #f59e0b; background: #fffbeb; }

  /* Origen */
  .dig-origen-stats { display: grid; gap: 12px; }
  .dig-origen-item { display: grid; grid-template-columns: 40px 1fr; gap: 4px 10px; align-items: center; }
  .dig-origen-num { font-size: 20px; font-weight: 800; color: var(--primary); text-align: center; }
  .dig-origen-label { font-size: 12px; color: var(--muted); }
  .dig-origen-bar { grid-column: 1/-1; height: 6px; background: var(--surface-low); border-radius: var(--radius-pill); position: relative; overflow: hidden; }
  .dig-origen-bar::after { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: var(--pct); background: var(--primary); border-radius: var(--radius-pill); transition: width .5s; }
  .dig-origen-bar-amber::after { background: #f59e0b; }
  .dig-origen-bar-gray::after { background: var(--surface-high); }

  /* Embudo */
  .dig-embudo { display: grid; gap: 8px; }
  .dig-embudo-row { display: grid; grid-template-columns: 120px 1fr 30px; gap: 8px; align-items: center; }
  .dig-embudo-label { font-size: 11px; color: var(--muted); text-align: right; }
  .dig-embudo-bar { height: 18px; background: var(--surface-low); border-radius: var(--radius-pill); position: relative; overflow: hidden; }
  .dig-embudo-bar::after { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: var(--pct); background: var(--surface-high); border-radius: var(--radius-pill); transition: width .5s; }
  .dig-bar-blue::after { background: #3b82f6; }
  .dig-bar-amber::after { background: #f59e0b; }
  .dig-bar-teal::after { background: #14b8a6; }
  .dig-bar-purple::after { background: #8b5cf6; }
  .dig-bar-green::after { background: #065f46; }
  .dig-embudo-val { font-size: 12px; font-weight: 700; color: var(--text); }

  /* Alertas */
  .dig-alertas-lista { display: grid; gap: 6px; }
  .dig-alerta-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: white; border: 1px solid #fcd34d; border-radius: var(--radius); text-decoration: none; color: var(--text); transition: border-color .15s; }
  .dig-alerta-item:hover { border-color: #f59e0b; }
  .dig-alerta-item strong { font-size: 13px; color: #92400e; }
  .dig-alerta-item span { font-size: 11px; color: var(--muted); }

  /* Recientes */
  .dig-recientes { display: grid; gap: 6px; }
  .dig-reciente-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border: 1px solid var(--surface-high); border-radius: var(--radius); text-decoration: none; color: var(--text); transition: border-color .15s; }
  .dig-reciente-item:hover { border-color: var(--primary); }
  .dig-reciente-item strong { display: block; font-size: 13px; color: var(--text); }
  .dig-reciente-item span { font-size: 11px; color: var(--muted); }

  /* Chips reutilizados */
  .chip-dig-no_iniciada { background: var(--surface-low); color: var(--muted); }
  .chip-dig-lista_para_digitalizacion,
  .chip-dig-en_digitalizacion { background: #fef3c7; color: #92400e; }
  .chip-dig-digitalizado { background: #d1fae5; color: #065f46; }
  .chip-dig-requiere_revision_por_cambio { background: #fee2e2; color: #991b1b; }

  @media (max-width: 768px) {
    .dig-metricas { flex-direction: column; }
    .dig-grid-2 { grid-template-columns: 1fr; }
    .dig-embudo-row { grid-template-columns: 80px 1fr 24px; }
  }
</style>
@endsection
