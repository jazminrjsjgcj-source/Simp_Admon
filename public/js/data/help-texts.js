// ===============================
// PUNTA — Textos de ayuda contextual por campo
// Los textos se inyectan automáticamente junto a cada label
// La clave es el texto exacto del label (sin el asterisco)
// ===============================

const fieldHelpTexts = {

  // ===== WIZARD TRÁMITE — Paso 1: Identificación =====

  "Tipo de registro": "Seleccione Trámite si la persona ciudadana debe cumplir requisitos para obtener un documento o resolución. Seleccione Servicio si la dependencia ofrece una atención sin requisitos formales.",

  "Nombre oficial": "Escriba el nombre oficial completo del trámite o servicio tal como aparece en la normativa o en el catálogo institucional. Ejemplo: Licencia de Funcionamiento Tipo A.",

  "Dependencia responsable": "Se autollena con la dependencia asociada al usuario activo. El código orgánico se usa para generar la homoclave cuando aplica.",

  "Unidad administrativa": "Seleccione el área o dirección dentro de la dependencia que atiende directamente a la ciudadanía para este trámite. Ejemplo: Ventanilla Única, Dirección de Licencias.",

  "Persona servidora pública responsable": "Nombre completo de la persona titular del área que valida la información del trámite. Este dato es opcional pero recomendado para trazabilidad.",

  "¿Cuenta con homoclave?": "Seleccione Sí si el trámite ya tiene una clave asignada en el catálogo vigente. Seleccione No para que el sistema genere una clave provisional con formato W-XXX-XX-000.",

  "Clave u homoclave": "Clave alfanumérica que identifica al trámite. Formato: W-XXX-XX-000 donde W=tipo de documento, XXX=código de dependencia, XX=código de área, 000=consecutivo. Ejemplo: T-110-01-001.",

  "Sector principal": "Seleccione el sector económico SCIAN al que pertenece el trámite. Esto permite clasificar y relacionar trámites por actividad económica.",

  "Subsector o actividad relacionada": "Seleccione el subsector específico dentro del sector elegido. Las opciones se actualizan automáticamente al cambiar el sector.",

  // ===== WIZARD TRÁMITE — Paso 2: Información =====

  "Objetivo del trámite": "Describa en una o dos oraciones qué resuelve el trámite para la persona ciudadana. Ejemplo: Obtener la autorización municipal para operar un establecimiento comercial.",

  "Población objetivo": "Seleccione a quién va dirigido el trámite: ciudadanía en general, personas físicas, personas morales o empresas.",

  "¿Está dirigido a grupos de atención prioritaria?": "Indique Sí si el trámite tiene condiciones especiales para grupos vulnerables como personas adultas mayores, personas con discapacidad, mujeres, etc.",

  "Grupo de atención prioritaria": "Seleccione el grupo prioritario al que se dirige el trámite. Solo aparece si respondió Sí a la pregunta anterior.",

  "Frecuencia": "Indique qué tan frecuentemente se solicita este trámite: Alta (diario o semanal), Media (mensual), Baja (trimestral o menos), Eventual (esporádico).",

  "Volumen anual estimado": "Escriba el número aproximado de solicitudes que recibe este trámite en un año. Ejemplo: 1250.",

  "¿Guarda relación con otros trámites o servicios?": "Indique Sí si este trámite es requisito previo de otro, o si requiere un trámite anterior para completarse.",

  "Buscar trámite relacionado": "Busque el trámite relacionado por nombre, folio u homoclave. Si no aparece, puede escribirlo manualmente en el campo siguiente.",

  "Si no está en catálogo, escriba el nombre": "Escriba el nombre del trámite relacionado si no lo encontró en el buscador. Esto permite registrar la relación aunque el trámite no esté en el sistema.",

  "Plazo máximo de resolución": "Indique el tiempo máximo que tiene la dependencia para resolver el trámite después de recibir la solicitud completa. Ejemplo: 15 días hábiles.",

  // ===== WIZARD TRÁMITE — Paso 3: Operación =====

  "Número de áreas que participan": "Capture cuántas áreas o departamentos internos intervienen en el proceso de resolución del trámite. Este número se usa para estimar la complejidad del costo burocrático. Ejemplo: 3.",
  "Áreas que participan": "Nombre las áreas o departamentos que intervienen, si se conocen. Ejemplo: Ventanilla Única, Tesorería, Protección Civil.",

  "¿Existen procesos redundantes?": "Indique Sí si durante el trámite se repiten pasos, se solicitan documentos ya entregados, o hay validaciones que podrían automatizarse. Puede capturar más de un proceso redundante.",

  "Tipo de redundancia": "Seleccione el tipo de proceso que se repite: duplicidad de captura, solicitud repetida de documentos, revisión por más de un área, traslado innecesario, o validación manual automatizable.",

  "Proceso o área donde ocurre": "Escriba en qué parte del proceso o en qué área se presenta la redundancia. Ejemplo: Ventanilla, revisión documental, caja. Si conoce las áreas que intervienen, captúrelas en el campo correspondiente.",

  "Descripción del proceso redundante": "Describa qué se repite, quién lo realiza y cómo afecta a la persona usuaria. Esta información sirve para identificar oportunidades de simplificación.",

  "Visitas requeridas": "Número de veces que la persona debe trasladarse físicamente a la dependencia para completar el trámite. Incluya todas las visitas: solicitud, entrega de documentos, pago, recepción.",

  "Tiempo promedio de traslado por visita": "Tiempo estimado que la persona invierte en trasladarse de ida y vuelta a la dependencia por cada visita.",

  "Tiempo promedio de espera por visita": "Tiempo estimado que la persona espera en la dependencia antes de ser atendida en cada visita.",

  "Tiempo promedio de atención por visita": "Tiempo estimado que dura la atención directa (ventanilla, revisión, entrega) por cada visita.",

  "Costo burocrático estimado": "Se calcula automáticamente como Bajo, Medio o Alto según el total de tiempo invertido por la ciudadanía (traslados + esperas + atención + obtención de requisitos) y el número de visitas y áreas.",

  "Proceso de atención": "Describa paso a paso lo que la persona ciudadana debe hacer para completar el trámite. Cada paso incluye la acción y un detalle breve. Agregue pasos con el botón inferior.",

  "Proceso de resolución interno": "Describa paso a paso lo que la dependencia hace internamente para resolver el trámite. Cada paso incluye la actividad y el área responsable.",

  "Tiempo burocrático total estimado": "Se calcula automáticamente sumando todos los tiempos de traslado, espera, atención y obtención de requisitos. Sirve para medir la carga administrativa sobre la ciudadanía.",

  // ===== WIZARD TRÁMITE — Paso 4: Requisitos =====

  "Nombre del requisito": "Escriba el nombre completo del documento o condición que la persona debe presentar. Ejemplo: Identificación oficial vigente, Comprobante de domicilio.",

  "¿Se presenta en original?": "Indique si la persona debe presentar el documento original.",

  "¿Se presenta en copia?": "Indique si la persona debe presentar una copia del documento.",

  "Tipo de presentación": "Seleccione cómo se presenta el requisito: como documento, formato oficial, comprobante, producto de otro trámite, o en formato digital.",

  "Días estimados para obtenerlo": "Días que la persona necesita para conseguir este requisito antes de iniciar el trámite. Ejemplo: un dictamen puede tardar 5 días.",

  "Horas estimadas para obtenerlo": "Horas adicionales que la persona necesita para obtener el requisito, sumadas a los días.",

  "Minutos estimados para obtenerlo": "Minutos adicionales que la persona necesita para obtener el requisito.",

  "ID automático": "Clave única generada por el sistema para identificar este requisito. Se construye con la homoclave del trámite más un consecutivo. No es editable.",

  "Observaciones del requisito": "Información adicional sobre el requisito: vigencia máxima, formato requerido (PDF, original legible), autoridad que lo emite, o condiciones especiales.",

  "Es producto de otro trámite": "Active esta casilla si el requisito es un documento que la persona obtiene al completar otro trámite. Ejemplo: Dictamen de Protección Civil.",

  "Trámite de origen": "Busque o escriba el nombre del trámite que genera el documento requerido como requisito.",

  "Documento o producto emitido": "Nombre del documento que se obtiene del trámite de origen. Ejemplo: Dictamen aprobado, Constancia de no adeudo.",

  // ===== WIZARD TRÁMITE — Paso 5: Fundamento =====

  "Normativa vinculada": "Busque por nombre de regulación, artículo, fracción, tema o palabra clave. Al seleccionar una coincidencia se autollenan tipo de normativa, artículo/fracción y resumen del fundamento.",

  "Tipo de normativa": "Seleccione el tipo de instrumento normativo: Reglamento, Lineamiento, Manual o Acuerdo.",

  "Artículo / fracción": "Referencia legal específica. Puede autollenarse al seleccionar una normativa del buscador o capturarse manualmente si aún no está indexada.",

  "Resumen del fundamento": "Explique brevemente qué establece la normativa respecto a este trámite. Ejemplo: Disposición aplicable para cobro de derechos y emisión de licencia.",

  // ===== WIZARD TRÁMITE — Paso 6: Ficha portal =====

  "Nombre ciudadano": "Nombre del trámite en lenguaje sencillo, como lo entendería la ciudadanía. Puede diferir del nombre oficial. Se autollena desde el nombre del trámite.",

  "Homoclave o clave pública": "Clave con la que la ciudadanía puede buscar el trámite. Se autollena desde la homoclave del paso 1.",

  "Documento que obtiene": "Escriba qué documento o resolución recibe la persona al completar el trámite. Ejemplo: Licencia, permiso, constancia, autorización.",

  "Descripción para ciudadanía": "Explique en lenguaje claro y directo para qué sirve el trámite, quién debe realizarlo y cuándo aplica.",

  "Casos en que debe realizarse": "Describa las situaciones en que una persona necesita hacer este trámite. Ejemplo: Cuando requiere renovar, obtener, registrar o validar...",

  "Persona responsable": "Nombre de la persona o cargo responsable de la atención al público. Se autollena si fue capturado en el paso 1.",

  "Área de atención ciudadana": "Nombre del área o ventanilla donde la ciudadanía es atendida. Ejemplo: Ventanilla Única, Dirección de Licencias.",

  "Modalidad": "Seleccione cómo se puede realizar el trámite: presencial, en línea, mixta, con cita, por ventanilla, o por correo electrónico.",

  "Canal principal": "Escriba el medio principal por el que la ciudadanía accede al trámite. Ejemplo: Portal ciudadano, ventanilla, correo.",

  "¿Requiere cita?": "Indique si la persona debe agendar cita previa para realizar el trámite.",

  "Enlace para cita o atención": "URL donde la ciudadanía puede agendar cita o acceder al trámite en línea.",

  "Fundamento aplicable": "Normativa que sustenta el trámite, en lenguaje accesible. Se autollena desde el fundamento del paso 5.",

  "Artículo o fracción": "Referencia legal específica. Se autollena desde el paso 5.",

  "Documento normativo público": "Nombre o enlace del documento normativo que la ciudadanía puede consultar.",

  "Resumen ciudadano del fundamento": "Explique de forma simple por qué existe el trámite y qué autoridad lo solicita, dirigido a la ciudadanía.",

  "¿Requiere formato?": "Indique si el trámite requiere llenar un formato oficial descargable o un formulario en línea.",

  "Nombre del formato": "Nombre del formato requerido. Ejemplo: Solicitud de licencia.",

  "Archivo del formato": "Suba el archivo del formato oficial en PDF o Word para descarga ciudadana.",

  "Enlace al formulario": "URL del formulario en línea si existe.",

  "Ajuste para publicación": "Opcional: precise cómo deben presentarse los requisitos para la ciudadanía, si difiere de lo capturado.",

  "Texto público del procedimiento": "Opcional: ajuste el paso a paso para que sea más claro para la ciudadanía, si difiere de lo capturado.",

  "¿Aplica inspección o verificación?": "Indique si la autoridad realiza una inspección física o verificación documental antes de emitir la resolución.",

  "Momento en que se realiza": "Escriba en qué etapa del trámite se realiza la inspección. Ejemplo: Antes de emitir la resolución.",

  "Descripción de la inspección": "Explique qué revisa la autoridad y cómo se informa a la persona solicitante del resultado.",

  "Plazo de resolución": "Tiempo máximo para que la dependencia emita la resolución. Ejemplo: 10 días hábiles.",

  "Plazo de prevención": "Tiempo que tiene la dependencia para solicitar información adicional a la persona. Ejemplo: 3 días hábiles.",

  "Respuesta a prevención": "Tiempo que tiene la persona para responder a la prevención. Ejemplo: 5 días hábiles.",

  "Ficta aplicable": "Seleccione qué ocurre si la dependencia no resuelve en plazo: Afirmativa (se aprueba), Negativa (se rechaza), o No aplica.",

  "Costo público": "Monto que la persona debe pagar. Escriba la cantidad o indique si es gratuito. Ejemplo: $500.00 MXN.",

  "Base de cálculo": "Referencia para calcular el costo: UMA, tarifa fija, porcentaje u otra base.",

  "Formas de pago": "Seleccione los medios de pago aceptados: caja, pago en línea, banco, transferencia.",

  "Enlace de pago": "URL del portal de pago en línea si está disponible.",

  "Resultado que se obtiene": "Seleccione el tipo de documento que recibe la persona: licencia, permiso, constancia, autorización, registro, dictamen, acuse.",

  "Nombre del documento resultado": "Nombre específico del documento que entrega la dependencia. Ejemplo: Licencia de funcionamiento.",

  "Medio de entrega": "Seleccione cómo se entrega el resultado: presencial, descarga digital, correo electrónico, mensajería.",

  "Formato del resultado": "Seleccione el formato: documento físico, PDF, acuse digital, registro en sistema.",

  "Vigencia": "Duración de validez del documento resultado. Ejemplo: 1 año, indefinida, por evento.",

  "Renovación": "Indique si el documento requiere renovación y con qué frecuencia.",

  "Oficina o ventanilla": "Ubicación exacta donde se atiende a la ciudadanía. Ejemplo: Palacio Municipal, Planta Baja, Ventanilla 3.",

  "Horario de atención": "Días y horas de atención. Ejemplo: Lunes a Viernes de 9:00 a 15:00 hrs.",

  "Teléfono": "Número de teléfono con extensión si aplica. Ejemplo: 612 123 4567 ext. 120.",

  "Correo": "Correo electrónico de contacto para la ciudadanía.",

  "Trámite relacionado": "Nombre del trámite que tiene relación con este. Se autollena si indicó relación en paso 2.",

  "Tipo de relación": "Seleccione cómo se relacionan: antecedente (se hace antes), requisito (se necesita para este), complementario (se hacen juntos), posterior (se hace después).",

  "Enlace del trámite": "URL donde la ciudadanía puede iniciar o consultar el trámite en línea.",

  "Carga de documentos": "Indique si el trámite permite subir documentos de forma digital.",

  "Pago en línea": "Indique si el trámite permite realizar el pago de forma digital.",

  "Seguimiento en línea": "Indique si la persona puede consultar el estatus de su solicitud por internet.",

  "Resolución digital": "Indique si la resolución o documento resultado se puede obtener de forma digital.",

  "Número de solicitudes": "Cantidad de solicitudes recibidas. Se autollena desde el volumen anual estimado del paso 2.",

  "Temporada de mayor demanda": "Indique en qué época del año se reciben más solicitudes. Ejemplo: Enero-marzo, cierre fiscal, vacaciones.",

  "Observaciones para publicación": "Notas útiles para la ciudadanía: restricciones, aclaraciones, recomendaciones o información adicional.",

  "Anexos públicos": "Suba archivos de apoyo que la ciudadanía pueda descargar: guías, instructivos, formatos.",

  "Documentos de apoyo": "Nombre o descripción de documentos de apoyo disponibles. Ejemplo: Guía, instructivo, formato editable.",

  "Área que valida": "Nombre de la dependencia o área que da el visto bueno antes de publicar.",

  "Persona que valida": "Nombre completo de la persona que autoriza la publicación.",

  "Estatus de validación": "Estado actual de la validación: pendiente, validado por área, o requiere ajuste.",

  "Fecha de validación": "Fecha en que se realizó o realizará la validación.",

  // ===== WIZARD AGENDA — Paso 1: Inicio =====

  "¿El trámite está en el catálogo?": "Si el trámite ya está registrado en PUNTA, seleccione Sí para buscarlo. Si no sabe, seleccione No sé para usar el buscador. Si no existe, seleccione No está para capturarlo desde cero.",

  "Buscar trámite existente": "Escriba el nombre, folio u homoclave del trámite para vincularlo a esta acción de agenda. Use el buscador antes de capturar desde cero para evitar duplicados.",

  "Registrar desde cero": "Si el trámite no existe en el catálogo, capture aquí los datos básicos. El trámite queda creado y vinculado automáticamente a esta acción de agenda.",

  // ===== WIZARD AGENDA — Paso 2: Alcance =====

  "Solo simplificación": "Seleccione si la acción busca únicamente reducir requisitos, tiempos, visitas o procesos internos sin incorporar tecnología digital.",

  "Solo digitalización": "Seleccione si la acción busca únicamente incorporar herramientas digitales (pago en línea, formulario digital, firma electrónica, etc.).",

  "Simplificación y digitalización": "Seleccione si la acción combina mejoras de simplificación administrativa con incorporación de herramientas digitales.",

  // ===== WIZARD AGENDA — Paso 3: Requisitos y fundamento =====

  "¿La acción modifica requisitos o fundamento?": "Indique si esta acción de agenda cambia los requisitos que debe presentar la ciudadanía o modifica el fundamento legal del trámite vinculado.",

  "Nota de ajuste": "Capture únicamente si la acción modifica requisitos o fundamento. Explique qué cambió y por qué.",

  // ===== WIZARD AGENDA — Paso 4: Simplificación =====

  "Reducción de requisitos": "La acción eliminará documentos solicitados o reducirá la cantidad de requisitos que la ciudadanía debe presentar.",

  "Eliminación de requisitos": "La acción quitará completamente uno o más requisitos que ya no son necesarios o que pueden verificarse por otros medios.",

  "Reducción de tiempos": "La acción acortará los tiempos de atención, espera o resolución del trámite.",

  "Reducción de visitas": "La acción reducirá el número de veces que la persona debe trasladarse a la dependencia.",

  "Simplificación administrativa": "La acción mejorará la gestión interna del trámite sin cambiar requisitos ni tiempos para la ciudadanía.",

  "Simplificación normativa": "La acción ajustará reglas, criterios o fundamento legal aplicable para facilitar la operación del trámite.",

  "Mejora del proceso": "La acción optimizará pasos internos, áreas participantes o mecanismos de seguimiento del trámite.",

  "Otro": "Permite describir una acción de mejora que no está contemplada en las opciones anteriores.",

  "Objetivo de la simplificación": "Describa qué carga administrativa se reducirá y qué beneficio tendrá la ciudadanía con esta acción.",

  "Meta esperada": "Defina el resultado esperado en términos medibles. Ejemplo: Reducir de 5 a 3 los requisitos, eliminar 1 visita presencial.",

  "Indicador de cumplimiento": "Dato que permite verificar si la meta se cumplió. Ejemplo: Número de requisitos eliminados.",

  "Indicador de avance": "Dato que permite medir el progreso de implementación. Ejemplo: Porcentaje de avance del plan de simplificación.",

  "Descripción de otra acción": "Capture solo si seleccionó Otro. Describa la acción de mejora que no aparece en las opciones.",

  "¿Deriva de recomendación de la autoridad?": "Indique Sí si esta acción fue solicitada o recomendada por la Autoridad Revisora durante una revisión previa.",

  "Fecha compromiso de simplificación": "Fecha límite para completar las acciones de simplificación registradas.",

  // ===== WIZARD AGENDA — Paso 4: Digitalización =====

  "Nivel actual de digitalización": "Seleccione el nivel en que se encuentra actualmente el trámite: 0 (sin digitalización), 1 (información en línea), 2 (acceso digital básico), 3 (trámite en línea), 4 (plataforma integrada).",

  "Nivel objetivo de digitalización": "Seleccione el nivel al que se quiere llegar con esta acción. Debe ser mayor que el nivel actual.",

  "¿Requiere interoperabilidad con otra institución?": "Indique Sí si la digitalización necesita intercambio de datos con otra dependencia o nivel de gobierno.",

  "Objetivo de la digitalización": "Explique qué capacidad digital se implementará y cómo beneficiará a la ciudadanía.",

  "Fecha compromiso de digitalización": "Fecha límite para completar las acciones de digitalización registradas.",

  // ===== WIZARD AGENDA — Paso 5: Calendario =====

  "Periodo o semestre de ejecución": "Seleccione el periodo en que se implementarán las acciones. Puede ser el periodo activo u otro semestre del año.",

  "Fecha de inicio": "Fecha en que comenzarán las actividades de implementación.",

  "Fecha estimada de conclusión": "Fecha en que se espera completar todas las acciones comprometidas.",

  "Responsable de seguimiento": "Nombre de la persona o área encargada de dar seguimiento al avance de la acción.",

  "Guía para anexar documentos": "Describa qué documentos va a anexar y para qué sirven. Esto ayuda a la Autoridad Revisora a entender el soporte de la acción.",

  "Carga de anexos": "Suba los archivos de soporte: mapas de proceso, capturas de pantalla, bitácoras, diagnósticos o cualquier evidencia relevante. Tipos sugeridos: PDF, DOCX, XLSX, PNG, JPG, ZIP.",

  // ===== WIZARD NORMATIVA — Paso 1 =====

  "Denominación del Reglamento": "Escriba el nombre oficial y completo del instrumento normativo. Ejemplo: Reglamento de Comercio Municipal del H. Ayuntamiento de La Paz.",

  "Fecha de Asignación": "Fecha en que la autoridad asignó o registró este instrumento normativo en el sistema.",

  "Ámbito de Aplicación": "Seleccione el alcance territorial o institucional: Estatal (aplica en todo el estado), Municipal (aplica en La Paz), Interno (normativa interna), Federal (aplica en todo el país).",

  // ===== WIZARD NORMATIVA — Paso 2 =====

  "Autoridad Fuente": "Nombre de la autoridad que emitió o publicó la normativa. Ejemplo: Congreso del Estado, Cabildo Municipal, Gobierno Federal.",

  "Fecha de Publicación": "Fecha en que la normativa fue publicada oficialmente en el Periódico Oficial o Diario Oficial.",

  "Categoría": "Seleccione la categoría temática de la normativa según el área de gobierno que regula.",

  "Archivo del Instrumento (PDF)": "Suba el archivo PDF de la normativa. Tamaño máximo: 20 MB. Solo se aceptan archivos en formato PDF.",

  // ===== CORRECCIÓN DE TRÁMITE =====

  "Resumen de corrección": "Describa brevemente qué cambios realizó para atender las observaciones de la Autoridad Revisora. Sea específico: qué sección modificó y por qué.",

  "Responsable de corrección": "Nombre de la persona que realizó las correcciones. Se autollena con el usuario activo.",

  "Fecha de envío": "Fecha en que se envían las correcciones para nueva revisión.",

  "Anexos / evidencia": "Suba archivos que respalden las correcciones: capturas, documentos actualizados o evidencia de los cambios realizados.",

  "Clave interna": "Clave o identificador interno de la dependencia para este trámite, si difiere de la homoclave.",

  "Descripción general": "Descripción breve del trámite que aparecerá en los listados internos.",

  "Costo del trámite": "Monto en pesos que la ciudadanía debe pagar. Use formato: $000.00 MXN. Si es gratuito, escriba Gratuito.",

  "Forma de pago": "Seleccione los medios de pago aceptados para el trámite.",

  "Modalidad de solicitud": "Indique cómo puede la ciudadanía iniciar la solicitud del trámite: presencial, en línea, mixta.",

  "Nivel de digitalización": "Nivel actual de digitalización del trámite según la escala de 0 a 4.",

  "Horarios de atención": "Horarios en que la ciudadanía puede realizar el trámite. Ejemplo: Lunes a Viernes 9:00-15:00 hrs.",

  "Ubicación / ventanilla": "Dirección y ubicación exacta donde se atiende. Ejemplo: Palacio Municipal, Planta Baja, Ventanilla 3.",

  "Texto para ciudadanía": "Texto público que describe el trámite de forma accesible para la ciudadanía.",

  "Tiempo total estimado": "Suma total del tiempo que invierte la ciudadanía. Se calcula desde los tiempos de visita y obtención de requisitos.",

  "Folio / Homoclave": "Identificador único del registro. Se asigna automáticamente y no puede modificarse.",

  // ===== CORRECCIÓN DE AGENDA =====

  "Nombre de la acción": "Nombre que identifica esta acción de mejora. Debe ser descriptivo y conciso. Ejemplo: Pago en línea, Reducción de requisitos.",

  "Tipo de acción": "Indique si es simplificación, digitalización o ambas.",

  "Descripción breve": "Resuma en una o dos oraciones qué busca lograr esta acción de agenda.",

  "Agenda institucional": "Seleccione el programa institucional al que se vincula esta acción: Agenda de Simplificación, Agenda de Digitalización o Agenda Integral.",

  "Prioridad": "Seleccione la prioridad de implementación de esta acción: Alta, Media o Baja.",

  "Justificación del alcance": "Explique por qué se eligió este alcance (simplificación, digitalización o ambas) para esta acción.",

  "Origen del trámite": "Indique si el trámite ya existía en el catálogo o si fue creado desde cero.",

  "Trámite vinculado": "Nombre del trámite asociado a esta acción de agenda.",

  "Homoclave / folio": "Clave del trámite vinculado a esta acción de agenda.",

  "Notas de vinculación": "Información adicional sobre la relación entre el trámite y la acción de agenda.",

  "Objetivo de mejora": "Describa qué problema se resolverá y qué beneficio concreto tendrá la ciudadanía.",

  "Nivel actual": "Nivel de digitalización en que se encuentra actualmente el trámite vinculado.",

  "Nivel objetivo": "Nivel de digitalización que se espera alcanzar con esta acción.",

  "Indicador": "Dato medible que permite verificar si la meta se cumplió.",

  "Meta cuantificable": "Resultado esperado en términos numéricos. Ejemplo: Reducir de 5 a 2 visitas, habilitar 100% de pagos en línea.",

  "Carga administrativa que disminuye": "Describa qué carga se reduce para la ciudadanía: tiempos, visitas, requisitos, costos.",

  "Periodo": "Seleccione el periodo de revisión en que se implementará la acción.",

  "Fecha inicio": "Fecha en que comenzarán las actividades de implementación.",

  "Fecha compromiso": "Fecha límite para completar la acción comprometida.",

  "Responsable operativo": "Nombre de la persona o área responsable de implementar la acción.",

  "Estatus actual": "Estado actual de implementación: diagnóstico, en implementación, digitalización parcial, completado.",

  "Evidencia": "Suba archivos que demuestren el avance o cumplimiento de la acción.",

  "Declaración de envío": "Declaración formal de que la información registrada es verídica y completa.",

  // ===== OTROS CAMPOS COMUNES =====

  "Buscar": "Escriba un nombre, homoclave, folio o palabra clave para filtrar los registros de la tabla.",

  "Estatus": "Estado actual del registro dentro del flujo de revisión y firma.",

  "Dependencia": "Dependencia del H. Ayuntamiento de La Paz asociada al registro.",

  "Tipo": "Categoría o clasificación del registro.",

  "Costo": "Nivel de costo burocrático calculado: Bajo, Medio o Alto.",

  "Espera por visita": "Tiempo estimado de espera en minutos por cada visita de la ciudadanía.",

  "Traslado por visita": "Tiempo estimado de traslado en minutos por cada visita.",

  "Presentación": "Forma en que se debe presentar el requisito: original, copia, digital.",

  "Tiempo de obtención": "Tiempo que tarda la persona en conseguir este requisito.",

  "Nombre completo": "Escriba el nombre completo del usuario. Ejemplo: Laura Mendoza Cruz.",

  "Usuario": "Nombre de usuario para acceder al sistema. Debe ser único.",

  "Rol": "Seleccione el rol que tendrá el usuario: Enlace, Autoridad Revisora, Sujeto Obligado, Área Jurídica o Administrador.",
};





