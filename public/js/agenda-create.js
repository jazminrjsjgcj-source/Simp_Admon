/*
 * agenda-create.js
 * Lógica del formulario de acciones de agenda (wizard de 6 pasos).
 *
 * Depende de window.PUNTA inyectado por la vista antes de este script:
 *   - window.PUNTA.apiTramitesBuscar    → ruta para búsqueda de trámites
 *   - window.PUNTA.apiTramiteDetalle    → base URL de detalle de trámite
 *   - window.PUNTA.apiHomoclavePrevisualizar → ruta para previsualizar homoclave
 */

(function () {
  var paso = 1;
  var caminoNuevo = false;
  var ULTIMO = 6;

  // ¿El paso forma parte del recorrido actual? El paso 2 (Trámite) solo si es camino nuevo.
  function pasoAplica(n) {
    if (n === 2) return caminoNuevo;
    return n >= 1 && n <= ULTIMO;
  }

  // Oculta del stepper el paso Trámite cuando no aplica.
  function ajustarStepperVisible() {
    document.querySelectorAll('[data-opcional="tramite"]').forEach(function (s) {
      s.style.display = caminoNuevo ? '' : 'none';
    });
  }

  function pintarStepper(n) {
    document.querySelectorAll('[data-step]').forEach(function (s) {
      var d = parseInt(s.dataset.step);
      var completo = d < n && pasoAplica(d);
      s.classList.toggle('active', d === n);
      s.classList.toggle('done', completo);
      s.classList.toggle('completed', completo);
    });
  }
  function mostrar(n) {
    document.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('activo', parseInt(p.dataset.panel) === n);
    });
    pintarStepper(n);
    paso = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  window.wzNav = function (dir) {
    if (dir > 0 && !validar(paso)) return;
    var n = paso + dir;
    // Saltar pasos que no aplican (ej. Trámite cuando es camino existente).
    while (n >= 1 && n <= ULTIMO && !pasoAplica(n)) { n += dir; }
    if (n >= 1 && n <= ULTIMO) mostrar(n);
  };

  // Muestra error inline en el paso activo en lugar de alert()
  function mostrarErrorPaso(msg) {
    var panel = document.querySelector('.wizard-panel.active, .wizard-step-panel.active, [data-paso="' + paso + '"]');
    var existente = document.getElementById('wzErrorMsg');
    if (existente) existente.remove();
    var div = document.createElement('div');
    div.id = 'wzErrorMsg';
    div.style.cssText = 'background:#fef2f2;border:1px solid #fca5a5;border-left:4px solid #ef4444;border-radius:8px;padding:10px 14px;margin-bottom:12px;color:#991b1b;font-size:13px;font-weight:600';
    div.textContent = msg;
    if (panel) panel.prepend(div);
    else document.querySelector('.wizard-body, form').prepend(div);
    setTimeout(function(){ if(div.parentNode) div.remove(); }, 5000);
  }

  // Limpia el error al avanzar exitosamente
  function limpiarErrorPaso() {
    var existente = document.getElementById('wzErrorMsg');
    if (existente) existente.remove();
  }

  // Toggle campo áreas participantes
  function toggleAreasDetalle(val) {
    var wrap = document.getElementById('areasDetalleWrap');
    if (wrap) wrap.style.display = (parseInt(val) > 1) ? '' : 'none';
  }

  function validar(n) {
    limpiarErrorPaso();
    if (n === 1) {
      if (!document.getElementById('modoTramite').value) {
        mostrarErrorPaso('Elija si el trámite ya existe o si se registrará desde cero.');
        return false;
      }
    }
    if (n === 2 && caminoNuevo) {
      var nom = document.querySelector('[name="tramite_nombre_oficial"]');
      var dep = document.querySelector('[name="tramite_dependencia_id"]');
      if (!nom.value.trim()) { mostrarErrorPaso('El nombre del trámite es obligatorio.'); nom.focus(); return false; }
      if (!dep.value) { mostrarErrorPaso('La dependencia del trámite es obligatoria.'); dep.focus(); return false; }

      // Rangos: rechazar datos imposibles (volumen y plazo) en el propio paso,
      // usando los topes de la fuente única (window.PUNTA.topes / config).
      var errRango = validarRangoTramite();
      if (errRango) { mostrarErrorPaso(errRango); return false; }
    }
    // Paso 3 (Alcance): debe elegirse una de las tres opciones antes de avanzar.
    if (n === 3) {
      if (!document.getElementById('alcanceCampo').value) {
        mostrarErrorPaso('Seleccione el alcance del registro (simplificación, digitalización o ambas).');
        return false;
      }
    }
    if (n === 4) {
      var desc = document.querySelector('[name="descripcion"]');
      if (!desc.value || desc.value.trim().length < 10) {
        mostrarErrorPaso('El objetivo de la simplificación es obligatorio (mínimo 10 caracteres).');
        desc.focus(); return false;
      }
    }
    return true;
  }

  // Valida los topes numéricos del trámite nuevo (volumen y plazo) contra los
  // límites de window.PUNTA.topes (config/punta.php, misma fuente que el
  // backend). Devuelve un mensaje de error, o null si todo está en rango.
  function validarRangoTramite() {
    var topes = (window.PUNTA && window.PUNTA.topes) || {};

    var vol = document.querySelector('[name="tramite_volumen_anual"]');
    if (vol && vol.value && topes.volumen_anual !== undefined) {
      if (parseFloat(vol.value) > topes.volumen_anual) {
        vol.focus();
        return 'El volumen anual supera el máximo permitido (' + topes.volumen_anual + '). Verifique el dato.';
      }
    }

    var plazo = document.querySelector('[name="tramite_plazo_resolucion_cantidad"]');
    var unidadSel = document.querySelector('[name="tramite_plazo_resolucion_unidad"]');
    if (plazo && plazo.value && topes.plazo_anios !== undefined) {
      var unidad = unidadSel ? unidadSel.value : 'anios';
      var topePlazo = unidad === 'anios' ? topes.plazo_anios
                    : unidad === 'meses' ? topes.plazo_anios * 12
                    : topes.plazo_anios * 365;
      if (parseFloat(plazo.value) > topePlazo) {
        plazo.focus();
        return 'El plazo de resolución supera el máximo permitido (' + topePlazo + '). Verifique el dato.';
      }
    }
    return null;
  }

  // ---- Camino (paso 1) ----
  window.elegirCamino = function (cual) {
    caminoNuevo = false; // Camino B eliminado: siempre es 'existente'.
    document.getElementById('modoTramite').value = 'existente';
    var opcEx = document.getElementById('opcExistente');
    if (opcEx) opcEx.classList.add('sel');
    var buscar = document.getElementById('bloqueBuscar');
    if (buscar) buscar.style.display = '';
    var sub = document.getElementById('precargaSub');
    if (sub) sub.textContent = 'Estos datos se precargan del trámite seleccionado.';
    ajustarStepperVisible();
  };

  // ---- Alcance ----
  // Paquete 3: al marcar una acción, abre y habilita su campo de explicación;
  // al desmarcarla, lo oculta, lo limpia y lo deshabilita (un textarea
  // deshabilitado no se envía, así el JSON solo lleva las acciones marcadas).
  window.toggleAccionExp = function (chk) {
    var exp = document.getElementById(chk.dataset.target);
    if (!exp) return;
    exp.style.display = chk.checked ? '' : 'none';
    var ta = exp.querySelector('textarea');
    if (ta) {
      ta.disabled = !chk.checked;
      if (!chk.checked) ta.value = '';
    }
  };

  window.elegirAlcance = function (el) {
    document.querySelectorAll('[data-alcance]').forEach(function (o) { o.classList.remove('sel'); });
    el.classList.add('sel');
    var alc = el.dataset.alcance;
    document.getElementById('alcanceCampo').value = alc;
    aplicarAlcance(alc);
  };

  // Paquete 3: muestra solo los bloques que corresponden al alcance elegido.
  window.aplicarAlcance = function (alc) {
    var verSimp = (alc === 'simplificacion' || alc === 'ambas');
    var verDig  = (alc === 'digitalizacion' || alc === 'ambas');
    var bSimp = document.getElementById('bloqueSimplificacion');
    var bDig  = document.getElementById('bloqueDigitalizacion');
    var checks = document.querySelector('.wz-checks');
    if (bSimp) bSimp.style.display = verSimp ? '' : 'none';
    if (bDig)  bDig.style.display  = verDig  ? '' : 'none';
    // Los checkboxes genéricos de mejora solo aplican a digitalización.
    if (checks) checks.style.display = verDig ? '' : 'none';
  };
  document.addEventListener('DOMContentLoaded', function () {
    var alc = document.getElementById('alcanceCampo');
    if (alc) aplicarAlcance(alc.value || '');
  });

  // ---- Detalle condicional Sí/No ----
  window.toggleDetalle = function (id, mostrarlo) {
    var el = document.getElementById(id);
    if (el) el.classList.toggle('visible', mostrarlo);
  };

  // ---- Búsqueda de trámite (camino A) ----
  var timer = null;
  var buscador = document.getElementById('buscadorTramite');
  if (buscador) {
    buscador.addEventListener('input', function () {
      clearTimeout(timer);
      var q = this.value.trim();
      var cont = document.getElementById('resultadosTramite');
      if (q.length < 2) { cont.innerHTML = ''; return; }
      cont.innerHTML = '<div class="wz-buscando">Buscando...</div>';
      timer = setTimeout(function () { buscar(q); }, 300);
    });
  }
  function buscar(q) {
    fetch((window.PUNTA && window.PUNTA.apiTramitesBuscar || '') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var cont = document.getElementById('resultadosTramite');
        if (!data.resultados || !data.resultados.length) {
          cont.innerHTML = '<div class="wz-buscando">Sin resultados.</div>';
          return;
        }
        cont.innerHTML = '';
        data.resultados.forEach(function (t) {
          var div = document.createElement('div');
          div.className = 'wz-result';
          div.innerHTML = '<strong>' + t.nombre + '</strong><span>' + (t.homoclave || 'Sin folio') + ' · ' + t.dependencia + '</span>';
          div.onclick = function () { elegirTramite(t); };
          cont.appendChild(div);
        });
      })
      .catch(function () {
        document.getElementById('resultadosTramite').innerHTML = '<div class="wz-buscando">Error al buscar.</div>';
      });
  }
  window.elegirTramite = function (t) {
    document.getElementById('tramiteIdSel').value = t.id;
    document.getElementById('tramiteElegidoNombre').textContent = t.nombre + ' (' + (t.homoclave || 'sin folio') + ')';
    document.getElementById('tramiteElegido').style.display = '';
    document.getElementById('resultadosTramite').innerHTML = '';
    document.getElementById('buscadorTramite').value = '';
    // Traer el detalle completo y precargar en solo-lectura.
    precargarTramite(t.id);
  };

  function precargarTramite(id) {
    var url = (window.PUNTA && window.PUNTA.apiTramiteDetalle || '') + '/' + id + '/detalle';
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('El servidor respondió ' + r.status + ' al pedir ' + url); }
        return r.json();
      })
      .then(function (d) {
        // Mapa: name del campo en el wizard -> valor del trámite.
        var mapa = {
          'tramite_nombre_oficial': d.nombre_oficial,
          'tramite_objetivo': d.objetivo,
          'tramite_servidor_publico': d.servidor_publico,
          'tramite_volumen_anual': d.volumen_anual,
          'tramite_plazo_resolucion_cantidad': d.plazo_resolucion_cantidad,
          'tramite_plazo_resolucion_unidad': d.plazo_resolucion_unidad,
          'nivel_actual': d.nivel_digitalizacion,
          'tramite_visitas_requeridas': d.visitas_requeridas,
          'tramite_fundamento': d.normativa_nombre,
          'tramite_dirigido_a': d.dirigido_a,
          'tramite_num_areas': d.num_areas,
          'tramite_areas_participantes': d.areas_participantes,
          'tramite_tiempo_traslado_horas': d.tiempo_traslado_horas,
          'tramite_tiempo_traslado_min': d.tiempo_traslado_min,
          'tramite_tiempo_espera_horas': d.tiempo_espera_horas,
          'tramite_tiempo_espera_min': d.tiempo_espera_min,
          'tramite_tiempo_atencion_horas': d.tiempo_atencion_horas,
          'tramite_tiempo_atencion_min': d.tiempo_atencion_min,
          'tramite_copias_cantidad': d.copias_cantidad,
          'tramite_copias_precio': d.copias_precio,
          'tramite_monto_derechos': d.monto_derechos,
        };
        Object.keys(mapa).forEach(function (name) {
          var el = document.querySelector('[name="' + name + '"]');
          if (el && mapa[name] != null) {
            el.value = mapa[name];
            el.setAttribute('readonly', 'readonly');
            el.classList.add('u-input-readonly');
            if (el.tagName === 'SELECT') el.setAttribute('disabled', 'disabled');
          }
        });

        // Si el trámite tiene áreas participantes, mostrar su detalle (el
        // oninput no se dispara al asignar el valor por JS, así que lo forzamos).
        if (d.num_areas != null && parseInt(d.num_areas) > 0 && typeof toggleAreasDetalle === 'function') {
          toggleAreasDetalle(d.num_areas);
        }

        // #15: precargar Grupos de Atención Prioritaria desde el trámite.
        // El dato pertenece al trámite (Art. 19 LNETB); la agenda lo refleja
        // como solo lectura para que el enlace vea con qué prioridad cuenta
        // sin tener que editarlo. Si más adelante quiere cambiarlo, debe
        // ir al edit del trámite.
        if (Array.isArray(d.grupos_atencion)) {
          var checks = document.querySelectorAll('#agendaGruposGrid input[type="checkbox"]');
          checks.forEach(function (chk) {
            chk.checked = d.grupos_atencion.indexOf(chk.value) !== -1;
            chk.disabled = true;
            chk.closest('.check-chip').classList.add('u-input-readonly');
          });
        }

        // Requisitos heredados del trámite (solo lectura).
        var cont = document.getElementById('tramiteRequisitos');
        var lista = document.getElementById('tramiteRequisitosLista');
        if (cont && lista) {
          lista.innerHTML = '';
          if (Array.isArray(d.requisitos) && d.requisitos.length > 0) {
            d.requisitos.forEach(function (req) {
              var li = document.createElement('li');
              var nombre = document.createElement('strong');
              nombre.textContent = req.nombre || 'Requisito';
              li.appendChild(nombre);
              if (req.tipo_presentacion) {
                var tag = document.createElement('span');
                tag.className = 'requisito-detalle';
                tag.textContent = req.tipo_presentacion.charAt(0).toUpperCase() + req.tipo_presentacion.slice(1);
                li.appendChild(tag);
              }
              lista.appendChild(li);
            });
            cont.style.display = '';
          } else {
            cont.style.display = 'none';
          }
        }

        // Costo burocrático heredado del trámite (solo lectura).
        var costoWrap = document.getElementById('costoHeredado');
        if (costoWrap && d.costo) {
          var calc = document.getElementById('costoHeredadoCalculado');
          var sinCalc = document.getElementById('costoHeredadoSinCalcular');
          if (d.costo.calculado) {
            var fmt = function (n) {
              var v = parseFloat(n || 0);
              return '$' + v.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };
            document.getElementById('chCbd').textContent = fmt(d.costo.cbd_directo);
            document.getElementById('chCbi').textContent = fmt(d.costo.cbi_indirecto);
            document.getElementById('chCbu').textContent = fmt(d.costo.cbu_unitario);
            document.getElementById('chCbt').textContent = fmt(d.costo.cbt_total);
            var cat = (d.costo.categoria || '').charAt(0).toUpperCase() + (d.costo.categoria || '').slice(1);
            document.getElementById('chCat').textContent = cat || '—';
            if (calc) calc.style.display = '';
            if (sinCalc) sinCalc.style.display = 'none';
          } else {
            if (calc) calc.style.display = 'none';
            if (sinCalc) sinCalc.style.display = '';
          }
          costoWrap.style.display = '';
        }
      })
      .catch(function (err) {
        mostrarErrorPaso('No se pudo precargar el trámite: ' + err.message);
      });
  }

  // Marca un select mostrando el texto recibido (cuando no tenemos el id).
  window.limpiarTramite = function () {
    document.getElementById('tramiteIdSel').value = '';
    var el = document.getElementById('tramiteElegido');
    if (el) el.style.display = 'none';
    var req = document.getElementById('tramiteRequisitos');
    if (req) req.style.display = 'none';
    var costo = document.getElementById('costoHeredado');
    if (costo) costo.style.display = 'none';
    // Revertir solo-lectura de los campos del trámite.
    document.querySelectorAll('[name^="tramite_"]').forEach(function (campo) {
      campo.removeAttribute('readonly');
      campo.removeAttribute('disabled');
      campo.classList.remove('u-input-readonly');
    });
  };

  // ---- Requisitos dinámicos (paso 5) ----
  var reqIdx = 0;
  window.addReq = function () {
    var i = reqIdx++;
    var art = document.createElement('article');
    art.className = 'requirement-card';
    art.style.marginBottom = '8px';
    art.innerHTML =
      '<div class="wizard-fields">' +
        '<div class="field span-2"><label>Nombre del requisito</label><input name="requisitos[' + i + '][nombre]" placeholder="Ej. Identificación oficial"></div>' +
        '<div class="field"><label>¿Original?</label><select name="requisitos[' + i + '][original]"><option value="1">Sí</option><option value="0" selected>No</option></select></div>' +
        '<div class="field"><label>¿Copia?</label><select name="requisitos[' + i + '][copia]"><option value="1">Sí</option><option value="0" selected>No</option></select></div>' +
        '<div class="field span-2"><label>Tiempo de recolección del requisito</label>' +
          '<div style="display:flex; gap:6px">' +
            '<div style="flex:1"><label class="split-label">Días háb.</label><input name="requisitos[' + i + '][dias]" type="number" min="0" max="365" value="0"></div>' +
            '<div style="flex:1"><label class="split-label">Horas</label><input name="requisitos[' + i + '][horas]" type="number" min="0" max="7" value="0"></div>' +
            '<div style="flex:1"><label class="split-label">Minutos</label><input name="requisitos[' + i + '][minutos]" type="number" min="0" max="59" value="0"></div>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.getElementById('reqLista').appendChild(art);
  };

  // ---- Guardar ----
  window.guardar = function (modo) {
    document.getElementById('accionCampo').value = modo;
    var alc = document.getElementById('alcanceCampo').value;
    if (!document.querySelector('[name="tipo"]')) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = 'tipo';
      h.value = (alc === 'digitalizacion') ? 'digitalizacion' : 'simplificacion';
      document.getElementById('wzForm').appendChild(h);
    }
    document.getElementById('wzForm').submit();
  };

  // ---- Pago de derechos: lista dinámica (alimenta el costo burocrático) ----
  var _derechos = [];
  function renderDerechos() {
    var cont = document.getElementById('derechosLista');
    if (!cont) return;
    cont.innerHTML = '';
    if (_derechos.length === 0) {
      cont.innerHTML = '<p class="u-muted" style="font-size:13px">Sin conceptos de derechos. Si el trámite no cobra derechos, déjalo vacío.</p>';
    }
    _derechos.forEach(function (d, i) {
      var fila = document.createElement('div');
      fila.className = 'derecho-fila';
      fila.style.cssText = 'display:flex; gap:8px; margin-bottom:6px;';
      fila.innerHTML =
        '<input type="text" placeholder="Concepto (ej. Derecho de inspección)" value="' + (d.concepto || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'concepto\', this.value)" style="flex:2">' +
        '<input type="number" min="0" step="0.01" placeholder="0.00" value="' + (d.monto || 0) + '" oninput="setDerecho(' + i + ', \'monto\', this.value)" style="flex:1">' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="quitarDerecho(' + i + ')">Quitar</button>';
      cont.appendChild(fila);
    });
    sincronizarDerechos();
  }
  function sincronizarDerechos() {
    var total = _derechos.reduce(function (s, d) { return s + (parseFloat(d.monto) || 0); }, 0);
    document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  }
  window.agregarDerecho = function () { _derechos.push({ concepto: '', monto: 0 }); renderDerechos(); };
  window.quitarDerecho = function (i) { _derechos.splice(i, 1); renderDerechos(); };
  window.setDerecho = function (i, campo, valor) {
    if (_derechos[i]) {
      _derechos[i][campo] = campo === 'monto' ? (parseFloat(valor) || 0) : valor;
      sincronizarDerechos();
    }
  };
  renderDerechos();

  // ---- Subsector dependiente del sector ----
  var SUBSECTORES = (window.PUNTA && window.PUNTA.subsectoresPorSector) || {};
  window.cargarSubsectores = function () {
    var sectorId = document.getElementById('selSector').value;
    var sel = document.getElementById('selSubsector');
    sel.innerHTML = '';
    var lista = SUBSECTORES[sectorId] || [];
    if (!sectorId || lista.length === 0) {
      sel.innerHTML = '<option value="">' + (sectorId ? 'Sin subsectores para este sector' : 'Primero elija un sector') + '</option>';
      sel.disabled = true;
      return;
    }
    sel.disabled = false;
    sel.innerHTML = '<option value="">Seleccione</option>';
    lista.forEach(function (s) {
      var op = document.createElement('option');
      op.value = s.id; op.textContent = s.nombre;
      sel.appendChild(op);
    });
  };

  // ---- Previsualización de homoclave (se genera de dependencia + unidad) ----
  (function previsualizarHomoclave() {
    var depInput  = document.querySelector('[name="tramite_dependencia_id"]');
    var unidadEl  = document.querySelector('[name="tramite_unidad_id"]');
    var homoclave = document.getElementById('homoclaveAgenda');
    if (!depInput || !unidadEl || !homoclave) return;
    function actualizar() {
      var depId = depInput.value, uniId = unidadEl.value;
      if (!depId || !uniId) { homoclave.value = ''; return; }
      fetch((window.PUNTA && window.PUNTA.apiHomoclavePrevisualizar || '') + '?dependencia_id=' + depId + '&unidad_id=' + uniId, {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data && data.homoclave) homoclave.value = data.homoclave; })
        .catch(function () { /* si falla, el backend la genera al guardar */ });
    }
    unidadEl.addEventListener('change', actualizar);
    actualizar();
  })();

  // B24 — Ficha del portal: muestra dirección y/o URL según la modalidad.
  window.sydToggleModalidad = function () {
    var sel = document.getElementById('sydPortalModalidad');
    var dir = document.getElementById('sydModalidadDireccion');
    var url = document.getElementById('sydModalidadUrl');
    if (!sel) return;
    var v = sel.value;
    if (dir) dir.style.display = (v === 'Presencial' || v === 'Mixta') ? '' : 'none';
    if (url) url.style.display = (v === 'En línea'   || v === 'Mixta') ? '' : 'none';
  };
  sydToggleModalidad();

  ajustarStepperVisible();
  mostrar(1);

  // ─── Retorno desde el wizard de trámites ───────────────────────────────
  // Si el usuario fue a crear un trámite completo y volvió, el blade inyecta
  // tramiteIdRetorno con el id recién creado. Lo seleccionamos como existente
  // y saltamos directo al paso 3 (Alcance) para que continúe la agenda.
  var idRetorno = (window.PUNTA && window.PUNTA.tramiteIdRetorno) || 0;
  if (idRetorno > 0) {
    elegirCamino('existente');
    document.getElementById('tramiteIdSel').value = idRetorno;
    precargarTramite(idRetorno);
    var elegido = document.getElementById('tramiteElegido');
    if (elegido) elegido.style.display = '';
    var nombre = document.getElementById('tramiteElegidoNombre');
    if (nombre) nombre.textContent = 'Trámite #' + idRetorno + ' (recién creado)';
    // Ir directo al paso 3 (Alcance), saltando la selección.
    mostrar(3);
  }
})();