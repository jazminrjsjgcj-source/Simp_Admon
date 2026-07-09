/* ===========================================================================
   regulaciones-favoritas.js
   Maneja el corazón de cada regulación en el catálogo:
   - Al hacer clic, marca/desmarca como favorita (POST a la ruta favorita).
   - Actualiza el estante de arriba en vivo: agrega o quita el lomo sin recargar.
   No usa interpolaciones Blade; lee todo de atributos data-* del HTML.
   =========================================================================== */
(function () {
  'use strict';

  var token = document.querySelector('meta[name="csrf-token"]');
  var csrf = token ? token.getAttribute('content') : '';

  var estante = document.getElementById('regEstante');
  var estanteVacio = document.getElementById('regEstanteVacio');
  var rutaShow = estante ? estante.getAttribute('data-ruta-show') : '';

  // Agrega un lomo al estante para una regulación recién marcada.
  function agregarLomo(id, nombre) {
    if (!estante) return;
    if (estante.querySelector('.reg-lomo[data-id="' + id + '"]')) return;

    var a = document.createElement('a');
    a.href = rutaShow + '/' + id;
    a.className = 'reg-lomo';
    a.setAttribute('data-id', id);
    a.title = nombre;

    var span = document.createElement('span');
    span.textContent = nombre;
    a.appendChild(span);

    estante.appendChild(a);
    actualizarVacio();
  }

  // Quita el lomo del estante cuando se desmarca.
  function quitarLomo(id) {
    if (!estante) return;
    var lomo = estante.querySelector('.reg-lomo[data-id="' + id + '"]');
    if (lomo) lomo.parentNode.removeChild(lomo);
    actualizarVacio();
  }

  // Muestra u oculta el mensaje de "sin favoritos" según queden lomos.
  function actualizarVacio() {
    if (!estante || !estanteVacio) return;
    var hayLomos = estante.querySelectorAll('.reg-lomo').length > 0;
    estanteVacio.style.display = hayLomos ? 'none' : '';
  }

  // Envía el toggle al servidor y actualiza la interfaz según la respuesta.
  function toggleFavorita(boton) {
    var id = boton.getAttribute('data-id');
    var nombre = boton.getAttribute('data-nombre');
    var url = boton.getAttribute('data-url');

    boton.disabled = true;

    fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) throw data;  // 422 con {error: "Máximo 10..."}
          return data;
        });
      })
      .then(function (data) {
        var esFav = !!data.favorita;
        boton.setAttribute('aria-pressed', esFav ? 'true' : 'false');
        if (esFav) {
          agregarLomo(id, nombre);
        } else {
          quitarLomo(id);
        }
      })
      .catch(function (err) {
        if (err && err.error) {
          alert(err.error);
        }
      })
      .finally(function () {
        boton.disabled = false;
      });
  }

  document.addEventListener('click', function (e) {
    var boton = e.target.closest ? e.target.closest('.reg-corazon') : null;
    if (boton) {
      e.preventDefault();
      toggleFavorita(boton);
    }
  });
})();
