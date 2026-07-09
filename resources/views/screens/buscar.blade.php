@extends('layouts.app')
@section('title', 'Buscador')

@section('content')
<div class="page-default" style="max-width:900px">

  {{-- ENCABEZADO --}}
  <div class="screen-head" style="margin-bottom:8px">
    <div>
      <h2 class="nowrap">Buscador</h2>
      <p class="nowrap">Busca en regulaciones, trámites, servicios, requisitos y fundamentos jurídicos.</p>
    </div>
    <button type="button" class="btn btn-outline" style="flex-shrink:0;font-size:12px;padding:5px 12px"
      onclick="document.getElementById('buscarAyuda').classList.toggle('open')">
      <i class="ti ti-help"></i> ¿Cómo buscar?
    </button>
  </div>

  {{-- PANEL DE AYUDA --}}
  <div class="buscar-ayuda" id="buscarAyuda">
    <div class="buscar-ayuda-grid">

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-search"></i> Campo de búsqueda</strong>
        <p>Escriba palabras clave y presione <em>Buscar</em>. No necesita escribir frases completas — palabras sueltas como <code>licencia</code>, <code>ambulantes</code> o <code>construcción</code> funcionan bien. El buscador encuentra coincidencias en el texto de artículos, regulaciones, trámites, requisitos y fundamentos.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-filter"></i> Filtrar por regulación</strong>
        <p>Use el campo <em>"Filtrar dentro de una regulación…"</em> para restringir la búsqueda a una o varias leyes específicas. Escriba parte del nombre para encontrarla, haga clic para agregarla como chip. Puede agregar varias. Para quitar un filtro, presione la <strong>×</strong> del chip. Los resultados mostrarán solo artículos de esas leyes, más los trámites, requisitos, fundamentos y acciones de agenda vinculados a ellas.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-category"></i> Filtrar por tipo</strong>
        <p>Los botones de tipo (Articulado, Regulaciones, Trámites, Requisitos, Fundamentos, Agenda) permiten buscar solo en las fuentes que le interesen. Por defecto busca en todas. Haga clic en uno o varios para activarlos — solo los activados se consultarán. Combine con el filtro de regulación para búsquedas muy específicas, por ejemplo: <code>ambulantes</code> en la <em>Ley de Hacienda</em> solo en <em>Trámites / Servicios</em>.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-bulb"></i> Respuesta destacada</strong>
        <p>Cuando su búsqueda coincide con un término definido en alguna regulación (como <code>servicio</code>, <code>trámite</code>, <code>sujeto obligado</code>), aparece un recuadro con la definición legal, la ley de donde proviene y el artículo exacto. Esta respuesta aparece siempre, incluso cuando hay filtro de regulación activo.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-book-2"></i> Lectura de artículos</strong>
        <p>Los resultados de tipo <em>Articulado</em> se pueden abrir en un modal de lectura haciendo clic. El modal muestra el artículo completo con sus fracciones e incisos, junto con un enlace para ver la regulación completa.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-sparkles"></i> Consejos</strong>
        <p>Use palabras clave simples (no preguntas largas). Si no encuentra resultados, pruebe con sinónimos o menos términos. El enlace <em>"Ver todos los resultados relacionados"</em> aparece cuando el buscador enfocó automáticamente su búsqueda en una sola fuente — haga clic para ver el panorama completo.</p>
      </div>

    </div>
  </div>

  {{-- CAMPO DE BÚSQUEDA + FILTRO POR REGULACIÓN --}}
  <form method="GET" action="{{ route('buscar') }}" class="buscar-form-wrap" autocomplete="off">
    <div class="buscar-fila-principal">
      <div class="buscar-input-wrap">
        <i class="ti ti-search buscar-icono"></i>
        <input
          type="search"
          name="q"
          value="{{ $consulta }}"
          placeholder="Ej. ¿quién emite licencias?, requisitos construcción, plazo uso de suelo..."
          class="buscar-input"
          autofocus>
        @if($consulta)
          <a href="{{ route('buscar') }}" class="buscar-limpiar" title="Limpiar búsqueda">&times;</a>
        @endif
      </div>
      <button type="submit" class="btn" style="flex-shrink:0">Buscar</button>
    </div>

    {{-- Selector de regulaciones: checkboxes dentro de <details> --}}
    @if($regulaciones->isNotEmpty())
      {{-- Chips de regulaciones seleccionadas --}}
      <div class="buscar-chips" id="buscarChips">
        {{-- Los chips se pintan aquí por JS; los hidden inputs también --}}
      </div>

      {{-- Input de búsqueda de regulación con dropdown flotante --}}
      <div class="buscar-reg-wrap">
        <i class="ti ti-filter buscar-reg-icono"></i>
        <input type="text" id="buscarRegInput"
               placeholder="Filtrar dentro de una regulación…"
               class="buscar-reg-input" autocomplete="off">
        <div class="buscar-reg-dropdown" id="buscarRegDropdown"></div>
      </div>
    @endif

    {{-- Filtro por tipo de fuente --}}
    @php
      $tiposDisponibles = [
        'articulo'   => ['label' => 'Articulado',        'icono' => 'ti-book-2'],
        'regulacion' => ['label' => 'Regulaciones',      'icono' => 'ti-scale'],
        'tramite'    => ['label' => 'Trámites / Servicios', 'icono' => 'ti-file-text'],
        'requisito'  => ['label' => 'Requisitos',        'icono' => 'ti-file-check'],
        'fundamento' => ['label' => 'Fundamentos',       'icono' => 'ti-gavel'],
        'agenda'     => ['label' => 'Agenda',             'icono' => 'ti-tools'],
      ];
      $tiposActivos = $tiposSeleccionados ?? [];
      $todosTipos   = empty($tiposActivos);
    @endphp
    <div class="buscar-tipos" id="buscarTiposContainer">
      @foreach($tiposDisponibles as $clave => $info)
        <button type="button" class="buscar-tipo-toggle {{ !$todosTipos && in_array($clave, $tiposActivos) ? 'activo' : '' }}"
          data-tipo="{{ $clave }}">
          <i class="ti {{ $info['icono'] }}"></i>
          {{ $info['label'] }}
        </button>
      @endforeach
      <div id="tiposHiddenContainer">
        @if(!$todosTipos)
          @foreach($tiposActivos as $t)
            <input type="hidden" name="tipos[]" value="{{ $t }}">
          @endforeach
        @endif
      </div>
    </div>
  </form>

  {{-- RESPUESTA DESTACADA (Modo respuesta) --}}
  @if($consulta && $respuestaDestacada)
    <div class="buscar-destacada">
      <div class="buscar-destacada-encabezado">
        <i class="ti ti-bulb"></i>
        <strong>{{ $respuestaDestacada['termino'] }}</strong>
        <span class="buscar-destacada-confianza buscar-destacada-confianza-{{ $respuestaDestacada['confianza'] }}">
          Confianza: {{ ucfirst($respuestaDestacada['confianza']) }}
        </span>
      </div>
      <p class="buscar-destacada-texto">{{ $respuestaDestacada['definicion'] }}</p>
      <div class="buscar-destacada-fuente">
        <a href="{{ route('regulaciones.show', $respuestaDestacada['regulacion_id']) }}">
          {{ $respuestaDestacada['fuente'] }}
          @if($respuestaDestacada['articulo'])
            — Artículo {{ $respuestaDestacada['articulo'] }}
          @endif
          @if($respuestaDestacada['fraccion'])
            , fracción {{ $respuestaDestacada['fraccion'] }}
          @endif
        </a>
      </div>
      @if(!empty($respuestaDestacada['definiciones_adicionales']))
        <details class="buscar-destacada-adicionales">
          <summary>
            {{ count($respuestaDestacada['definiciones_adicionales']) }}
            {{ count($respuestaDestacada['definiciones_adicionales']) === 1 ? 'definición adicional encontrada' : 'definiciones adicionales encontradas' }}
            en otras regulaciones
          </summary>
          <ul>
            @foreach($respuestaDestacada['definiciones_adicionales'] as $adicional)
              <li>
                <a href="{{ route('regulaciones.show', $adicional['regulacion_id']) }}">
                  {{ $adicional['fuente'] }}
                  @if($adicional['articulo'])
                    — Artículo {{ $adicional['articulo'] }}
                  @endif
                </a>
              </li>
            @endforeach
          </ul>
        </details>
      @endif
    </div>
  @endif

  {{-- RESULTADOS --}}
  @if($consulta)
    <div class="buscar-meta">
      {{ $resultados->count() }} resultado{{ $resultados->count() !== 1 ? 's' : '' }}
      para <strong>"{{ $consulta }}"</strong>
      @if($regulacionesFiltro && $regulacionesFiltro->isNotEmpty())
        en
        @foreach($regulacionesFiltro as $rf)
          <strong>{{ $rf->nombre }}</strong>{{ !$loop->last ? ', ' : '' }}
        @endforeach
      @endif
      <span class="buscar-tiempo">{{ $tiempo }} ms</span>
    </div>

    {{-- Modo explorar: solo aparece cuando la búsqueda fue enfocada a una
         sola fuente, para que el usuario siempre pueda ver el panorama
         completo si lo enfocado no era lo que buscaba.
         No aplica cuando hay filtro por regulación activo. --}}
    @if($modo === 'enfocado')
      <div class="buscar-modo-aviso">
        Resultados enfocados según tu pregunta.
        <a href="{{ route('buscar', ['q' => $consulta, 'todos' => 1]) }}">Ver todos los resultados relacionados</a>
      </div>
    @endif

    @if($resultados->isEmpty())
      {{-- Estado vacío (Refactoring UI §46) --}}
      <div class="buscar-vacio">
        <i class="ti ti-search-off" style="font-size:48px;color:var(--muted);display:block;margin-bottom:12px"></i>
        <strong>No se encontraron resultados</strong>
        @if($regulacionesFiltro && $regulacionesFiltro->isNotEmpty())
          <p>No se encontró "{{ $consulta }}" dentro de {{ $regulacionesFiltro->pluck('nombre')->join(', ', ' ni ') }}.</p>
          <p style="margin-top:8px">
            <a href="{{ route('buscar', ['q' => $consulta]) }}" class="btn btn-outline">
              Buscar en todas las regulaciones
            </a>
          </p>
        @else
          <p>Intenta con otras palabras o con menos términos. El buscador busca coincidencias exactas en el texto de regulaciones, trámites, servicios, requisitos y fundamentos.</p>
          <p style="font-size:12px;color:var(--muted)">Sugerencias: usa palabras clave simples como "licencia", "construcción", "catastro", "residuos".</p>
        @endif
      </div>
    @else
      <div class="buscar-resultados">
        @foreach($resultados as $r)
          @php
            // Extraer el ID del resultado según su tipo para la bitácora
            $resultadoId = $r['meta']['nodo_id']
              ?? $r['meta']['regulacion_id']
              ?? $r['meta']['tramite_id']
              ?? $r['meta']['requisito_id']
              ?? $r['meta']['fundamento_id']
              ?? $r['meta']['accion_id']
              ?? 0;
          @endphp
          <div class="buscar-resultado-card">
            <a href="{{ $r['url'] }}" class="buscar-resultado"
              data-tipo="{{ $r['tipo'] }}"
              data-id="{{ $resultadoId }}"
              onclick="return abrirModalDetalle('{{ $r['tipo'] }}', {{ $resultadoId }}, '{{ $r['url'] }}')"
            >
              <div class="buscar-resultado-icono">
                <i class="ti {{ $r['icono'] }}"></i>
              </div>
              <div class="buscar-resultado-contenido">
                <div class="buscar-resultado-titulo">{{ $r['titulo'] }}</div>
                <div class="buscar-resultado-subtitulo">{{ $r['subtitulo'] }}</div>
                @if($r['fragmento'])
                  <div class="buscar-resultado-fragmento">{{ $r['fragmento'] }}</div>
                @endif
                @if($r['tipo'] === 'tramite' && !empty($r['meta']['plazo']))
                  <span class="buscar-tag">Plazo: {{ $r['meta']['plazo'] }}</span>
                @endif
                @if($r['tipo'] === 'tramite' && !empty($r['meta']['cbu']))
                  <span class="buscar-tag">CBU: ${{ number_format($r['meta']['cbu'], 2) }}</span>
                @endif
                @if($r['tipo'] === 'tramite' && !empty($r['meta']['estatus']))
                  <span class="buscar-tag">{{ ucfirst(str_replace('_', ' ', $r['meta']['estatus'])) }}</span>
                @endif
                @if($r['tipo'] === 'agenda' && !empty($r['meta']['tramite_nombre']))
                  <span class="buscar-tag">Trámite: {{ Str::limit($r['meta']['tramite_nombre'], 60) }}</span>
                @endif
                @if($r['tipo'] === 'agenda' && !empty($r['meta']['fecha_compromiso']))
                  <span class="buscar-tag">Compromiso: {{ \Carbon\Carbon::parse($r['meta']['fecha_compromiso'])->format('d/m/Y') }}</span>
                @endif
              </div>
              <div class="buscar-resultado-tipo">
                @php
                  $tipoLabels = [
                    'articulo'   => 'Articulado',
                    'regulacion' => 'Regulación',
                    'tramite'    => 'Trámite / Servicio',
                    'requisito'  => 'Requisito',
                    'fundamento' => 'Fundamento',
                    'agenda'     => 'Agenda',
                  ];
                @endphp
                <span class="buscar-badge buscar-badge-{{ $r['tipo'] }}">
                  {{ $tipoLabels[$r['tipo']] ?? $r['tipo'] }}
                </span>
              </div>
            </a>
            {{-- Capa 2: Feedback --}}
            @if($busquedaLogId)
              <div class="buscar-feedback" id="fb-{{ $loop->index }}">
                <span class="buscar-feedback-label">¿Te sirvió?</span>
                <button type="button" class="buscar-fb-btn buscar-fb-si"
                  onclick="enviarFeedback({{ $loop->index }}, '{{ $r['tipo'] }}', {{ $resultadoId }}, {{ json_encode($r['titulo']) }}, true)">
                  Sí
                </button>
                <button type="button" class="buscar-fb-btn buscar-fb-no"
                  onclick="enviarFeedback({{ $loop->index }}, '{{ $r['tipo'] }}', {{ $resultadoId }}, {{ json_encode($r['titulo']) }}, false)">
                  No
                </button>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    @endif
  @else
    {{-- Estado inicial: sugerencias de qué buscar --}}
    <div class="buscar-sugerencias">
      <p><strong>¿Qué puedes buscar?</strong></p>
      <div class="buscar-sugerencias-grid">
        <div class="buscar-sugerencia">
          <i class="ti ti-book-2"></i>
          <strong>Artículos de regulaciones</strong>
          <small>Busca dentro del texto de los artículos, fracciones e incisos de todas las regulaciones del catálogo.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-scale"></i>
          <strong>Regulaciones</strong>
          <small>Encuentra regulaciones por nombre, materia, objetivo o palabras clave.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-file-text"></i>
          <strong>Trámites y servicios</strong>
          <small>Busca trámites y servicios por nombre, objetivo o población a la que van dirigidos.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-file-check"></i>
          <strong>Requisitos</strong>
          <small>Encuentra en qué trámites se pide un documento específico, con tiempo y costo estimado.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-gavel"></i>
          <strong>Fundamentos jurídicos</strong>
          <small>Descubre qué ley, reglamento o artículo fundamenta cada trámite o servicio.</small>
        </div>
      </div>
    </div>
  @endif

</div>

{{--
  Modal de lectura del articulado.

  Se abre al hacer clic en un resultado de búsqueda tipo "Articulado".
  El contenido se pide en JSON a /buscar/articulo/{nodo} (ver
  BuscadorController::obtenerArticulo) y se arma con JavaScript — el mismo
  patrón que ya usa el proyecto en otros lugares (por ejemplo, la
  previsualización de homoclave en el wizard de trámites).

  Reutiliza las clases .modal-backdrop / .modal / .modal-head / .modal-body
  / .modal-actions de 07-modals.css, las mismas que usa el modal de
  observaciones — con la variante .modal-articulado (definida en
  15-buscador.css) que lo hace más ancho, porque un resultado legal necesita
  más espacio de lectura que un formulario corto.
--}}
<div class="modal-backdrop" id="modalDetalle">
  <div class="modal modal-articulado">
    <div class="modal-head">
      <div>
        <h3 id="detalleTitulo">Cargando…</h3>
        <p class="modal-ref" id="detalleRef"></p>
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar"
        onclick="document.getElementById('modalDetalle').classList.remove('open')"></button>
    </div>

    <div class="modal-body" id="detalleCuerpo">
      <p style="text-align:center;color:var(--muted)">Cargando…</p>
    </div>

    <div class="modal-actions">
      <a href="#" id="detalleVerCompleto" class="btn btn-outline">Ver completo</a>
      <button type="button" class="btn"
        onclick="document.getElementById('modalDetalle').classList.remove('open')">Cerrar</button>
    </div>
  </div>
</div>

<script>
  // ── Datos para el selector de regulaciones ────────────────────────────
  var REGS = @json($regulaciones);
  var IDS_PREVIOS = @json($regulacionIds ?? []);

  // ── Selector tipo chip ────────────────────────────────────────────────
  (function () {
    var input    = document.getElementById('buscarRegInput');
    var dropdown = document.getElementById('buscarRegDropdown');
    var chipsBox = document.getElementById('buscarChips');
    if (!input) return;

    // IDs seleccionados (inicia con los que venían en la URL)
    var seleccionados = IDS_PREVIOS.map(function (id) { return parseInt(id); });

    function regPorId(id) {
      return REGS.find(function (r) { return r.id === id; });
    }

    // Pinta los chips y los hidden inputs dentro del form
    function pintarChips() {
      if (!seleccionados.length) {
        chipsBox.innerHTML = '';
        chipsBox.style.display = 'none';
        return;
      }
      chipsBox.style.display = '';
      chipsBox.innerHTML = seleccionados.map(function (id) {
        var reg = regPorId(id);
        var nombre = reg ? reg.nombre : 'Regulación #' + id;
        var tipo = reg && reg.tipo ? '<span class="buscar-chip-tipo">' + reg.tipo + '</span>' : '';
        return '<span class="buscar-chip">'
          + '<i class="ti ti-scale"></i> '
          + nombre + ' ' + tipo
          + '<button type="button" class="buscar-chip-x" data-id="' + id + '">×</button>'
          + '</span>'
          + '<input type="hidden" name="regulacion_id[]" value="' + id + '">';
      }).join('');

      chipsBox.querySelectorAll('.buscar-chip-x').forEach(function (btn) {
        btn.addEventListener('click', function () {
          seleccionados = seleccionados.filter(function (id) {
            return id !== parseInt(btn.getAttribute('data-id'));
          });
          pintarChips();
        });
      });
    }

    // Muestra el dropdown con las regulaciones que coinciden
    function mostrar() {
      var q = input.value.trim().toLowerCase();
      var lista = REGS.filter(function (r) {
        // Excluir las ya seleccionadas
        if (seleccionados.indexOf(r.id) >= 0) return false;
        if (!q) return true;
        return (r.nombre || '').toLowerCase().indexOf(q) >= 0;
      }).slice(0, 10);

      if (!lista.length) {
        dropdown.innerHTML = q
          ? '<div class="buscar-reg-item buscar-reg-vacio">Sin coincidencias</div>'
          : '';
        return;
      }

      dropdown.innerHTML = lista.map(function (r) {
        var tipo = r.tipo ? '<span class="buscar-reg-tipo">' + r.tipo + '</span>' : '';
        return '<div class="buscar-reg-item" data-id="' + r.id + '">'
          + '<span>' + r.nombre + '</span>' + tipo
          + '</div>';
      }).join('');

      dropdown.querySelectorAll('.buscar-reg-item[data-id]').forEach(function (el) {
        el.addEventListener('mousedown', function (e) {
          e.preventDefault();
          seleccionados.push(parseInt(el.getAttribute('data-id')));
          input.value = '';
          dropdown.innerHTML = '';
          pintarChips();
          input.focus();
        });
      });
    }

    input.addEventListener('focus', mostrar);
    input.addEventListener('input', mostrar);
    input.addEventListener('blur', function () {
      setTimeout(function () { dropdown.innerHTML = ''; }, 150);
    });

    // Pintar chips iniciales (regulaciones que venían en la URL)
    pintarChips();
  })();

  // ── Toggle de tipos de fuente ───────────────────────────────────────
  (function () {
    document.querySelectorAll('.buscar-tipo-toggle').forEach(function (label) {
      label.addEventListener('click', function () {
        var cb = label.querySelector('input[type="checkbox"]');
        // El click ya toggled el checkbox; solo actualizamos la clase.
        if (cb.checked) {
          label.classList.add('activo');
        } else {
          label.classList.remove('activo');
        }
      });
    });
  })();

  // ── Variables globales ──────────────────────────────────────────────
  var BUSQUEDA_LOG_ID = @json($busquedaLogId);
  var CONSULTA_ACTUAL = @json($consulta);
  var CSRF_TOKEN      = @json(csrf_token());

  // Escapa texto antes de insertarlo como innerHTML
  function esc(texto) {
    var div = document.createElement('div');
    div.textContent = texto || '';
    return div.innerHTML;
  }

  // ── Bitácora: clic y feedback ───────────────────────────────────────

  // Registrar clic en resultado (fire-and-forget)
  document.querySelectorAll('.buscar-resultado[data-tipo]').forEach(function (el) {
    el.addEventListener('click', function () {
      if (!BUSQUEDA_LOG_ID) return;
      fetch('/buscar/clic', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({
          log_id: BUSQUEDA_LOG_ID,
          tipo:   el.getAttribute('data-tipo'),
          id:     parseInt(el.getAttribute('data-id'))
        })
      }).catch(function () {});
    });
  });

  // Registrar feedback
  function enviarFeedback(idx, tipo, id, titulo, util) {
    if (!BUSQUEDA_LOG_ID) return;
    var card = document.getElementById('fb-' + idx);
    if (!card) return;

    // Cambiar visual INMEDIATAMENTE (sin esperar al servidor)
    card.innerHTML = '<span class="buscar-feedback-gracias">'
      + (util ? '👍 Útil' : '👎 No útil')
      + '</span>';

    // Enviar al servidor (fire-and-forget)
    fetch('/buscar/feedback', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
      body: JSON.stringify({
        log_id: BUSQUEDA_LOG_ID, consulta: CONSULTA_ACTUAL,
        tipo: tipo, id: id, titulo: titulo, util: util
      })
    }).catch(function () {});
  }

  // ── Modal de detalle (genérico para todos los tipos) ──────────────

  function abrirModalDetalle(tipo, id, urlCompleta) {
    var modal   = document.getElementById('modalDetalle');
    var cuerpo  = document.getElementById('detalleCuerpo');
    var titulo  = document.getElementById('detalleTitulo');
    var ref     = document.getElementById('detalleRef');
    var btnVer  = document.getElementById('detalleVerCompleto');

    titulo.textContent = 'Cargando…';
    ref.textContent = '';
    cuerpo.innerHTML = '<p style="text-align:center;color:var(--muted)">Cargando…</p>';
    btnVer.href = urlCompleta;
    modal.classList.add('open');

    fetch('/buscar/detalle/' + tipo + '/' + id, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) {
        titulo.textContent = d.titulo || 'Detalle';
        ref.textContent = d.subtitulo || '';
        if (d.url) btnVer.href = d.url;

        var html = '';

        // Campos principales como párrafos con label
        (d.campos || []).forEach(function (campo) {
          html += '<div class="detalle-campo">';
          html += '<span class="detalle-label">' + esc(campo.label) + '</span>';
          html += '<p class="articulo-parrafo">' + esc(campo.valor) + '</p>';
          html += '</div>';
        });

        // Hijos (fracciones de artículos, requisitos de trámites, etc.)
        (d.hijos || []).forEach(function (hijo) {
          html += '<div class="articulo-hijo">';
          html += '<strong>' + esc(hijo.tipo) + (hijo.numero ? ' ' + esc(hijo.numero) : '') + '</strong>';
          html += '<p class="articulo-parrafo">' + esc(hijo.texto || '') + '</p>';
          html += '</div>';
        });

        // Tags
        if (d.tags && d.tags.length) {
          html += '<div style="margin-top:12px">';
          d.tags.forEach(function (tag) {
            html += '<span class="buscar-tag">' + esc(tag) + '</span>';
          });
          html += '</div>';
        }

        cuerpo.innerHTML = html || '<p style="color:var(--muted)">Sin contenido disponible.</p>';
      })
      .catch(function () {
        titulo.textContent = 'No se pudo cargar el contenido';
        cuerpo.innerHTML = '<p style="color:var(--chip-red)">Ocurrió un error. Puede abrir el enlace directamente.</p>';
      });

    return false;
  }
  // ── Toggle de tipos de fuente (clickeable ANTES de buscar) ────────
  (function () {
    var container = document.getElementById('buscarTiposContainer');
    if (!container) return;

    container.addEventListener('click', function (e) {
      var btn = e.target.closest('.buscar-tipo-toggle');
      if (!btn) return;

      btn.classList.toggle('activo');

      // Reconstruir hidden inputs
      var hiddenBox = document.getElementById('tiposHiddenContainer');
      hiddenBox.innerHTML = '';

      container.querySelectorAll('.buscar-tipo-toggle.activo').forEach(function (el) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'tipos[]';
        input.value = el.getAttribute('data-tipo');
        hiddenBox.appendChild(input);
      });
    });
  })();

</script>
@endsection
