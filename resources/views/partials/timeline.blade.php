{{--
  Timeline lateral de un registro.

  Uso: @include('partials.timeline', ['tipo' => 'propuesta', 'id' => $propuesta->id])

  Muestra la línea de tiempo vertical del registro (el más reciente arriba).
  $eventos, $total y $LIMITE llegan automáticamente desde el View::composer
  registrado en AppServiceProvider (BitacoraService::eventosRecientes).
  No se necesita pasar nada más que $tipo e $id desde el @include.
--}}

<div class="card card-pad">
  <h3 class="timeline-titulo">Historial</h3>
  <p class="timeline-subtitulo">Todo lo que ha pasado con este registro.</p>

  @if($eventos->isEmpty())
    <p class="timeline-vacio">Aún no hay movimientos registrados.</p>
  @else
    <div class="timeline">
      @foreach($eventos as $ev)
        <div class="timeline-evento {{ $ev->tipo === 'created' ? 'es-creacion' : ($ev->tipo === 'deleted' ? 'es-eliminacion' : '') }}">
          <div class="timeline-fecha">{{ \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y H:i') }}</div>
          <div class="timeline-accion">{{ $ev->accion }}</div>
          <div class="timeline-usuario">{{ $ev->usuario_nombre ?? 'Sistema' }}</div>
          @if($ev->detalle)
            @php $listaCambios = explode(' | ', $ev->detalle); @endphp
            <details class="timeline-colapse">
              <summary class="timeline-colapse-toggle">Ver qué cambió ({{ count($listaCambios) }})</summary>
              <div class="timeline-cambios">
                @foreach($listaCambios as $cambio)
                  {{-- Cada línea de cambio se trunca a 120 chars con tooltip del completo
                       para evitar que un JSON largo desborde la columna lateral. --}}
                  <div class="timeline-cambio-linea" title="{{ $cambio }}">{{ \Illuminate\Support\Str::limit($cambio, 120) }}</div>
                @endforeach
              </div>
            </details>
          @endif
        </div>
      @endforeach
    </div>

    @if($total > $LIMITE)
      <p class="timeline-mas">Mostrando los {{ $LIMITE }} más recientes de {{ $total }} movimientos.</p>
    @endif

    {{-- Botón para abrir el historial completo en un modal (carga por AJAX). --}}
    <button type="button" class="btn btn-outline btn-sm timeline-ver-todo"
      onclick="abrirHistorialModal('{{ $tipo }}', {{ $id }})">
      Ver historial completo
    </button>
  @endif
</div>

{{-- Modal del historial completo. Vive una sola vez por página; el JS lo
     reutiliza para cualquier registro. Carga las filas por AJAX desde
     historial.json, con selector de 5/10/100 por página y navegación. --}}
<div id="historialModal" class="historial-modal-overlay" style="display:none;" onclick="cerrarHistorialModalSiFuera(event)">
  <div class="historial-modal">
    <div class="historial-modal-head">
      <h3>Historial completo</h3>
      <button type="button" class="historial-modal-cerrar" onclick="cerrarHistorialModal()" aria-label="Cerrar">&times;</button>
    </div>

    <div class="historial-modal-controles">
      <label>
        Mostrar
        <select id="historialPorPagina" onchange="recargarHistorialModal()">
          <option value="5">5</option>
          <option value="10" selected>10</option>
          <option value="100">100</option>
        </select>
        por página
      </label>
      <span id="historialTotalInfo" class="historial-total-info"></span>
    </div>

    <div id="historialModalBody" class="historial-modal-body">
      {{-- Las filas se inyectan aquí por JS. --}}
    </div>

    <div class="historial-modal-paginacion">
      <button type="button" class="btn btn-outline btn-sm" id="historialPrev" onclick="cambiarPaginaHistorial(-1)">Anterior</button>
      <span id="historialPaginaInfo"></span>
      <button type="button" class="btn btn-outline btn-sm" id="historialNext" onclick="cambiarPaginaHistorial(1)">Siguiente</button>
    </div>
  </div>
</div>

@once
@push('scripts')
<script>
  // Estado del modal de historial. Se guarda el tipo/id del registro abierto
  // y la página actual para poder navegar y recargar al cambiar el tamaño.
  var historialState = { tipo: null, id: null, pagina: 1, ultima: 1 };

  function abrirHistorialModal(tipo, id) {
    historialState.tipo = tipo;
    historialState.id = id;
    historialState.pagina = 1;
    document.getElementById('historialModal').style.display = 'flex';
    cargarHistorial();
  }

  function cerrarHistorialModal() {
    document.getElementById('historialModal').style.display = 'none';
  }

  function cerrarHistorialModalSiFuera(e) {
    // Cierra solo si se hizo clic en el fondo oscuro, no en el contenido.
    if (e.target === document.getElementById('historialModal')) {
      cerrarHistorialModal();
    }
  }

  function recargarHistorialModal() {
    historialState.pagina = 1; // al cambiar el tamaño, volver a la primera página
    cargarHistorial();
  }

  function cambiarPaginaHistorial(delta) {
    var nueva = historialState.pagina + delta;
    if (nueva < 1 || nueva > historialState.ultima) return;
    historialState.pagina = nueva;
    cargarHistorial();
  }

  function cargarHistorial() {
    var porPagina = document.getElementById('historialPorPagina').value;
    var body = document.getElementById('historialModalBody');
    body.innerHTML = '<p class="historial-cargando">Cargando...</p>';

    var url = '/historial/' + historialState.tipo + '/' + historialState.id +
              '/json?por_pagina=' + porPagina + '&page=' + historialState.pagina;

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        historialState.pagina = data.pagina_actual;
        historialState.ultima = data.ultima_pagina;

        if (!data.filas || !data.filas.length) {
          body.innerHTML = '<p class="historial-vacio">Sin movimientos registrados.</p>';
        } else {
          body.innerHTML = data.filas.map(function (f) {
            var clase = f.tipo === 'created' ? 'es-creacion' : (f.tipo === 'deleted' ? 'es-eliminacion' : '');
            var lista = f.cambios || [];
            var cambios = lista.map(function (c) {
              var txt = c.length > 120 ? c.slice(0, 120) + '…' : c;
              return '<div class="timeline-cambio-linea" title="' + escaparHtml(c) + '">' + escaparHtml(txt) + '</div>';
            }).join('');
            // Los cambios van dentro de un <details> colapsable: el detalle se
            // muestra solo cuando el usuario hace clic en "Ver qué cambió".
            var bloqueCambios = lista.length
              ? '<details class="timeline-colapse">' +
                  '<summary class="timeline-colapse-toggle">Ver qué cambió (' + lista.length + ')</summary>' +
                  '<div class="timeline-cambios">' + cambios + '</div>' +
                '</details>'
              : '';
            return '<div class="timeline-evento ' + clase + '">' +
                     '<div class="timeline-fecha">' + escaparHtml(f.fecha) + '</div>' +
                     '<div class="timeline-accion">' + escaparHtml(f.accion) + '</div>' +
                     '<div class="timeline-usuario">' + escaparHtml(f.usuario) + '</div>' +
                     bloqueCambios +
                   '</div>';
          }).join('');
        }

        document.getElementById('historialTotalInfo').textContent = data.total + ' movimiento' + (data.total === 1 ? '' : 's') + ' en total';
        document.getElementById('historialPaginaInfo').textContent = 'Página ' + data.pagina_actual + ' de ' + data.ultima_pagina;
        document.getElementById('historialPrev').disabled = (data.pagina_actual <= 1);
        document.getElementById('historialNext').disabled = (data.pagina_actual >= data.ultima_pagina);
      })
      .catch(function () {
        body.innerHTML = '<p class="historial-vacio">No se pudo cargar el historial.</p>';
      });
  }

  // Escapa texto para evitar inyección de HTML al construir las filas.
  function escaparHtml(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }
</script>
@endpush
@endonce
