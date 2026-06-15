// PUNTA â€” Roles, perfiles, permisos y configuración por rol
// Fase 2: Separado desde main.js original

const roleProfiles = {
  enlace: { label: "Enlace", name: "Usuario Enlace", initials: "UE", org: "Dirección General de Gobierno Digital", title: "Hola, Enlace", subtitle: "Carga trámites, solicita regulaciones, registra acciones de agenda y atiende observaciones de tu dependencia." },
  sujeto: { label: "Sujeto Obligado", name: "Sujeto Obligado", initials: "SO", org: "Dirección General de Gobierno Digital", title: "Hola, Sujeto Obligado", subtitle: "Revisa, observa y firma los trámites de tu dependencia." },
  revisora: { label: "Autoridad Revisora", name: "Autoridad Revisora", initials: "AR", org: "Secretaría General Municipal", title: "Hola, Revisora", subtitle: "Revisa los trámites enviados por los enlaces y registra observaciones." },
  juridico: { label: "Área Jurídica", name: "Jurídico", initials: "JR", org: "Secretaría General Municipal", title: "Hola, Jurídico", subtitle: "Registra y gestiona las normativas del sistema PUNTA." },
  admin: { label: "Administrador", name: "Administrador", initials: "AD", org: "Sistema PUNTA", title: "Hola, Administrador", subtitle: "Gestiona los usuarios, roles y accesos del sistema PUNTA." },
};

const roleAccess = {
  enlace: ["dashboard", "tramites", "regulaciones", "agenda", "calendarioAgenda", "agendaRegulatoria", "firmas"],
  sujeto: ["dashboard", "tramites", "agenda", "calendarioAgenda"],
  revisora: ["dashboard", "tramites", "agenda", "calendarioAgenda", "agendaRegulatoria"],
  juridico: ["dashboard", "regulaciones", "calendarioAgenda", "agendaRegulatoria"],
  admin: ["dashboard", "agendaRegulatoria", "usuariosAdmin", "bitacoraAdmin"],
};

const defaultNavLabels = {
  dashboard: "Dashboard",
  tramites: "Trámites",
  regulaciones: "Regulaciones",
  agenda: "Agenda",
  calendarioAgenda: "Calendario",
  bitacoraAdmin: "Bitácora",
  agendaRegulatoria: "Agenda Regulatoria",
  firmas: "Firmas",
  porRevisar: "Por Revisar",
  revisados: "Revisados",
  porFirmar: "Por Firmar",
  firmados: "Firmados",
  normativaNueva: "Registrar Normativa",
  usuariosAdmin: "Usuarios",
  nuevoUsuarioAdmin: "Nuevo Usuario",
};

const roleNavLabels = {
  revisora: { dashboard: "Inicio", tramites: "Trámites", agenda: "Agenda" },
  sujeto: { dashboard: "Inicio", tramites: "Trámites", agenda: "Agenda" },
  juridico: { dashboard: "Inicio", regulaciones: "Normativas" },
  admin: { dashboard: "Inicio", agendaRegulatoria: "Agenda Regulatoria" },
};

const roleDashboardData = {
  juridico: {
    kpis: [["14", "Normativas registradas"], ["11", "Vigentes"], ["3", "En revisión"], ["0", "Derogadas"]],
    title: "Normativas recientes",
    rows: [
      { folio: "REG-001", elemento: "Manual de Ventanilla Única", modulo: "Normativas", estatus: "Vigente", action: "openModal('regulacion')", label: "Ver" },
      { folio: "REG-004", elemento: "Lineamientos de Simplificación Administrativa", modulo: "Normativas", estatus: "Vigente", action: "openModal('regulacion')", label: "Ver" },
      { folio: "REG-007", elemento: "Reglamento de Transparencia Municipal", modulo: "Normativas", estatus: "En revisión", action: "showScreen('regulaciones')", label: "Revisar" },
    ],
  },
  sujeto: {
    kpis: [["2", "Por firmar"], ["3", "Observados"], ["8", "Firmados"], ["1", "Pendiente de revisión"]],
    filters: [
      { screen: "tramites", filter: "porRevisar", title: "Trámites por firmar", status: "Listo para firmar" },
      { screen: "tramites", filter: "porRevisar", title: "Trámites observados", status: "Observado" },
      { screen: "tramites", filter: "revisados", title: "Trámites firmados" },
      { screen: "agenda", filter: "porRevisar", title: "Acciones de agenda pendientes de revisión" },
    ],
    title: "Trámites pendientes de firma",
    rows: [
      { folio: "TRM-5543", elemento: "Renovación de Licencia Comercial", modulo: "Trámites", estatus: "Listo para firmar", action: "openSignatureDetail('TRM-5543')", label: "Revisar y firmar" },
      { folio: "TRM-8821", elemento: "Licencia de Funcionamiento Tipo A", modulo: "Trámites", estatus: "Observado", action: "startSujetoObservationReview()", label: "Revisar" },
    ],
  },
  revisora: {
    kpis: [["2", "Por revisar"], ["5", "Observados"], ["12", "Aprobados"], ["1", "Acción de agenda"]],
    filters: [
      { screen: "tramites", filter: "porRevisar", title: "Trámites por revisar" },
      { screen: "tramites", filter: "revisados", title: "Trámites observados", status: "Observado" },
      { screen: "tramites", filter: "revisados", title: "Trámites aprobados", status: "Aprobado" },
      { screen: "agenda", filter: "porRevisar", title: "Acciones de agenda por revisar" },
    ],
    title: "Trámites pendientes de revisión",
    rows: [
      { folio: "TRM-8821", elemento: "Licencia de Funcionamiento Tipo A", modulo: "Trámites", estatus: "Pendiente", action: "startReview()", label: "Revisar" },
      { folio: "TRM-1204", elemento: "Permiso de Construcción Menor", modulo: "Trámites", estatus: "Pendiente", action: "startReview()", label: "Revisar" },
      { folio: "AGD-002", elemento: "Pago en línea", modulo: "Agenda", estatus: "Pendiente", action: "startReview('agenda')", label: "Revisar" },
    ],
  },
  admin: {
    kpis: [["7", "Usuarios totales"], ["5", "Activos"], ["2", "Inactivos / Suspendidos"], ["4", "Roles activos"]],
    title: "Últimos usuarios registrados",
    rows: [
      { folio: "USR-003", elemento: "Laura Mendoza Cruz", modulo: "Autoridad Revisora", estatus: "Activo", action: "openModal('editarUsuarioAdmin')", label: "Editar" },
      { folio: "USR-004", elemento: "Jorge Herrera Díaz", modulo: "Sujeto Obligado", estatus: "Activo", action: "openModal('editarUsuarioAdmin')", label: "Editar" },
      { folio: "USR-005", elemento: "Ana Flores Ruiz", modulo: "Área Jurídica", estatus: "Activo", action: "openModal('editarUsuarioAdmin')", label: "Editar" },
    ],
  },
};

const reviewModes = {
  tramite: {
    backAction: "showScreen('tramites')",
    status: "En revisión",
    folio: "TRM-5543",
    title: "Renovación de Licencia Comercial",
    subtitle: "Dirección de Desarrollo Económico y Turismo",
    defaultSection: "Datos generales",
    sections: [
      { title: "Datos generales", subtitle: "Información básica del trámite.", data: [["Nombre", "Renovación de Licencia Comercial"], ["Dependencia", "Dirección de Desarrollo Económico y Turismo"], ["Tipo", "Trámite"], ["Modalidad", "Presencial y en línea"], ["Plazo", "15 días hábiles"], ["Vigencia", "1 año"], ["Costo", "$500.00 MXN"], ["Descripción", "Procedimiento mediante el cual los ciudadanos pueden renovar su licencia de funcionamiento anual para establecimientos comerciales.", true]] },
      { title: "Información general", subtitle: "Objetivo, población objetivo y volumen del trámite.", data: [["Objetivo", "Renovar la autorización municipal para que un establecimiento comercial pueda operar durante el ejercicio vigente.", true], ["Población objetivo", "Personas físicas y morales"], ["Frecuencia", "Alta"], ["Volumen anual estimado", "1,250 solicitudes"], ["Modalidad de solicitud", "Presencial y en línea"]] },
      { title: "Operación y costos", subtitle: "Proceso de atención, tiempos, visitas y costo declarado.", data: [["Costo del trámite", "$500.00 MXN"], ["Forma de pago", "Caja municipal y pago en línea"], ["Áreas participantes", "3 áreas"], ["Visitas requeridas", "1 visita"], ["Espera estimada", "45 minutos"], ["Nivel de digitalización", "Nivel 2 - Acceso digital básico"]], list: [["1", "Ingresar solicitud de renovación", "Portal o ventanilla"], ["2", "Presentar requisitos vigentes", "Documentos digitales o físicos"], ["3", "Realizar pago de derechos", "Caja municipal o pago en línea"], ["4", "Recibir licencia renovada", "Descarga digital o entrega física"]] },
      { title: "Requisitos", subtitle: "4 registrados.", list: [["1", "Identificación oficial vigente", "Documento - Obligatorio"], ["2", "Comprobante de domicilio", "Documento - Obligatorio"], ["3", "Formato de solicitud", "Formato - Obligatorio"], ["4", "Dictamen de Protección Civil", "Producto de otro trámite - Obligatorio"]] },
      { title: "Fundamento jurídico", subtitle: "Normativa aplicable al trámite.", data: [["Normativa", "Ley de Ingresos Municipal"], ["Artículo", "Artículo 45, Fracción II"], ["Resumen", "Disposición aplicable para cobro de derechos y emisión de licencia comercial.", true]] },
      { title: "Oficinas y canales de atención", subtitle: "Ventanilla, horarios, contacto y medios disponibles.", data: [["Horario", "Lunes a Viernes de 9:00 a 15:00 hrs"], ["Ubicación", "Palacio Municipal, Planta Baja, Ventanilla 3"], ["Teléfono", "614 000 0000 ext. 120"], ["Correo", "ventanilla@dependencia.gob.mx"], ["Canal presencial", "Disponible"], ["Canal digital", "Portal PUNTA"]] },
      { title: "Ficha portal ciudadano", subtitle: "Contenido visible para la ciudadanía.", data: [["Nombre ciudadano", "Renovación de licencia comercial"], ["Documento que obtiene", "Licencia comercial renovada"], ["Horarios", "Lunes a Viernes de 9:00 a 15:00 hrs"], ["Ubicación", "Palacio Municipal, Ventanilla 3"], ["Pasos ciudadanos", "Reunir requisitos, agendar cita, realizar pago y recibir licencia.", true]] },
      { title: "Seguimiento, firmas y publicación", subtitle: "Estatus del registro, responsables y flujo de validación.", data: [["Estatus", "Correcciones en revisión"], ["Responsable de captura", "Usuario Enlace"], ["Validación Sujeto Obligado", "Pendiente"], ["Aprobación Autoridad Revisora", "Pendiente"], ["Publicación portal", "Posterior a aprobación"], ["Acuse", "Disponible al validar"]] }
    ]
  },
  agenda: {
    backAction: "showScreen('agenda')",
    status: "En revisión",
    folio: "AGD-002",
    title: "Pago en línea",
    subtitle: "Acción de Agenda - Digitalización vinculada a Permiso de Construcción Menor",
    defaultSection: "Datos de la acción",
    sections: [
      { title: "Datos de la acción", subtitle: "Identificación de la acción que se revisa.", data: [["Folio", "AGD-002"], ["Trámite vinculado", "Permiso de Construcción Menor"], ["Tipo de acción", "Digitalización"], ["Acción registrada", "Pago en línea"], ["Responsable", "Sistemas"], ["Estatus", "Observado"]] },
      { title: "Alcance y necesidad", subtitle: "Motivo ciudadano e institucional de la mejora.", data: [["Necesidad usuaria", "Reducir traslados y permitir pago remoto del permiso." , true], ["Población beneficiada", "Personas solicitantes de permisos de construcción menor"], ["Canal actual", "Presencial"], ["Canal objetivo", "Portal PUNTA"]] },
      { title: "Trámite vinculado", subtitle: "Datos mínimos del trámite asociado a la agenda.", data: [["Nombre del trámite", "Permiso de Construcción Menor"], ["Dependencia", "Dirección de Desarrollo Urbano"], ["Modalidad", "Presencial"], ["Plazo actual", "10 días hábiles"], ["Costo", "$350.00 MXN"], ["Producto", "Permiso de construcción menor"]] },
      { title: "Acciones de mejora", subtitle: "Simplificación o digitalización propuesta.", data: [["Nivel actual", "Nivel 0 - Sin digitalización"], ["Nivel objetivo", "Nivel 3 - Trámite en línea"], ["Meta esperada", "Habilitar pago en línea y descarga de comprobante digital.", true], ["Indicador", "Porcentaje de pagos realizados en línea"]] },
      { title: "Requisitos y fundamento", subtitle: "Impacto de la acción sobre requisitos o normativa.", data: [["Modifica requisitos", "No"], ["Modifica fundamento jurídico", "No"], ["Requisitos visibles", "Se mantienen los requisitos del trámite vinculado"], ["Fundamento", "Reglamento de Desarrollo Urbano vigente"]] },
      { title: "Calendario y seguimiento", subtitle: "Fechas, responsable y avance comprometido.", data: [["Periodo", "Periodo de Revisión Activo"], ["Fecha inicio", "01 Nov 2024"], ["Fecha estimada de conclusión", "12 Dic 2024"], ["Responsable de seguimiento", "Sistemas"], ["Estatus actual", "Digitalización parcial"], ["Próximo hito", "Pruebas de integración de pago"]] },
      { title: "Evidencias y anexos", subtitle: "Soporte documental para revisión.", list: [["1", "Mapa del flujo de pago", "Documento de soporte"], ["2", "Captura de pantalla del prototipo", "Imagen"], ["3", "Bitácora de pruebas", "Documento técnico"]] },
      { title: "Firmas y acuse", subtitle: "Validación de la acción de agenda.", data: [["Firma Sujeto Obligado", "Pendiente"], ["Firma Enlace", "Pendiente"], ["Aprobación Autoridad Revisora", "Pendiente"], ["Acuse", "Disponible al validar"]] }
    ]
  }
};


