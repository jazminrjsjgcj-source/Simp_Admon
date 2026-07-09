/*
 * tramites-create.js
 * Lógica del formulario de trámites (wizard de 7 pasos).
 *
 * Depende de window.PUNTA.valorUma inyectado por la vista
 * antes de cargar este script (ver tramites/create.blade.php).
 */

// ─── Bloque 1: cálculo de costos, derechos y campos condicionales ───────────

// #8 Costo público: muestra el monto y el selector UMA/Pesos solo cuando es
// "Con precio". El monto que se guarda (costo_monto) siempre va en PESOS.
function actualizarCosto() {
  var tipo    = document.getElementById('costoTipo');
  var monto   = document.getElementById('costoMonto');
  var unidad  = document.getElementById('costoUnidad');
  var equiv   = document.getElementById('costoEquiv');
  if (!tipo || !monto || !unidad) return;

  var esGratuito = tipo.value === 'gratuito';
  var esVariable = tipo.value === 'con_costo_variable';
  var conMonto   = tipo.value === 'con_costo'; // solo "Con precio" captura un monto fijo

  // El monto y la unidad solo se muestran cuando hay un precio fijo.
  // Gratuito y "Con precio variable" no llevan monto.
  monto.style.display  = conMonto ? '' : 'none';
  unidad.style.display = conMonto ? '' : 'none';

  if (!conMonto) {
    monto.value = 0;
    if (equiv) equiv.textContent = '';
  }

  var valorCapturado = parseFloat(monto.value) || 0;
  var esUma = unidad.value === 'UMA';
  // El monto en pesos: si es UMA, convertir con el valor vigente.
  var valorPesos = esUma ? (valorCapturado * (typeof VALOR_UMA !== 'undefined' ? VALOR_UMA : 0)) : valorCapturado;

  // Mostrar la equivalencia cuando es UMA (solo si hay monto).
  if (equiv) {
    equiv.textContent = conMonto ? (esUma ? ('≈ $' + valorPesos.toFixed(2) + ' MXN') : 'MXN') : '';
  }

  // Texto que se guarda para el portal ciudadano.
  var texto;
  if (!tipo.value)     texto = '';               // aún no se elige "Seleccione una opción"
  else if (esGratuito) texto = 'Gratuito';
  else if (esVariable) texto = 'Costo variable';
  else                 texto = '$' + valorPesos.toFixed(2) + ' MXN';

  document.getElementById('costoTexto').value        = texto;
  document.getElementById('costoTipoHidden').value   = tipo.value;
  document.getElementById('costoMontoHidden').value  = conMonto ? valorPesos : 0;
  document.getElementById('costoUnidadHidden').value = conMonto ? unidad.value : 'pesos';
  if (typeof actualizarResumenPortal === 'function') actualizarResumenPortal();
}
document.addEventListener('DOMContentLoaded', actualizarCosto);

// Pago de derechos: lista dinámica de conceptos (concepto + monto).
var _derechos = [];

// Valor de la UMA vigente (en pesos), inyectado desde PHP para convertir
// los derechos en UMA a pesos al mostrar el total. La fórmula real vive en
// PHP; esto es solo para la vista previa del total.
var VALOR_UMA = (window.PUNTA && window.PUNTA.valorUma) || 0;

// Convierte el monto de un derecho a pesos según su unidad.
function derechoEnPesos(d) {
  var m = parseFloat(d.monto) || 0;
  return (d.unidad === 'UMA') ? m * VALOR_UMA : m;
}

function renderDerechos() {
  var cont = document.getElementById('derechosLista');
  if (!cont) return;
  cont.innerHTML = '';

  if (_derechos.length === 0) {
    cont.innerHTML = '<p class="derechos-vacio">Sin conceptos de derechos. Si el trámite no cobra derechos, déjalo vacío.</p>';
  }

  _derechos.forEach(function (d, i) {
    var wrap = document.createElement('div');
    wrap.className = 'derecho-wrap';
    var esUma = d.unidad === 'UMA';
    var equiv = esUma ? (' ≈ $' + derechoEnPesos(d).toFixed(2)) : '';
    var tieneFj = !!(d.fj_norma || d.fj_capitulo || d.fj_articulo);
    wrap.innerHTML =
      '<div class="derecho-fila">' +
        '<input type="text" placeholder="Concepto (ej. Derecho de inspección)" value="' + (d.concepto || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'concepto\', this.value)">' +
        '<input type="number" min="0" step="0.01" placeholder="0.00" value="' + (d.monto || 0) + '" oninput="setDerecho(' + i + ', \'monto\', this.value)">' +
        '<select onchange="setDerecho(' + i + ', \'unidad\', this.value)">' +
          '<option value="pesos"' + (esUma ? '' : ' selected') + '>Pesos</option>' +
          '<option value="UMA"' + (esUma ? ' selected' : '') + '>UMA</option>' +
        '</select>' +
        '<label><input type="checkbox"' + (d.es_variable ? ' checked' : '') + ' onchange="setDerecho(' + i + ', \'es_variable\', this.checked)"> Variable</label>' +
        '<span class="derecho-equiv">' + equiv + '</span>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="quitarDerecho(' + i + ')">Quitar</button>' +
      '</div>' +
      '<div class="fj-bloque" style="margin-top:6px">' +
        '<label class="fj-pregunta">¿Este derecho tiene fundamento jurídico? *</label>' +
        '<div class="fj-radios">' +
          '<label><input type="radio" name="der_fj_' + i + '" value="1"' + (tieneFj ? ' checked' : '') + ' onchange="setDerechoFj(' + i + ', true); toggleFjRadio(this)"> Sí</label>' +
          '<label><input type="radio" name="der_fj_' + i + '" value="0"' + (tieneFj ? '' : ' checked') + ' onchange="setDerechoFj(' + i + ', false); toggleFjRadio(this)"> No</label>' +
        '</div>' +
        '<div class="fj-campos fj-linea" style="display:' + (tieneFj ? '' : 'none') + '">' +
          '<div><label>Ley o reglamento</label><input value="' + (d.fj_norma || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'fj_norma\', this.value)" placeholder="Ej. Ley de Hacienda"></div>' +
          '<div><label>Capítulo</label><input value="' + (d.fj_capitulo || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'fj_capitulo\', this.value)" placeholder="Ej. Cap. II"></div>' +
          '<div><label>Artículo</label><input value="' + (d.fj_articulo || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'fj_articulo\', this.value)" placeholder="Ej. Art. 45"></div>' +
        '</div>' +
      '</div>';
    cont.appendChild(wrap);
  });

  // Recalcular total (en pesos, convirtiendo UMA) y sincronizar el hidden.
  var total = _derechos.reduce(function (s, d) { return s + derechoEnPesos(d); }, 0);
  document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
  document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  sincronizarMontoDerechos(total);
  toggleMontoReferencia(); // muestra/oculta "Monto de derechos" según haya derechos
}

// Refleja el total de derechos en el campo (solo lectura) del paso de
// costos burocráticos, para que el usuario vea de dónde sale.
function sincronizarMontoDerechos(total) {
  var campo = document.getElementById('montoDerechosCalc');
  if (campo) campo.value = total.toFixed(2);
  actualizarResumenPortal();
}

// Copia el costo público y los derechos (capturados en Operación) al
// resumen de solo lectura de la Ficha Portal (paso 6).
function actualizarResumenPortal() {
  // Resumen del costo público.
  var resCosto = document.getElementById('costoPublicoResumen');
  var tipo = document.getElementById('costoTipoHidden');
  var monto = document.getElementById('costoMontoHidden');
  if (resCosto && tipo) {
    if (tipo.value === 'con_costo') {
      var v = parseFloat(monto ? monto.value : 0) || 0;
      resCosto.value = 'Con costo: $' + v.toFixed(2) + ' MXN';
    } else {
      resCosto.value = 'Gratuito';
    }
  }
  // Resumen de los derechos.
  var resDer = document.getElementById('derechosResumen');
  if (resDer) {
    if (!_derechos || _derechos.length === 0) {
      resDer.innerHTML = '<p class="derechos-vacio">Sin conceptos de derechos.</p>';
    } else {
      var html = '';
      var total = 0;
      _derechos.forEach(function (d) {
        var m = derechoEnPesos(d);
        total += m;
        var etiqueta = (d.concepto || 'Sin concepto').replace(/</g, '&lt;');
        if (d.unidad === 'UMA') etiqueta += ' <small>(' + (parseFloat(d.monto) || 0) + ' UMA)</small>';
        if (d.es_variable) etiqueta += ' <small>(variable)</small>';
        html += '<div style="display:flex;justify-content:space-between;padding:2px 0">' +
                '<span>' + etiqueta + '</span>' +
                '<strong>$' + m.toFixed(2) + '</strong></div>';
      });
      html += '<div style="display:flex;justify-content:space-between;border-top:1px solid var(--surface-high);margin-top:4px;padding-top:4px">' +
              '<span>Total</span><strong>$' + total.toFixed(2) + ' MXN</strong></div>';
      resDer.innerHTML = html;
    }
  }
}

// Muestra el campo de dirección y/o URL según la modalidad elegida.
function toggleModalidadCampos() {
  var sel = document.getElementById('portalModalidad');
  var dir = document.getElementById('modalidadDireccion');
  var url = document.getElementById('modalidadUrl');
  if (!sel) return;
  var v = sel.value;
  if (dir) dir.style.display = (v === 'Presencial' || v === 'Mixta') ? '' : 'none';
  if (url) url.style.display = (v === 'En línea'   || v === 'Mixta') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleModalidadCampos);

// Ítem F: la etapa de operación solo aplica a personas morales.
function toggleEtapaOperacion() {
  var sel  = document.querySelector('select[name="dirigido_a"]');
  var wrap = document.getElementById('etapaOperacionWrap');
  if (!sel || !wrap) return;
  wrap.style.display = (sel.value === 'moral' || sel.value === 'ambas') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', function () {
  var sel = document.querySelector('select[name="dirigido_a"]');
  if (sel) sel.addEventListener('change', toggleEtapaOperacion);
  toggleEtapaOperacion();
});

// Ítem E: muestra la base de cálculo solo cuando el pago de derechos es variable.
// Bug #B11: también oculta el campo "Monto de derechos" y muestra un aviso
// explicativo cuando es variable (para evitar el confuso $0.00).
function toggleMontoReferencia() {
  // Auto-detecta si ALGÚN derecho es variable (checkbox por derecho).
  // Si hay al menos uno → muestra aviso + referencia, oculta monto fijo.
  var hayDerechos = _derechos.length > 0;
  var hayVariable = _derechos.some(function (d) { return d.es_variable; });
  var hidden   = document.getElementById('montoVariableChk');
  var wrap     = document.getElementById('montoReferenciaWrap');
  var fijo     = document.getElementById('montoDerechosFijoWrap');
  var aviso    = document.getElementById('montoDerechosVariableAviso');
  if (hidden) hidden.value = hayVariable ? '1' : '0';
  // "Monto de derechos" solo se muestra si hay derechos y ninguno es variable.
  // Sin derechos no tiene sentido mostrar $0.00.
  if (fijo)   fijo.style.display  = (hayDerechos && !hayVariable) ? '' : 'none';
  if (wrap)   wrap.style.display  = hayVariable ? '' : 'none';
  if (aviso)  aviso.style.display = hayVariable ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleMontoReferencia);

// Ítem A: mostrar campo de trámites relacionados solo cuando el tipo de
// relación no es "Ninguna" (rubro 10.2 del instrumento ATDT).
function toggleRelacionados() {
  // Art. 29, fracción VI LNETB: si el tipo de relación es distinto de
  // "Ninguna", mostrar el detalle de trámites relacionados.
  var sel = document.querySelector('select[name="tipo_relacion"]');
  var wrap = document.getElementById('relacionadosDetalleWrap');
  if (!wrap) return;
  wrap.style.display = (sel && sel.value !== 'Ninguna' && sel.value !== '') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', function () {
  toggleRelacionados();
});
function toggleAreasParticipantes() {
  var n = document.getElementById('numAreas');
  var wrap = document.getElementById('areasParticipantesWrap');
  if (!n || !wrap) return;
  wrap.style.display = (parseInt(n.value, 10) > 0) ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', function () {
  var n = document.getElementById('numAreas');
  if (n) n.addEventListener('input', toggleAreasParticipantes);
  toggleAreasParticipantes();
});

function agregarDerecho() {
  _derechos.push({ concepto: '', monto: 0, unidad: 'pesos', es_variable: false });
  renderDerechos();
}

function quitarDerecho(i) {
  _derechos.splice(i, 1);
  renderDerechos();
  toggleMontoReferencia(); // re-evaluar si queda algún variable
}

function setDerecho(i, campo, valor) {
  if (_derechos[i]) {
    if (campo === 'monto') {
      _derechos[i][campo] = parseFloat(valor) || 0;
    } else {
      _derechos[i][campo] = valor;
    }
    // Cambiar la unidad re-renderiza para refrescar la equivalencia en pesos.
    if (campo === 'unidad') {
      renderDerechos();
      return;
    }
    // Para monto/concepto/variable: recalcular total sin re-render (no perder foco).
    var total = _derechos.reduce(function (s, d) { return s + derechoEnPesos(d); }, 0);
    document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
    sincronizarMontoDerechos(total);
    // Auto-detectar si algún derecho es variable → mostrar/ocultar aviso.
    if (campo === 'es_variable') toggleMontoReferencia();
  }
}

// Cuando el derecho elige "No tiene fundamento", limpia los 3 campos.
function setDerechoFj(i, tiene) {
  if (_derechos[i] && !tiene) {
    _derechos[i].fj_norma = '';
    _derechos[i].fj_capitulo = '';
    _derechos[i].fj_articulo = '';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  try {
    var inicial = JSON.parse(document.getElementById('derechosJson').value || '[]');
    if (Array.isArray(inicial)) _derechos = inicial;
  } catch (e) { _derechos = []; }
  renderDerechos();
});

// ─── Bloque 2: navegación del wizard y wizard de pasos ──────────────────────

(function () {
  var cur = 1, total = 7;

  /**
   * Previsualiza la homoclave en vivo a partir de la dependencia (fija
   * del perfil) y la unidad administrativa seleccionada. Llama al
   * endpoint que arma el formato LPZ-(siglas dep)-(siglas unidad)-(N).
   */
  (function previsualizarHomoclave() {
    function init() {
      var depInput   = document.querySelector('[name="dependencia_id"]');
      var unidadEl   = document.querySelector('[name="unidad_id"]');
      var natInput   = document.querySelector('[name="naturaleza"]');
      var homoclave  = document.getElementById('homoclave_input');
      if (!depInput || !unidadEl || !homoclave) return;

      // Copia el valor a la homoclave del paso 1 Y a la homoclave pública del
      // portal (paso 6). El transitorio "Generando…" no se copia al portal.
      function setHomoclave(val) {
        homoclave.value = val;
        var portalHc = document.getElementById('portalHomoclavePublica');
        if (portalHc) portalHc.value = (val === 'Generando…') ? '' : val;
      }

      function actualizar() {
        var depId = depInput.value;
        var uniId = unidadEl.value;
        if (!depId || !uniId) {
          setHomoclave('');
          return;
        }
        var naturaleza = (natInput && natInput.value) || 'tramite';
        setHomoclave('Generando…');
        fetch('/api/homoclave/previsualizar?dependencia_id=' + encodeURIComponent(depId)
              + '&unidad_id=' + encodeURIComponent(uniId)
              + '&naturaleza=' + encodeURIComponent(naturaleza), {
          headers: { 'Accept': 'application/json' }
        })
          .then(function (r) { return r.json().catch(function () { return null; }); })
          .then(function (data) {
            setHomoclave((data && data.homoclave) ? data.homoclave : '');
          })
          .catch(function () { setHomoclave(''); });
      }

      // Recalcula al cambiar unidad o dependencia.
      unidadEl.addEventListener('change', actualizar);
      depInput.addEventListener('change', actualizar);

      // Recalcula al cambiar la naturaleza. Como es un hidden que fija el
      // selector "¿Qué va a registrar?", escuchamos el clic en cada opción: el
      // clic de la tarjeta corre elegirNaturaleza() primero (fija el valor) y
      // luego este handler, así que ya lee la naturaleza actualizada.
      ['optTramite', 'optServicio'].forEach(function (id) {
        var opt = document.getElementById(id);
        if (opt) opt.addEventListener('click', actualizar);
      });
      // Hook global por si otro código necesita forzar el recálculo.
      window.recalcularHomoclave = actualizar;

      // Dispara al cargar (cubre el caso de unidad auto-seleccionada).
      actualizar();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  })();

  // Reglas de validación por paso. Cada regla puede tener:
  //   name  → atributo name del campo
  //   label → etiqueta legible para el mensaje
  //   max   → (opcional) clave del tope en window.PUNTA.topes; si el valor
  //           capturado supera ese tope, se marca error de rango. Los topes
  //           viven en config/punta.php (fuente única compartida con backend).
  //   maxUnidad → (opcional, solo plazo) name del select de unidad que ajusta
  //           el tope: años tal cual, meses x12, días x365.
  var required = {
    1: [
      { name: 'nombre_oficial',         label: 'Nombre oficial', maxlen: 500 },
      { name: 'tipo_tramite_id',        label: 'Tipo de trámite' },   // oculto si es servicio → se salta
      { name: 'tipo_servicio',          label: 'Tipo de servicio' },  // oculto si es trámite → se salta
      { name: 'unidad_id',              label: 'Unidad administrativa' },
      { name: 'servidor_publico',       label: 'Servidor público responsable', maxlen: 255 },
    ],
    2: [
      { name: 'objetivo',               label: 'Objetivo del trámite', maxlen: 2000 },
      { name: 'dirigido_a',             label: '¿A quién va dirigido?' },
      { name: 'etapa_operacion',        label: 'Etapa de operación' },  // oculto si no es persona moral → se salta
      { name: 'frecuencia',             label: 'Frecuencia' },
      { name: 'volumen_anual',          label: 'Volumen anual estimado', max: 'volumen_anual', min: 0 },
      { name: 'sector_id',              label: 'Sector económico' },
      { name: 'subsector_id',           label: 'Subsector económico' },  // deshabilitado hasta elegir sector → se salta
    ],
    3: [
      { name: 'poblacion_objetivo',     label: 'Población objetivo' },
      { name: 'plazo_resolucion_cantidad',label: 'Plazo de resolución', max: 'plazo_anios', maxUnidad: 'plazo_resolucion_unidad', min: 0 },
      { name: 'tiempo_traslado_min',    label: 'Minutos de traslado', max: 'minutos', min: 0 },
      { name: 'tiempo_espera_min',      label: 'Minutos de espera', max: 'minutos', min: 0 },
      { name: 'tiempo_atencion_min',    label: 'Minutos de atención', max: 'minutos', min: 0 },
      { name: 'copias_cantidad',        label: 'Número de copias', max: 'copias', min: 0 },
      { name: 'copias_precio',          label: 'Precio por copia', min: 0 },
      { name: 'num_areas',              label: 'Número de áreas', max: 'num_areas', min: 0 },
      { name: 'visitas_requeridas',     label: 'Visitas requeridas', max: 'visitas', min: 0 },
    ],
    4: [],
    5: [],
    6: [
      { name: 'portal_nombre_ciudadano', label: 'Nombre ciudadano' },
      { name: 'portal_resultado',        label: 'Resultado que se obtiene' },
      { name: 'portal_modalidad',        label: 'Modalidad de atención' },
      { name: 'portal_direccion',        label: 'Dirección' },   // condicional: solo visible en Presencial/Mixta
      { name: 'portal_url',              label: 'URL en línea' }, // condicional: solo visible en En línea/Mixta
      { name: 'portal_descripcion',      label: 'Descripción' },
      { name: 'portal_horario',          label: 'Horario de atención' },
      { name: 'portal_documento_obtiene',label: 'Documento que obtiene' },
      { name: 'portal_canal_principal',  label: 'Canal principal de atención' },
      { name: 'portal_medio_entrega',    label: 'Medio de entrega' },
      { name: 'portal_forma_pago',       label: 'Forma de pago' },
      { name: 'portal_vigencia',         label: 'Vigencia' },
      { name: 'portal_oficina',          label: 'Oficina' },
      { name: 'portal_casos_realizarse', label: 'Casos en que se realiza' },
      { name: 'portal_telefono',         label: 'Teléfono' },
      { name: 'portal_correo',           label: 'Correo electrónico' },
    ],
  };

  // Tope numérico efectivo de una regla, leído de la fuente única (config →
  // window.PUNTA.topes). Para el plazo, ajusta el tope según la unidad elegida.
  function topeDeRegla(r, panel) {
    var topes = (window.PUNTA && window.PUNTA.topes) || {};
    var base = topes[r.max];
    if (base === undefined || base === null) return null;
    if (r.maxUnidad) {
      var sel = panel.querySelector('[name="' + r.maxUnidad + '"]');
      var unidad = sel ? sel.value : 'anios';
      if (unidad === 'anios') return base;
      if (unidad === 'meses') return base * 12;
      return base * 365; // habiles / naturales (días)
    }
    return base;
  }

  function clearErrors(panel) {
    panel.querySelectorAll('.field-error').forEach(function(e){ e.remove(); });
    panel.querySelectorAll('.input-invalid').forEach(function(el){
      el.classList.remove('input-invalid');
      el.style.outline = ''; // limpia el marcado de contenedores dinámicos
    });
  }

  function showError(field, msg) {
    field.classList.add('input-invalid');
    var err = document.createElement('p');
    err.className = 'field-error';
    err.textContent = msg;
    err.style.cssText = 'color:#dc2626;font-size:12px;margin:4px 0 0;';
    field.parentElement.appendChild(err);
  }

  // Marca en rojo un contenedor (grupos, pasos) y le agrega un mensaje debajo.
  function marcarContenedor(el, msg) {
    el.classList.add('input-invalid');
    el.style.outline = '1px solid #dc2626';
    el.style.borderRadius = '8px';
    var err = document.createElement('p');
    err.className = 'field-error';
    err.textContent = msg;
    err.style.cssText = 'color:#dc2626;font-size:12px;margin:4px 0 0;';
    el.parentElement.appendChild(err);
  }

  // Validaciones extra por paso, para campos que no son inputs simples
  // (monto condicional, colecciones dinámicas). Devuelven {ok, first}.
  var validacionesExtra = {
    3: function (panel) {
      var ok = true, first = null;

      // Costo público: debe elegirse una opción (no quedar en "Seleccione una opción").
      var costoTipo   = document.getElementById('costoTipoHidden');
      var costoSelect = document.getElementById('costoTipo');
      var costoMonto  = document.getElementById('costoMonto');
      if (!costoTipo || !costoTipo.value) {
        if (costoSelect) { showError(costoSelect, 'Seleccione el tipo de costo público.'); if (!first) first = costoSelect; }
        ok = false;
      } else if (costoTipo.value === 'con_costo') {
        // Si es "Con precio", el monto debe ser mayor a 0.
        if (!costoMonto || !(parseFloat(costoMonto.value) > 0)) {
          if (costoMonto) { showError(costoMonto, 'Ingrese el monto del costo público (mayor a 0).'); if (!first) first = costoMonto; }
          ok = false;
        }
      }

      // Nivel de digitalización: debe calcularse con el cuestionario (no queda "Sin definir").
      var nivelHidden = document.getElementById('nivelDigHidden');
      if (!nivelHidden || !nivelHidden.value) {
        var nivelSel = document.getElementById('nivelDigSelect');
        if (nivelSel) { showError(nivelSel, 'Calcule el nivel de digitalización con el cuestionario oficial.'); if (!first) first = nivelSel; }
        ok = false;
      }

      // Fundamento jurídico del costo: debe elegirse Sí o No.
      var fjPrimero = panel.querySelector('input[name="costo_fj_tiene"]');
      if (fjPrimero && fjPrimero.offsetParent !== null) {
        var fjMarcado = panel.querySelectorAll('input[name="costo_fj_tiene"]:checked').length > 0;
        if (!fjMarcado) {
          var contFj = fjPrimero.closest('.fj-bloque') || fjPrimero.closest('.field') || fjPrimero.parentElement;
          marcarContenedor(contFj, 'Indique si el costo tiene fundamento jurídico.');
          if (!first) first = contFj;
          ok = false;
        }
      }

      // Grupos de atención prioritaria: al menos uno marcado.
      var marcados = panel.querySelectorAll('input[name="grupos_atencion[]"]:checked');
      if (marcados.length === 0) {
        var algun = panel.querySelector('input[name="grupos_atencion[]"]');
        var cont  = algun ? (algun.closest('.wizard-fields') || algun.parentElement) : null;
        if (cont) { marcarContenedor(cont, 'Seleccione al menos un grupo de atención prioritaria.'); if (!first) first = cont; }
        ok = false;
      }

      // Pasos para realizar el trámite: al menos uno, y cada paso con sus dos
      // campos llenos (quién lo realiza y en qué consiste).
      var pasos = [];
      try { pasos = JSON.parse((document.getElementById('pasosJson') || {}).value || '[]'); } catch (e) { pasos = []; }
      var lista = document.getElementById('pasosLista');
      if (!Array.isArray(pasos) || pasos.length === 0) {
        if (lista) { marcarContenedor(lista, 'Agregue al menos un paso para realizar el trámite.'); if (!first) first = lista; }
        ok = false;
      } else if (pasos.some(function (p) { return !p.area || !String(p.area).trim() || !p.accion || !String(p.accion).trim(); })) {
        if (lista) { marcarContenedor(lista, 'Cada paso debe indicar quién lo realiza y en qué consiste. No deje campos vacíos.'); if (!first) first = lista; }
        ok = false;
      }

      return { ok: ok, first: first };
    },

    // ── Paso 4: Requisitos ── cada requisito presente debe estar completo.
    4: function (panel) {
      var ok = true, first = null;

      // Índices de los requisitos presentes en el DOM.
      var indices = {};
      panel.querySelectorAll('[name^="requisitos["]').forEach(function (c) {
        var m = c.name.match(/^requisitos\[(\d+)\]/);
        if (m) indices[m[1]] = true;
      });

      Object.keys(indices).forEach(function (i) {
        function campo(sub) { return panel.querySelector('[name="requisitos[' + i + '][' + sub + ']"]'); }
        function marcar(el, msg) { if (el) { showError(el, msg); if (!first) first = el; ok = false; } }

        // Nombre
        var nombre = campo('nombre');
        if (nombre && !nombre.value.trim()) marcar(nombre, 'El nombre del requisito es obligatorio.');

        // Tipo de presentación: al menos uno marcado.
        if (panel.querySelectorAll('[name="requisitos[' + i + '][tipo][]"]:checked').length === 0) {
          var pt = panel.querySelector('[name="requisitos[' + i + '][tipo][]"]');
          if (pt) { var ct = pt.closest('.field') || pt.parentElement; marcarContenedor(ct, 'Marque al menos un tipo de presentación.'); if (!first) first = ct; ok = false; }
        }

        // Tiempo de obtención: la suma de días+horas+minutos debe ser mayor a 0.
        var d  = parseInt((campo('dias')    || {}).value, 10) || 0;
        var h  = parseInt((campo('horas')   || {}).value, 10) || 0;
        var mn = parseInt((campo('minutos') || {}).value, 10) || 0;
        if (d + h + mn <= 0) marcar(campo('dias'), 'Indique el tiempo de obtención (no puede quedar en 0).');

        // Observaciones para publicación.
        var obs = campo('observaciones');
        if (obs && !obs.value.trim()) marcar(obs, 'Agregue las observaciones para publicación.');

        // Costo: si el modo es "monto fijo" (el campo de monto está visible), debe ser > 0.
        var monto = campo('costo_monto');
        if (monto && monto.offsetParent !== null && !(parseFloat(monto.value) > 0)) {
          marcar(monto, 'Ingrese el costo del requisito (mayor a 0).');
        }

        // Fundamento jurídico: Sí/No obligatorio; si es Sí, sus campos no van vacíos.
        if (panel.querySelectorAll('[name="requisitos[' + i + '][fj_tiene]"]:checked').length === 0) {
          var pf = panel.querySelector('[name="requisitos[' + i + '][fj_tiene]"]');
          if (pf) { var cf = pf.closest('.fj-bloque') || pf.closest('.field') || pf.parentElement; marcarContenedor(cf, 'Indique si el requisito tiene fundamento jurídico.'); if (!first) first = cf; ok = false; }
        } else if (panel.querySelector('[name="requisitos[' + i + '][fj_tiene]"][value="1"]:checked')) {
          ['fj_norma', 'fj_capitulo', 'fj_articulo'].forEach(function (fc) {
            var el = campo(fc);
            if (el && el.offsetParent !== null && !el.value.trim()) marcar(el, 'Complete el fundamento jurídico del requisito.');
          });
        }
      });

      return { ok: ok, first: first };
    },

    // ── Paso 5: Fundamento jurídico ── según el modo (catálogo o manual).
    5: function (panel) {
      var ok = true, first = null;
      var manualChk = document.getElementById('fundamentoManualChk');
      var esManual  = !!(manualChk && manualChk.checked);

      if (esManual) {
        // Modo manual: normativa, tipo y artículo son obligatorios.
        [['fundamento_normativa', 'Escriba la normativa de origen.'],
         ['fundamento_articulo',  'Indique el artículo o fracción.']].forEach(function (par) {
          var el = panel.querySelector('[name="' + par[0] + '"]');
          if (el && el.offsetParent !== null && !el.value.trim()) { showError(el, par[1]); if (!first) first = el; ok = false; }
        });
        var tipoSel = panel.querySelector('[name="fundamento_tipo"]');
        if (tipoSel && tipoSel.offsetParent !== null && !tipoSel.value) { showError(tipoSel, 'Seleccione el tipo de normativa.'); if (!first) first = tipoSel; ok = false; }
      } else {
        // Modo catálogo: al menos una regulación agregada.
        var agregadas = panel.querySelectorAll('#fundamentoCatalogo .cita-card').length;
        if (agregadas === 0) {
          var cont = document.getElementById('fundamentoCatalogo');
          if (cont) { marcarContenedor(cont, 'Agregue al menos una regulación del catálogo, o marque "no está en el catálogo" para escribirla a mano.'); if (!first) first = cont; }
          ok = false;
        }
      }

      return { ok: ok, first: first };
    }
  };

  function validateStep(step) {
    var panel = document.querySelector('[data-panel="'+step+'"]');
    if (!panel) return true;
    clearErrors(panel);
    var rules = required[step];
    if (!rules) return true;
    var ok = true;
    var first = null;
    rules.forEach(function(r) {
      var field = panel.querySelector('[name="'+r.name+'"]');
      if (!field) return;

      // Saltar campos que no aplican: ocultos (condicionales que no se muestran,
      // p. ej. "tipo de servicio" cuando es trámite) o deshabilitados (p. ej.
      // subsector hasta elegir sector). Así solo se exige lo que está visible.
      if (field.offsetParent === null || field.disabled) return;

      // 1) Presencia: el campo no puede quedar vacío.
      if (!field.value || !field.value.trim()) {
        showError(field, r.label + ' es obligatorio.');
        if (!first) first = field;
        ok = false;
        return;
      }

      // 2) Longitud máxima de texto (ej. nombre_oficial max 500 caracteres).
      if (r.maxlen && field.value.length > r.maxlen) {
        showError(field, r.label + ' no debe tener más de ' + r.maxlen + ' caracteres (tiene ' + field.value.length + ').');
        if (!first) first = field;
        ok = false;
        return;
      }

      // 3) Valor mínimo (ej. volumen no puede ser negativo).
      if (r.min !== undefined) {
        var val = parseFloat(field.value);
        if (!isNaN(val) && val < r.min) {
          showError(field, r.label + ' no puede ser menor que ' + r.min + '.');
          if (!first) first = field;
          ok = false;
          return;
        }
      }

      // 4) Rango máximo: si la regla define un tope, el valor no puede superarlo.
      if (r.max) {
        var tope = topeDeRegla(r, panel);
        var valor = parseFloat(field.value);
        if (tope !== null && !isNaN(valor) && valor > tope) {
          showError(field, r.label + ' supera el máximo permitido (' + tope + '). Verifique el dato.');
          if (!first) first = field;
          ok = false;
        }
      }
    });

    // Validaciones extra del paso (monto condicional, colecciones dinámicas).
    if (validacionesExtra[step]) {
      var extra = validacionesExtra[step](panel);
      if (!extra.ok) { ok = false; if (!first) first = extra.first; }
    }

    if (first && first.focus) first.focus();
    return ok;
  }

  // Registra qué pasos ya fueron completados
  var completed = {};

  function go(step) {
    document.querySelectorAll('[data-panel]').forEach(function(p){
      p.classList.toggle('active', parseInt(p.dataset.panel) === step);
    });
    document.querySelectorAll('[data-step]').forEach(function(s){
      var n = parseInt(s.dataset.step);
      var esCompleto = (completed[n] === true) && (n !== step);
      s.classList.toggle('active',    n === step);
      s.classList.toggle('done',      esCompleto);
      s.classList.toggle('completed', esCompleto);
    });
    document.getElementById('stepLabel').textContent = step;

    var btnAtras    = document.getElementById('btnAtras');
    var btnSig      = document.getElementById('btnSig');
    var btnGuardar  = document.getElementById('btnGuardar');
    var btnBorrador = document.getElementById('btnBorrador');

    // Mostrar/ocultar botones
    if (step > 1)      { btnAtras.classList.remove('hidden'); }
    else               { btnAtras.classList.add('hidden'); }
    if (step < total)  { btnSig.classList.remove('hidden'); btnGuardar.classList.add('hidden'); btnBorrador.classList.add('hidden'); }
    else               { btnSig.classList.add('hidden');    btnGuardar.classList.remove('hidden'); btnBorrador.classList.remove('hidden'); }

    // En el último paso, ocultar el botón flotante "Guardar borrador": está fijo
    // en la esquina inferior derecha (z-index alto) y se encimaba sobre "Guardar
    // y enviar", por lo que el clic caía en el flotante y guardaba como borrador.
    // En el paso 7 ya está el botón "Guardar como borrador" del pie.
    var flotanteBorrador = document.querySelector('[data-wizard-borrador]');
    if (flotanteBorrador) flotanteBorrador.style.display = (step === total) ? 'none' : '';

    cur = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  window.wizardNav = function(d) {
    // Al avanzar: validar campos requeridos del paso actual
    if (d > 0) {
      if (!validateStep(cur)) return;
      completed[cur] = true;
    }
    var n = cur + d;
    if (n >= 1 && n <= total) go(n);
  };

  // Stepper clicable: cada círculo lleva a su paso.
  //  - Hacia atrás o a un paso ya completado: navegación libre.
  //  - Hacia adelante: valida en cadena cada paso intermedio (mismo criterio
  //    que "Siguiente"); si alguno falla, se detiene ahí para mostrar el error.
  function irAPaso(n) {
    if (n === cur || n < 1 || n > total) return;

    if (n < cur || completed[n] === true) {
      go(n);
      return;
    }

    for (var s = cur; s < n; s++) {
      if (!validateStep(s)) {
        if (s !== cur) go(s); // llevar al primer paso con errores
        return;
      }
      completed[s] = true;
    }
    go(n);
  }

  document.querySelectorAll('#tramiteStepper [data-step]').forEach(function (paso) {
    paso.style.cursor = 'pointer';
    paso.addEventListener('click', function () {
      irAPaso(parseInt(paso.dataset.step, 10));
    });
  });

  // Limpiar error al escribir
  document.querySelectorAll('input, select, textarea').forEach(function(f) {
    f.addEventListener('input',  function(){ this.style.borderColor=''; var e=this.parentElement.querySelector('.field-error'); if(e) e.remove(); });
    f.addEventListener('change', function(){ this.style.borderColor=''; var e=this.parentElement.querySelector('.field-error'); if(e) e.remove(); });
  });

  // Dependencia → Unidades
  var depSel = document.getElementById('depSelect');
  var uniSel = document.getElementById('unidadSelect');
  if (depSel && uniSel) {
    var allOpts = Array.from(uniSel.querySelectorAll('option[data-dep]'));
    depSel.addEventListener('change', function() {
      var depId = this.value;
      uniSel.innerHTML = '<option value="">Seleccione unidad...</option>';
      allOpts.forEach(function(opt) {
        if (opt.dataset.dep === depId) uniSel.appendChild(opt.cloneNode(true));
      });
    });
  }

  // Requisitos dinámicos
  window.agregarRequisito = function() {
    var container = document.getElementById('reqContainer');
    var i = container.querySelectorAll('article.requirement-card').length;
    var a = document.createElement('article');
    a.className = 'requirement-card';
    a.innerHTML = '<strong class="req-titulo">Requisito '+(i+1)+'</strong>'
      +'<div class="wizard-fields">'
      +'<div class="field span-2"><label>Nombre del requisito</label>'
      +'<input name="requisitos['+i+'][nombre]" placeholder="Nombre del requisito"></div>'
      +'<div class="field"><label>Tipo de presentación</label>'
      +'<div class="tipo-pres-checks">'
      +'<label><input type="checkbox" name="requisitos['+i+'][tipo][]" value="original"> Original</label>'
      +'<label><input type="checkbox" name="requisitos['+i+'][tipo][]" value="copia"> Copia</label>'
      +'<label><input type="checkbox" name="requisitos['+i+'][tipo][]" value="digital"> Digital</label>'
      +'</div></div>'
      +'<div class="field"><label>Tiempo de obtención</label>'
      +'<div class="split-fields split-fields-labeled">'
      +'<div><label class="split-label">Días háb.</label><input name="requisitos['+i+'][dias]" type="number" min="0" max="365" value="0"></div>'
      +'<div><label class="split-label">Horas</label><input name="requisitos['+i+'][horas]" type="number" min="0" max="7" value="0"></div>'
      +'<div><label class="split-label">Minutos</label><input name="requisitos['+i+'][minutos]" type="number" min="0" max="59" value="0"></div>'
      +'</div></div>'
      +'<div class="field span-2"><label>Observaciones</label>'
      +'<textarea name="requisitos['+i+'][observaciones]" rows="2"></textarea></div>'
      +'<div class="field"><label>¿Este requisito tiene costo?</label>'
      +'<select name="requisitos['+i+'][costo_modo]" onchange="toggleCostoReq(this)">'
      +'<option value="sin">Sin costo</option>'
      +'<option value="fijo">Sí, monto fijo</option>'
      +'<option value="variable">Sí, costo variable (no cuantificable)</option>'
      +'</select></div>'
      +'<div class="field req-costo-monto" style="display:none"><label>Monto del requisito</label>'
      +'<input name="requisitos['+i+'][costo_monto]" type="number" min="0" step="0.01" value="0" placeholder="Ej. 250.00"></div>'
      +'<div class="field req-costo-monto" style="display:none"><label>Unidad del costo</label>'
      +'<select name="requisitos['+i+'][costo_unidad]"><option value="PESOS">Pesos</option><option value="UMA">UMA</option></select></div>'
      +'<div class="field span-2 fj-bloque">'
      +'<label class="fj-pregunta">¿Este requisito tiene fundamento jurídico? *</label>'
      +'<div class="fj-radios">'
      +'<label><input type="radio" name="requisitos['+i+'][fj_tiene]" value="1" required onchange="toggleFjRadio(this)"> Sí</label>'
      +'<label><input type="radio" name="requisitos['+i+'][fj_tiene]" value="0" required onchange="toggleFjRadio(this)"> No</label>'
      +'</div>'
      +'<div class="fj-campos fj-linea" style="display:none">'
      +'<div><label>Ley o reglamento</label><input name="requisitos['+i+'][fj_norma]" placeholder="Ej. Reglamento de Comercio"></div>'
      +'<div><label>Capítulo</label><input name="requisitos['+i+'][fj_capitulo]" placeholder="Ej. Cap. III"></div>'
      +'<div><label>Artículo</label><input name="requisitos['+i+'][fj_articulo]" placeholder="Ej. Art. 12"></div>'
      +'</div></div>'
      +'</div>'
      +'<div class="section-actions mt-2">'
      +'<button type="button" class="btn btn-outline btn-sm danger" onclick="eliminarRequisito(this)">Eliminar requisito</button>'
      +'</div>';
    container.appendChild(a);
  };

  // Ítem E: muestra el campo de monto solo cuando el requisito tiene costo fijo.
  // En "sin costo" y "variable" no se captura monto (el variable no es cuantificable).
  // #16: ahora son DOS campos (monto + unidad UMA/Pesos), ambos con la clase
  // .req-costo-monto, así que se muestran/ocultan juntos.
  window.toggleCostoReq = function(sel) {
    var card = sel.closest('article.requirement-card');
    if (!card) return;
    var wraps = card.querySelectorAll('.req-costo-monto');
    wraps.forEach(function (w) {
      w.style.display = (sel.value === 'fijo') ? '' : 'none';
    });
  };

  // Muestra u oculta los campos de fundamento según el radio Sí/No.
  window.toggleFjRadio = function(radio) {
    var bloque = radio.closest('.fj-bloque');
    if (!bloque) return;
    var campos = bloque.querySelector('.fj-campos');
    if (campos) campos.style.display = (radio.value === '1') ? '' : 'none';
  };

  window.eliminarRequisito = function(btn) {
    btn.closest('article').remove();
    renumerarRequisitos();
  };

  function renumerarRequisitos() {
    var cards = document.querySelectorAll('#reqContainer article.requirement-card');
    cards.forEach(function(card, idx) {
      // Actualizar título visible
      var titulo = card.querySelector('.req-titulo, strong');
      if (titulo) titulo.textContent = 'Requisito ' + (idx + 1);

      // Actualizar name attributes para mantener índices secuenciales
      card.querySelectorAll('input, select, textarea').forEach(function(input) {
        if (input.name) {
          input.name = input.name.replace(/requisitos\[\d+\]/, 'requisitos[' + idx + ']');
        }
      });
    });
  }
  // ── Fundamento de origen: catálogo o manual (excluyentes) ──────────────────
  // El catálogo conserva el buscador de MÚLTIPLES citas. El check pasa a
  // captura manual y oculta/bloquea el catálogo (y al revés). El name oculto
  // fundamento_modo viaja al servidor, que respeta el modo para no mezclar.
  window.toggleFundamentoManual = function () {
    var chk  = document.getElementById('fundamentoManualChk');
    var cat  = document.getElementById('fundamentoCatalogo');
    var man  = document.getElementById('fundamentoManual');
    var modo = document.getElementById('fundamentoModo');
    if (!chk) return;
    var manual = chk.checked;
    if (cat) cat.classList.toggle('hidden', manual);
    if (man) man.classList.toggle('hidden', !manual);
    if (modo) modo.value = manual ? 'manual' : 'catalogo';
  };
  window.toggleFundamentoManual(); // refleja el estado inicial (incl. old() tras error)

  go(1);
})();


  // ─── Fase F.4: Horarios de atención ────────────────────────────
  var DIAS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  var HORA_DEFAULT_INICIO = '09:00', HORA_DEFAULT_FIN = '15:00';
  // ─── Modal de horarios (B14): flujo de 3 pasos ───────────────────────
  // Paso 1: el usuario fija un horario BASE (apertura/cierre).
  // Paso 2: marca los días con chips; el horario base se copia a cada día.
  // Paso 3: vista previa donde puede editar el horario de un día puntual.
  // La estructura persistida no cambia: { 'Lunes': {activo, inicio, fin}, ... }
  var horariosData = (function () {
    var raw = document.getElementById('horariosJson').value;
    try { return raw ? JSON.parse(raw) : {}; } catch(e) { return {}; }
  })();

  // Horario base que se aplica al marcar días. Si ya hay días guardados, toma
  // el horario del primero como base; si no, usa el predeterminado.
  var horarioBase = (function () {
    var primero = DIAS.find(function (d) { return horariosData[d] && horariosData[d].activo; });
    if (primero) return { inicio: horariosData[primero].inicio, fin: horariosData[primero].fin };
    return { inicio: HORA_DEFAULT_INICIO, fin: HORA_DEFAULT_FIN };
  })();

  // Paso 1: cambia el horario base y lo propaga a los días YA marcados que aún
  // no tienen un horario distinto (respeta las excepciones que el usuario editó).
  function setHorarioBase(campo, valor) {
    horarioBase[campo] = valor;
  }

  // Paso 2: alterna un día. Al marcarlo, hereda el horario base actual.
  function toggleDiaChip(dia) {
    if (horariosData[dia] && horariosData[dia].activo) {
      horariosData[dia].activo = false;
    } else {
      horariosData[dia] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
    }
    renderHorariosUI();
  }

  // Accesos rápidos: marcan un conjunto de días con el horario base.
  function horarioPreset(tipo) {
    var dias = tipo === 'lv' ? DIAS.slice(0,5) : tipo === 'ls' ? DIAS.slice(0,6) : DIAS;
    DIAS.forEach(function (d) {
      if (dias.includes(d)) {
        horariosData[d] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
      } else if (horariosData[d]) {
        horariosData[d].activo = false;
      }
    });
    renderHorariosUI();
  }

  function horarioLimpiar() {
    horariosData = {};
    renderHorariosUI();
  }

  // Paso 3: edita el horario de UN día puntual (excepción), sin tocar la base.
  function updateHoraDia(dia, campo, valor) {
    if (!horariosData[dia]) horariosData[dia] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
    horariosData[dia][campo] = valor;
  }

  // Pinta los chips (paso 2) y la vista previa (paso 3) según el estado actual.
  function renderHorariosUI() {
    // Paso 1: refleja el horario base en los inputs.
    var baseIni = document.getElementById('horarioBaseInicio');
    var baseFin = document.getElementById('horarioBaseFin');
    if (baseIni) baseIni.value = horarioBase.inicio;
    if (baseFin) baseFin.value = horarioBase.fin;

    // Paso 2: chips de días.
    var chips = document.getElementById('horarioChips');
    if (chips) {
      chips.innerHTML = DIAS.map(function (dia) {
        var activo = horariosData[dia] && horariosData[dia].activo;
        return '<button type="button" class="horario-chip' + (activo ? ' activo' : '') + '" ' +
               'onclick="toggleDiaChip(\'' + dia + '\')">' + dia.substring(0,3) + '</button>';
      }).join('');
    }

    // Paso 3: vista previa, solo días marcados, cada uno editable.
    var preview = document.getElementById('horarioPreview');
    if (preview) {
      var activos = DIAS.filter(function (d) { return horariosData[d] && horariosData[d].activo; });
      if (!activos.length) {
        preview.innerHTML = '<p class="horario-preview-vacio">Marque al menos un día arriba para ver la vista previa.</p>';
      } else {
        preview.innerHTML = activos.map(function (dia) {
          var h = horariosData[dia];
          return '<div class="horario-preview-row">' +
                   '<span class="horario-preview-dia">' + dia + '</span>' +
                   '<input type="time" value="' + h.inicio + '" class="u-text-sm" ' +
                     'onchange="updateHoraDia(\'' + dia + '\',\'inicio\',this.value)">' +
                   '<span class="horario-preview-sep">–</span>' +
                   '<input type="time" value="' + h.fin + '" class="u-text-sm" ' +
                     'onchange="updateHoraDia(\'' + dia + '\',\'fin\',this.value)">' +
                 '</div>';
        }).join('');
      }
    }
  }

  function guardarHorarios() {
    var activos = DIAS.filter(function (d) { return horariosData[d] && horariosData[d].activo; });
    document.getElementById('horariosJson').value = JSON.stringify(horariosData);

    // Resumen legible para el campo de solo lectura.
    var resumen = '';
    if (activos.length === 7) {
      resumen = 'Lun–Dom ' + horariosData[activos[0]].inicio + '–' + horariosData[activos[0]].fin + ' hrs';
    } else if (activos.length === 5 && JSON.stringify(activos) === JSON.stringify(DIAS.slice(0,5))) {
      resumen = 'Lun–Vie ' + horariosData['Lunes'].inicio + '–' + horariosData['Lunes'].fin + ' hrs';
    } else if (activos.length > 0) {
      resumen = activos.map(function (d) {
        return d.substring(0,3) + ' ' + horariosData[d].inicio + '–' + horariosData[d].fin;
      }).join(', ');
    }
    document.getElementById('horarioResumen').value = resumen;
    cerrarHorarios();
  }

  function abrirHorarios() { renderHorariosUI(); document.getElementById('modalHorarios').classList.add('open'); }
  function cerrarHorarios() { document.getElementById('modalHorarios').classList.remove('open'); }


  // ─── Pasos para realizar el trámite ──────────────────────────────────
  // Cada paso: { es_subpaso: bool, area: '', accion: '' }. La numeración
  // 1, 2, 3 / 1.1, 1.2 se calcula al pintar según la posición.
  var _pasos = [];
  try { _pasos = JSON.parse(document.getElementById('pasosJson').value || '[]'); } catch (e) { _pasos = []; }

  function numeroDePaso(indice) {
    // Calcula "1", "2", "1.1"... recorriendo desde el inicio.
    var principal = 0, sub = 0;
    for (var k = 0; k <= indice; k++) {
      if (_pasos[k].es_subpaso) { sub++; }
      else { principal++; sub = 0; }
    }
    var p = _pasos[indice];
    return p.es_subpaso ? (principal + '.' + sub) : ('' + principal);
  }

  function renderPasos() {
    var cont = document.getElementById('pasosLista');
    if (!cont) return;
    cont.innerHTML = '';
    if (_pasos.length === 0) {
      cont.innerHTML = '<p class="derechos-vacio">Sin pasos. Use "Agregar paso" para enumerar el proceso.</p>';
    }
    _pasos.forEach(function (p, i) {
      var num = numeroDePaso(i);
      var etiqueta = p.es_subpaso ? ('Subpaso ' + num) : ('Paso ' + num);
      var art = document.createElement('article');
      art.className = 'requirement-card';
      // Sangría leve en subpasos para conservar la jerarquía visual (1.1 dentro de 1).
      if (p.es_subpaso) art.style.marginLeft = '28px';
      art.innerHTML =
        '<strong>' + etiqueta + '</strong>' +
        '<div class="wizard-fields">' +
          '<div class="field span-2"><label>¿Quién lo realiza? (área o responsable)</label>' +
            '<input type="text" placeholder="Ej. Ventanilla de Comercio" value="' + (p.area || '').replace(/"/g, '&quot;') + '" oninput="setPaso(' + i + ', \'area\', this.value)"></div>' +
          '<div class="field span-2"><label>¿En qué consiste este paso?</label>' +
            '<textarea rows="2" placeholder="Ej. Recibe la solicitud y verifica los documentos" oninput="setPaso(' + i + ', \'accion\', this.value)">' + (p.accion || '') + '</textarea></div>' +
        '</div>' +
        '<div class="section-actions mt-2">' +
          '<button type="button" class="btn btn-outline btn-sm danger" onclick="quitarPaso(' + i + ')">Quitar</button>' +
        '</div>';
      cont.appendChild(art);
    });
    document.getElementById('pasosJson').value = JSON.stringify(_pasos);
    actualizarBotonSubpaso();
  }

  // Habilita o deshabilita el botón "+ Agregar subpaso": un subpaso (1.1, 1.2)
  // solo tiene sentido dentro de un paso principal ya existente.
  function actualizarBotonSubpaso() {
    var btn = document.getElementById('btnAgregarSubpaso');
    if (!btn) return;
    var hayPasoPrincipal = _pasos.some(function (p) { return !p.es_subpaso; });
    btn.disabled = !hayPasoPrincipal;
    btn.title = hayPasoPrincipal ? '' : 'Primero agregue un paso';
  }

  function agregarPaso(esSubpaso) {
    // No se permite un subpaso si todavía no hay ningún paso principal.
    if (esSubpaso && !_pasos.some(function (p) { return !p.es_subpaso; })) {
      return;
    }
    _pasos.push({ es_subpaso: !!esSubpaso, area: '', accion: '' });
    renderPasos();
  }
  function setPaso(i, campo, valor) {
    if (_pasos[i]) { _pasos[i][campo] = valor; document.getElementById('pasosJson').value = JSON.stringify(_pasos); }
  }
  function quitarPaso(i) {
    _pasos.splice(i, 1);
    renderPasos();
  }
  document.addEventListener('DOMContentLoaded', renderPasos);
// ─────────────────────────────────────────────────────────────────────────
// Vista previa imprimible del formulario ANTES de guardar (acuse no oficial).
// Lee lo capturado en pantalla, lo agrupa por paso y abre una ventana lista
// para imprimir. No envía nada al servidor; los costos calculados (CBD/CBU/CBT)
// no aparecen aquí porque los calcula el servidor al guardar.
// ─────────────────────────────────────────────────────────────────────────
function vistaPreviaAcuse() {
  var form = document.getElementById('tramiteForm');
  if (!form) return;

  var nombresPaso = {
    1: 'Identificación', 2: 'Información general', 3: 'Operación y costos',
    4: 'Requisitos', 5: 'Fundamento jurídico', 6: 'Portal ciudadano'
  };

  var natInput   = form.querySelector('[name="naturaleza"]');
  var tipoTexto  = (natInput && natInput.value === 'servicio') ? 'Servicio' : 'Trámite';
  var homoclave  = (document.getElementById('homoclave_input') || {}).value || '—';
  var nombreOf   = (form.querySelector('[name="nombre_oficial"]') || {}).value || '(sin nombre)';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
    });
  }

  function labelDe(control) {
    var field = control.closest('.field');
    if (field) {
      var lbl = field.querySelector('label');
      if (lbl) return lbl.textContent.trim().replace(/\s*\*$/, '');
    }
    return control.name || '';
  }

  function valorDe(control) {
    if (control.tagName === 'SELECT') {
      var op = control.options[control.selectedIndex];
      return op ? op.textContent.trim() : '';
    }
    if (control.type === 'checkbox') return control.checked ? 'Sí' : '';
    if (control.type === 'radio') {
      if (!control.checked) return '__skip__';
      var l = control.closest('label');
      return l ? l.textContent.trim() : control.value;
    }
    return control.value;
  }

  function filasPanel(panel) {
    var filas = '', vistos = {};
    panel.querySelectorAll('input, select, textarea').forEach(function (c) {
      if (!c.name || c.type === 'hidden') return;
      if (/^requisitos\[/.test(c.name)) return;
      if (c.name === 'derechos_json' || c.name === 'derechos') return;
      var v = valorDe(c);
      if (v === '__skip__' || v === '' || v == null) return;
      if (c.type === 'radio') { if (vistos['r_' + c.name]) return; vistos['r_' + c.name] = 1; }
      filas += '<tr><td class="k">' + esc(labelDe(c)) + '</td><td class="v">' + esc(v) + '</td></tr>';
    });
    return filas;
  }

  function seccionCostoPublico() {
    var cp = (form.querySelector('[name="portal_costo_publico"]') || {}).value;
    if (!cp) return '';
    return '<table class="tb kv"><tbody><tr><td class="k">Costo público</td><td class="v">' + esc(cp) + '</td></tr></tbody></table>';
  }

  function seccionDerechos() {
    if (typeof _derechos === 'undefined' || !_derechos.length) return '';
    var filas = '';
    _derechos.forEach(function (d) {
      var monto = (d.unidad === 'UMA')
        ? ((parseFloat(d.monto) || 0) + ' UMA')
        : ('$' + (parseFloat(d.monto) || 0).toFixed(2));
      filas += '<tr><td>' + esc(d.concepto || '—') + '</td><td>' + esc(monto) +
               '</td><td>' + (d.es_variable ? 'Variable' : 'Fijo') + '</td></tr>';
    });
    return '<h3>Pago de derechos</h3><table class="tb"><thead><tr>' +
           '<th>Concepto</th><th>Monto</th><th>Tipo</th></tr></thead><tbody>' +
           filas + '</tbody></table>';
  }

  function seccionRequisitos() {
    var reqs = {};
    form.querySelectorAll('[data-panel="4"] [name^="requisitos["]').forEach(function (c) {
      var mi = c.name.match(/^requisitos\[(\d+)\]/);
      if (!mi) return;
      var i = mi[1];
      reqs[i] = reqs[i] || { tipo: [] };
      if (c.type === 'checkbox') { if (c.checked) reqs[i].tipo.push(c.value); return; }
      var mc = c.name.match(/\]\[([a-z_]+)\]/);
      if (mc) reqs[i][mc[1]] = c.value;
    });
    var filas = '';
    Object.keys(reqs).forEach(function (i) {
      var r = reqs[i];
      if (!r.nombre) return;
      filas += '<tr><td>' + esc(r.nombre) + '</td><td>' + esc(r.tipo.join(', ') || '—') +
               '</td><td>' + esc(r.observaciones || '') + '</td></tr>';
    });
    if (!filas) return '';
    return '<table class="tb"><thead><tr><th>Nombre</th><th>Presentación</th>' +
           '<th>Observaciones</th></tr></thead><tbody>' + filas + '</tbody></table>';
  }

  var cuerpo = '';
  form.querySelectorAll('[data-panel]').forEach(function (panel) {
    var num = parseInt(panel.dataset.panel, 10);
    if (num === 7) return;
    var filas = filasPanel(panel), extra = '';
    if (num === 3) extra = seccionCostoPublico() + seccionDerechos();
    if (num === 4) extra = seccionRequisitos();
    if (!filas && !extra) return;
    cuerpo += '<section><h2>' + esc(nombresPaso[num] || ('Paso ' + num)) + '</h2>';
    if (filas) cuerpo += '<table class="tb kv"><tbody>' + filas + '</tbody></table>';
    cuerpo += extra + '</section>';
  });

  var fecha = new Date().toLocaleString('es-MX');
  var html = '<!doctype html><html lang="es"><head><meta charset="utf-8">' +
    '<title>Vista previa — ' + esc(nombreOf) + '</title><style>' +
    'body{font-family:system-ui,Arial,sans-serif;color:#1a1a1a;margin:32px;font-size:13px;line-height:1.5}' +
    'h1{font-size:18px;margin:0 0 2px;color:#750038}' +
    'h2{font-size:14px;margin:18px 0 6px;border-bottom:2px solid #750038;padding-bottom:3px;color:#750038}' +
    'h3{font-size:13px;margin:12px 0 4px}.meta{color:#666;margin-bottom:16px}' +
    'table{border-collapse:collapse;width:100%;margin:4px 0}' +
    '.kv td{padding:3px 8px;vertical-align:top}.kv .k{width:34%;color:#555;font-weight:600}' +
    '.tb th,.tb td{border:1px solid #ddd;padding:5px 8px;text-align:left}.tb th{background:#f5f0f3}' +
    '.aviso{background:#fff7fb;border:1px solid #eadfe4;padding:8px 12px;border-radius:8px;margin:8px 0;font-size:12px;color:#555}' +
    '.toolbar{margin-bottom:16px}.toolbar button{background:#750038;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px}' +
    '@media print{.toolbar{display:none}}' +
    '</style></head><body>' +
    '<div class="toolbar"><button onclick="window.print()">Imprimir</button></div>' +
    '<h1>Vista previa de ' + esc(tipoTexto) + '</h1>' +
    '<div class="meta"><strong>' + esc(nombreOf) + '</strong><br>Homoclave: ' + esc(homoclave) +
    ' · Generado: ' + esc(fecha) + '</div>' +
    '<div class="aviso">Esta es una vista previa de lo capturado. <strong>No es un acuse oficial</strong> ' +
    'y aún no se ha guardado ni enviado a revisión.</div>' + cuerpo + '</body></html>';

  var w = window.open('', '_blank');
  if (!w) { alert('Permite las ventanas emergentes para ver la vista previa.'); return; }
  w.document.open();
  w.document.write(html);
  w.document.close();
}