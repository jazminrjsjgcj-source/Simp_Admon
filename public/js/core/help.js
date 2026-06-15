// ===============================
// PUNTA — Sistema de ayuda contextual
// Inyecta botones "?" junto a labels y muestra recuadros colapsables
// ===============================

function injectFieldHelp() {
  var injected = 0;

  // --- 1. Labels normales dentro de .field ---
  document.querySelectorAll(".field label, .field-inline label").forEach(function (label) {
    // Saltar check-cards (se procesan aparte)
    if (label.classList.contains("check-card")) return;

    // Saltar filtros de tablas y barras de búsqueda
    if (label.closest(".filters")) return;

    var rawText = label.textContent.trim();
    var cleanText = rawText.replace(/\s+/g, " ").trim().replace(/\s*\*\s*$/, "").trim();

    var helpText = fieldHelpTexts[cleanText];
    if (!helpText) return;
    if (label.querySelector(".field-help-btn")) return;

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "field-help-btn";
    btn.textContent = "?";
    btn.setAttribute("aria-label", "Ayuda: " + cleanText);

    var box = document.createElement("div");
    box.className = "field-help-box";
    box.textContent = helpText;

    label.appendChild(btn);

    var fieldContainer = label.closest(".field") || label.parentElement;
    fieldContainer.appendChild(box);

    btn.addEventListener("click", helpToggle(btn, box));
    injected++;
  });

  // --- 2. Check-cards (checkbox labels con <strong>) ---
  document.querySelectorAll("label.check-card strong").forEach(function (strong) {
    var cleanText = strong.textContent.trim();

    var helpText = fieldHelpTexts[cleanText];
    if (!helpText) return;
    if (strong.querySelector(".field-help-btn")) return;

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "field-help-btn";
    btn.textContent = "?";
    btn.setAttribute("aria-label", "Ayuda: " + cleanText);

    var box = document.createElement("div");
    box.className = "field-help-box";
    box.textContent = helpText;

    strong.appendChild(btn);

    var card = strong.closest("label.check-card") || strong.parentElement;
    card.parentElement.insertBefore(box, card.nextSibling);

    btn.addEventListener("click", helpToggle(btn, box));
    injected++;
  });

  return injected;
}

function helpToggle(btn, box) {
  return function (e) {
    e.preventDefault();
    e.stopPropagation();

    // Cerrar otros abiertos
    document.querySelectorAll(".field-help-box.visible").forEach(function (other) {
      if (other !== box) {
        other.classList.remove("visible");
        var otherBtn = other.previousElementSibling
          ? other.previousElementSibling.querySelector(".field-help-btn.active")
          : null;
        if (!otherBtn) {
          document.querySelectorAll(".field-help-btn.active").forEach(function (b) {
            if (b !== btn) b.classList.remove("active");
          });
        } else {
          otherBtn.classList.remove("active");
        }
      }
    });

    box.classList.toggle("visible");
    btn.classList.toggle("active");
  };
}

// Cerrar ayuda al hacer clic fuera
document.addEventListener("click", function (e) {
  if (!e.target.closest(".field-help-btn") && !e.target.closest(".field-help-box")) {
    document.querySelectorAll(".field-help-box.visible").forEach(function (box) {
      box.classList.remove("visible");
    });
    document.querySelectorAll(".field-help-btn.active").forEach(function (btn) {
      btn.classList.remove("active");
    });
  }
});

// Auto-inicialización + exports globales
document.addEventListener('DOMContentLoaded', injectFieldHelp);
window.injectFieldHelp = injectFieldHelp;
window.toggleHelp = function(btn) {
  var box = btn.closest('.field') && btn.closest('.field').querySelector('.field-help-box');
  if (!box) return;
  box.classList.toggle('visible');
  btn.classList.toggle('active');
};
