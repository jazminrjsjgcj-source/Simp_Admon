{{--
  Componente: <x-citar-tramite :relacionados="$relacionadosPrevios" />

  Permite al enlace seleccionar trámites del catálogo que guardan relación
  con el trámite que está registrando (rubro 10.2 ATDT). Cada trámite
  seleccionado se guarda como relacionados[i][id] para que el controller
  lo procese y guarde en la tabla pivot tramite_relacionados.

  También mantiene el campo relacionados_detalle (texto libre) para notas
  adicionales o para trámites que no están en el catálogo.

  Atributos:
    relacionados → colección/array de trámites ya relacionados (edición)
    tramite_actual_id → ID del trámite actual para excluirlo del buscador
--}}
@props([
    'relacionados'      => [],
    'tramite_actual_id' => null,
])

@php
    // Normalizar los relacionados previos a un array plano de objetos
    // con los campos que el JS necesita para renderizar los chips.
    $previos = collect($relacionados)
        ->map(fn ($t) => [
            'id'          => $t->id ?? $t['id'] ?? null,
            'nombre'      => $t->nombre_oficial ?? $t['nombre'] ?? '',
            'homoclave'   => $t->homoclave ?? $t['homoclave'] ?? '',
            'dependencia' => $t->dependencia->nombre ?? $t['dependencia'] ?? '',
        ])
        ->filter(fn ($t) => !empty($t['id']))
        ->values();
@endphp

<div class="field span-2" id="citarTramiteWrap">
    <label>Trámites relacionados</label>

    {{-- Lista de chips de los trámites ya seleccionados --}}
    <div id="tramiteRelacionadoLista" class="cita-lista"></div>

    {{-- Buscador con autocompletado --}}
    <div class="cita-buscador-wrap" style="margin-top:8px">
        <input
            type="text"
            id="tramiteRelacionadoBuscador"
            class="cita-buscador"
            placeholder="Buscar trámite por nombre u homoclave..."
            autocomplete="off"
        >
        <div id="tramiteRelacionadoResultados" class="cita-resultados"></div>
    </div>

    {{-- Campo de notas libres (para trámites fuera del catálogo) --}}
    <x-field-help label="Notas adicionales (opcional)" style="margin-top:12px">
        <textarea
            name="relacionados_detalle"
            rows="2"
            placeholder="Ej. También requiere el Visto Bueno de Protección Civil (trámite externo no registrado en el catálogo)."
        >{{ old('relacionados_detalle') }}</textarea>
    </x-field-help>
</div>

@once
@push('scripts')
<script>
(function () {
    // ---- Datos iniciales ------------------------------------------------
    var previos       = @json($previos);
    var excluirId     = {{ $tramite_actual_id ?? 'null' }};
    var seleccionados = [];  // array de { id, nombre, homoclave, dependencia }

    // Precargar los previos (modo edición)
    previos.forEach(function (t) { seleccionados.push(t); });

    // ---- Elementos del DOM ----------------------------------------------
    var lista      = document.getElementById('tramiteRelacionadoLista');
    var buscador   = document.getElementById('tramiteRelacionadoBuscador');
    var resultados = document.getElementById('tramiteRelacionadoResultados');

    // ---- Helpers --------------------------------------------------------
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function yaSeleccionado(id) {
        return seleccionados.some(function (t) { return String(t.id) === String(id); });
    }

    // ---- Renderizar la lista de chips seleccionados ---------------------
    function renderLista() {
        // Campos hidden para enviar al controller
        var hiddens = seleccionados.map(function (t, i) {
            return '<input type="hidden" name="relacionados[' + i + '][id]" value="' + esc(t.id) + '">';
        }).join('');

        if (seleccionados.length === 0) {
            lista.innerHTML = hiddens + '<div class="cita-empty">No se han agregado trámites relacionados.</div>';
            return;
        }

        var chips = seleccionados.map(function (t, i) {
            return '<div class="cita-card">' +
                '<input type="hidden" name="relacionados[' + i + '][id]" value="' + esc(t.id) + '">' +
                '<div class="cita-card-head">' +
                    '<div style="display:flex;flex-direction:column;gap:2px;min-width:0">' +
                        '<strong style="font-size:13px;line-height:1.3">' + esc(t.nombre) + '</strong>' +
                        '<span class="cita-tipo">' + esc(t.homoclave || t.dependencia || '') + '</span>' +
                    '</div>' +
                    '<button type="button" class="cita-quitar" data-i="' + i + '">Quitar</button>' +
                '</div>' +
            '</div>';
        }).join('');

        lista.innerHTML = chips;

        // Botones Quitar
        lista.querySelectorAll('.cita-quitar').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-i'), 10);
                seleccionados.splice(idx, 1);
                renderLista();
            });
        });
    }

    // ---- Buscador con debounce -----------------------------------------
    var timer = null;

    buscador.addEventListener('input', function () {
        clearTimeout(timer);
        var q = this.value.trim();

        if (q.length < 2) {
            resultados.innerHTML = '';
            resultados.style.display = 'none';
            return;
        }

        timer = setTimeout(function () {
            fetch('{{ route("api.tramites.buscar") }}?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var items = (data.resultados || []).filter(function (t) {
                    // Excluir el trámite actual y los ya seleccionados
                    if (excluirId && String(t.id) === String(excluirId)) return false;
                    if (yaSeleccionado(t.id)) return false;
                    return true;
                });

                if (items.length === 0) {
                    resultados.innerHTML = '<div class="cita-item cita-vacio">Sin coincidencias o ya agregado.</div>';
                    resultados.style.display = 'block';
                    return;
                }

                resultados.innerHTML = items.map(function (t) {
                    var dep = t.dependencia ? '<span class="cita-tipo">' + esc(t.dependencia) + '</span>' : '';
                    var hom = t.homoclave   ? '<span class="cita-tipo">' + esc(t.homoclave) + '</span>'   : '';
                    return '<div class="cita-item" data-id="' + esc(t.id) + '" ' +
                        'data-nombre="' + esc(t.nombre) + '" ' +
                        'data-homoclave="' + esc(t.homoclave || '') + '" ' +
                        'data-dependencia="' + esc(t.dependencia || '') + '">' +
                        '<span>' + esc(t.nombre) + '</span>' + hom + dep +
                    '</div>';
                }).join('');

                resultados.style.display = 'block';

                // Click en un resultado → agregarlo
                resultados.querySelectorAll('.cita-item[data-id]').forEach(function (el) {
                    el.addEventListener('click', function () {
                        seleccionados.push({
                            id:          this.dataset.id,
                            nombre:      this.dataset.nombre,
                            homoclave:   this.dataset.homoclave,
                            dependencia: this.dataset.dependencia,
                        });
                        buscador.value = '';
                        resultados.innerHTML = '';
                        resultados.style.display = 'none';
                        renderLista();
                    });
                });
            })
            .catch(function (err) {
                console.error('[citar-tramite] Error en búsqueda:', err);
                resultados.innerHTML = '<div class="cita-item cita-vacio">Error al buscar. Intente de nuevo.</div>';
                resultados.style.display = 'block';
            });
        }, 280);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!buscador.contains(e.target) && !resultados.contains(e.target)) {
            resultados.style.display = 'none';
        }
    });

    // ---- Inicializar ----------------------------------------------------
    renderLista();

}());
</script>
@endpush
@endonce
