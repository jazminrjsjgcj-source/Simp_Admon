/*
 * horarios.js
 * Modal de horarios de atención, reutilizable (flujo de 3 pasos).
 *
 * Es una versión AUTOCONTENIDA del modal que ya vive embebido en
 * tramites-create.js: define sus propias constantes y expone sus funciones en
 * window para que los onclick del componente <x-modal-horarios> las encuentren.
 *
 * Se diseñó así a propósito para NO tocar tramites-create.js (que funciona):
 * Trámites sigue usando su lógica embebida; las vistas nuevas (Agenda) cargan
 * este archivo. El script se auto-inicializa solo si el modal existe en la
 * página, por lo que es seguro incluirlo en cualquier vista.
 *
 * DEUDA TÉCNICA CONOCIDA: esta lógica está DUPLICADA con el bloque de horarios
 * embebido en tramites-create.js (viola DRY). Es temporal: la migración de
 * Trámites a este archivo compartido está documentada en
 * outputs/DEUDA_TECNICA_horarios.md y debe hacerse en una sesión de refactor.
 *
 * Estructura persistida (idéntica a Trámites):
 *   { 'Lunes': {activo, inicio, fin}, ... }  en el input hidden #horariosJson
 *   y un resumen legible en #horarioResumen.
 */
(function () {
  // Solo opera si la página tiene el modal de horarios.
  if (!document.getElementById('modalHorarios')) return;

  var DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
  var HORA_DEFAULT_INICIO = '09:00', HORA_DEFAULT_FIN = '15:00';

  // Estado inicial: lee lo ya guardado en el hidden (si se está editando).
  var horariosData = (function () {
    var el = document.getElementById('horariosJson');
    var raw = el ? el.value : '';
    try { return raw ? JSON.parse(raw) : {}; } catch (e) { return {}; }
  })();

  // Horario base que se aplica al marcar días. Si ya hay días guardados, toma
  // el horario del primero como base; si no, usa el predeterminado.
  var horarioBase = (function () {
    var primero = DIAS.find(function (d) { return horariosData[d] && horariosData[d].activo; });
    if (primero) return { inicio: horariosData[primero].inicio, fin: horariosData[primero].fin };
    return { inicio: HORA_DEFAULT_INICIO, fin: HORA_DEFAULT_FIN };
  })();

  function setHorarioBase(campo, valor) {
    horarioBase[campo] = valor;
  }

  function toggleDiaChip(dia) {
    if (horariosData[dia] && horariosData[dia].activo) {
      horariosData[dia].activo = false;
    } else {
      horariosData[dia] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
    }
    renderHorariosUI();
  }

  function horarioPreset(tipo) {
    var dias = tipo === 'lv' ? DIAS.slice(0, 5) : tipo === 'ls' ? DIAS.slice(0, 6) : DIAS;
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

  function updateHoraDia(dia, campo, valor) {
    if (!horariosData[dia]) horariosData[dia] = { activo: true, inicio: horarioBase.inicio, fin: horarioBase.fin };
    horariosData[dia][campo] = valor;
  }

  function renderHorariosUI() {
    var baseIni = document.getElementById('horarioBaseInicio');
    var baseFin = document.getElementById('horarioBaseFin');
    if (baseIni) baseIni.value = horarioBase.inicio;
    if (baseFin) baseFin.value = horarioBase.fin;

    var chips = document.getElementById('horarioChips');
    if (chips) {
      chips.innerHTML = DIAS.map(function (dia) {
        var activo = horariosData[dia] && horariosData[dia].activo;
        return '<button type="button" class="horario-chip' + (activo ? ' activo' : '') + '" ' +
               'onclick="toggleDiaChip(\'' + dia + '\')">' + dia.substring(0, 3) + '</button>';
      }).join('');
    }

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
    var hidden = document.getElementById('horariosJson');
    if (hidden) hidden.value = JSON.stringify(horariosData);

    var resumen = '';
    if (activos.length === 7) {
      resumen = 'Lun–Dom ' + horariosData[activos[0]].inicio + '–' + horariosData[activos[0]].fin + ' hrs';
    } else if (activos.length === 5 && JSON.stringify(activos) === JSON.stringify(DIAS.slice(0, 5))) {
      resumen = 'Lun–Vie ' + horariosData['Lunes'].inicio + '–' + horariosData['Lunes'].fin + ' hrs';
    } else if (activos.length > 0) {
      resumen = activos.map(function (d) {
        return d.substring(0, 3) + ' ' + horariosData[d].inicio + '–' + horariosData[d].fin;
      }).join(', ');
    }
    var resumenEl = document.getElementById('horarioResumen');
    if (resumenEl) resumenEl.value = resumen;
    cerrarHorarios();
  }

  function abrirHorarios() {
    renderHorariosUI();
    document.getElementById('modalHorarios').classList.add('open');
  }

  function cerrarHorarios() {
    document.getElementById('modalHorarios').classList.remove('open');
  }

  // Exponer en window: los onclick del HTML del modal las invocan por nombre.
  window.setHorarioBase = setHorarioBase;
  window.toggleDiaChip  = toggleDiaChip;
  window.horarioPreset  = horarioPreset;
  window.horarioLimpiar = horarioLimpiar;
  window.updateHoraDia  = updateHoraDia;
  window.guardarHorarios = guardarHorarios;
  window.abrirHorarios  = abrirHorarios;
  window.cerrarHorarios = cerrarHorarios;
})();
