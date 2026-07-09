/**
 * lector-regulacion.js — Ventana flotante de lectura de regulaciones.
 *
 * Abre una ventana flotante (draggable, resizable) que muestra el documento
 * original de una regulación (PDF nativo o Word convertido a HTML) mientras
 * el usuario sigue trabajando en la página de fondo.
 *
 * Dos formas de abrirlo:
 *
 *   1) Desde JavaScript:
 *        window.abrirLectorRegulacion(id, nombre);
 *
 *   2) Declarativo (delegación de eventos):
 *        <button data-lector-reg-id="12" data-lector-reg-nombre="Ley de...">
 *          Ver documento
 *        </button>
 */
(function () {
  var cfg = window.LECTOR_REG_CFG || {};

  var overlay   = document.getElementById('lectorRegulacionOverlay');
  var panel     = document.getElementById('lectorRegulacionPanel');
  var header    = panel ? panel.querySelector('.lector-reg-header') : null;
  var titulo    = document.getElementById('lectorRegulacionTitulo');
  var iframe    = document.getElementById('lectorRegulacionIframe');
  var loader    = document.getElementById('lectorRegulacionCargando');
  var btnCerrar = document.getElementById('lectorRegulacionCerrar');
  var linkFicha = document.getElementById('lectorRegulacionAbrirCompleto');

  if (!overlay || !panel) return;

  var idAbierto = null;
  var elementoConFoco = null;

  // ── Abrir / Cerrar ──────────────────────────────────────────────────

  function abrir(id, nombre) {
    if (!id) return;

    titulo.textContent = nombre || 'Regulación';
    linkFicha.href = cfg.showBase + '/' + id;

    if (idAbierto !== id) {
      idAbierto = id;
      loader.classList.remove('hidden');
      iframe.classList.add('hidden');
      iframe.src = cfg.previewBase + '/' + id + '/preview';
    }

    // Resetear posición al abrir (esquina inferior derecha).
    panel.style.left = '';
    panel.style.top  = '';
    panel.style.right  = '24px';
    panel.style.bottom = '24px';

    elementoConFoco = document.activeElement;
    overlay.classList.add('abierto');
    overlay.setAttribute('aria-hidden', 'false');
    btnCerrar.focus();
  }

  function cerrar() {
    overlay.classList.remove('abierto');
    overlay.setAttribute('aria-hidden', 'true');

    // Liberar el documento del iframe después de un breve delay
    // (permite que la animación de cierre termine antes de vaciar).
    setTimeout(function () {
      if (!overlay.classList.contains('abierto')) {
        iframe.src = 'about:blank';
        idAbierto = null;
      }
    }, 350);

    if (elementoConFoco && typeof elementoConFoco.focus === 'function') {
      elementoConFoco.focus();
    }
  }

  // ── Iframe load: ocultar spinner ────────────────────────────────────

  iframe.addEventListener('load', function () {
    if (iframe.src && iframe.src !== 'about:blank') {
      loader.classList.add('hidden');
      iframe.classList.remove('hidden');
    }
  });

  // ── Drag: arrastrar la ventana por la barra de título ───────────────

  var isDragging = false;
  var dragOffsetX = 0;
  var dragOffsetY = 0;

  function onDragStart(e) {
    // No arrastrar si el clic fue en un botón o enlace del header.
    if (e.target.closest('button') || e.target.closest('a')) return;

    isDragging = true;
    header.classList.add('dragging');

    // Convertir de right/bottom a left/top para posicionar con el mouse.
    var rect = panel.getBoundingClientRect();
    panel.style.left   = rect.left + 'px';
    panel.style.top    = rect.top + 'px';
    panel.style.right  = 'auto';
    panel.style.bottom = 'auto';

    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;

    // Desactivar selección de texto mientras se arrastra.
    document.body.style.userSelect = 'none';

    e.preventDefault();
  }

  function onDragMove(e) {
    if (!isDragging) return;

    var x = e.clientX - dragOffsetX;
    var y = e.clientY - dragOffsetY;

    // Limitar para que no se salga de la pantalla.
    x = Math.max(0, Math.min(x, window.innerWidth - 200));
    y = Math.max(0, Math.min(y, window.innerHeight - 50));

    panel.style.left = x + 'px';
    panel.style.top  = y + 'px';
  }

  function onDragEnd() {
    if (!isDragging) return;
    isDragging = false;
    header.classList.remove('dragging');
    document.body.style.userSelect = '';
  }

  header.addEventListener('mousedown', onDragStart);
  document.addEventListener('mousemove', onDragMove);
  document.addEventListener('mouseup', onDragEnd);

  // ── Cerrar: botón X y tecla Escape ──────────────────────────────────

  btnCerrar.addEventListener('click', cerrar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('abierto')) {
      cerrar();
    }
  });

  // ── Delegación de eventos: data-lector-reg-id ───────────────────────

  document.addEventListener('click', function (e) {
    var disparador = e.target.closest ? e.target.closest('[data-lector-reg-id]') : null;
    if (!disparador) return;
    e.preventDefault();
    abrir(
      disparador.getAttribute('data-lector-reg-id'),
      disparador.getAttribute('data-lector-reg-nombre')
    );
  });

  // API global.
  window.abrirLectorRegulacion = abrir;
  window.cerrarLectorRegulacion = cerrar;
})();
