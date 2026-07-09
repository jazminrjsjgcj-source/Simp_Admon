/**
 * anti-duplicado.js
 * Evita envíos dobles en todos los formularios del sistema.
 *
 * Al hacer submit, deshabilita SOLO el botón que se pulsó y le pone "Guardando…".
 * Los demás botones NO se tocan (antes se deshabilitaban todos y, por el estilo
 * .btn:disabled{opacity:.48}, parecía que se pulsaban los dos a la vez).
 *
 * El deshabilitado se DIFIERE con setTimeout(0) para no perder el name/value del
 * botón pulsado (un botón deshabilitado no se envía). La protección real contra
 * doble envío la da la bandera dataset.enviando, que se pone de inmediato.
 *
 * Es permanente (no de pruebas). Para quitarlo, borra la línea del layout.
 */
(function () {
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    // Buscar todos los botones submit del form
    var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    if (btns.length === 0) return;

    // Marcar el form para evitar re-entrada (doble submit)
    if (form.dataset.enviando === '1') {
      e.preventDefault();
      return;
    }
    form.dataset.enviando = '1';

    // El botón que realmente disparó el envío.
    var pulsado = e.submitter || null;

    // IMPORTANTE: deshabilitar se DIFIERE con setTimeout(0). Si se deshabilita
    // durante el evento submit (fase de captura), el navegador NO incluye el
    // name/value del botón pulsado (p. ej. accion=enviar) y el trámite se
    // guardaba como borrador. Al diferirlo, el valor ya quedó serializado.
    setTimeout(function () {
      // Solo se toca el botón que se pulsó. Los demás NO se tocan, para que no
      // se vean "activados" a la vez. El doble envío ya lo evita dataset.enviando.
      if (pulsado) {
        pulsado.disabled = true;
        if (pulsado.tagName === 'BUTTON') {
          pulsado.dataset.textoOriginal = pulsado.textContent;
          pulsado.textContent = 'Guardando…';
        }
      } else {
        // Respaldo (navegador sin e.submitter): deshabilitar todos.
        btns.forEach(function (btn) { btn.disabled = true; });
      }
    }, 0);

    // Seguro: si después de 8 segundos no se fue (error de red, etc.),
    // rehabilitar los botones para que el usuario pueda reintentar.
    setTimeout(function () {
      form.dataset.enviando = '';
      btns.forEach(function (btn) {
        btn.disabled = false;
        if (btn.dataset.textoOriginal) {
          btn.textContent = btn.dataset.textoOriginal;
          delete btn.dataset.textoOriginal;
        }
      });
    }, 8000);
  }, true); // capture phase: se ejecuta antes de otros listeners
})();