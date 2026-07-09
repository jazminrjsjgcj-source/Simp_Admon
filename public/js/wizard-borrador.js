/**
 * Guardado de borrador en los wizards del sistema.
 *
 * Sobre cualquier formulario con wizard (.wizard-content o .wizard-step):
 *   1) Bloquea ENTER para enviar (evita el bug de que ENTER regresaba al paso 1).
 *   2) Agrega un botón flotante "Guardar borrador", visible en cualquier paso.
 *   3) Muestra una vez, al entrar, un aviso de que se puede guardar cuando sea.
 *
 * Cuando el borrador no aplica (trámite desde la Agenda SyD, donde solo se
 * envía), no se muestran ni el botón ni el aviso.
 */
(function () {
  'use strict';

  function esWizard(form) {
    return !!form.querySelector('.wizard-content, .wizard-step');
  }

  // El borrador se deshabilita si el wizard viene de la Agenda SyD (retorno=agenda).
  function borradorDeshabilitado(form) {
    var retorno = form.querySelector('input[name="retorno"]');
    return !!(retorno && retorno.value === 'agenda');
  }

  // Envía el formulario como borrador, sea cual sea el patrón del wizard.
  function guardarBorrador(form) {
    // Si el wizard define su propia ruta de borrador (con validaciones previas
    // propias, como agenda regulatoria), respétala en vez del submit genérico.
    if (typeof window.guardarBorradorWizard === 'function') {
      window.guardarBorradorWizard();
      return;
    }

    var accion = form.querySelector('[name="accion"]');
    if (!accion) {
      accion = document.createElement('input');
      accion.type = 'hidden';
      accion.name = 'accion';
      form.appendChild(accion);
    }
    accion.value = 'borrador';
    // form.submit() salta la validación HTML5: guarda aunque esté incompleto.
    form.submit();
  }

  // Bloquea ENTER para enviar (permite salto de línea en textarea y activar botones).
  function bloquearEnter(form) {
    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      var t = e.target || {};
      var tag = (t.tagName || '').toUpperCase();
      var tipo = (t.type || '').toLowerCase();
      if (tag === 'TEXTAREA') return;                      // salto de línea OK
      if (tipo === 'submit' || tipo === 'button') return;  // activar botón OK
      e.preventDefault();                                  // bloquear envío por ENTER
    });
  }

  function crearBotonFlotante(form) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'wizard-borrador-fab';
    btn.textContent = 'Guardar borrador';
    btn.setAttribute('data-wizard-borrador', '1');
    btn.addEventListener('click', function () { guardarBorrador(form); });
    document.body.appendChild(btn);
  }

  // Aviso emergente, una sola vez por sesión.
  function mostrarAvisoBorrador() {
    // Si ya se mostró en esta sesión, no repetirlo (el usuario puede crear
    // varios registros seguidos). El flag se limpia solo al cerrar el navegador.
    try {
      if (sessionStorage.getItem('avisoBorradorVisto')) return;
      sessionStorage.setItem('avisoBorradorVisto', '1');
    } catch (e) {
      // Si sessionStorage no está disponible, mostrar el aviso de todos modos.
    }

    var aviso = document.createElement('div');
    aviso.className = 'wizard-borrador-aviso';
    aviso.innerHTML =
      'Puedes guardar tu avance cuando lo necesites con el botón '
      + '<strong>Guardar borrador</strong>. No tienes que terminar todo el formulario.'
      + '<button type="button" class="wizard-borrador-aviso-x" aria-label="Cerrar">&times;</button>';
    document.body.appendChild(aviso);

    var cerrar = function () { aviso.remove(); };
    aviso.querySelector('.wizard-borrador-aviso-x').addEventListener('click', cerrar);
    setTimeout(cerrar, 6000);
  }

  function init() {
    var forms = Array.prototype.slice
      .call(document.querySelectorAll('form'))
      .filter(esWizard);

    if (!forms.length) return;

    // ENTER se bloquea siempre, incluso viniendo de agenda.
    forms.forEach(bloquearEnter);

    // El botón y el aviso solo si el borrador está permitido aquí.
    var form = forms[0];
    if (borradorDeshabilitado(form)) return;

    crearBotonFlotante(form);
    mostrarAvisoBorrador();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();