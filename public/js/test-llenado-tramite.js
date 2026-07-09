/**
 * AYUDA DE PRUEBAS — Botones "Llenar TODO" y "Llenar ABSURDO" para TODOS
 * los wizards. Detecta el formulario y llena campos, pasos/subpasos,
 * checkboxes/radios que despliegan secciones y selects dinámicos.
 *
 * Se incluye en el layout (global). Solo muestra botones si detecta un wizard.
 * Para quitarlo, borra la línea del layout y este archivo.
 */
(function () {
  function onReady(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  onReady(function () {
    var formMap = { tramiteForm: 'tramite', propForm: 'agenda_regulatoria', wzForm: 'agenda_syd' };
    var form = null, tipo = null;
    for (var id in formMap) { form = document.getElementById(id); if (form) { tipo = formMap[id]; break; } }
    if (!form) { var f = document.querySelector('form[action*="regulaciones"]'); if (f) { form = f; tipo = 'regulacion'; } }
    if (!form) return;

    // ─── Helpers ───
    function field(name) { return form.querySelector('[name="' + name + '"]'); }
    function fire(el) { el.dispatchEvent(new Event('input',{bubbles:true})); el.dispatchEvent(new Event('change',{bubbles:true})); }

    function set(name, val) {
      var el = field(name);
      if (!el) return;
      if (el.tagName === 'SELECT') {
        for (var i = 0; i < el.options.length; i++) {
          if (el.options[i].value === String(val) || el.options[i].textContent.trim() === String(val)) {
            el.selectedIndex = i; fire(el); return;
          }
        }
        return;
      }
      if (el.type === 'radio') {
        var radios = form.querySelectorAll('[name="' + name + '"]');
        for (var j = 0; j < radios.length; j++) {
          if (radios[j].value === String(val)) { radios[j].checked = true; fire(radios[j]); return; }
        }
        return;
      }
      if (el.type === 'checkbox') { el.checked = !!val; fire(el); return; }
      var era = el.readOnly;
      if (era) el.readOnly = false;
      el.value = val; fire(el);
      if (era) el.readOnly = true;
    }

    function pickFirstReal(name) {
      var el = field(name);
      if (!el || el.tagName !== 'SELECT') return;
      for (var i = 0; i < el.options.length; i++) {
        if (el.options[i].value !== '') { el.selectedIndex = i; fire(el); return; }
      }
    }

    function checkGrupos(n) {
      var chks = form.querySelectorAll('input[type="checkbox"][name*="grupos"]');
      for (var i = 0; i < Math.min(n, chks.length); i++) {
        if (!chks[i].checked) { chks[i].checked = true; fire(chks[i]); }
      }
    }

    function clickBtn(text) {
      var btns = form.querySelectorAll('button[type="button"]');
      for (var i = 0; i < btns.length; i++) {
        if (btns[i].textContent.trim().indexOf(text) !== -1) { btns[i].click(); return true; }
      }
      return false;
    }

    function setHorarios() {
      var dias = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
      var obj = {};
      dias.forEach(function(d) { obj[d] = { inicio:'09:00', fin:'15:00', activo:true }; });
      var hj = field('horarios_json');
      if (hj) { hj.value = JSON.stringify(obj); fire(hj); }
      set('portal_horario', 'Lun–Vie 09:00–15:00 hrs');
    }

    // Llena los inputs de pasos recién renderizados en #pasosLista
    function llenarPasosRendered(datosPasos) {
      var cont = document.getElementById('pasosLista');
      if (!cont) return;
      var cards = cont.querySelectorAll('.requirement-card');
      datosPasos.forEach(function(p, i) {
        if (!cards[i]) return;
        var inputs = cards[i].querySelectorAll('input[type="text"]');
        var textareas = cards[i].querySelectorAll('textarea');
        if (inputs[0]) { inputs[0].value = p.area; fire(inputs[0]); }
        if (textareas[0]) { textareas[0].value = p.accion; fire(textareas[0]); }
      });
    }

    // ─── LLENADO VÁLIDO: TRÁMITE ───
    function llenarTramiteValido() {
      // Base: llamar al llenado existente si está disponible
      if (typeof window.llenarEjemplo === 'function') window.llenarEjemplo('tramite');

      // === Datos generales ===
      set('nombre_oficial', 'Licencia de Funcionamiento para Establecimiento Comercial Tipo A');
      pickFirstReal('tipo_tramite_id');
      pickFirstReal('unidad_id');
      pickFirstReal('sector_id');
      pickFirstReal('subsector_id');
      set('servidor_publico', 'María Elena Rodríguez Castro');
      set('objetivo', 'Obtener la autorización municipal para operar un establecimiento comercial fijo en el Municipio de La Paz, B.C.S.');
      set('dirigido_a', 'ambas');
      set('frecuencia', 'Alta');
      set('volumen_anual', '1850');
      set('plazo_resolucion_cantidad', '10');
      pickFirstReal('plazo_resolucion_unidad');
      set('num_areas', '3');
      set('areas_participantes', 'Ventanilla Única, Tesorería Municipal, Protección Civil');
      set('visitas_requeridas', '2');
      set('etapa_operacion', 'OPERACIÓN');
      set('poblacion_objetivo', 'Comerciantes establecidos del municipio');

      // === Tiempos ===
      set('tiempo_traslado_horas', '0'); set('tiempo_traslado_min', '30');
      set('tiempo_espera_horas', '0');   set('tiempo_espera_min', '45');
      set('tiempo_atencion_horas', '1'); set('tiempo_atencion_min', '30');
      set('copias_cantidad', '3'); set('copias_precio', '1.50');

      // === Costo variable (toggle checkbox) ===
      // Variable se auto-detecta por derecho, no hay checkbox global
      
      

      // === Fundamento del cobro (toggle radio) ===
      set('costo_fj_tiene', '1');
      set('costo_fj_norma', 'Ley de Hacienda Municipal');
      set('costo_fj_capitulo', 'Cap. II');
      set('costo_fj_articulo', 'Art. 45');

      // === Relacionados (toggle radio Sí) ===
      set('tipo_relacion', 'Secuencia');
      set('relacionados_detalle', 'Permiso de uso de suelo, Dictamen de protección civil');

      // === Derecho (con fundamento) ===
      if (typeof window.agregarDerecho === 'function') {
        window.agregarDerecho();
        if (typeof window.setDerecho === 'function') {
          window.setDerecho(0, 'concepto', 'Derecho por licencia de funcionamiento');
          window.setDerecho(0, 'monto', 500);
          window.setDerecho(0, 'unidad', 'pesos');
          window.setDerecho(0, 'fj_norma', 'Ley de Hacienda Municipal');
          window.setDerecho(0, 'fj_capitulo', 'Cap. IV');
          window.setDerecho(0, 'fj_articulo', 'Art. 89');
        }
        // Abrir el fundamento del derecho
        if (typeof window.setDerechoFj === 'function') {
          // No llamar con false — ya llenamos los campos
        }
      }

      // === Requisito: tipo + costo + fundamento (toggles) ===
      set('requisitos[0][nombre]', 'Identificación oficial vigente (INE o pasaporte)');
      set('requisitos[0][tipo]', 'original');
      set('requisitos[0][dias]', '0');
      set('requisitos[0][horas]', '1');
      set('requisitos[0][minutos]', '0');
      set('requisitos[0][observaciones]', 'Vigencia máxima 10 años. Se aceptan INE, pasaporte o cédula profesional.');
      // Toggle costo del requisito → "con_costo"
      set('requisitos[0][costo_modo]', 'con_costo');
      set('requisitos[0][costo_monto]', '250');
      set('requisitos[0][costo_unidad]', 'PESOS');
      // Toggle fundamento del requisito → Sí
      set('requisitos[0][fj_tiene]', '1');
      set('requisitos[0][fj_norma]', 'Reglamento de Comercio');
      set('requisitos[0][fj_capitulo]', 'Cap. III');
      set('requisitos[0][fj_articulo]', 'Art. 12');

      // === Fundamento jurídico del trámite (checkbox manual) ===
      var chkManual = document.getElementById('fundamentoManualChk');
      if (chkManual && !chkManual.checked) { chkManual.checked = true; fire(chkManual); }
      set('fundamento_normativa', 'Reglamento de Comercio del Municipio de La Paz');
      set('fundamento_tipo', 'Reglamento');
      set('fundamento_articulo', 'Artículo 45, Fracción II');
      set('fundamento_resumen', 'Establece que toda persona física o moral que desee operar un establecimiento comercial fijo deberá obtener la licencia correspondiente.');

      // === Ficha del Portal Ciudadano (completa) ===
      set('portal_nombre_ciudadano', 'Licencia para abrir un negocio');
      set('portal_homoclave_publica', 'LPZ-LIC-001');
      set('portal_documento_obtiene', 'Licencia de funcionamiento');
      set('portal_resultado', 'Licencia de funcionamiento vigente por 1 año');
      set('portal_modalidad', 'Mixta'); // despliega dirección + URL
      set('portal_canal_principal', 'Presencial');
      set('portal_medio_entrega', 'Presencial');
      set('portal_forma_pago', 'Efectivo');
      set('portal_vigencia', '1 año');
      set('portal_oficina', 'Ventanilla Única Municipal');
      set('portal_descripcion', 'Si quiere abrir un negocio en La Paz necesita esta licencia. Lleve identificación, comprobante de domicilio y el pago de derechos.');
      set('portal_casos_realizarse', 'Al iniciar la operación de un establecimiento comercial fijo en el municipio.');
      set('portal_direccion', 'Blvd. Forjadores 123, Col. Centro, La Paz, B.C.S.');
      set('portal_url', 'https://tramites.lapaz.gob.mx/licencia');
      set('portal_telefono', '6121234567');
      set('portal_correo', 'tramites@lapaz.gob.mx');
      set('portal_costo_publico', '$1,250.00 MXN');

      // === Horarios ===
      setHorarios();

      // === Grupos prioritarios (checkboxes) ===
      checkGrupos(3);

      // === Pasos del trámite: 3 pasos + 1 subpaso ===
      var datosPasos = [
        { area: 'Ventanilla de Comercio',    accion: 'Recibe la solicitud y verifica que la documentación esté completa.' },
        { area: 'Ventanilla de Comercio',    accion: 'Revisa que el comprobante de domicilio corresponda a una zona comercial autorizada.' },
        { area: 'Tesorería Municipal',       accion: 'Genera la línea de captura y recibe el pago de derechos correspondiente.' },
        { area: 'Dirección de Comercio',     accion: 'Emite la licencia de funcionamiento y la entrega al solicitante.' }
      ];
      // Agregar pasos clickeando botones (asegura que las funciones internas se ejecuten)
      clickBtn('Agregar paso');  // paso 1
      clickBtn('Agregar subpaso'); // subpaso 1.1
      clickBtn('Agregar paso');  // paso 2
      clickBtn('Agregar paso');  // paso 3
      // Llenar los campos renderizados
      setTimeout(function() { llenarPasosRendered(datosPasos); }, 100);
    }

    // ─── LLENADO ABSURDO: TRÁMITE ───
    function llenarTramiteAbsurdo() {
      var largo = new Array(601).join('X');

      set('nombre_oficial', largo);
      set('servidor_publico', largo + ' 12345 ###');
      set('objetivo', largo);
      set('volumen_anual', '-9999');
      set('plazo_resolucion_cantidad', '999999');
      set('num_areas', '99999');
      set('areas_participantes', largo);
      set('visitas_requeridas', '-50');
      set('poblacion_objetivo', largo);
      set('tiempo_traslado_horas', '99'); set('tiempo_traslado_min', '999');
      set('tiempo_espera_horas', '88');   set('tiempo_espera_min', '777');
      set('tiempo_atencion_horas', '77'); set('tiempo_atencion_min', '888');
      set('copias_cantidad', '999999');   set('copias_precio', '-100');

      // Toggle costo variable + valor absurdo
      // Variable se auto-detecta por derecho, no hay checkbox global
      
      set('monto_derechos_referencia', largo);

      // Toggle fundamento cobro + valores absurdos
      set('costo_fj_tiene', '1');
      set('costo_fj_norma', largo);
      set('costo_fj_capitulo', largo);
      set('costo_fj_articulo', largo);

      // Toggle relacionados + valor absurdo
      set('tipo_relacion', 'Secuencia');
      set('relacionados_detalle', largo);

      // Requisito absurdo
      set('requisitos[0][nombre]', largo);
      set('requisitos[0][tipo]', 'VALOR_INVALIDO');
      set('requisitos[0][dias]', '99999');
      set('requisitos[0][horas]', '99');
      set('requisitos[0][minutos]', '999');
      set('requisitos[0][costo_modo]', 'con_costo');
      set('requisitos[0][costo_monto]', '-5000');
      set('requisitos[0][costo_unidad]', 'PESOS');
      set('requisitos[0][fj_tiene]', '1');
      set('requisitos[0][fj_norma]', largo);

      // Fundamento manual absurdo
      var chkManual = document.getElementById('fundamentoManualChk');
      if (chkManual && !chkManual.checked) { chkManual.checked = true; fire(chkManual); }
      set('fundamento_normativa', largo);
      set('fundamento_tipo', largo);
      set('fundamento_articulo', largo);
      set('fundamento_resumen', largo);

      // Portal absurdo
      set('portal_nombre_ciudadano', largo);
      set('portal_homoclave_publica', largo);
      set('portal_documento_obtiene', largo);
      set('portal_modalidad', 'Mixta');
      set('portal_correo', 'esto-no-es-un-correo');
      set('portal_url', 'no-es-una-url');
      set('portal_telefono', largo);
      set('portal_vigencia', largo);
      set('portal_oficina', largo);
      set('portal_casos_realizarse', largo);
      set('portal_direccion', largo);

      // Pasos absurdos: 5 pasos + subpasos sin área ni acción, y uno mega largo
      for (var i = 0; i < 5; i++) clickBtn('Agregar paso');
      clickBtn('Agregar subpaso');
      clickBtn('Agregar subpaso');
      setTimeout(function() {
        var cont = document.getElementById('pasosLista');
        if (!cont) return;
        var inputs = cont.querySelectorAll('input[type="text"]');
        var textareas = cont.querySelectorAll('textarea');
        // Primer paso: texto mega largo; el resto vacío (para que truene validación)
        if (inputs[0]) { inputs[0].value = largo; fire(inputs[0]); }
        if (textareas[0]) { textareas[0].value = largo; fire(textareas[0]); }
      }, 100);

      // Grupos: marcar todos
      checkGrupos(99);

      // Derecho absurdo
      if (typeof window.agregarDerecho === 'function') {
        window.agregarDerecho();
        if (typeof window.setDerecho === 'function') {
          window.setDerecho(0, 'concepto', largo);
          window.setDerecho(0, 'monto', -99999);
          window.setDerecho(0, 'unidad', 'MONEDA_FALSA');
        }
      }
    }

    // ─── OTROS WIZARDS ───
    var validoPorTipo = {
      tramite: llenarTramiteValido,
      agenda_regulatoria: function() { if (typeof window.llenarEjemplo === 'function') window.llenarEjemplo('agenda_regulatoria'); },
      agenda_syd: function() { if (typeof window.llenarEjemplo === 'function') window.llenarEjemplo('agenda_syd'); },
      regulacion: function() { if (typeof window.llenarEjemplo === 'function') window.llenarEjemplo('regulacion'); }
    };

    var absurdoPorTipo = {
      tramite: llenarTramiteAbsurdo,
      agenda_regulatoria: function() {
        var largo = new Array(601).join('X');
        set('responsable_nombre', largo); set('responsable_cargo', largo);
        set('nombre', largo); set('sectores_impactados', largo);
        set('justificacion', largo); set('problematica', largo);
        set('alternativas', largo); set('beneficios', largo);
        set('costos_burocraticos', largo); set('fecha_tentativa', '2000-13-99');
        set('impacto_comercio', largo); set('observaciones', largo);
      },
      agenda_syd: function() {
        var largo = new Array(601).join('X');
        set('tramite_nombre_oficial', largo); set('tramite_servidor_publico', largo + ' #### 999');
        set('tramite_objetivo', largo); set('tramite_volumen_anual', '-9999');
        set('tramite_plazo_resolucion_cantidad', '999999'); set('tramite_num_areas', '99999');
        set('tramite_visitas_requeridas', '-50'); set('tramite_tiempo_atencion_horas', '99');
        set('tramite_tiempo_atencion_min', '999'); set('descripcion', largo);
        set('meta', largo); set('indicador', largo);
      },
      regulacion: function() {
        var largo = new Array(601).join('X');
        set('nombre', largo); set('objetivo', largo); set('fundamento_juridico', largo);
        set('palabras_clave', largo); set('resumen', largo);
        set('fecha_publicacion', '2099-99-99'); set('fecha_vigencia', '1800-01-01');
      }
    };

    // ─── Handlers ───
    function llenarValido() { var fn = validoPorTipo[tipo]; if (fn) fn(); toast('✅ ' + tipo.toUpperCase() + ' — VÁLIDO', '#16a34a'); }
    function llenarAbsurdo() { var fn = absurdoPorTipo[tipo]; if (fn) fn(); toast('💥 ' + tipo.toUpperCase() + ' — ABSURDO', '#b91c1c'); }

    // ─── UI ───
    function toast(msg, color) {
      var t = document.createElement('div'); t.textContent = msg;
      t.style.cssText = 'position:fixed;left:16px;bottom:120px;z-index:9999;background:'+(color||'#333')+';color:#fff;padding:10px 14px;border-radius:8px;font:600 13px system-ui;box-shadow:0 4px 14px rgba(0,0,0,.25);max-width:300px';
      document.body.appendChild(t);
      setTimeout(function(){t.style.transition='opacity .3s';t.style.opacity='0';setTimeout(function(){if(t.parentNode)t.remove();},300);},2600);
    }

    var bar = document.createElement('div');
    bar.style.cssText = 'position:fixed;left:16px;bottom:64px;z-index:9998;display:flex;flex-direction:column;gap:8px';
    function mkBtn(label, color, handler) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = label;
      b.style.cssText = 'background:'+color+';color:#fff;border:none;border-radius:8px;padding:10px 14px;font:600 13px system-ui;cursor:pointer;box-shadow:0 3px 10px rgba(0,0,0,.2)';
      b.addEventListener('click', handler); return b;
    }
    bar.appendChild(mkBtn('🧪 Llenar TODO (válido)', '#750038', llenarValido));
    bar.appendChild(mkBtn('💥 Llenar ABSURDO', '#b91c1c', llenarAbsurdo));
    document.body.appendChild(bar);
  });
})();
