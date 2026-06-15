// ===============================
// PUNTA — Action Registry Seguro
// Fase 5: Reemplaza Function(action)() por registro explícito
// ===============================

// --- Registry ---

const actionRegistry = {};

function registerAction(name, handler) {
  if (!name || typeof handler !== 'function') {
    console.warn('[ActionRegistry] Acción inválida:', name);
    return;
  }
  actionRegistry[name] = handler;
}

// --- Parser seguro ---

function parseAction(actionStr) {
  if (!actionStr) return null;
  actionStr = String(actionStr).trim();

  // Caso: "functionName" o "functionName()"
  var match = actionStr.match(/^(\w+)(?:\((.*)\))?$/s);
  if (!match) return null;

  var name = match[1];
  var argsStr = (match[2] || '').trim();

  if (!argsStr) return { name: name, args: [] };

  return { name: name, args: parseActionArgs(argsStr) };
}

function parseActionArgs(argsStr) {
  var args = [];
  var parts = argsStr.split(',');

  for (var i = 0; i < parts.length; i++) {
    var part = parts[i].trim();

    // String con comillas simples: 'valor'
    if (/^'([^']*)'$/.test(part)) {
      args.push(part.slice(1, -1));
    }
    // String con comillas dobles: "valor"
    else if (/^"([^"]*)"$/.test(part)) {
      args.push(part.slice(1, -1));
    }
    // Número
    else if (/^-?\d+(\.\d+)?$/.test(part)) {
      args.push(Number(part));
    }
    // Cualquier otra cosa no soportada
    else {
      console.warn('[ActionRegistry] Argumento no soportado:', part, 'en:', argsStr);
      args.push(part);
    }
  }

  return args;
}

// --- Ejecutor ---

function runAction(actionStr, context) {
  var parsed = parseAction(actionStr);

  if (!parsed) {
    console.warn('[ActionRegistry] No se pudo parsear:', actionStr);
    return;
  }

  var handler = actionRegistry[parsed.name];

  if (!handler) {
    console.warn('[ActionRegistry] Acción no registrada:', parsed.name, '→', actionStr);
    return;
  }

  return handler.apply(null, parsed.args);
}

// ===============================
// Registro de acciones del sistema
// ===============================

// --- Navegación ---
registerAction('showScreen', function (id) { showScreen(id); });
registerAction('login', function () { login(); });
registerAction('logout', function () { logout(); });
registerAction('selectLoginRole', function (role) { selectLoginRole(role); });

// --- Modales ---
registerAction('openModal', function (kind) { openModal(kind); });
registerAction('closeModal', function () { closeModal(); });

// --- Trámites ---
registerAction('startTramiteWizard', function () { startTramiteWizard(); });
registerAction('setTramiteStep', function (step) { setTramiteStep(step); });
registerAction('nextTramiteStep', function () { setTramiteStep(tramiteStep + 1); });
registerAction('prevTramiteStep', function () { setTramiteStep(tramiteStep - 1); });
registerAction('addProcessStep', function (toolName) { addProcessStep(toolName); });
registerAction('addRedundancy', function (listName) { addRedundancy(listName); });
registerAction('addRequirement', function (listName) { addRequirement(listName); });
registerAction('calculateBureaucraticCost', function () { calculateBureaucraticCost(); });

// --- Agenda ---
registerAction('startAgendaAction', function () { startAgendaAction(); });
registerAction('setAgendaStep', function (step) { setAgendaStep(step); });
registerAction('nextAgendaStep', function () { setAgendaStep(agendaStep + 1); });
registerAction('prevAgendaStep', function () { setAgendaStep(agendaStep - 1); });

// --- Normativa ---
registerAction('setNormativaStep', function (step) { setNormativaStep(step); });
registerAction('nextNormativaStep', function () { setNormativaStep(normativaStep + 1); });
registerAction('prevNormativaStep', function () { setNormativaStep(normativaStep - 1); });
registerAction('clearNormativaFile', function () { clearNormativaFile(); });
registerAction('addNormativaVersion', function () { addNormativaVersion(); });

// --- Revisión y observaciones ---
registerAction('startReview', function (type) { startReview(type); });
registerAction('approveReview', function () { approveReview(); });
registerAction('openSectionObservationModal', function (section) { openSectionObservationModal(section); });
registerAction('saveSectionObservation', function () { saveSectionObservation(); });
registerAction('startSujetoObservationReview', function () { startSujetoObservationReview(); });
registerAction('openSujetoObservationForm', function (section) { openSujetoObservationForm(section); });
registerAction('sendSujetoObservations', function () { sendSujetoObservations(); });

// --- Correcciones ---
registerAction('startCorrection', function (type) { startCorrection(type); });
registerAction('startAgendaCorrection', function () { startAgendaCorrection(); });

// --- Firmas ---
registerAction('signDocument', function (role) { signDocument(role); });
registerAction('approveValidation', function (type) { approveValidation(type); });
registerAction('downloadAcuse', function (type) { downloadAcuse(type); });
registerAction('downloadFichaPortal', function () { downloadFichaPortal(); });
registerAction('openSignatureDetail', function (folio) { openSignatureDetail(folio); });
registerAction('renderFirmas', function () { renderFirmas(); });

// --- Dashboard ---
registerAction('renderDashboardFilter', function (index) { renderDashboardFilter(index); });

// --- Agenda Regulatoria y AIR ---
registerAction('nextPropuestaStep', function () { nextPropuestaStep(); });
registerAction('prevPropuestaStep', function () { prevPropuestaStep(); });
registerAction('nextAirStep', function () { nextAirStep(); });
registerAction('prevAirStep', function () { prevAirStep(); });
registerAction('saveProportionalityThreshold', function () { saveProportionalityThreshold(); });
registerAction('resetProportionalityThreshold', function () { resetProportionalityThreshold(); });

// --- Calendario ---
registerAction('calendarioMesAnterior', function () { calendarioMesAnterior(); });
registerAction('calendarioMesSiguiente', function () { calendarioMesSiguiente(); });
registerAction('setCalendarioVista', function (vista) { setCalendarioVista(vista); });
registerAction('setCalendarioFiltro', function (tipo) { setCalendarioFiltro(tipo); });
registerAction('openCalendarioDetail', function (id) { openCalendarioDetail(id); });

// --- Utilidades ---
registerAction('printPage', function () { window.print(); });
