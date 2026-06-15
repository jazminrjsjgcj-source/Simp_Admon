// ===============================
// PUNTA — Capa de API simulada
// En producción se reemplaza por fetch() a endpoints Laravel.
// Por ahora simula respuestas usando localStorage.
// ===============================

var API_BASE = "/api/v1";

// --- Simulación de API ---
// Cada función retorna los datos directamente (síncrono en prototipo).
// En Laravel se reemplazará por fetch() + async/await.

var apiSimulada = {

  // --- Umbral de proporcionalidad ---

  getUmbral: function () {
    try {
      var stored = localStorage.getItem("punta_umbral_proporcionalidad");
      return stored ? JSON.parse(stored) : {
        status: "pendiente",
        value: "",
        sector: "",
        year: new Date().getFullYear(),
        methodology: ""
      };
    } catch (e) {
      console.warn("[API] Error al leer umbral:", e);
      return { status: "pendiente", value: "", sector: "", year: new Date().getFullYear(), methodology: "" };
    }
  },

  saveUmbral: function (data) {
    try {
      localStorage.setItem("punta_umbral_proporcionalidad", JSON.stringify(data));
      return { ok: true };
    } catch (e) {
      console.warn("[API] Error al guardar umbral:", e);
      return { ok: false, error: e.message };
    }
  },

  // --- Patrón para futuros endpoints ---
  // En Laravel cada uno se convierte en:
  //   getTramites: async function(filters) {
  //     var response = await fetch(API_BASE + "/tramites?" + new URLSearchParams(filters));
  //     return response.json();
  //   }

};
