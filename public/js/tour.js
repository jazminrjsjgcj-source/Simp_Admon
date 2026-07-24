/**
 * TOUR GUIADO — burbujas sobre la interfaz real.
 * ═════════════════════════════════════════════
 *
 * Lee el guion que el layout deja en window.PUNTA_TOUR (viene de config/tours.php)
 * y va resaltando elementos con una burbuja al lado. No llena campos, no envía
 * formularios y no guarda nada.
 *
 * ── POR QUÉ NO USA UNA LIBRERÍA ──
 *
 * driver.js o Shepherd.js harían esto y más. Pero el proyecto carga su JavaScript
 * con <script src> sueltos, sin bundler: meter una dependencia significaría o bien
 * un CDN (que falla el día que el municipio tenga la red caída o filtrada) o bien
 * copiar un archivo minificado a public/js que nadie sabrá actualizar.
 *
 * El tour son ~150 líneas. La librería son 5 KB minificados que nadie de aquí
 * podrá depurar. A ese tamaño, la dependencia cuesta más de lo que ahorra.
 *
 * ── CÓMO SE COMPORTA ANTE UN GUION ROTO ──
 *
 * Si un ancla no existe en la página, el paso SE SALTA en silencio y sigue con el
 * siguiente. Es deliberado: un formulario muestra campos distintos según el rol y
 * según lo que el usuario haya elegido, y un tour que se muere porque falta un
 * campo opcional sería peor que no tenerlo. Los saltos se avisan por consola para
 * que quien edite el guion los vea.
 */
(function () {
  'use strict';

  var guion = window.PUNTA_TOUR || null;
  if (!guion || !guion.pasos || !guion.pasos.length) return;

  var indice = 0;
  var capa   = null;   // el fondo oscuro
  var globo  = null;   // la burbuja
  var marcado = null;  // elemento resaltado ahora mismo

  // ─── Utilidades ──────────────────────────────────────────────────────────

  function ver(selector) {
    try {
      var el = document.querySelector(selector);
      // Un elemento existe pero está oculto (paso del wizard no visible todavía):
      // offsetParent null lo detecta. No sirve para position:fixed, pero en este
      // formulario no hay ninguno que sea ancla.
      if (!el) return null;
      if (el.offsetParent === null && getComputedStyle(el).position !== 'fixed') return null;
      return el;
    } catch (e) {
      // Selector mal escrito en config/tours.php. No debe tumbar el tour.
      console.warn('[tour] selector inválido:', selector, e);
      return null;
    }
  }

  function esperar(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
  }

  // ─── Pintado ─────────────────────────────────────────────────────────────

  function montarCapa() {
    capa = document.createElement('div');
    capa.className = 'tour-capa';
    capa.addEventListener('click', cerrar);
    document.body.appendChild(capa);

    globo = document.createElement('div');
    globo.className = 'tour-globo';
    globo.setAttribute('role', 'dialog');
    globo.setAttribute('aria-live', 'polite');
    document.body.appendChild(globo);
  }

  function resaltar(el) {
    if (marcado) marcado.classList.remove('tour-foco');
    marcado = el;
    if (el) el.classList.add('tour-foco');
  }

  function colocar(el, lado) {
    var r = el.getBoundingClientRect();
    var g = globo.getBoundingClientRect();
    var margen = 12;
    var top, left;

    if (lado === 'top')         { top = r.top - g.height - margen;  left = r.left; }
    else if (lado === 'left')   { top = r.top;  left = r.left - g.width - margen; }
    else if (lado === 'right')  { top = r.top;  left = r.right + margen; }
    else                        { top = r.bottom + margen;          left = r.left; }

    // Que no se salga de la pantalla.
    var maxLeft = window.innerWidth  - g.width  - margen;
    var maxTop  = window.innerHeight - g.height - margen;
    if (left > maxLeft) left = maxLeft;
    if (left < margen)  left = margen;
    if (top  > maxTop)  top  = maxTop;
    if (top  < margen)  top  = margen;

    globo.style.top  = (top  + window.scrollY) + 'px';
    globo.style.left = (left + window.scrollX) + 'px';
  }

  function pintar(paso, el) {
    var ultimo = (indice === guion.pasos.length - 1);

    globo.innerHTML =
      '<div class="tour-globo-num">' + (indice + 1) + ' de ' + guion.pasos.length + '</div>' +
      '<h4>' + paso.titulo + '</h4>' +
      '<div class="tour-globo-texto">' + paso.texto + '</div>' +
      '<div class="tour-globo-pie">' +
        '<button type="button" class="tour-salir">Salir</button>' +
        '<button type="button" class="tour-sig">' + (ultimo ? 'Terminar' : 'Siguiente') + '</button>' +
      '</div>';

    globo.querySelector('.tour-salir').addEventListener('click', cerrar);
    globo.querySelector('.tour-sig').addEventListener('click', siguiente);

    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // Esperar a que el scroll asiente antes de medir posiciones.
    setTimeout(function () { colocar(el, paso.lado || 'bottom'); }, 320);
  }

  // ─── Recorrido ───────────────────────────────────────────────────────────

  async function mostrar() {
    if (indice >= guion.pasos.length) return cerrar(true);

    var paso = guion.pasos[indice];

    // 'antes': pulsar algo (típicamente "Siguiente" del wizard) para que el panel
    // de este paso llegue a existir. Sin esto, el tour solo podría hablar del
    // primer paso del formulario.
    if (paso.antes) {
      var disparador = ver(paso.antes);
      if (disparador) {
        disparador.click();
        await esperar(350);   // dar tiempo a la transición del wizard
      }
    }

    var el = ver(paso.ancla);

    if (!el) {
      console.warn('[tour] se salta el paso ' + (indice + 1) + ': no existe ' + paso.ancla);
      indice++;
      return mostrar();
    }

    resaltar(el);
    pintar(paso, el);
  }

  function siguiente() { indice++; mostrar(); }

  function cerrar(completado) {
    resaltar(null);
    if (globo) globo.remove();
    if (capa)  capa.remove();
    globo = capa = null;

    document.removeEventListener('keydown', teclado);

    // Se avisa al servidor SOLO si llegó al final, para no marcar como visto un
    // tour que alguien cerró en el segundo paso.
    if (completado === true && guion.url_completado) {
      fetch(guion.url_completado, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ tour: guion.clave })
      }).catch(function () { /* que no se entere el usuario si falla */ });
    }
  }

  function teclado(e) {
    if (e.key === 'Escape')     cerrar();
    if (e.key === 'ArrowRight') siguiente();
  }

  function arrancar() {
    if (capa) return;   // ya está abierto
    indice = 0;
    montarCapa();
    document.addEventListener('keydown', teclado);
    mostrar();
  }

  // ─── Enganches ───────────────────────────────────────────────────────────

  // El botón "¿Cómo funciona esto?" del layout.
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-tour-iniciar]')) {
      e.preventDefault();
      arrancar();
    }
  });

  // Arranque automático la primera vez que alguien pisa la pantalla.
  if (guion.autoarranque) {
    // Un respiro para que el resto de scripts del wizard terminen de montar.
    setTimeout(arrancar, 600);
  }

  window.PUNTA_TOUR_iniciar = arrancar;   // por si hace falta llamarlo desde consola
})();
