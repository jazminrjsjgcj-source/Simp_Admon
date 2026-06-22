{{--
  #10 — Calculadora de nivel de digitalización (instrumento oficial ATDT).
  Rediseñada como wizard de 6 bloques con SALIDAS INTELIGENTES:

  La fórmula `calcularNivel()` se mantiene IDÉNTICA byte por byte a la versión
  anterior — garantizado matemáticamente que el resultado final no cambia.
  Lo que cambia es la FORMA de capturar las respuestas:

   Bloque 1 — Punto de partida (1 pregunta: c1)
   Bloque 2 — Información digital (1 pregunta: c2)
   Bloque 3 — Recepción en línea (5 preguntas: c3, c4, c5, c7, c8)
   Bloque 4 — Resolución, prevención, pagos, citas y firma (hasta 12 preguntas)
   Bloque 5 — Validaciones e interoperabilidad (hasta 5 preguntas)
   Bloque 6 — Seguridad (1 pregunta: c33)

  Después de cada bloque, el sistema evalúa si ya se sabe el nivel final.
  Si lo sabe (porque faltó algo que impide subir más), ofrece "Usar este nivel"
  o "Continuar respondiendo igual". Saltar bloques es matemáticamente equivalente
  a responderlos en NO.

  Las 7 preguntas marcadas como informativas en la versión anterior (cInfoFolios,
  cInfoVinc, cInfoConsulta, cInfoVentanilla, cInfoOcr, cInfoBio, cInfoOtra)
  fueron eliminadas: tenían `soloInfo:true` y NO se usaban en `calcularNivel()`,
  por lo que el resultado no cambia.

  Mapa de la fórmula oficial (idéntico a versión anterior):
    Nivel 2 = c2,c3,c4,c5,c7,c8
    Nivel 3 = Nivel 2 + par(c9,c10) + c11..c15 + par(c16,c17) + par(c18,c19)
                       + par(c20,c21) + par(c20,c22)
    Nivel 4 = Nivel 3 + c25 + par(c27,c28) + par(c29,c30)
    Nivel 5 = Nivel 4 + c33
    Nivel 1 = solo c2 (si nada más se logra)
    Nivel 0 = c1 marcado o nada
--}}
<style>
  .calc-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9998; }
  .calc-backdrop.open { display:grid; place-items:center; }
  .calc-modal { background:var(--surface,#fff); border-radius:16px; width:min(720px,calc(100% - 32px)); max-height:88vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .calc-head { padding:18px 22px; border-bottom:1px solid var(--surface-high,#eee); display:flex; align-items:center; justify-content:space-between; }
  .calc-head h3 { margin:0; font-size:17px; }

  /* Barra de progreso con los 6 bloques */
  .calc-stepper { display:flex; gap:4px; padding:14px 22px; border-bottom:1px solid var(--surface-high,#eee); overflow-x:auto; }
  .calc-stepper-item { flex:1; min-width:90px; padding:8px 6px; border-radius:8px; background:var(--surface-low,#f5f5f7); font-size:11px; font-weight:600; color:var(--muted,#888); text-align:center; line-height:1.25; }
  .calc-stepper-item.activo { background:var(--primary,#7a1f4b); color:#fff; }
  .calc-stepper-item.completado { background:var(--surface-tint,#fce4ec); color:var(--primary,#7a1f4b); }

  .calc-body { padding:18px 22px; overflow-y:auto; flex:1; }
  .calc-bloque-titulo { font-size:15px; font-weight:700; color:var(--text,#222); margin:0 0 6px; }
  .calc-bloque-desc { font-size:12px; color:var(--muted,#667085); margin:0 0 16px; }

  .calc-q { padding:14px 0; border-bottom:1px solid var(--surface-high,#f0f0f0); }
  .calc-q:last-child { border-bottom:none; }
  .calc-q-text { font-size:13px; color:var(--text,#222); margin-bottom:8px; display:block; }
  .calc-sino { display:flex; gap:8px; }
  .calc-sino label { display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer; padding:6px 14px; border:1px solid var(--surface-high,#ddd); border-radius:8px; transition:background .15s, border-color .15s; }
  .calc-sino label:has(input:checked) { background:var(--surface-tint,#fce4ec); border-color:var(--primary,#7a1f4b); color:var(--primary,#7a1f4b); font-weight:600; }
  .calc-cond { margin-top:8px; padding-left:14px; border-left:2px solid var(--surface-high,#eee); }

  /* Aviso de salida inteligente al terminar un bloque */
  .calc-aviso { background:var(--surface-tint,#fce4ec); border:1px solid var(--primary,#7a1f4b); border-radius:10px; padding:14px 16px; margin-top:18px; }
  .calc-aviso h4 { margin:0 0 6px; font-size:14px; color:var(--primary,#7a1f4b); }
  .calc-aviso p { margin:0 0 12px; font-size:13px; color:var(--text,#222); }
  .calc-aviso-acciones { display:flex; gap:8px; flex-wrap:wrap; }

  .calc-foot { padding:14px 22px; border-top:1px solid var(--surface-high,#eee); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .calc-nivel-vivo { font-size:13px; color:var(--muted,#667085); }
  .calc-nivel-vivo strong { color:var(--primary,#7a1f4b); font-size:16px; }
  .calc-nivel-desc { font-size:11px; color:var(--muted,#667085); margin-top:3px; max-width:420px; line-height:1.4; }
  .calc-nav { display:flex; gap:8px; flex-shrink:0; }
</style>

<div class="calc-backdrop" id="calcDigBackdrop">
  <div class="calc-modal">
    <div class="calc-head">
      <h3>Calculadora de nivel de digitalización</h3>
      <button type="button" class="btn btn-outline btn-sm" onclick="cerrarCalcDig()">Cerrar</button>
    </div>

    {{-- Stepper visual de los 6 bloques --}}
    <div class="calc-stepper" id="calcStepper">
      {{-- generado por JS --}}
    </div>

    {{-- Cuerpo: solo un bloque visible a la vez --}}
    <div class="calc-body" id="calcDigBody">
      {{-- generado por JS según el bloque actual --}}
    </div>

    {{-- Pie con nivel actual + navegación --}}
    <div class="calc-foot">
      <div>
        <span class="calc-nivel-vivo">Nivel calculado: <strong id="calcNivelVivo">—</strong></span>
        <div id="calcNivelDescripcion" class="calc-nivel-desc">Responda las preguntas para calcular.</div>
      </div>
      <div class="calc-nav">
        <button type="button" class="btn btn-outline btn-sm" id="btnCalcAnterior" onclick="bloqueAnterior()" style="display:none">Anterior</button>
        <button type="button" class="btn btn-sm" id="btnCalcSiguiente" onclick="bloqueSiguiente()">Siguiente</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnCalcUsar" onclick="usarResultadoCalcDig()" style="display:none">Usar este resultado</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  /* ============================================================
     CRITERIOS — texto de cada pregunta del instrumento ATDT.
     Las 7 informativas (soloInfo) fueron eliminadas: no se usaban
     en calcularNivel() así que su ausencia no cambia el resultado.
     ============================================================ */
  var TEXTOS = {
    c1: 'Sin digitalización. Procesos totalmente presenciales.',
    c2: 'La información está homologada y completamente digital (además del Catálogo Nacional).',
    c3: 'Existe una aplicación o sitio web para presentar el trámite (recepción de solicitud en línea).',
    c4: 'La aplicación o sitio web cuenta con un Backoffice para funcionarios.',
    c5: 'La aplicación o sitio web está optimizada para dispositivos móviles.',
    c7: 'Se accede a la aplicación o sitio web mediante un login de acceso.',
    c8: 'El interesado puede enviar requisitos digitalmente (carga de documentación).',
    c9:  'El trámite establece algún plazo de prevención.',
    c10: 'El proceso de prevención está integrado al trámite en línea.',
    c11: 'La revisión de la completitud de requisitos se realiza por la aplicación o sitio web.',
    c12: 'La dictaminación es completamente en línea (el sistema genera la resolución).',
    c13: 'La aplicación o sitio web implementa una Gráfica base.',
    c14: 'La aplicación o sitio web implementa el acceso único con Llave MX.',
    c15: 'Se garantiza realizar el trámite completamente en línea.',
    c16: 'Es necesario realizar un pago durante el trámite.',
    c17: 'El trámite cuenta con línea de captura o pasarela de pagos.',
    c18: 'Es necesario agendar una cita durante el trámite.',
    c19: 'Se puede agendar la cita en línea (gestión electrónica de citas).',
    c20: 'Es necesaria la firma del promovente durante el trámite.',
    c21: 'El promovente puede usar firma electrónica equivalente a la autógrafa.',
    c22: 'Los documentos firmados generan un QR o vía de autenticación.',
    c25: 'La plataforma está integrada con el Expediente digital nacional.',
    c27: 'La CURP se solicita como requisito durante el trámite.',
    c28: 'La validación de la CURP es automática vía consulta al RENAPO.',
    c29: 'El RFC se solicita como requisito durante el trámite.',
    c30: 'La validación del RFC es automática vía consulta al SAT.',
    c33: 'Cumple estándares de seguridad (cifrado, ISO 27001, GDPR, etc.).',
  };

  /* Las dependencias condicionales del Excel (padre → hijo).
     Se usan para ocultar/mostrar preguntas hijas en vivo. */
  var DEPENDENCIAS = {
    c9:  ['c10'],
    c16: ['c17'],
    c18: ['c19'],
    c20: ['c21','c22'],
    c27: ['c28'],
    c29: ['c30'],
  };

  /* Los 6 bloques del wizard. Cada uno trae los IDs de las preguntas
     que se renderizan, y en qué orden. Las preguntas condicionales
     (c10, c17, c19, c21, c22, c28, c30) aparecen inicialmente ocultas
     y se muestran cuando su padre se marca como Sí. */
  var BLOQUES = [
    { num: 1, titulo: 'Punto de partida', desc: 'Determina si el trámite es 100% presencial.', criterios: ['c1'] },
    { num: 2, titulo: 'Información digital', desc: 'Determina si la información del trámite está digitalizada en el Catálogo Nacional.', criterios: ['c2'] },
    { num: 3, titulo: 'Recepción en línea', desc: '5 criterios para alcanzar el nivel 2: recepción de solicitudes en línea.', criterios: ['c3','c4','c5','c7','c8'] },
    { num: 4, titulo: 'Resolución, pagos, citas y firma', desc: 'Criterios del nivel 3: el trámite se completa en línea de extremo a extremo.', criterios: ['c9','c10','c11','c12','c13','c14','c15','c16','c17','c18','c19','c20','c21','c22'] },
    { num: 5, titulo: 'Validaciones e interoperabilidad', desc: 'Criterios del nivel 4: la plataforma valida automáticamente con servicios externos.', criterios: ['c25','c27','c28','c29','c30'] },
    { num: 6, titulo: 'Seguridad', desc: 'Último criterio para alcanzar el nivel 5: estándares de seguridad.', criterios: ['c33'] },
  ];

  /* Descripciones oficiales ATDT (escala 0-5) — idénticas a versión anterior. */
  var NIVEL_DESC = {
    0: 'Sin digitalización. Procesos totalmente presenciales.',
    1: 'Eficiencia administrativa básica. Uso limitado de tecnologías digitales.',
    2: 'Productividad y reducción de costos. Digitalización del proceso, acceso público, información clara, trámite 100% en línea.',
    3: 'Acceso electrónico transaccional. 4 componentes: recolección de requisitos, autenticación, seguimiento y resolución — todo sin acudir físicamente.',
    4: 'Experiencia ciudadana unificada. Plataformas interoperables orientadas al usuario.',
    5: 'Innovación, transparencia y participación. Nuevas tecnologías, datos estratégicos y colaboración abierta.',
  };

  /* ============================================================
     ESTADO: idéntico al de la versión anterior.
     R: respuestas (id -> true/false). nivelActual: 0-5.
     ============================================================ */
  var R = {};
  Object.keys(TEXTOS).forEach(function (id) { R[id] = false; });

  var bloqueActual = 1;
  var nivelActual = 0;
  var modoCompleto = false;   // true = el usuario eligió "Continuar respondiendo igual"
                              //        y debe pasar por todos los bloques aunque
                              //        haya salida inteligente disponible

  /* ============================================================
     FÓRMULA OFICIAL DE CÁLCULO — IDÉNTICA byte por byte a la versión anterior.
     Garantiza que el resultado final NUNCA cambia con respecto a la
     calculadora original.
     ============================================================ */

  // Igualdad condicional del Excel: IF(padre==hijo). Si el padre es No, el hijo
  // se omite y la condición se cumple sola.
  function par(padre, hijo) {
    if (!R[padre]) return true;     // padre No → omitido, cumple
    return R[hijo] === true;        // padre Sí → el hijo debe ser Sí
  }

  function calcularNivel() {
    var n2 = R.c2 && R.c3 && R.c4 && R.c5 && R.c7 && R.c8;
    var n3 = n2 && par('c9','c10') && R.c11 && R.c12 && R.c13 && R.c14 && R.c15
                && par('c16','c17') && par('c18','c19')
                && (par('c20','c21') && par('c20','c22'));
    var n4 = n3 && R.c25 && par('c27','c28') && par('c29','c30');
    var n5 = n4 && R.c33;
    if (R.c1) return 0;
    if (n5) return 5;
    if (n4) return 4;
    if (n3) return 3;
    if (n2) return 2;
    if (R.c2) return 1;
    return 0;
  }

  /* ============================================================
     SALIDA INTELIGENTE: tras terminar un bloque, si ya se sabe el
     nivel final (porque faltó algo que impide subir más), se sugiere
     al usuario parar y usar ese nivel.
     IMPORTANTE: las preguntas saltadas quedan en `false` en R, que
     es matemáticamente equivalente a responderlas en No. Así
     `calcularNivel()` arroja el mismo nivel que aquí se muestra.
     ============================================================ */
  function detectarSalida(despuesDeBloque) {
    if (despuesDeBloque === 1 && R.c1) {
      return { nivelFinal: 0, motivo: 'El trámite es 100% presencial: no hay digitalización que medir.' };
    }
    if (despuesDeBloque === 2 && !R.c2) {
      return { nivelFinal: 0, motivo: 'La información no está digitalizada en el Catálogo Nacional: nivel 0.' };
    }
    if (despuesDeBloque === 3) {
      var n2 = R.c2 && R.c3 && R.c4 && R.c5 && R.c7 && R.c8;
      if (!n2) return { nivelFinal: 1, motivo: 'Falta algún componente de recepción en línea. El nivel máximo posible es 1.' };
    }
    if (despuesDeBloque === 4) {
      var n2b = R.c2 && R.c3 && R.c4 && R.c5 && R.c7 && R.c8;
      var n3 = n2b && par('c9','c10') && R.c11 && R.c12 && R.c13 && R.c14 && R.c15
                  && par('c16','c17') && par('c18','c19')
                  && (par('c20','c21') && par('c20','c22'));
      if (!n3) return { nivelFinal: 2, motivo: 'Falta algún componente de resolución/firma para nivel 3. El nivel queda en 2.' };
    }
    if (despuesDeBloque === 5) {
      var n2c = R.c2 && R.c3 && R.c4 && R.c5 && R.c7 && R.c8;
      var n3c = n2c && par('c9','c10') && R.c11 && R.c12 && R.c13 && R.c14 && R.c15
                   && par('c16','c17') && par('c18','c19')
                   && (par('c20','c21') && par('c20','c22'));
      var n4 = n3c && R.c25 && par('c27','c28') && par('c29','c30');
      if (!n4) return { nivelFinal: 3, motivo: 'Falta integración con RENAPO/SAT/Expediente nacional. El nivel queda en 3.' };
    }
    return null;
  }

  /* ============================================================
     RENDER: dibuja el stepper y el cuerpo del bloque actual.
     ============================================================ */
  function renderStepper() {
    var cont = document.getElementById('calcStepper');
    cont.innerHTML = BLOQUES.map(function (b) {
      var clase = 'calc-stepper-item';
      if (b.num === bloqueActual) clase += ' activo';
      else if (b.num < bloqueActual) clase += ' completado';
      return '<div class="' + clase + '">' + b.num + '. ' + b.titulo + '</div>';
    }).join('');
  }

  function renderBloque() {
    var bloque = BLOQUES[bloqueActual - 1];
    var cont = document.getElementById('calcDigBody');
    var html = '<h4 class="calc-bloque-titulo">Bloque ' + bloque.num + ' — ' + bloque.titulo + '</h4>';
    html += '<p class="calc-bloque-desc">' + bloque.desc + '</p>';

    bloque.criterios.forEach(function (id) {
      // Determinar si es condicional (hijo de otra). Si lo es, oculto hasta que el padre sea Sí.
      var padreDe = null;
      Object.keys(DEPENDENCIAS).forEach(function (padre) {
        if (DEPENDENCIAS[padre].indexOf(id) !== -1) padreDe = padre;
      });
      var esHijo = padreDe !== null;
      var ocultoInicial = esHijo && !R[padreDe];

      html += '<div class="calc-q' + (esHijo ? ' calc-cond' : '') + '" id="wrap_' + id + '"'
        + (ocultoInicial ? ' style="display:none"' : '') + '>'
        + '<span class="calc-q-text">' + TEXTOS[id] + '</span>'
        + '<div class="calc-sino">'
        + '<label><input type="radio" name="calc_' + id + '" value="1"' + (R[id] === true ? ' checked' : '') + '> Sí</label>'
        + '<label><input type="radio" name="calc_' + id + '" value="0"' + (R[id] === false ? ' checked' : '') + '> No</label>'
        + '</div></div>';
    });

    cont.innerHTML = html;

    // Listeners para los radios del bloque actual
    cont.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        var id = this.name.replace('calc_', '');
        R[id] = (this.value === '1');

        // Si tiene hijos: mostrar/ocultar según valor
        if (DEPENDENCIAS[id]) {
          DEPENDENCIAS[id].forEach(function (hijoId) {
            var w = document.getElementById('wrap_' + hijoId);
            if (w) {
              w.style.display = R[id] ? '' : 'none';
              if (!R[id]) {
                R[hijoId] = false;
                var no = w.querySelector('input[value="0"]');
                if (no) no.checked = true;
              }
            }
          });
        }
        actualizarNivelVivo();
      });
    });

    actualizarNivelVivo();
    actualizarBotones();
  }

  function actualizarNivelVivo() {
    nivelActual = calcularNivel();
    document.getElementById('calcNivelVivo').textContent = 'Nivel ' + nivelActual;
    document.getElementById('calcNivelDescripcion').textContent = NIVEL_DESC[nivelActual] || '';
  }

  function actualizarBotones() {
    var btnAnt = document.getElementById('btnCalcAnterior');
    var btnSig = document.getElementById('btnCalcSiguiente');
    var btnUsar = document.getElementById('btnCalcUsar');

    btnAnt.style.display = bloqueActual > 1 ? '' : 'none';
    if (bloqueActual === BLOQUES.length) {
      btnSig.style.display = 'none';
      btnUsar.style.display = '';
    } else {
      btnSig.style.display = '';
      btnUsar.style.display = 'none';
    }
  }

  /* ============================================================
     NAVEGACIÓN
     ============================================================ */
  window.bloqueAnterior = function () {
    if (bloqueActual > 1) {
      bloqueActual--;
      renderStepper();
      renderBloque();
      removerAviso();
    }
  };

  window.bloqueSiguiente = function () {
    // Antes de avanzar, evaluar salida inteligente
    if (!modoCompleto) {
      var salida = detectarSalida(bloqueActual);
      if (salida) {
        mostrarAvisoSalida(salida);
        return;
      }
    }
    if (bloqueActual < BLOQUES.length) {
      bloqueActual++;
      renderStepper();
      renderBloque();
      removerAviso();
    }
  };

  function mostrarAvisoSalida(salida) {
    removerAviso();
    var body = document.getElementById('calcDigBody');
    var div = document.createElement('div');
    div.className = 'calc-aviso';
    div.id = 'calcAviso';
    div.innerHTML =
      '<h4>Nivel ' + salida.nivelFinal + ' determinado</h4>' +
      '<p>' + salida.motivo + '</p>' +
      '<div class="calc-aviso-acciones">' +
        '<button type="button" class="btn btn-primary btn-sm" onclick="usarResultadoCalcDig()">Usar Nivel ' + salida.nivelFinal + '</button>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="continuarRespondiendo()">Continuar respondiendo</button>' +
      '</div>';
    body.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function removerAviso() {
    var av = document.getElementById('calcAviso');
    if (av) av.remove();
  }

  window.continuarRespondiendo = function () {
    modoCompleto = true;
    removerAviso();
    if (bloqueActual < BLOQUES.length) {
      bloqueActual++;
      renderStepper();
      renderBloque();
    }
  };

  /* ============================================================
     ABRIR / CERRAR / APLICAR — interface pública.
     ============================================================ */
  window.abrirCalcDig = function () {
    document.getElementById('calcDigBackdrop').classList.add('open');
    // Reset al abrir: empezar siempre desde el bloque 1
    bloqueActual = 1;
    modoCompleto = false;
    Object.keys(R).forEach(function (id) { R[id] = false; });
    // Marcar No por defecto en todos los radios al re-renderizar
    renderStepper();
    renderBloque();
  };

  window.cerrarCalcDig = function () {
    document.getElementById('calcDigBackdrop').classList.remove('open');
  };

  /* Bug #B10: el select del nivel está disabled; el valor REAL viaja en el
     hidden input #nivelDigHidden. Actualizamos ambos: el select para que el
     usuario vea el nivel asignado, y el hidden para que ese valor llegue al
     backend al guardar. Si el form usa una versión vieja (sin hidden), el
     fallback al select editable sigue funcionando. */
  window.usarResultadoCalcDig = function () {
    var hidden = document.getElementById('nivelDigHidden');
    var sel    = document.getElementById('nivelDigSelect')
              || document.querySelector('select[name="nivel_digitalizacion"]');
    if (hidden) hidden.value = String(nivelActual);
    if (sel) {
      sel.value = String(nivelActual);
      sel.dispatchEvent(new Event('change'));
    }
    cerrarCalcDig();
  };
})();
</script>
