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
<div class="page-wide dash-centrado">

  {{-- Saludo + buscador.
       La caja de búsqueda vive AQUÍ: el dashboard es el punto de entrada, y
       /buscar quedó como la pantalla de resultados. Al enviar, navega a /buscar
       con la consulta, así que no duplica lógica: es el mismo formulario GET.
       Reutiliza las clases de 15-buscador.css (se carga en todas las páginas)
       para que la caja se vea igual que en el buscador. --}}
  @php
    // now() usa la zona horaria de la app (config/app.php).
    $hora = now()->hour;
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $primerNombre = explode(' ', trim(auth()->user()->name))[0] ?? '';
    $fraseRol = [
      'enlace'  => 'Carga trámites, solicita regulaciones, registra acciones de agenda y atiende observaciones de tu dependencia.',
      'sujeto'  => 'Revisa, observa y firma los trámites de tu dependencia.',
      'revisora'=> 'Revisa los trámites enviados por los enlaces y registra observaciones.',
      'juridico'=> 'Registra y gestiona las normativas del sistema PUNTA.',
      'admin'   => 'Gestiona los usuarios, roles y accesos del sistema PUNTA.',
    ][$rol] ?? 'Bienvenido al sistema PUNTA.';
  @endphp

  <div class="dash-bienvenida">
    {{-- El titular alterna entre el saludo y la pregunta. Las dos frases están en
         el marcado y se turnan con una animación de CSS: así no hace falta
         JavaScript y quien use lector de pantalla escucha ambas. --}}
    <h2 class="dash-saludo">
      <span>{{ $saludo }}{{ $primerNombre !== '' ? ', ' . $primerNombre : '' }}</span>
      <span>¿En qué te puedo ayudar?</span>
    </h2>
    <p class="dash-saludo-sub">{{ $fraseRol }}</p>

    <form method="GET" action="{{ route('buscar') }}" class="dash-buscador" autocomplete="off">
      <div class="buscar-input-wrap">
        <i class="ti ti-search buscar-icono"></i>
        <input type="search" name="q" class="buscar-input"
          placeholder="Ej. ¿quién emite licencias?, requisitos construcción, plazo uso de suelo...">
      </div>
      <button type="submit" class="btn" style="flex-shrink:0">Buscar</button>
    </form>
  </div>

  {{-- Actividad general: panel lateral tipo pestaña de folder.
       Vive pegado al borde derecho de la ventana y SE SUPERPONE al contenido, así
       que no descoloca nada del dashboard. Cerrado deja ver solo la pestaña; al
       pulsarla se despliega la lista en vertical. La elección se recuerda.
       Solo existe en esta vista, así que solo aparece en el dashboard.
       Lo ven enlace, sujeto y jurídico. --}}
  @if(in_array($rol, ['enlace','sujeto','juridico']) && isset($actividadGeneral) && $actividadGeneral->count())
    @php
      // Iconos SVG por tipo de módulo (trazos simples, coherentes con el sistema).
      $iconosSvg = [
        'tramite'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>',
        'agenda'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'propuesta' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'regulacion'=> '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'registro'  => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
      ];
    @endphp
    <aside class="panel-lateral panel-lateral--actividad" id="panelActividad" aria-label="Actividad reciente">
      {{-- Pestaña tipo lengüeta de folder: sobresale hacia la izquierda y es lo
           único visible cuando el panel está cerrado. --}}
      <button type="button" class="panel-pestana" onclick="togglePanelLateral('panelActividad')"
        aria-controls="panelActividad" title="Mostrar u ocultar la actividad reciente">
        Actividad reciente
      </button>

      <div class="panel-lateral-cuerpo">
        <div class="panel-lateral-head">
          <h3>Actividad reciente</h3>
          <button type="button" class="panel-cerrar" onclick="togglePanelLateral('panelActividad')" aria-label="Cerrar">&times;</button>
        </div>
        <div class="actividad-lista">
          {{-- Sin duplicar la lista: eso hacía falta solo para el bucle horizontal. --}}
          @foreach($actividadGeneral as $evento)
          <div class="actividad-tarjeta">
            <div class="actividad-tarjeta-head">
              <span class="actividad-icono">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconosSvg[$evento->icono] ?? $iconosSvg['registro'] !!}</svg>
              </span>
              <span class="actividad-modulo">{{ $evento->modulo_etiqueta }}</span>
              <span class="actividad-badge actividad-badge-{{ $evento->evento }}">{{ $evento->evento === 'creado' ? 'Nuevo' : 'Completado' }}</span>
            </div>
            <p class="actividad-titulo"><strong>{{ $evento->prefijo }}</strong> {{ $evento->nombre }}</p>
            <div class="actividad-meta">
              @if($evento->dependencia)<span>{{ $evento->dependencia }}</span><span>·</span>@endif
              <span>{{ $evento->fecha_relativa }}</span>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </aside>


  @endif

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

  {{--
    Bug #B8: todo lo que sigue (botones de acción, pendientes, firmas y estado vacío)
    se envuelve en #dashSecondaryContent. El JS de dashFiltrar lo oculta cuando hay
    un filtro activo, y dashLimpiar lo restaura. Así, al filtrar, el usuario ve
    SOLO los KPIs (con el activo marcado) y el panel de resultados — todo lo demás
    desaparece hasta que desactive el filtro.
  --}}
  {{-- Bloque secundario del dashboard: pendientes por módulo.
       El JS de los KPIs lo oculta mientras hay un filtro activo y lo restaura al
       limpiarlo, por eso va envuelto en #dashSecondaryContent. --}}
  <div id="dashSecondaryContent">
  @php $verDependencia = auth()->user()->veVariasDependencias(); @endphp

  {{-- Los accesos rápidos se movieron al layout, a la pestaña "Acciones rápidas",
       para poder usarlos desde cualquier módulo y no solo desde el dashboard. --}}

  {{-- Pendientes: Trámites --}}
  @if($pendientesTramites->count())
  <div class="card">
    <div class="panel-head">
      <div><h3 class="nowrap">Trámites pendientes</h3><p class="nowrap">Trámites que requieren tu atención.</p></div>
      <a href="{{ route('tramites.index', ['naturaleza' => 'tramite']) }}" class="btn btn-outline btn-sm">Ver trámites</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Nombre</th><th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesTramites as $t)
        <tr>
          <td>{{ $t->homoclave ?? 'Sin folio' }}</td>
          <td><strong>{{ $t->nombre_oficial }}</strong></td>
          <td><x-badge-estatus :estatus="$t->estatus" /></td>
          <td class="table-action-cell"><div class="table-actions">
            {{-- Ver: siempre disponible para quien tenga el registro a la vista --}}
            <a href="{{ route('tramites.show',$t) }}" class="btn table-action-btn btn-outline btn-sm">Ver</a>
            {{-- Atender: solo si el rol puede editar este trámite Y el estatus lo permite (borrador / en_correccion) --}}
            @if(auth()->user()->puedeEditarTramite($t) && $t->puedeSerEditado())
              <a href="{{ route('tramites.edit',$t) }}" class="btn table-action-btn btn-sm">Atender</a>
            {{-- Revisar: solo si el rol observa o aprueba (revisora, jurídico) --}}
            @elseif(auth()->user()->tienePermiso('tramites.observar') || auth()->user()->tienePermiso('tramites.aprobar'))
              <a href="{{ route('tramites.show',$t) }}" class="btn table-action-btn btn-sm">Revisar</a>
            @endif
          </div></td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif

  {{-- Pendientes: Servicios --}}
  @if(($pendientesServicios ?? collect())->count())
  <div class="card">
    <div class="panel-head">
      <div><h3 class="nowrap">Servicios pendientes</h3><p class="nowrap">Servicios municipales que requieren tu atención.</p></div>
      <a href="{{ route('tramites.index', ['naturaleza' => 'servicio']) }}" class="btn btn-outline btn-sm">Ver servicios</a>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Nombre</th><th>Tipo</th><th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesServicios as $s)
        <tr>
          <td>{{ $s->homoclave ?? 'Sin folio' }}</td>
          <td><strong>{{ $s->nombre_oficial }}</strong></td>
          <td><small>{{ $s->tipo_servicio ?? 'Sin tipo' }}</small></td>
          <td><x-badge-estatus :estatus="$s->estatus" /></td>
          <td class="table-action-cell"><div class="table-actions">
            <a href="{{ route('tramites.show', $s) }}" class="btn table-action-btn btn-outline btn-sm">Ver</a>
            @if(auth()->user()->puedeEditarTramite($s) && $s->puedeSerEditado())
              <a href="{{ route('tramites.edit', $s) }}" class="btn table-action-btn btn-sm">Atender</a>
            @elseif(auth()->user()->tienePermiso('tramites.observar') || auth()->user()->tienePermiso('tramites.aprobar'))
              <a href="{{ route('tramites.show', $s) }}" class="btn table-action-btn btn-sm">Revisar</a>
            @endif
          </div></td>
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
          <td>{{ $a->folio ?? 'AGD-' . str_pad($a->id,3,'0',STR_PAD_LEFT) }}</td>
          <td><strong>{{ Str::limit($a->descripcion,50) }}</strong></td>
          <td><span class="badge">{{ ucfirst($a->tipo) }}</span></td>
          <td><x-badge-estatus :estatus="$a->estatus" /></td>
          <td class="table-action-cell"><div class="table-actions">
            {{-- Ver: siempre disponible --}}
            <a href="{{ route('agenda.show',$a) }}" class="btn table-action-btn btn-outline btn-sm">Ver</a>
            {{-- Atender: admin siempre; enlace solo su propia acción en estado editable --}}
            @if(
              auth()->user()->isRol(App\Models\User::ROL_ADMIN) ||
              (
                auth()->user()->isRol(App\Models\User::ROL_ENLACE)
                && $a->created_by === auth()->id()
                && in_array($a->estatus, ['borrador','en_correccion'])
              )
            )
              <a href="{{ route('agenda.edit',$a) }}" class="btn table-action-btn btn-sm">Atender</a>
            {{-- Revisar: solo si el rol observa o aprueba (revisora, jurídico) --}}
            @elseif(auth()->user()->tienePermiso('agenda.observar') || auth()->user()->tienePermiso('agenda.aprobar'))
              <a href="{{ route('agenda.show',$a) }}" class="btn table-action-btn btn-sm">Revisar</a>
            @endif
          </div></td>
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
          <td>{{ $p->folio ?? 'REG-' . str_pad($p->id,3,'0',STR_PAD_LEFT) }}</td>
          <td><strong>{{ Str::limit($p->nombre,50) }}</strong></td>
          <td>{{ $p->tipo_regulacion ?? '' }}</td>
          <td><x-badge-estatus :estatus="$p->estatus ?? 'borrador'" /></td>
          <td class="table-action-cell"><div class="table-actions">
            {{-- Ver: siempre disponible --}}
            <a href="{{ route('propuestas.show',$p) }}" class="btn table-action-btn btn-outline btn-sm">Ver</a>
            {{-- Atender: admin siempre; quien tiene permiso de editar, la propuesta es de su dependencia, Y está en borrador --}}
            @if(
              auth()->user()->isRol(App\Models\User::ROL_ADMIN) ||
              (auth()->user()->tienePermiso('agenda_regulatoria.editar') && auth()->user()->esDeSuDependencia($p) && $p->estatus === App\Models\PropuestaRegulatoria::ESTATUS_BORRADOR)
            )
              <a href="{{ route('propuestas.edit',$p) }}" class="btn table-action-btn btn-sm">Atender</a>
            {{-- Revisar: solo si el rol observa o aprueba (revisora, jurídico) --}}
            @elseif(auth()->user()->tienePermiso('agenda_regulatoria.observar') || auth()->user()->tienePermiso('agenda_regulatoria.aprobar'))
              <a href="{{ route('propuestas.show',$p) }}" class="btn table-action-btn btn-sm">Revisar</a>
            @endif
          </div></td>
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
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Propuesta</th>@if($verDependencia)<th>Dependencia</th>@endif<th>Estatus</th><th class="table-action-cell">Acción</th></tr></thead><tbody>
      @foreach($pendientesAir as $air)
        <tr>
          <td><strong>{{ Str::limit($air->propuesta?->nombre ?? 'Sin propuesta', 50) }}</strong></td>
          @if($verDependencia)<td>@dato($air->propuesta?->dependencia?->nombre)</td>@endif
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
          <td><span class="badge">{{ $f['tipo'] }}</span></td>
          <td class="table-action-cell"><div class="table-actions"><a href="{{ $f['url_firma'] }}" class="btn table-action-btn">Firmar</a></div></td>
        </tr>
      @endforeach
    </tbody></table></div>

    {{-- ── "MOSTRANDO 5 DE 60" ──

         Esta tabla enseña un máximo de 5 pendientes. Antes NO tenía ningún límite: cargaba TODOS
         los documentos en firma, aunque fueran quinientos. Quinientos modelos en memoria y
         quinientas filas de HTML en una tabla que nadie iba a leer.

         Pero poner el límite y callarse habría sido cambiar un problema por otro, y por uno peor.

         Alguien con 60 documentos esperando su firma vería CINCO, y la tarjeta le diría "Estos
         registros están esperando que los firmes". Firmaría esos cinco y se iría tranquilo,
         creyendo que ya terminó. Los otros 55 se quedarían ahí, sin que nadie supiera.

         Un problema de rendimiento se NOTA: la página tarda. Un problema de información no se
         nota nunca — y por eso es peor.

         Un resumen que no dice que es un resumen no es un resumen: es un dato falso. --}}
    @if(($totalPendientesFirma ?? 0) > $pendientesFirma->count())
      <div class="card-body-padded" style="text-align:center;border-top:1px solid var(--border)">
        <span class="text-muted-sm">
          Mostrando {{ $pendientesFirma->count() }} de
          <strong>{{ $totalPendientesFirma }}</strong> documentos que esperan tu firma.
        </span>
        <a href="{{ route('firmas.index') }}" class="btn btn-outline btn-sm" style="margin-left:8px">
          Ver los {{ $totalPendientesFirma }}
        </a>
      </div>
    @endif
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

  </div>{{-- /#dashSecondaryContent (Bug #B8) --}}

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

    // Bug #B8: ocultar el resto del dashboard mientras hay filtro activo.
    // Solo permanecen visibles los KPIs (con el activo marcado) y el panel de
    // resultados. Se restaura en dashLimpiar.
    var secundario = document.getElementById('dashSecondaryContent');
    if (secundario) secundario.style.display = 'none';

    var panel  = document.getElementById('dashFilterPanel');
    var loading = document.getElementById('dashFilterLoading');
    var table   = document.getElementById('dashFilterTable');
    var body    = document.getElementById('dashFilterBody');
    var title   = document.getElementById('dashFilterTitle');
    var sub     = document.getElementById('dashFilterSub');

    var labels = { tramites: 'Trámites y Servicios', agenda: 'Agenda SyD', propuestas: 'Propuestas regulatorias' };
    var labelsFiltro = { pendientes: 'Pendientes', por_revisar: 'Por revisar', por_aprobar: 'Por aprobar', completados: 'Completados', por_corregir: 'Por corregir', por_firmar: 'Por firmar', en_tramite: 'En trámite', cerrados: 'Completados', regulaciones_por_revisar: 'Regulaciones por revisar', regulaciones_vigentes: 'Regulaciones vigentes', mis_observaciones: 'Mis observaciones', en_revision: 'En revisión', en_correccion: 'Por corregir',
      tramites_dependencia: 'Trámites de mi dependencia', servicios_dependencia: 'Servicios de mi dependencia', propuestas_dependencia: 'Propuestas de mi dependencia', agenda_dependencia: 'Acciones de mi dependencia', solo_tramites: 'Solo trámites', solo_servicios: 'Solo servicios',
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
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
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

    // Bug #B8: restaurar visibilidad del resto del dashboard cuando se
    // desactiva el filtro.
    var secundario = document.getElementById('dashSecondaryContent');
    if (secundario) secundario.style.display = '';
  };

  // El dashboard inicia SIN filtro, mostrando el panorama completo. El usuario
  // elige una tarjeta (p. ej. "Pendientes") cuando quiere acotar la vista.
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