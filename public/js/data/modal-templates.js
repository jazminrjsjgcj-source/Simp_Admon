// ===============================
// PUNTA — Templates de contenido para modales
// Extraído de modal.js para separar datos de lógica
// ===============================

// --- Helpers de template ---

function modalRows(items) {
  return '<div class="modal-grid">'
    + items.map(function (item) {
      return '<div class="modal-data-item"><span>' + item.label + '</span><strong>' + item.value + '</strong></div>';
    }).join("")
    + '</div>';
}

function modalField(label, control) {
  return '<label class="modal-field"><span>' + label + '</span>' + control + '</label>';
}

// --- Títulos ---

var modalTitles = {
  periodo: "Configurar Periodo",
  notificaciones: "Notificaciones",
  perfil: "Perfil de Usuario",
  sesion: "Sesión",
  ficha: "Ficha Portal",
  descargarTodo: "Descargar todo",
  solicitarNormativa: "Solicitar Normativa",
  subirRegulacion: "Subir Regulación",
  usuarioAdmin: "Usuario",
  editarUsuarioAdmin: "Editar usuario",
  constanciaExencion: "Constancia de Exención",
  propuestaGuardada: "Propuesta Registrada",
  airEnviado: "AIR Enviado",
  detallePropuesta: "Detalle de Propuesta",
  detalleAIR: "Detalle del AIR",
  dictamen: "Dictamen",
  regulacion: "Descargar Regulación",
  historial: "Historial del Trámite",
  tramiteGuardado: "Trámite Guardado",
  guardado: "Acción Guardada",
  correccionesEnviadas: "Correcciones Enviadas",
  logCambios: "Log de Cambios",
  observacionSeccion: "Realizar Observación",
  observacionesEnviadas: "Observaciones Enviadas",
  firmaSujeto: "Firma del Sujeto Obligado",
  firmaEnlace: "Firma del Enlace",
  aprobacionTramite: "Aprobación de Trámite",
  aprobacionAgenda: "Aprobación de Acción",
};

// --- Botones de footer por tipo de modal ---

var modalActionMap = {
  sesion: '<button class="btn secondary" data-action="closeModal()">Cancelar</button><button class="btn" data-action="logout()">Cerrar sesión</button>',
  perfil: '<button class="btn secondary" data-action="closeModal()">Cerrar</button>',
  periodoAdmin: '<button class="btn secondary" data-action="closeModal()">Cancelar</button><button class="btn" data-action="closeModal()">Guardar periodo</button>',
  subirRegulacion: '<button class="btn secondary" data-action="closeModal()">Cancelar</button><button class="btn" data-action="closeModal()">Guardar regulación</button>',
  editarUsuarioAdmin: '<button class="btn secondary" data-action="closeModal()">Cancelar</button><button class="btn" data-action="closeModal()">Guardar cambios</button>',
  observacionSeccion: '<button class="btn secondary" data-action="closeModal()">Cancelar</button><button class="btn" data-action="saveSectionObservation()">Guardar observación</button>',
  _default: '<button class="btn secondary" data-action="closeModal()">Cerrar</button><button class="btn" data-action="closeModal()">Aceptar</button>'
};

function getModalActions(kind) {
  if (kind === "periodo" && currentRole === "admin") return modalActionMap.periodoAdmin;
  return modalActionMap[kind] || modalActionMap._default;
}

// --- Cuerpos de modal (evaluados al momento de llamarlos) ---

function getModalBody(kind) {
  var profile = roleProfiles[currentRole] || roleProfiles.enlace;

  var bodies = {
        periodo: currentRole === "admin" ? `
          <div class="info-card">
            <h4>Configurar y dar de alta periodo</h4>
            <div class="modal-stack">
              ${modalField("Periodo activo", `<select><option>Periodo 2024 - Revisión General</option></select>`)}
              <div class="modal-grid">
                ${modalField("Fecha de inicio", `<input type="date" value="2024-11-01">`)}
                ${modalField("Fecha de fin", `<input type="date" value="2024-11-24">`)}
              </div>
              ${modalField("Nuevo periodo", `<input placeholder="Ej. Periodo 2026 - Revisión General">`)}
              ${modalField("Alcance", `<select><option>Trámites y acciones de agenda</option><option>Solo trámites</option><option>Solo agenda</option></select>`)}
            </div>
          </div>
        ` : `
          <div class="info-card">
            <h4>Periodo de Revisión Activo</h4>
            ${modalRows([
              { label: "Periodo activo", value: "Periodo 2024 - Revisión General" },
              { label: "Fecha de inicio", value: "01 Nov 2024" },
              { label: "Fecha de fin", value: "24 Nov 2024" },
              { label: "Permiso", value: "Solo lectura" },
            ])}
            <p class="modal-copy">Solo el Administrador puede cambiar el periodo activo.</p>
          </div>
        `,
        notificaciones: `
          <div class="modal-stack">
            <article class="notification-item">
              <strong>Observación en TRM-8821</strong>
              <p>Revisa la observación registrada por la autoridad revisora.</p>
              <small>Hace 2 horas</small>
            </article>
            <article class="notification-item">
              <strong>Acción AGD-001 próxima a vencer</strong>
              <p>Fecha compromiso: 30 Nov 2024.</p>
              <small>Hace 5 horas</small>
            </article>
            <article class="notification-item">
              <strong>Normativa REG-002 disponible</strong>
              <p>Lineamientos de Protección Civil actualizados.</p>
              <small>Ayer</small>
            </article>
          </div>
        `,
        perfil: `
          <div class="modal-stack">
            <div class="info-card">
              <h4>Perfil de Usuario</h4>
              ${modalRows([
                { label: "Nombre", value: profile.name },
                { label: "Dependencia", value: profile.org },
                { label: "Rol", value: profile.label },
                { label: "Correo electrónico", value: "usuario@punta.gob.mx" },
              ])}
            </div>
            <div class="modal-shortcuts">
              <button class="btn secondary" data-action="openModal('notificaciones')">Ver notificaciones</button>
              <button class="btn secondary" data-action="openModal('periodo')">${currentRole === "admin" ? "Configurar periodo" : "Ver periodo"}</button>
              <button class="btn" data-action="openModal('sesion')">Cerrar sesión</button>
            </div>
          </div>
        `,
        sesion: `
          <div class="info-card">
            <h4>Cerrar sesión</h4>
            <p class="modal-copy">¿Deseas salir del sistema y volver a la pantalla de inicio de sesión?</p>
          </div>
        `,
        ficha: `
          <div class="info-card">
            <h4>Ficha Portal</h4>
            ${modalRows([
              { label: "Nombre ciudadano", value: "Licencia de Funcionamiento Tipo A" },
              { label: "Modalidad", value: "Presencial / En línea" },
              { label: "Costo", value: "$450.00 MXN" },
              { label: "Documento que obtiene", value: "Licencia de Funcionamiento" },
            ])}
            <div class="section-actions section-actions-start u-px-0">
              <button class="btn" data-action="downloadFichaPortal">Generar PDF Ficha Portal</button>
            </div>
          </div>
        `,
        solicitarNormativa: `
          <div class="info-card">
            <h4>Solicitar Normativa</h4>
            <div class="modal-stack">
              ${modalField("Tipo de normativa", `<select><option>Reglamento</option><option>Lineamiento</option><option>Manual</option><option>Acuerdo</option></select>`)}
              ${modalField("Nombre o descripción", `<input placeholder="Ej. Reglamento de Comercio Actualizado 2024">`)}
              ${modalField("Justificación", `<textarea placeholder="Indique por qué requiere esta normativa..."></textarea>`)}
            </div>
          </div>
        `,
        subirRegulacion: `
          <div class="info-card">
            <h4>Subir regulación</h4>
            <p class="modal-copy">Carga o actualiza documentos normativos. Esta acción corresponde únicamente al Área Jurídica.</p>
            <div class="modal-stack u-mt-md">
              ${modalField("Tipo de regulación", `<select><option>Reglamento</option><option>Lineamiento</option><option>Manual</option><option>Acuerdo</option></select>`)}
              ${modalField("Nombre de la regulación", `<input placeholder="Ej. Reglamento de Comercio Municipal">`)}
              ${modalField("Fecha de publicación", `<input type="date">`)}
              ${modalField("Documento", `<input type="file">`)}
              ${modalField("Notas de carga", `<textarea placeholder="Indique versión, cambios relevantes o dependencia vinculada..."></textarea>`)}
            </div>
          </div>
        `,
        usuarioAdmin: `
          <div class="info-card">
            <h4>Usuario guardado</h4>
            <p class="modal-copy">La información del usuario, rol y dependencia fue registrada para el sistema PUNTA.</p>
          </div>
        `,
        editarUsuarioAdmin: `
          <div class="info-card">
            <h4>Editar usuario y funciones</h4>
            <p class="modal-copy">Actualice los datos de la cuenta. Las funciones se muestran de forma predeterminada según el rol seleccionado.</p>
            <div class="modal-stack">
              ${modalField("Nombre completo", `<input value="Laura Mendoza Cruz" placeholder="Nombre completo">`)}
              <div class="modal-grid">
                ${modalField("Usuario", `<input value="lmendoza" placeholder="usuario">`)}
                ${modalField("Correo", `<input type="email" value="lmendoza@revisor.gob.mx" placeholder="correo@dominio.gob.mx">`)}
                ${modalField("Rol", `<select><option>Usuario Enlace</option><option selected>Autoridad Revisora</option><option>Sujeto Obligado</option><option>Área Jurídica</option><option>Administrador</option></select>`)}
                ${modalField("Estatus", `<select><option selected>Activo</option><option>Inactivo</option><option>Suspendido</option></select>`)}
              </div>
              ${modalField("Dependencia", `<select data-dependency-catalog><option value="">Seleccione dependencia...</option><option selected>Secretaría General Municipal</option></select>`)}
              <div class="info-card">
                <h4>Funciones habilitadas</h4>
                <p class="modal-copy">Rol Autoridad Revisora: solo se muestran las funciones que le corresponden por defecto. No se pueden agregar funciones fuera del rol.</p>
                <div class="check-grid">
                  <label class="check-card"><input type="checkbox" checked disabled><div><strong>Inicio</strong><span>Ver panel principal y pendientes.</span></div></label>
                  <label class="check-card"><input type="checkbox" checked disabled><div><strong>Trámites</strong><span>Revisar, observar y aprobar trámites.</span></div></label>
                  <label class="check-card"><input type="checkbox" checked disabled><div><strong>Agenda</strong><span>Revisar, observar y aprobar acciones de agenda.</span></div></label>
                  <label class="check-card"><input type="checkbox" checked disabled><div><strong>Observaciones por sección</strong><span>Solicitar correcciones al Enlace por bloque.</span></div></label>
                  <label class="check-card"><input type="checkbox" checked disabled><div><strong>Aprobación</strong><span>Aprobar registros firmados y validados.</span></div></label>
                  <label class="check-card"><input type="checkbox" checked disabled><div><strong>Acuses</strong><span>Consultar y descargar constancias de validación.</span></div></label>
                </div>
              </div>
            </div>
          </div>
        `,
        historial: `
          <div class="info-card">
            <h4>Historial del Trámite TRM-8821</h4>
            <div class="timeline-list">
              <article><small>10 Oct 2023</small><span>Trámite enviado a revisión.</span></article>
              <article><small>12 Oct 2023</small><span>Observaciones registradas por Autoridad Revisora.</span></article>
              <article><small>15 Oct 2023</small><span>Trámite devuelto al enlace para corrección.</span></article>
            </div>
          </div>
        `,
        regulacion: `
          <div class="info-card">
            <h4>Reglamento de Comercio Municipal</h4>
            ${modalRows([
              { label: "Tipo", value: "Reglamento" },
              { label: "Dependencia", value: "Dirección General de Gobierno Digital" },
              { label: "Publicación", value: "04 Ene 2023" },
              { label: "Trámites vinculados", value: "8" },
            ])}
          </div>
        `,
        tramiteGuardado: `
          <div class="info-card">
            <h4>Trámite guardado exitosamente</h4>
            <p class="modal-copy">El trámite fue registrado en el sistema PUNTA. Se generó el folio, la ficha portal y la bitácora correspondiente. El siguiente paso es que el Sujeto Obligado y el Enlace acepten la información para enviarla a revisión por la Autoridad Revisora.</p>
          </div>
        `,
        guardado: `
          <div class="info-card">
            <h4>Acción guardada</h4>
            <p class="modal-copy">Se generó el folio, el calendario de seguimiento y la bitácora correspondiente.</p>
          </div>
        `,
        correccionesEnviadas: `
          <div class="info-card">
            <h4>Correcciones enviadas</h4>
            <p class="modal-copy">La información corregida fue enviada nuevamente a revisión. El estatus del registro se actualizará en la bitácora y el log de cambios quedará visible para Sujeto Obligado y Autoridad Revisora.</p>
          </div>
        `,
        logCambios: `
          <div class="info-card">
            <h4>Correcciones enviadas por el Enlace</h4>
            ${modalRows([
              { label: "Registro", value: "TRM-5543 - Renovación de Licencia Comercial" },
              { label: "Enviado por", value: "Usuario Enlace" },
              { label: "Fecha de envío", value: "16 Oct 2024, 13:42 hrs" },
              { label: "Estado", value: "Correcciones en revisión" },
            ])}
          </div>
          <div class="change-log-list">
            <article>
              <span>Fundamento jurídico</span>
              <strong>Artículo / fracción</strong>
              <div class="change-log-values">
                <p><b>Antes</b>Artículo 41, Fracción I de Ley de Ingresos 2023</p>
                <p><b>Ahora</b>Artículo 45, Fracción II de Ley de Ingresos Municipal vigente</p>
              </div>
              <small>Atiende observación de Ana Patricia Gómez · Área Jurídica Revisora</small>
            </article>
            <article>
              <span>Requisitos</span>
              <strong>Dictamen de Protección Civil</strong>
              <div class="change-log-values">
                <p><b>Antes</b>Sin vigencia especificada</p>
                <p><b>Ahora</b>Vigencia de 12 meses a partir de su emisión</p>
              </div>
              <small>Atiende observación de Carlos Méndez Ruiz · Autoridad Revisora</small>
            </article>
            <article>
              <span>Operación y costos</span>
              <strong>Costo del trámite</strong>
              <div class="change-log-values">
                <p><b>Antes</b>$450.00 MXN</p>
                <p><b>Ahora</b>$500.00 MXN</p>
              </div>
              <small>Atiende observación de María Fernanda López · Autoridad Revisora</small>
            </article>
          </div>
        `,
        observacionSeccion: `
          <div class="info-card">
            <h4>Observación en ${currentObservationSection}</h4>
            <p class="modal-copy">Registre la observación únicamente para esta sección. El Enlace la verá asociada al bloque correspondiente para corregirla.</p>
          </div>
          <div class="modal-stack">
            ${modalField("Sección", `<input value="${currentObservationSection}" readonly>`)}
            ${modalField("Prioridad", `<select id="sectionObservationPriority"><option>Alta</option><option>Media</option></select>`)}
            ${modalField("Descripción de la observación *", `<textarea id="sectionObservationText" placeholder="Describe qué debe corregir el Enlace en esta sección..."></textarea>`)}
            ${modalField("Imagen de soporte opcional", `<input type="file">`)}
          </div>
        `,
        observacionesEnviadas: `
          <div class="info-card">
            <h4>Observaciones enviadas</h4>
            <p class="modal-copy">Las observaciones registradas fueron enviadas al Enlace. El Enlace es el responsable de atender y corregir la información por sección.</p>
          </div>
        `,
        firmaSujeto: `
          <div class="info-card">
            <h4>Aceptación registrada</h4>
            <p class="modal-copy">El Sujeto Obligado aceptó la información del trámite. Ahora el Enlace debe aceptar para enviar a la Autoridad Revisora.</p>
          </div>
        `,
        firmaEnlace: `
          <div class="info-card">
            <h4>Aceptación del Enlace registrada</h4>
            <p class="modal-copy">El Enlace aceptó y envió el trámite a la Autoridad Revisora para revisión y aprobación.</p>
          </div>
        `,
        aprobacionTramite: `
          <div class="info-card">
            <h4>Trámite aprobado</h4>
            <p class="modal-copy">La Autoridad Revisora aprobó el trámite. El acuse está disponible para descarga y firma física.</p>
            <p class="modal-copy"><strong>Siguiente paso:</strong> descargue el acuse desde la pantalla de Firmas para firma física del Sujeto Obligado y el Enlace.</p>
          </div>
        `,
        aprobacionAgenda: `
          <div class="info-card">
            <h4>Acción de agenda aprobada</h4>
            <p class="modal-copy">La Autoridad Revisora aprobó la acción. El acuse está disponible para descarga y firma física.</p>
          </div>
        `,
        descargarTodo: `
          <div class="info-card">
            <h4>Regulaciones disponibles para descarga</h4>
            <p class="modal-copy">Se descargarán los archivos PDF de las regulaciones cargadas en la dependencia.</p>
            <div class="modal-grid u-mt-md">
              ${data.regulaciones.map(function(r) {
                return '<div class="modal-data-item" class="modal-download-item">'
                  + '<div><strong>' + r.nombre + '</strong><br><span class="modal-download-meta">' + r.tipo + ' · ' + r.id + ' · ' + r.tramites + ' trámites vinculados</span></div>'
                  + '<span class="' + (r.cargada ? 'modal-download-available' : 'modal-download-missing') + '">' + (r.cargada ? '✓ Disponible' : 'âš  Sin archivo') + '</span>'
                  + '</div>';
              }).join("")}
            </div>
            <p class="modal-copy modal-small-note">Solo se descargarán las regulaciones marcadas como "Disponible". Las regulaciones sin archivo deben ser cargadas por el Área Jurídica.</p>
          </div>
        `,
        descargarRegulacion: `
          <div class="info-card">
            <h4>Descargar regulación</h4>
            <p class="modal-copy">Documento disponible para descarga y consulta institucional.</p>
          </div>
        `,
        detalle: `
          <div class="info-card">
            <h4>Atender elemento</h4>
            <p class="modal-copy">Revisa la información señalada y atiende la acción pendiente.</p>
          </div>
        `,
        constanciaExencion: `
          <div class="info-card modal-doc-frame">
            <div class="modal-doc-header-accent">
              <div class="modal-doc-org-accent">H. AYUNTAMIENTO DE LA PAZ, B.C.S.</div>
              <h4 class="modal-doc-title-accent">CONSTANCIA DE EXENCIÓN DEL AIR</h4>
            </div>
            <p class="modal-copy">El Sujeto Obligado manifestó que la propuesta regulatoria se ubica en un supuesto de exención previsto en el <strong>artículo 36 de la LNETB</strong>. La plataforma PUNTA emite esta constancia de forma automática.</p>
            <p class="modal-copy u-mt-md"><strong>Con esta constancia, el Sujeto Obligado puede solicitar la publicación de la propuesta regulatoria en el Medio de Difusión Oficial.</strong></p>
          </div>
        `,
        propuestaGuardada: `
          <div class="info-card">
            <h4>Propuesta registrada en Agenda Regulatoria</h4>
            <p class="modal-copy">La propuesta regulatoria fue registrada exitosamente. Se someterá a consulta pública durante 10 días. Después, según el costo burocrático y el umbral de proporcionalidad, se determinará si requiere AIR completo o puede solicitar exención.</p>
          </div>
        `,
        airEnviado: `
          <div class="info-card">
            <h4>AIR enviado para evaluación</h4>
            <p class="modal-copy">El Análisis de Impacto Regulatorio fue enviado a la Autoridad de Simplificación y Digitalización. Se someterá a consulta pública y posteriormente se emitirá dictamen. Requiere firma del Enlace, Jurídico y Sujeto Obligado.</p>
          </div>
        `,
        detallePropuesta: `
          <div class="info-card">
            <h4>Detalle de propuesta regulatoria</h4>
            ${modalRows([
              { label: "Folio", value: "PR-2026-001" },
              { label: "Nombre", value: "Reglamento de Comercio Ambulante" },
              { label: "Tipo", value: "Reglamento" },
              { label: "Dependencia", value: "Dirección de Comercio" },
              { label: "Materia", value: "Comercio" },
              { label: "Costos burocráticos", value: "Sí" },
              { label: "Fecha tentativa", value: "15 Jun 2026" },
            ])}
          </div>
        `,
        detalleAIR: `
          <div class="info-card">
            <h4>Detalle del Análisis de Impacto Regulatorio</h4>
            ${modalRows([
              { label: "Folio AIR", value: "AIR-2026-001" },
              { label: "Propuesta", value: "Reglamento de Comercio Ambulante" },
              { label: "Estatus", value: "Dictaminado" },
              { label: "Consulta pública", value: "Completada (20 días)" },
              { label: "Dictamen", value: "Aprobado con recomendaciones" },
            ])}
          </div>
        `,
        dictamen: `
          <div class="info-card">
            <h4>Dictamen del AIR</h4>
            <p class="modal-copy"><strong>Resultado:</strong> Aprobado con recomendaciones de simplificación.</p>
            <p class="modal-copy"><strong>Observaciones:</strong> Se recomienda incluir trámite digital y reducir plazo de resolución de 15 a 10 días hábiles.</p>
            <p class="modal-copy"><strong>Dictaminado por:</strong> Autoridad de Simplificación y Digitalización</p>
          </div>
        `,
      };
  return bodies[kind] || null;
}
