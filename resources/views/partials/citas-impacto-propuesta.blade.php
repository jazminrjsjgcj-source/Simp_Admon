{{--
  #7 — Citas de impacto de una propuesta regulatoria (Flujo 1).
  Muestra los trámites y requisitos que la propuesta declara que va a modificar.

  En modo edición ($modoEdicion = true):
    - Muestra las citas existentes con botón "Quitar".
    - Ofrece un buscador de trámite → selector de requisito → artículo para agregar.
  En modo lectura ($modoEdicion = false):
    - Solo lista las citas registradas.

  Espera:
    $propuesta    → PropuestaRegulatoria con impactos.tramite e impactos.requisito cargados
    $modoEdicion  → bool (true en edit, false en show)
--}}
@php
  $modoEdicion = $modoEdicion ?? false;
  $impactos    = $propuesta->impactos ?? collect();
@endphp

<div class="card" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <h3>Trámites y requisitos afectados</h3>
      <p>Impacto esperado de esta propuesta regulatoria sobre trámites existentes.</p>
    </div>
  </div>
  <div class="card-body-padded">

    {{-- Lista de citas existentes --}}
    @if($impactos->isNotEmpty())
      <div class="table-wrap" style="margin-bottom:16px">
      <table class="data-table">
        <thead>
          <tr>
            <th>Trámite o servicio</th>
            <th>Requisito afectado</th>
            <th>Artículo / Fracción</th>
            <th>Descripción del cambio</th>
            @if($modoEdicion)<th class="table-action-cell"></th>@endif
          </tr>
        </thead>
        <tbody>
          @foreach($impactos as $imp)
            <tr>
              <td>{{ $imp->tramite->nombre_oficial ?? '—' }}</td>
              <td>{{ $imp->requisito->nombre ?? 'Registro en general' }}</td>
              <td>{{ $imp->articulo_fraccion ?? '—' }}</td>
              <td>{{ $imp->descripcion ?? '—' }}</td>
              @if($modoEdicion)
                <td class="table-action-cell">
                  <form method="POST"
                        action="{{ route('propuestas.impacto.quitar', ['propuesta' => $propuesta->id, 'impacto' => $imp->id]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline"
                            onclick="return confirmarAccion(this, '¿Quitar esta cita de impacto?')">Quitar</button>
                  </form>
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    @else
      <p class="muted" style="margin-bottom:16px">No se han registrado citas de impacto para esta propuesta.</p>
    @endif

    {{-- Formulario para agregar nueva cita (solo en modo edición) --}}
    @if($modoEdicion)
      <div class="citas-agregar">
        <strong style="display:block; font-size:14px; margin-bottom:10px">Agregar cita de impacto</strong>
        <form method="POST"
              action="{{ route('propuestas.impacto.agregar', $propuesta) }}"
              id="formAgregarImpacto">
          @csrf
          <div class="wizard-fields">
            {{-- Buscador de trámite --}}
            <div class="field span-2">
              <label>Trámite o servicio afectado *</label>
              <input type="text" id="buscadorTramiteImpacto" autocomplete="off"
                     placeholder="Buscar por nombre o clave...">
              <div id="resultadosTramiteImpacto" style="position:relative"></div>
              <input type="hidden" name="tramite_id" id="tramiteImpactoId">
              <input type="text" id="tramiteImpactoNombre" readonly style="margin-top:6px;display:none" class="u-input-disabled">
            </div>

            {{-- Selector de requisito (se carga cuando se elige trámite) --}}
            <div class="field span-2" id="requisitoImpactoWrap" style="display:none">
              <label>Requisito específico <small>(opcional — dejar en blanco si afecta el registro en general)</small></label>
              <select name="requisito_id" id="requisitoImpactoSelect">
                <option value="">— Registro en general —</option>
              </select>
            </div>

            <div class="field">
              <label>Artículo / Fracción</label>
              <input name="articulo_fraccion" type="text" maxlength="200"
                     placeholder="Ej. Art. 15 Fracc. III">
            </div>

            <div class="field">
              <label>Descripción del cambio</label>
              <input name="descripcion" type="text" maxlength="500"
                     placeholder="Ej. Elimina este requisito">
            </div>

            <div class="field span-2">
              <button type="submit" class="btn btn-sm">Agregar cita</button>
            </div>
          </div>
        </form>
      </div>

      <script>
      (function () {
        var timer = null;
        var buscador = document.getElementById('buscadorTramiteImpacto');
        var resultados = document.getElementById('resultadosTramiteImpacto');
        var idInput = document.getElementById('tramiteImpactoId');
        var nombreInput = document.getElementById('tramiteImpactoNombre');
        var requisitoWrap = document.getElementById('requisitoImpactoWrap');
        var requisitoSelect = document.getElementById('requisitoImpactoSelect');

        if (!buscador) return;

        buscador.addEventListener('input', function () {
          clearTimeout(timer);
          var q = this.value.trim();
          resultados.innerHTML = '';
          if (q.length < 2) return;
          timer = setTimeout(function () {
            fetch('{{ url("api/tramites/buscar") }}?q=' + encodeURIComponent(q), {
              headers: { 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              resultados.innerHTML = '';
              if (!data.resultados || !data.resultados.length) {
                resultados.innerHTML = '<div class="busqueda-sin-resultados">Sin resultados</div>';
                return;
              }
              var ul = document.createElement('ul');
              ul.className = 'busqueda-lista';
              data.resultados.forEach(function (t) {
                var li = document.createElement('li');
                li.textContent = t.nombre + (t.homoclave ? ' (' + t.homoclave + ')' : '');
                li.addEventListener('click', function () {
                  elegirTramiteImpacto(t.id, t.nombre);
                });
                ul.appendChild(li);
              });
              resultados.appendChild(ul);
            });
          }, 300);
        });

        window.elegirTramiteImpacto = function (id, nombre) {
          idInput.value = id;
          buscador.value = nombre;
          nombreInput.value = nombre;
          resultados.innerHTML = '';
          // Cargar los requisitos del trámite elegido.
          fetch('{{ url("api/tramites") }}/' + id + '/detalle', {
            headers: { 'Accept': 'application/json' }
          })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            requisitoSelect.innerHTML = '<option value="">— Registro en general —</option>';
            if (d.requisitos && d.requisitos.length) {
              d.requisitos.forEach(function (req) {
                var opt = document.createElement('option');
                opt.value = req.id;
                opt.textContent = req.nombre;
                requisitoSelect.appendChild(opt);
              });
              requisitoWrap.style.display = '';
            } else {
              requisitoWrap.style.display = 'none';
            }
          });
        };
      })();
      </script>
    @endif
  </div>
</div>