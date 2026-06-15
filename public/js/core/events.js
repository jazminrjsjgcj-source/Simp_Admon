// ===============================
// PUNTA — Delegación de eventos y controles globales
// Fase 2: Separado desde main.js original
// ===============================

// --- Controles de interfaz ---
    function bindControls() {
      document.querySelectorAll(".nav button").forEach(button => button.addEventListener("click", () => {
        if (button.dataset.screen === "firmas") signatureDetail = null;
        showScreen(button.dataset.screen);
      }));
      document.querySelectorAll("[data-choice-group]").forEach(choice => {
        choice.addEventListener("click", () => {
          const group = choice.dataset.choiceGroup;
          document.querySelectorAll(`[data-choice-group="${group}"]`).forEach(item => {
            item.classList.remove("selected");
            const input = item.querySelector("input");
            if (input) input.checked = false;
          });
          choice.classList.add("selected");
          const input = choice.querySelector("input");
          if (input) input.checked = true;
          if (group === "agenda-start") {
            setAgendaStartMode(choice.dataset.choice);
          }
          if (group === "tramite-prioritario") {
            setTramitePriorityMode(choice.dataset.choice);
          }
          if (group === "tramite-relacion") {
            setTramiteRelationMode(choice.dataset.choice);
          }
          if (group === "tramite-redundante") {
            setTramiteRedundantMode(choice.dataset.choice);
          }
          if (group === "agenda-scope") setAgendaScope(choice.dataset.choice);
        });
      });
      document.querySelectorAll("[data-switch-start]").forEach(button => button.addEventListener("click", () => {
        setAgendaStartMode(button.dataset.switchStart || "cero");
      }));
      document.querySelectorAll("[data-accordion-toggle]").forEach(button => button.addEventListener("click", () => button.closest(".agenda-accordion-item").classList.toggle("open")));
      document.querySelectorAll("[data-scroll-target]").forEach(button => button.addEventListener("click", () => scrollToCorrectionSection(button.dataset.scrollTarget)));
      document.querySelectorAll("[data-filter-input], [data-filter-select]").forEach(el => el.addEventListener("input", renderRows));
      document.querySelectorAll("#normativaNueva input, #normativaNueva select").forEach(el => el.addEventListener("input", updateNormativaSummary));
      const normativaArchivo = document.querySelector("#normativaArchivo");
      if (normativaArchivo) normativaArchivo.addEventListener("change", updateNormativaFilePreview);
      document.querySelectorAll("[data-review-filter]").forEach(button => button.addEventListener("click", () => {
        const [module, filter] = button.dataset.reviewFilter.split(":");
        if (module === "agenda") {
          reviewAgendaFilter = filter;
        } else {
          reviewTramiteFilter = filter;
        }
        renderRows();
      }));
      document.querySelectorAll("[data-filter-target]").forEach(card => card.addEventListener("click", () => {
        if (card.dataset.reviewTarget) {
          renderDashboardFilter(Number(card.dataset.dashboardFilterIndex));
          return;
        }
        showScreen(card.dataset.filterTarget);
      }));
      document.addEventListener("change", (event) => {
        if (event.target.matches("[data-sector-select]")) {
          updateSubsectorCatalog(event.target);
        }
        if (event.target.matches("[data-bureaucratic-input]")) {
          calculateBureaucraticCost();
        }
        if (event.target.matches("[data-requirement-origin-toggle]")) {
          toggleRequirementOrigin(event.target);
        }
        if (event.target.matches("[data-observation-check]")) {
          setObservationChecked(event.target.dataset.observationId, event.target.checked);
        }
      });
      document.addEventListener("input", (event) => {
        if (event.target.matches("[data-norm-search]")) {
          renderNormativaSearchResults(event.target);
        }
        if (event.target.matches("[data-bureaucratic-input]")) {
          calculateBureaucraticCost();
        }
        if (event.target.matches("[data-homoclave-valor]")) {
          var wizardRoot = event.target.closest(".wizard-panel") || event.target.closest(".agenda-accordion-body") || document;
          actualizarHomoclave(wizardRoot);
        }
      });
      document.addEventListener("click", (event) => {
        const normResult = event.target.closest("[data-norm-result]");
        if (normResult) {
          event.preventDefault();
          selectNormativaResult(normResult);
        }
      });
      // Listeners para codificación de homoclave
      document.addEventListener("change", function (event) {
        if (event.target.matches("[data-dependencia-codificada]")) {
          updateAreaCatalog(event.target);
          var wizardRoot = event.target.closest(".wizard-panel") || event.target.closest(".agenda-accordion-body") || document;
          actualizarHomoclave(wizardRoot);
        }
        if (event.target.matches("[data-area-codificada]")) {
          var wizardRoot = event.target.closest(".wizard-panel") || event.target.closest(".agenda-accordion-body") || document;
          actualizarHomoclave(wizardRoot);
        }
        if (event.target.matches("[data-homoclave-opcion]")) {
          var wizardRoot = event.target.closest(".wizard-panel") || event.target.closest(".agenda-accordion-body") || document;
          actualizarHomoclave(wizardRoot);
        }
      });
      // Tabs de Agenda Regulatoria
      document.querySelectorAll("[data-ar-tab]").forEach(function (btn) {
        btn.addEventListener("click", function () { switchARTab(btn.dataset.arTab); });
      });
    }

// Fase 5: Function(action)() reemplazado por action registry seguro.
    function handleDeclarativeAction(event) {
      const trigger = event.target.closest("[data-action]");

      if (!trigger) {
        return;
      }

      event.preventDefault();
      const action = trigger.dataset.action;

      if (action) {
        runAction(action, { event: event, element: trigger });
      }
    }

    document.addEventListener("click", handleDeclarativeAction);
