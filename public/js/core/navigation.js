// ===============================
// PUNTA — Navegación, roles y autenticación
// Fase 2: Separado desde main.js original
// ===============================

// --- Estado de sesión ---
let selectedLoginRole = "enlace";
let currentRole = "enlace";

function roleHome(role) {
  return {
    enlace: "dashboard",
    sujeto: "dashboard",
    revisora: "dashboard",
    juridico: "dashboard",
    admin: "dashboard",
  }[role] || "dashboard";
}

function selectLoginRole(role) {
  selectedLoginRole = role;
  document.querySelectorAll("[data-role-option]").forEach((button) => {
    button.classList.toggle("selected", button.dataset.roleOption === role);
  });
}

function applyRole(role) {
  const profile = roleProfiles[role] || roleProfiles.enlace;
  const canManagePeriod = role === "admin";
  signatureDetail = null;
  dashboardActiveFilterIndex = null;
  currentRole = role;
  document.querySelector("#activeRoleLabel").textContent = profile.label;
  document.querySelector("#profileName").textContent = profile.name;
  document.querySelector("#profileInitials").textContent = profile.initials;
  document.querySelector("#profileOrg").textContent = profile.org;
  document.querySelector("#welcomeTitle").textContent = profile.title;
  document.querySelector("#welcomeSubtitle").textContent = profile.subtitle;
  document.querySelector("#periodAction").disabled = !canManagePeriod;
  document.querySelector("#periodAction").classList.toggle("is-disabled", !canManagePeriod);
  document.querySelector("#periodAction").textContent = canManagePeriod ? "Configurar periodo" : "Periodo";
  document.querySelector("#periodAction").title = canManagePeriod ? "Configurar periodo activo" : "Solo el administrador puede cambiar el periodo";
  configureRoleAccess(role);
  applyUserDependencyDefaults();
  renderProportionalityThreshold();
  renderFirmas();
}

function configureRoleAccess(role) {
  const allowed = roleAccess[role] || roleAccess.enlace;
  const canCreateRecords = role === "enlace";
  const isJuridico = role === "juridico";

  document.querySelectorAll(".nav button").forEach((button) => {
    button.classList.toggle("is-hidden", !allowed.includes(button.dataset.screen));
    const label = button.querySelector(".nowrap");
    if (label) label.textContent = roleNavLabels[role]?.[button.dataset.screen] || defaultNavLabels[button.dataset.screen] || label.textContent;
  });
  document.querySelectorAll(".quick-card").forEach((button) => {
    button.classList.toggle("is-hidden", !canCreateRecords);
  });
  document.querySelectorAll(".create-record-actions, .agenda-register").forEach((element) => {
    element.classList.toggle("is-hidden", !canCreateRecords);
  });

  const regulationPrimary = document.querySelector("#regulationPrimaryButton");
  const regulationBulk = document.querySelector("#regulationBulkButton");
  const tramitesTitle = document.querySelector("#tramitesScreenTitle");
  const tramitesSubtitle = document.querySelector("#tramitesScreenSubtitle");
  const agendaTitle = document.querySelector("#agendaScreenTitle");
  const agendaSubtitle = document.querySelector("#agendaScreenSubtitle");

  const isReviewRole = role === "revisora" || role === "sujeto";
  if (tramitesTitle) tramitesTitle.textContent = isReviewRole ? "Trámites" : "Mis trámites";
  if (tramitesSubtitle) tramitesSubtitle.textContent = isReviewRole ? "Revise trámites por estado: por revisar o revisados." : "Consulta únicamente los trámites completos de tu dependencia.";
  if (agendaTitle) agendaTitle.textContent = isReviewRole ? "Agenda" : "Agenda de Simplificación y Digitalización";
  if (agendaSubtitle) agendaSubtitle.textContent = isReviewRole ? "Revise acciones de agenda por estado: por revisar o revisadas." : "Acciones, metas, indicadores, evidencias, firmas y acuses.";

  if (regulationPrimary) {
    regulationPrimary.textContent = isJuridico ? "Registrar Normativa" : "Solicitar Normativa";
    regulationPrimary.dataset.action = isJuridico ? "showScreen('normativaNueva')" : "openModal('solicitarNormativa')";
  }
  if (regulationBulk) {
    regulationBulk.classList.toggle("is-hidden", isJuridico);
  }
  updateReviewFilterTabs();
  renderRoleDashboard();
}

function login() {
  const user = document.querySelector("#loginUser").value.trim();
  const password = document.querySelector("#loginPassword").value.trim();
  const error = document.querySelector("#loginError");

  if (!user || !password) {
    error.textContent = "Ingresa usuario y contraseña para continuar.";
    return;
  }

  error.textContent = "";
  applyRole(selectedLoginRole);
  renderRows();
  document.querySelector("#loginScreen").classList.add("is-hidden");
  document.querySelector("#systemApp").classList.remove("is-hidden");
  showScreen(roleHome(currentRole));
}

function logout() {
  document.querySelector("#systemApp").classList.add("is-hidden");
  document.querySelector("#loginScreen").classList.remove("is-hidden");
  document.querySelector("#loginPassword").value = "";
  document.querySelector("#loginError").textContent = "";
  closeModal();
}

function bindLogin() {
  document.querySelector("#loginForm").addEventListener("submit", (event) => {
    event.preventDefault();
    login();
  });
}

    function showScreen(id) {
      document.querySelectorAll(".screen").forEach(screen => screen.classList.toggle("active", screen.id === id));
      document.querySelectorAll(".nav button").forEach(button => button.classList.toggle("active", button.dataset.screen === id || (id === "agendaWizard" && button.dataset.screen === "agenda") || (id === "agendaCorrectionScreen" && button.dataset.screen === "agenda") || (id === "calendarioAgenda" && button.dataset.screen === "calendarioAgenda") || (id === "tramiteWizard" && button.dataset.screen === "tramites") || (id === "correctionScreen" && button.dataset.screen === "tramites") || (id === "normativaNueva" && button.dataset.screen === "regulaciones") || (id === "sujetoObservaciones" && button.dataset.screen === "porFirmar") || (id === "propuestaWizard" && button.dataset.screen === "agendaRegulatoria") || (id === "airWizard" && button.dataset.screen === "agendaRegulatoria") || (id === "exencionScreen" && button.dataset.screen === "agendaRegulatoria") || (id === "reviewScreen" && button.dataset.screen === (reviewContext === "agenda" ? "agenda" : "tramites"))));
      if (id === "agendaWizard") setAgendaStep(agendaStep);
      if (id === "agenda") normalizeAgendaDateColumns();
      if (id === "tramiteWizard") setTramiteStep(tramiteStep);
      if (id === "normativaNueva") setNormativaStep(normativaStep);
      if (id === "firmas") renderFirmas();
      if (id === "agendaRegulatoria") renderAgendaRegulatoriaRows();
      if (id === "calendarioAgenda") renderCalendario();
      if (id === "bitacoraAdmin") renderBitacora();
      if (id === "propuestaWizard") setPropuestaStep(1);
      if (id === "airWizard") setAirStep(1);
      applyUserDependencyDefaults(document.querySelector("#" + id) || document);
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
