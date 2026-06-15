// ===============================
// PUNTA — Sistema de modales (coordinador)
// Templates en: js/data/modal-templates.js
// ===============================

// --- Estado ---
let currentObservationSection = "Datos generales";

// --- Renderizado ---

function modalContent(kind) {
  var profile = roleProfiles[currentRole] || roleProfiles.enlace;
  var isSessionModal = kind === "sesion";

  // Layout
  var modal = document.querySelector("#modalBackdrop .modal");
  modal.classList.toggle("modal-compact", isSessionModal);

  // Título
  document.querySelector("#modalTitle").textContent = modalTitles[kind] || "Detalle";
  document.querySelector("#modalRef").textContent = isSessionModal ? "Confirmación" : "Rol " + profile.label;

  // Cuerpo (desde modal-templates.js)
  var body = getModalBody(kind);
  document.querySelector("#modalBody").innerHTML = body || '<div class="info-card"><h4>' + (modalTitles[kind] || "Información") + '</h4><p class="modal-copy">Vista de referencia. Esta acción conserva el flujo visual del prototipo PUNTA.</p></div>';

  // Acciones de footer (desde modal-templates.js)
  document.querySelector("#modalActions").innerHTML = getModalActions(kind);
}

// --- API pública ---

function openModal(kind) {
  modalContent(kind);
  if (typeof populateDependencySelects === "function") populateDependencySelects();
  document.querySelector("#modalBackdrop").classList.add("open");
}

function closeModal() {
  document.querySelector("#modalBackdrop").classList.remove("open");
}
