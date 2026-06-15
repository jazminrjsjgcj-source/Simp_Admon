// ===============================
// PUNTA — Funciones utilitarias y helpers
// Fase 2: Separado desde main.js original
// Fase 4: canReview() unificada como alias de hasReviewRole()
// ===============================

// --- Badges y estados ---
    function badge(status) {
      const normalized = ["Completado", "Aprobado", "Validado", "Firmado"].includes(status) ? "success-b" : ["Observado", "Listo para firmar"].includes(status) ? "warning-b" : "muted";
      return `<span class="badge ${normalized}">${status}</span>`;
    }

    function canAttend(item) {
      return currentRole === "enlace" && item.estatus === "Observado";
    }

    // Alias de compatibilidad: canReview → hasReviewRole (Fase 4)
    function canReview() {
      return hasReviewRole();
    }

    function canSeeChangeLog() {
      return currentRole === "revisora" || currentRole === "sujeto";
    }

    function canReviewPending(item) {
      return canReview() && (item.estatus === "Observado" || item.estatus === "Pendiente");
    }

    function pendingReviewAction(item) {
      if (item.modulo === "Trámites") return "startReview()";
      if (item.modulo === "Agenda") return "startReview('agenda')";
      if (item.modulo === "Normativa") return "openModal('regulacion')";
      return "openModal('detalle')";
    }

    function pendingActionButton(item) {
      if (canAttend(item)) {
        return tableActionButton("Atender", item.modulo === "Agenda" ? "startAgendaCorrection()" : "startCorrection('tramite')");
      }
      if (canReviewPending(item)) {
        return tableActionButton("Revisar", pendingReviewAction(item));
      }
      return "";
    }

    function regulationActionButton(item) {
      if (currentRole === "juridico") {
        return tableActionButton(item.cargada ? "Actualizar archivo" : "Subir archivo", "openModal('subirRegulacion')");
      }
      return item.cargada ? tableActionButton("Descargar", "openModal('regulacion')") : "";
    }

    function tableActionButton(label, action, variant = "") {
      if (!label || !action) return "";
      const variantClass = variant ? ` ${variant}` : "";
      return `<button class="btn table-action-btn${variantClass}" data-action="${action}">${label}</button>`;
    }

    function tableActions(...buttons) {
      const content = buttons.filter(Boolean).join("");
      return content ? `<div class="table-actions">${content}</div>` : "";
    }

    function reviewActionLabel(item, filter) {
      if (filter === "porRevisar") return "Revisar";
      return item.action.includes("downloadAcuse") ? "Ver acuse" : "Ver";
    }

    function hasReviewRole() {
      return currentRole === "revisora" || currentRole === "sujeto";
    }


    function bureaucraticCost(value) {
      return ["Alto", "Medio", "Bajo"].includes(value) ? value : "Medio";
    }

    function normalizeTramiteCostCells() {
      document.querySelectorAll("#tramitesRows tr").forEach(row => {
        const costCell = row.children[5];
        if (!costCell) return;
        costCell.textContent = bureaucraticCost(costCell.textContent.trim());
      });
    }

    function enhanceResponsiveTables() {
      document.querySelectorAll("table").forEach((table) => {
        table.classList.add("data-table");
        const headers = [...table.querySelectorAll("thead th")].map(header => header.textContent.trim());
        table.querySelectorAll("thead th").forEach((header, index, allHeaders) => {
          header.classList.toggle("table-action-cell", index === allHeaders.length - 1);
        });
        table.querySelectorAll("tbody tr").forEach((row) => {
          [...row.children].forEach((cell, index) => {
            cell.dataset.label = headers[index] || "";
            cell.classList.toggle("table-action-cell", index === row.children.length - 1);
            if (index === row.children.length - 1 && cell.querySelector(".btn") && !cell.querySelector(".table-actions")) {
              cell.querySelectorAll(".btn").forEach(button => button.classList.add("table-action-btn"));
              const actions = document.createElement("div");
              actions.className = "table-actions";
              while (cell.firstChild) {
                actions.appendChild(cell.firstChild);
              }
              cell.appendChild(actions);
            }
          });
        });
      });
    }

    function numberFromInput(selector, root = document) {
      return Number(root.querySelector(selector)?.value || 0) || 0;
    }

    function formatDuration(totalMinutes) {
      const days = Math.floor(totalMinutes / 1440);
      const hours = Math.floor((totalMinutes % 1440) / 60);
      const minutes = totalMinutes % 60;
      return `${days} días ${hours} horas ${minutes} minutos`;
    }

    function formatNumber(value) {
      var number = Number(value || 0);
      if (!number) return "0";
      return number.toLocaleString("es-MX", { maximumFractionDigits: 2 });
    }
