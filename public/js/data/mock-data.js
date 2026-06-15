// ===============================
// PUNTA — Datos simulados del sistema
// ===============================
// Fase 2: Separado desde main.js original

    const data = {
      tramites: [
        { id: "TRM-5543", nombre: "Renovación de Licencia Comercial", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", estatus: "Completado", costo: "Bajo", updated: "16 Oct 2023" },
        { id: "TRM-8821", nombre: "Licencia de Funcionamiento Tipo A", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", estatus: "Completado", costo: "Medio", updated: "10 Oct 2023" }
      ],
      regulaciones: [
        { id: "REG-001", nombre: "Reglamento de Comercio Municipal", tipo: "Reglamento", dependencia: "Dirección General de Gobierno Digital", publicacion: "04 Ene 2023", tramites: "8", cargada: true },
        { id: "REG-002", nombre: "Lineamientos de Protección Civil", tipo: "Lineamiento", dependencia: "Dirección General de Gobierno Digital", publicacion: "18 Mar 2022", tramites: "5", cargada: false },
        { id: "REG-003", nombre: "Manual de Ventanilla Única", tipo: "Manual", dependencia: "Dirección General de Gobierno Digital", publicacion: "09 Jun 2021", tramites: "12", cargada: true }
      ],
      agenda: [
        { id: "AGD-001", tramite: "Licencia de Funcionamiento", tipo: "Simplificación", accion: "Reducir requisitos", fecha: "30 Nov 2024", estatus: "Pendiente", responsable: "Enlace" },
        { id: "AGD-002", tramite: "Permiso de Construcción Menor", tipo: "Digitalización", accion: "Pago en línea", fecha: "12 Dic 2024", estatus: "Observado", responsable: "Sistemas" },
        { id: "AGD-003", tramite: "Constancia de No Adeudo", tipo: "Simplificación", accion: "Reducir tiempo de respuesta", fecha: "20 Dic 2024", estatus: "Completado", responsable: "Enlace" }
      ],
      pending: [
        { folio: "TRM-8821", elemento: "Licencia de Funcionamiento Tipo A", modulo: "Trámites", estatus: "Observado", action: "Atender" },
        { folio: "REG-003", elemento: "Manual de Ventanilla Única", modulo: "Normativa", estatus: "Pendiente", action: "" },
        { folio: "AGD-002", elemento: "Pago en línea", modulo: "Agenda", estatus: "Observado", action: "Atender" }
      ]
    };

    const roleReviewData = {
      revisora: {
        tramites: {
          porRevisar: [
            { folio: "TRM-8821", registro: "Licencia de Funcionamiento Tipo A", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", fecha: "Hace 2 horas", estatus: "Pendiente", costo: "Medio", action: "startReview()" },
            { folio: "TRM-1204", registro: "Permiso de Construcción Menor", dependencia: "Dirección General de Gobierno Digital", tipo: "Servicio", fecha: "Hace 1 día", estatus: "Pendiente", costo: "Alto", action: "startReview()" },
          ],
          revisados: [
            { folio: "TRM-5543", registro: "Renovación de Licencia Comercial", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", fecha: "16 Oct 2024", estatus: "Aprobado", costo: "Bajo", action: "downloadAcuse('tramite')" },
            { folio: "TRM-3310", registro: "Constancia de No Adeudo Fiscal", dependencia: "Dirección General de Gobierno Digital", tipo: "Servicio", fecha: "14 Oct 2024", estatus: "Observado", costo: "Medio", action: "openModal('historial')" },
          ],
        },
        agenda: {
          porRevisar: [
            { folio: "AGD-002", registro: "Pago en línea", tipo: "Digitalización", accion: "Implementar pago digital", fecha: "Hace 3 horas", estatus: "Pendiente", action: "startReview('agenda')" },
            { folio: "AGD-004", registro: "Cita digital", tipo: "Digitalización", accion: "Habilitar agenda de citas", fecha: "Hace 1 día", estatus: "Pendiente", action: "startReview('agenda')" },
          ],
          revisados: [
            { folio: "AGD-001", registro: "Reducir requisitos", tipo: "Simplificación", accion: "Eliminar copias innecesarias", fecha: "12 Oct 2024", estatus: "Validado", action: "downloadAcuse('agenda')" },
            { folio: "AGD-003", registro: "Reducir tiempo de respuesta", tipo: "Simplificación", accion: "Ajustar plazos internos", fecha: "09 Oct 2024", estatus: "Observado", action: "openModal('historial')" },
          ],
        },
      },
      sujeto: {
        tramites: {
          porRevisar: [
            { folio: "TRM-5543", registro: "Renovación de Licencia Comercial", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", fecha: "Listo para firma", estatus: "Listo para firmar", costo: "Bajo", action: "openSignatureDetail('TRM-5543')" },
            { folio: "TRM-8821", registro: "Licencia de Funcionamiento Tipo A", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", fecha: "Observaciones atendidas", estatus: "Observado", costo: "Medio", action: "startSujetoObservationReview()" },
          ],
          revisados: [
            { folio: "TRM-5543", registro: "Renovación de Licencia Comercial", dependencia: "Dirección General de Gobierno Digital", tipo: "Trámite", fecha: "Firmado", estatus: "Firmado", costo: "Bajo", action: "downloadAcuse('tramite')" },
            { folio: "TRM-3310", registro: "Constancia de No Adeudo Fiscal", dependencia: "Dirección General de Gobierno Digital", tipo: "Servicio", fecha: "Validado", estatus: "Validado", costo: "Medio", action: "downloadAcuse('tramite')" },
          ],
        },
        agenda: {
          porRevisar: [
            { folio: "AGD-002", registro: "Pago en línea", tipo: "Digitalización", accion: "Confirmar avance de digitalización", fecha: "Listo para revisión", estatus: "Pendiente", action: "startReview('agenda')" },
            { folio: "AGD-004", registro: "Cita digital", tipo: "Digitalización", accion: "Validar alcance de agenda", fecha: "Observaciones atendidas", estatus: "Observado", action: "startReview('agenda')" },
          ],
          revisados: [
            { folio: "AGD-001", registro: "Reducir requisitos", tipo: "Simplificación", accion: "Eliminar copias innecesarias", fecha: "Firmado", estatus: "Firmado", action: "downloadAcuse('agenda')" },
            { folio: "AGD-003", registro: "Reducir tiempo de respuesta", tipo: "Simplificación", accion: "Ajustar plazos internos", fecha: "Validado", estatus: "Validado", action: "downloadAcuse('agenda')" },
          ],
        },
      },
    };

    const signatureRecords = {
      "TRM-5543": {
        status: "Pendiente de revisión del Sujeto Obligado",
        processStatus: "en_revision_sujeto",
        title: "Renovación de Licencia Comercial",
        folio: "TRM-5543",
        dependency: "Dirección General de Bienestar y Desarrollo Económico",
        type: "Trámite",
        modality: "Presencial y en línea",
        dueDate: "15 días hábiles",
        validity: "1 año",
        cost: "$500.00 MXN",
        description: "Procedimiento mediante el cual los ciudadanos pueden renovar su licencia de funcionamiento anual para establecimientos comerciales.",
        signedBy: { sujeto: "Pendiente", enlace: "Pendiente", autoridad: "Pendiente" },
      },
    };

    const documentProcessSteps = [
      {
        title: "Revisión del Sujeto Obligado",
        description: "El Sujeto Obligado revisa el trámite. Puede enviar observaciones al Enlace o aceptar.",
      },
      {
        title: "Aceptación del Sujeto Obligado",
        description: "El Sujeto Obligado acepta formalmente que la información del trámite es correcta.",
      },
      {
        title: "Aceptación del Enlace",
        description: "El Enlace acepta y envía el trámite a la Autoridad Revisora.",
      },
      {
        title: "Revisión de Autoridad Revisora",
        description: "La Autoridad Revisora revisa el expediente. Puede observar o aprobar.",
      },
      {
        title: "Aprobación",
        description: "La Autoridad Revisora aprueba el trámite.",
      },
      {
        title: "Acuse para firma física",
        description: "Se genera el acuse con todos los datos para firma física del Sujeto Obligado y el Enlace.",
      },
    ];

    const documentProcessStatus = {
      enviado: { completed: 0, current: 1 },
      en_revision_sujeto: { completed: 0, current: 1 },
      observado_sujeto: { completed: 0, current: 1 },
      aceptado_sujeto: { completed: 1, current: 2 },
      aceptado_enlace: { completed: 2, current: 3 },
      en_revision_revisora: { completed: 3, current: 4 },
      observado_revisora: { completed: 3, current: 4 },
      aprobado: { completed: 5, current: 6 },
      acuse_disponible: { completed: 6, current: 6 },
    };

    // ===============================
    // Datos: Agenda Regulatoria y AIR
    // ===============================

    const dataAgendaRegulatoria = {
      propuestas: [
        { id: "PR-2026-001", nombre: "Reglamento de Comercio Ambulante", tipo: "Reglamento", dependencia: "Dirección General de Bienestar y Desarrollo Económico", materia: "Comercio", estatus: "Aprobada", fecha: "15 Jun 2026", costos: "Sí", costoEstimado: 380000, impactaEconomia: true, exenta: false },
        { id: "PR-2026-002", nombre: "Lineamientos de Protección Civil para Eventos Masivos", tipo: "Lineamiento", dependencia: "Secretaría General Municipal", materia: "Seguridad", estatus: "Registrada", fecha: "30 Jul 2026", costos: "Sí", costoEstimado: 180000, impactaEconomia: true, exenta: false },
        { id: "PR-2026-003", nombre: "Manual de Ventanilla Digital", tipo: "Manual", dependencia: "Dirección General de Gobierno Digital", materia: "Digitalización", estatus: "Registrada", fecha: "01 Sep 2026", costos: "No", costoEstimado: 0, impactaEconomia: false, exenta: true },
      ],
      air: [
        { id: "AIR-2026-001", propuesta: "Reglamento de Comercio Ambulante", folio: "PR-2026-001", estatus: "Dictaminado", tipo: "AIR Completo", fecha: "20 May 2026" },
        { id: "AIR-2026-002", propuesta: "Lineamientos de Protección Civil", folio: "PR-2026-002", estatus: "En consulta pública", tipo: "AIR Completo", fecha: "10 Jun 2026" },
      ],
      exenciones: [
        { id: "EXE-2026-001", propuesta: "Manual de Ventanilla Digital", folio: "PR-2026-003", estatus: "Constancia emitida", supuesto: "Fracc. VIII", fecha: "05 Mar 2026" },
      ]
    };



