{{--
  Componente: <x-citar-regulacion :citas="$citasPrevias" />

  Cita MÚLTIPLES regulaciones del catálogo, con índice de capítulos/artículos
  y respaldo manual. Expone la función global window.montarCitas() para que el
  mismo widget de citas se use también dentro de cada requisito.

  Cada cita envía:  PREFIJO[i][regulacion_id]  y  PREFIJO[i][articulo_fraccion]
  - En el trámite:   prefijo "citas"
  - En un requisito: prefijo "requisitos[i][citas]"

  Atributos:
    citas  → fundamentos previos del trámite (edición)
    label  → texto del label superior
--}}
@props([
    'citas' => [],
    'label' => 'Regulaciones que dan fundamento al trámite',
])

@php
    $regulacionesCitables = \App\Models\Regulacion::query()
        ->where('conversion_estatus', \App\Models\Regulacion::CONVERSION_LISTO)
        ->where('estatus', \App\Models\Regulacion::ESTATUS_VIGENTE)
        ->orderBy('nombre')
        ->get(['id', 'nombre', 'tipo', 'indice']);

    $citasPrevias = collect($citas)
        ->filter(fn ($c) => !empty($c->regulacion_id ?? $c['regulacion_id'] ?? null))
        ->map(fn ($c) => [
            'regulacion_id'     => $c->regulacion_id ?? $c['regulacion_id'],
            'articulo_fraccion' => $c->articulo_fraccion ?? $c['articulo_fraccion'] ?? '',
        ])
        ->values();
@endphp

<div class="field span-2">
    <label>{{ $label }}</label>
    @if($regulacionesCitables->isEmpty())
        <div class="assist-box">
            Aún no hay regulaciones convertidas en el catálogo. Puede escribir el
            fundamento manualmente en los campos siguientes.
        </div>
    @else
        {{-- Contenedor montado por la función global montarCitas --}}
        <div class="cita-widget" data-prefijo="citas" data-previas='@json($citasPrevias)'></div>
        <small class="help-small">
            Puede agregar varias. Al elegir una, el buscador se limpia para la siguiente.
            Solo se listan regulaciones vigentes con conversión completada.
        </small>
    @endif
</div>

@once
@push('scripts')
<script>
window.REGS_CITABLES = @json($regulacionesCitables);

(function () {
  var REGS = window.REGS_CITABLES || [];

  function regPorId(id) {
    return REGS.find(function (r) { return String(r.id) === String(id); });
  }
  function parseIndice(reg) {
    var idx = reg ? reg.indice : null;
    if (typeof idx === 'string') { try { idx = JSON.parse(idx); } catch (e) { idx = null; } }
    return Array.isArray(idx) ? idx : [];
  }
  function esc(s) { return (s || '').replace(/"/g, '&quot;'); }

  // Monta un widget de citas dentro de `cont`, con `prefijo` para los name.
  window.montarCitas = function (cont, prefijo, previas) {
    if (!cont) return;
    var citas = [];

    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="cita-lista"></div>' +
      '<div class="cita-buscador-wrap">' +
        '<input type="text" class="cita-buscador" placeholder="Buscar y agregar una regulación..." autocomplete="off">' +
        '<div class="cita-resultados"></div>' +
      '</div>';
    cont.appendChild(wrap);

    var lista      = wrap.querySelector('.cita-lista');
    var buscador   = wrap.querySelector('.cita-buscador');
    var resultados = wrap.querySelector('.cita-resultados');

    function filtrar() {
      var q = buscador.value.trim().toLowerCase();
      if (q === '') { resultados.innerHTML = ''; return; }
      var puestas = citas.map(function (c) { return String(c.id); });
      var l = REGS.filter(function (r) {
        return (r.nombre || '').toLowerCase().indexOf(q) >= 0 && puestas.indexOf(String(r.id)) < 0;
      }).slice(0, 8);
      if (l.length === 0) {
        resultados.innerHTML = '<div class="cita-item cita-vacio">Sin coincidencias o ya agregada</div>';
        return;
      }
      resultados.innerHTML = l.map(function (r) {
        var tipo = r.tipo ? '<span class="cita-tipo">' + r.tipo + '</span>' : '';
        return '<div class="cita-item" data-id="' + r.id + '"><span>' + r.nombre + '</span>' + tipo + '</div>';
      }).join('');
      resultados.querySelectorAll('.cita-item[data-id]').forEach(function (el) {
        el.addEventListener('click', function () { agregar(el.getAttribute('data-id')); });
      });
    }
    buscador.addEventListener('input', filtrar);

    function agregar(id, articulo) {
      var reg = regPorId(id);
      if (!reg) return;
      citas.push({ id: reg.id, nombre: reg.nombre, tipo: reg.tipo, indice: parseIndice(reg), articulo: articulo || '' });
      buscador.value = '';
      resultados.innerHTML = '';
      pintar();
    }

    function pintar() {
      if (citas.length === 0) {
        lista.innerHTML = '<div class="cita-empty">Aún no hay regulaciones agregadas</div>';
        return;
      }
      lista.innerHTML = citas.map(function (ct, i) {
        var tipo = ct.tipo ? '<span class="cita-tipo">' + ct.tipo + '</span>' : '';
        var indiceHtml;
        if (ct.indice.length) {
          var ops = '<option value="">— Elija capítulo/artículo o escriba abajo —</option>' +
            ct.indice.map(function (it) {
              var t = esc(it.titulo);
              var sel = ct.articulo === it.titulo ? ' selected' : '';
              return '<option' + sel + '>' + t + '</option>';
            }).join('');
          indiceHtml = '<select class="cita-indice-select" data-i="' + i + '">' + ops + '</select>';
        } else {
          indiceHtml = '<p class="cita-sin-idx">Sin índice disponible — escriba el artículo abajo.</p>';
        }
        return '<div class="cita-card">' +
            '<div class="cita-card-head">' +
              '<strong>' + ct.nombre + ' ' + tipo + '</strong>' +
              '<button type="button" class="cita-quitar" data-i="' + i + '">Quitar</button>' +
            '</div>' +
            indiceHtml +
            '<input type="text" class="cita-art-manual" data-i="' + i + '" placeholder="Artículo o fracción (a mano)" value="' + esc(ct.articulo) + '">' +
            '<input type="hidden" name="' + prefijo + '[' + i + '][regulacion_id]" value="' + ct.id + '">' +
            '<input type="hidden" class="cita-art-hidden" data-i="' + i + '" name="' + prefijo + '[' + i + '][articulo_fraccion]" value="' + esc(ct.articulo) + '">' +
          '</div>';
      }).join('');
      // Listeners de la lista pintada.
      lista.querySelectorAll('.cita-quitar').forEach(function (b) {
        b.addEventListener('click', function () { citas.splice(+b.getAttribute('data-i'), 1); pintar(); });
      });
      lista.querySelectorAll('.cita-indice-select').forEach(function (s) {
        s.addEventListener('change', function () { setArt(+s.getAttribute('data-i'), s.value); });
      });
      lista.querySelectorAll('.cita-art-manual').forEach(function (inp) {
        inp.addEventListener('input', function () { setArt(+inp.getAttribute('data-i'), inp.value); });
      });
    }

    function setArt(i, v) {
      citas[i].articulo = v;
      var h = lista.querySelector('.cita-art-hidden[data-i="' + i + '"]');
      if (h) h.value = v;
      var inp = lista.querySelector('.cita-art-manual[data-i="' + i + '"]');
      if (inp && inp.value !== v) inp.value = v;
    }

    // Precargar previas (edición).
    if (previas && previas.length) {
      previas.forEach(function (c) { agregar(c.regulacion_id, c.articulo_fraccion); });
    } else {
      pintar();
    }
  };

  // Montar automáticamente los widgets presentes al cargar (fundamento del
  // trámite y requisitos estáticos ya presentes en el HTML).
  document.querySelectorAll('.cita-widget, .req-citas').forEach(function (cont) {
    if (cont.getAttribute('data-montado')) return;
    cont.setAttribute('data-montado', '1');
    var prefijo = cont.getAttribute('data-prefijo') || 'citas';
    var previas = [];
    try { previas = JSON.parse(cont.getAttribute('data-previas') || '[]'); } catch (e) {}
    window.montarCitas(cont, prefijo, previas);
  });
})();
</script>
<style>
  .cita-lista { margin-bottom: 10px; }
  .cita-empty { padding: 12px; font-size: 13px; color: var(--muted); text-align: center; border: 1px dashed var(--surface-high); border-radius: var(--radius-sm); }
  .cita-buscador-wrap { position: relative; }
  .cita-buscador { width: 100%; }
  .cita-resultados { position: relative; }
  .cita-item { padding: 9px 12px; font-size: 13px; cursor: pointer; border: 0.5px solid var(--surface-high); border-top: none; display: flex; justify-content: space-between; align-items: center; background: var(--surface); }
  .cita-item:hover { background: var(--surface-low); }
  .cita-vacio { color: var(--muted); cursor: default; }
  .cita-tipo { font-size: 11px; color: var(--primary-container); background: var(--primary-fixed); padding: 1px 8px; border-radius: var(--radius-pill); font-weight: 400; }
  .cita-card { border: 0.5px solid var(--surface-high); border-radius: var(--radius-sm); padding: 10px; margin-bottom: 8px; background: var(--surface); }
  .cita-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 13px; font-weight: 500; }
  .cita-quitar { border: 0.5px solid var(--surface-high); background: var(--surface); border-radius: var(--radius-sm); padding: 3px 10px; font-size: 12px; cursor: pointer; font-weight: 400; }
  .cita-indice-select { width: 100%; margin-bottom: 6px; }
  .cita-sin-idx { font-size: 12px; color: var(--muted); margin: 0 0 6px; }
  .cita-art-manual { width: 100%; box-sizing: border-box; font-size: 13px; }
</style>
@endpush
@endonce
