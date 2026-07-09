@props([
    'tipo',        // 'tramite', etc. (para la ruta de observar)
    'id',          // id del registro
    'campos',      // mapa seccion => [campo => etiqueta] de config('punta.campos_observables_tramite')
    'revisores',   // colección de usuarios a quienes dirigir la observación
])

{{--
  Modal único de "Agregar observación" (corrección #18).

  Se incluye UNA vez en el detalle. Cada sección tiene su botón
  (ver el partial 'boton-observar' o el x-boton-observar), que llama a
  abrirModalObservacion(seccion) con la sección correspondiente. El modal
  carga dinámicamente los campos de esa sección para que el revisor elija
  a cuál liga la observación.
--}}

<div class="modal-backdrop" id="modalObservacion">
  <div class="modal">
    <div class="modal-head">
      <div>
        <h3>Agregar observación</h3>
        <p class="modal-ref" id="obsSeccionRef">Sección</p>
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar"
        onclick="document.getElementById('modalObservacion').classList.remove('open')"></button>
    </div>

    <form method="POST" action="{{ route('revision.observar', ['tipo' => $tipo, 'id' => $id]) }}">
      @csrf
      <input type="hidden" name="seccion" id="obsSeccion">

      <div class="modal-body">
        <div class="field">
          <label for="obsCampo">Campo observado</label>
          <select name="campo" id="obsCampo">
            {{-- Se llena por JS según la sección. "Toda la sección" es una opción
                 válida (campo vacío = observación general); por eso el select NO
                 lleva required: el backend acepta `campo` como nullable. --}}
          </select>
        </div>

        <div class="field">
          <label for="obsTexto">Observación</label>
          <textarea name="texto" id="obsTexto" rows="4" required minlength="10" maxlength="2000"
            placeholder="Describa qué debe corregirse en este campo..."></textarea>
        </div>

        <div class="field">
          <label for="obsDestinatario">Dirigida a</label>
          <select name="destinatario_id" id="obsDestinatario" required>
            <option value="">Seleccione a quién va dirigida</option>
            @foreach($revisores as $r)
              @php
                // Construir el detalle "Cargo · Rol" omitiendo lo que no exista.
                $detalle = collect([$r->cargo ?? null, ucfirst($r->rol ?? '')])
                    ->filter()->implode(' · ');
              @endphp
              <option value="{{ $r->id }}">
                {{ $r->name }}@if($detalle) — {{ $detalle }}@endif
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline"
          onclick="document.getElementById('modalObservacion').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn">Guardar observación</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Mapa de campos por sección, inyectado desde config('punta.campos_observables_tramite').
  window._camposObservables = @json($campos);

  // Abre el modal para una sección: carga sus campos y muestra el modal.
  function abrirModalObservacion(seccion) {
    var campos = window._camposObservables[seccion] || {};
    var select = document.getElementById('obsCampo');
    select.innerHTML = '';

    // Opción para observar la sección completa (sin campo específico).
    var optSeccion = document.createElement('option');
    optSeccion.value = '';
    optSeccion.textContent = 'Toda la sección';
    select.appendChild(optSeccion);

    Object.keys(campos).forEach(function (clave) {
      var opt = document.createElement('option');
      opt.value = clave;
      opt.textContent = campos[clave];
      select.appendChild(opt);
    });

    document.getElementById('obsSeccion').value = seccion;
    document.getElementById('obsSeccionRef').textContent = seccion;
    document.getElementById('obsTexto').value = '';
    document.getElementById('modalObservacion').classList.add('open');
  }
</script>
