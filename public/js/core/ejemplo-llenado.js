/**
 * Fase D: Botón de ejemplo de llenado.
 *
 * Llena formularios con datos ficticios coherentes para pruebas.
 * No guarda ni envía. El usuario debe revisar y guardar manualmente.
 *
 * Uso: llamar window.llenarEjemplo('tramite') desde el botón.
 */
(function () {

  // ─── Datos de ejemplo por formulario ───

  var ejemplos = {

    tramite: {
      nombre_oficial: 'Licencia de Funcionamiento para Establecimiento Comercial Tipo A',
      servidor_publico: 'María Elena Rodríguez Castro',
      objetivo: 'Obtener la autorización municipal para operar un establecimiento comercial fijo con venta de productos al público en general dentro del Municipio de La Paz, B.C.S.',
      dirigido_a: 'ambas',
      frecuencia: 'Alta',
      volumen_anual: '1850',
      plazo_resolucion_cantidad: '10',
      plazo_resolucion_unidad: 'habiles',
      num_areas: '3',
      areas_participantes: 'Ventanilla Única, Tesorería Municipal, Protección Civil',
      visitas_requeridas: '2',
      nivel_digitalizacion: '1',
      monto_derechos: '1250.00',
      copias_cantidad: '3',
      copias_precio: '1.50',
      'requisitos[0][nombre]': 'Identificación oficial vigente (INE o pasaporte)',
      'requisitos[0][original]': '1',
      'requisitos[0][copia]': '1',
      'requisitos[0][dias]': '0',
      'requisitos[0][horas]': '1',
      'requisitos[0][observaciones]': 'Vigencia máxima 10 años. Se aceptan INE, pasaporte o cédula profesional.',
      fundamento_normativa: 'Reglamento de Comercio del Municipio de La Paz',
      fundamento_tipo: 'Reglamento',
      fundamento_articulo: 'Artículo 45, Fracción II',
      fundamento_resumen: 'Establece que toda persona física o moral que desee operar un establecimiento comercial fijo deberá obtener la licencia de funcionamiento correspondiente ante la autoridad municipal.',
      portal_nombre: 'Licencia para abrir un negocio',
      portal_modalidad: 'Presencial',
      portal_descripcion: 'Si quiere abrir un negocio en La Paz, necesita esta licencia. Lleve su identificación, comprobante de domicilio y el pago de derechos a Ventanilla Única.',
      portal_costo: '$1,250.00 MXN',
    },

    agenda_regulatoria: {
      responsable_nombre: 'Carlos Méndez Ruiz',
      responsable_cargo: 'Enlace de Simplificación',
      tipo_regulacion: 'Reglamento',
      materia: 'Comercio',
      nombre: 'Reglamento de Comercio Ambulante del Municipio de La Paz, B.C.S.',
      sectores_impactados: 'Comercio informal, vendedores ambulantes, tianguistas, mercados sobre ruedas, ciudadanía consumidora',
      fecha_tentativa: _fechaEnNMeses(3),
      justificacion: 'El municipio carece de un instrumento normativo actualizado que regule de manera integral la actividad comercial en vía pública. El reglamento vigente data de 2008 y no contempla las modalidades actuales de comercio ambulante ni los criterios de ordenamiento territorial.',
      problematica: 'Existe una proliferación desordenada de puestos ambulantes en zonas turísticas y comerciales que genera conflictos viales, problemas de higiene y competencia desleal con el comercio establecido. La falta de regulación actualizada impide al municipio ordenar esta actividad de manera efectiva.',
      alternativas: 'Se evaluaron tres alternativas: 1) Campaña voluntaria de reordenamiento (descartada por falta de mecanismos de cumplimiento), 2) Acuerdo con cámaras de comercio para autorregulación (insuficiente cobertura), 3) Emisión de reglamento municipal (seleccionada por dar certeza jurídica a todas las partes).',
      beneficios: 'Ordenamiento del comercio en vía pública, reducción de conflictos viales, mejora de imagen urbana en zonas turísticas, certeza jurídica para vendedores ambulantes, recaudación por permisos.',
      genera_costos_burocraticos: '1',
      costos_burocraticos: 'Se crearán dos trámites nuevos: Permiso de Comercio Ambulante (anual) y Constancia de Ubicación Autorizada. Se estima un costo de $350 por permiso anual.',
      impacta_tramites_existentes: '1',
      tramites_impacta: 'Modifica el trámite actual de "Permiso de Venta en Vía Pública" que opera sin regulación clara. También impacta la Licencia de Funcionamiento al definir zonas exclusivas.',
      acciones_simplificacion: 'Unificar el permiso actual de venta en vía pública con el nuevo permiso de comercio ambulante, eliminando la duplicidad de trámites.',
      acciones_digitalizacion: 'Habilitar solicitud en línea del permiso anual y mapa digital de ubicaciones autorizadas consultable por la ciudadanía.',
      fundamento_juridico: 'Art. 115 fracc. II Constitución Política de los Estados Unidos Mexicanos; Art. 42 fracc. VI Ley Orgánica del Municipio Libre del Estado de B.C.S.; Art. 3 fracc. XXVIII LNETB.',
      impacta_comercio_inversion: '1',
      impacto_comercio: 'Establece nuevas condiciones para el ejercicio del comercio ambulante: zonas autorizadas, horarios, higiene y presentación. Impacta a aproximadamente 2,400 vendedores ambulantes registrados y no registrados.',
      presenta_proyecto: 'no',
      num_anexos: '0',
    },

    regulacion: {
      nombre: 'Reglamento de Protección Civil del Municipio de La Paz, Baja California Sur',
      tipo: 'Reglamento',
      materia: 'Protección Civil',
      fecha_publicacion: _fechaHace(6),
      fecha_vigencia: _fechaHace(5),
      objetivo: 'Establecer las bases de coordinación, organización y funcionamiento del Sistema Municipal de Protección Civil, así como regular las acciones de prevención, auxilio y recuperación ante fenómenos perturbadores de origen natural o humano en el Municipio de La Paz.',
      fundamento_juridico: 'Art. 115 fracc. III inciso i) Constitución Política; Art. 8 Ley General de Protección Civil; Art. 12 Ley de Protección Civil del Estado de B.C.S.; Art. 42 fracc. XII Ley Orgánica del Municipio Libre.',
      palabras_clave: 'protección civil, emergencia, desastre, huracán, sismo, evacuación, dictamen, programa interno',
      deroga_otra: '0',
      resumen: 'Este reglamento regula cómo el municipio se organiza para prevenir y responder ante emergencias como huracanes, sismos e incendios. Establece los requisitos para obtener dictámenes de protección civil para negocios y eventos.',
    },

    agenda_syd: {
      tramite_nombre_oficial: 'Licencia de Funcionamiento para Establecimiento Comercial',
      tramite_servidor_publico: 'Ana Patricia Flores López',
      tramite_objetivo: 'Obtener la autorización municipal para operar un establecimiento comercial fijo en el Municipio de La Paz.',
      tramite_dirigido_a: 'ambas',
      tramite_frecuencia: 'Alta',
      tramite_volumen_anual: '1850',
      tramite_plazo_resolucion_cantidad: '10',
      tramite_plazo_resolucion_unidad: 'habiles',
      tramite_num_areas: '3',
      tramite_visitas_requeridas: '2',
      tramite_tiempo_atencion_horas: '1',
      tramite_tiempo_atencion_min: '30',
      tramite_tiempo_espera_horas: '0',
      tramite_tiempo_espera_min: '45',
      tramite_tiempo_traslado_horas: '0',
      tramite_tiempo_traslado_min: '30',
      tramite_nivel_digitalizacion: '1',
      tramite_copias_cantidad: '3',
      tramite_copias_precio: '5.00',
      tramite_fundamento: 'Reglamento de Comercio del Municipio de La Paz, artículo 45, fracción II.',
      descripcion: 'Reducir requisitos y digitalizar la solicitud para acortar el tiempo de atención.',
      meta: 'Reducir 40% el tiempo de resolución',
      indicador: 'Días promedio de resolución',
    },
  };

  // ─── Funciones auxiliares ───

  function _fechaEnNMeses(n) {
    var d = new Date();
    d.setMonth(d.getMonth() + n);
    return d.toISOString().split('T')[0];
  }

  function _fechaHace(meses) {
    var d = new Date();
    d.setMonth(d.getMonth() - meses);
    return d.toISOString().split('T')[0];
  }

  function llenar(nombre, valor) {
    var campo = document.querySelector('[name="' + nombre + '"]');
    if (!campo) return;

    if (campo.tagName === 'SELECT') {
      // Buscar opción por value o por texto
      for (var i = 0; i < campo.options.length; i++) {
        if (campo.options[i].value === valor || campo.options[i].textContent.trim() === valor) {
          campo.selectedIndex = i;
          campo.dispatchEvent(new Event('change', { bubbles: true }));
          return;
        }
      }
    } else if (campo.tagName === 'TEXTAREA') {
      campo.value = valor;
      campo.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (campo.type === 'file') {
      // No se puede llenar un input file por seguridad
      return;
    } else {
      if (campo.readOnly) return; // no sobreescribir readonly (homoclave)
      campo.value = valor;
      campo.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  // ─── API pública ───

  window.llenarEjemplo = function (tipo) {
    var datos = ejemplos[tipo];
    if (!datos) {
      console.warn('No hay datos de ejemplo para:', tipo);
      return;
    }

    Object.keys(datos).forEach(function (nombre) {
      llenar(nombre, datos[nombre]);
    });

    // Mostrar advertencia
    var aviso = document.getElementById('aviso-ejemplo');
    if (aviso) aviso.style.display = '';
  };

  window.limpiarEjemplo = function () {
    var form = document.querySelector('form');
    if (!form) return;

    form.querySelectorAll('input, textarea, select').forEach(function (campo) {
      if (campo.readOnly || campo.disabled || campo.type === 'hidden') return;
      if (campo.tagName === 'SELECT') {
        campo.selectedIndex = 0;
        campo.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (campo.type === 'file') {
        campo.value = '';
      } else {
        campo.value = '';
        campo.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });

    var aviso = document.getElementById('aviso-ejemplo');
    if (aviso) aviso.style.display = 'none';
  };

})();
