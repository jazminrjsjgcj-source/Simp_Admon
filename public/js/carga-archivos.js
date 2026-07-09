/**
 * Componente de carga de archivos personalizado.
 *
 * Maneja la visualización de los archivos seleccionados, la validación de
 * tamaño y la eliminación de archivos antes de guardar. El input real está
 * oculto; este script lo controla.
 *
 * Como un <input type="file"> no deja quitar un archivo suelto de su lista,
 * usamos un DataTransfer para reconstruir la selección sin el eliminado.
 */
(function () {
  'use strict';

  // Tamaño máximo en MB leído del atributo data, con respaldo de 10.
  function maxMb(contenedor) {
    var reglas = contenedor.querySelector('.carga-reglas');
    var match = reglas ? reglas.textContent.match(/(\d+)\s*MB/) : null;
    return match ? parseInt(match[1], 10) : 10;
  }

  function formatearTamano(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  // Redibuja la lista de archivos y el texto de estado.
  window.cargaArchivosActualizar = function (uid) {
    var input = document.getElementById(uid);
    var lista = document.getElementById(uid + '_lista');
    var estado = document.getElementById(uid + '_estado');
    var contenedor = document.querySelector('[data-carga="' + uid + '"]');
    if (!input || !lista || !estado) return;

    var limite = maxMb(contenedor) * 1024 * 1024;
    var archivos = Array.prototype.slice.call(input.files);

    lista.innerHTML = '';

    if (archivos.length === 0) {
      estado.textContent = 'Ningún archivo seleccionado';
      return;
    }

    estado.textContent = archivos.length === 1
      ? '1 archivo seleccionado'
      : archivos.length + ' archivos seleccionados';

    archivos.forEach(function (archivo, i) {
      var excede = archivo.size > limite;
      var li = document.createElement('li');
      li.className = 'carga-item' + (excede ? ' carga-item-error' : '');

      var info = document.createElement('span');
      info.className = 'carga-nombre';
      info.textContent = archivo.name + ' (' + formatearTamano(archivo.size) + ')';
      if (excede) {
        info.textContent += ' — excede el máximo';
      }

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline btn-sm';
      btn.textContent = 'Eliminar';
      btn.onclick = function () { cargaArchivosEliminar(uid, i); };

      li.appendChild(info);
      li.appendChild(btn);
      lista.appendChild(li);
    });
  };

  // Quita un archivo de la selección reconstruyendo la lista sin él.
  window.cargaArchivosEliminar = function (uid, indice) {
    var input = document.getElementById(uid);
    if (!input) return;

    var dt = new DataTransfer();
    var archivos = Array.prototype.slice.call(input.files);
    archivos.forEach(function (archivo, i) {
      if (i !== indice) dt.items.add(archivo);
    });
    input.files = dt.files;
    cargaArchivosActualizar(uid);
  };

  // ─── Obligatoriedad sin `required` nativo ────────────────────────────────
  // Para los componentes marcados data-required="1": el botón de envío de su
  // formulario queda deshabilitado mientras no haya archivo (resuelve que el
  // botón "Subir evidencia" estuviera activo sin nada que subir), y se valida
  // al enviar como segunda barrera.

  // ¿Hay al menos un archivo seleccionado en este componente?
  function tieneArchivo(contenedor) {
    var input = contenedor.querySelector('.carga-input-oculto');
    return !!(input && input.files && input.files.length > 0);
  }

  // Botón de envío del formulario que contiene a este componente.
  function botonSubmitDe(contenedor) {
    var form = contenedor.closest('form');
    return form ? form.querySelector('[type="submit"]') : null;
  }

  // Refleja el estado (con/sin archivo) en el botón de envío.
  function sincronizarBoton(contenedor) {
    if (contenedor.getAttribute('data-required') !== '1') return;
    var btn = botonSubmitDe(contenedor);
    if (!btn) return;
    var ok = tieneArchivo(contenedor);
    btn.disabled = !ok;
    btn.classList.toggle('u-btn-disabled', !ok);
  }

  // Engancha cada componente obligatorio: estado inicial del botón, refresco al
  // cambiar la selección, y validación al enviar el formulario.
  function inicializarObligatorios() {
    document.querySelectorAll('.carga-archivos[data-required="1"]').forEach(function (contenedor) {
      sincronizarBoton(contenedor);

      var input = contenedor.querySelector('.carga-input-oculto');
      if (input) {
        input.addEventListener('change', function () { sincronizarBoton(contenedor); });
      }

      var form = contenedor.closest('form');
      if (form && !form.dataset.cargaValida) {
        form.dataset.cargaValida = '1'; // evita enganchar el mismo form dos veces
        form.addEventListener('submit', function (e) {
          var faltante = Array.prototype.slice
            .call(form.querySelectorAll('.carga-archivos[data-required="1"]'))
            .find(function (c) { return !tieneArchivo(c); });
          if (faltante) {
            e.preventDefault();
            var estado = faltante.querySelector('.carga-estado');
            if (estado) {
              estado.textContent = 'Seleccione un archivo antes de continuar.';
              estado.classList.add('carga-estado-error');
            }
          }
        });
      }
    });
  }

  // ─── Arrastrar y soltar (#20) ────────────────────────────────────────────
  // Cada zona .carga-dropzone vuelca los archivos soltados en el input oculto
  // de su componente y reutiliza cargaArchivosActualizar para pintar la lista y
  // validar tamaños. Respeta `multiple`: si el input no es múltiple, toma solo
  // el primer archivo.

  function asignarArchivos(input, archivos) {
    var dt = new DataTransfer();
    var lista = Array.prototype.slice.call(archivos);
    if (!input.multiple && lista.length > 1) {
      lista = [lista[0]];
    }
    lista.forEach(function (a) { dt.items.add(a); });
    input.files = dt.files;
  }

  function inicializarDropzones() {
    document.querySelectorAll('.carga-dropzone').forEach(function (zona) {
      if (zona.dataset.dropListo === '1') return; // no enganchar dos veces
      zona.dataset.dropListo = '1';

      var uid = zona.getAttribute('data-target');
      var input = document.getElementById(uid);
      if (!input) return;

      ['dragenter', 'dragover'].forEach(function (ev) {
        zona.addEventListener(ev, function (e) {
          e.preventDefault();
          e.stopPropagation();
          zona.classList.add('carga-dropzone-activa');
        });
      });

      ['dragleave', 'dragend'].forEach(function (ev) {
        zona.addEventListener(ev, function (e) {
          e.preventDefault();
          e.stopPropagation();
          zona.classList.remove('carga-dropzone-activa');
        });
      });

      zona.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        zona.classList.remove('carga-dropzone-activa');

        if (!e.dataTransfer || !e.dataTransfer.files || e.dataTransfer.files.length === 0) return;

        asignarArchivos(input, e.dataTransfer.files);
        cargaArchivosActualizar(uid); // pinta lista + valida tamaño

        // Si el componente es obligatorio, refrescar el botón de envío.
        var contenedor = document.querySelector('[data-carga="' + uid + '"]');
        if (contenedor) {
          input.dispatchEvent(new Event('change')); // dispara sincronizarBoton
        }
      });
    });
  }

  // El componente puede renderizarse después de cargar el script (p. ej. en
  // includes), así que se inicializa al estar listo el DOM.
  function inicializarTodo() {
    inicializarObligatorios();
    inicializarDropzones();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarTodo);
  } else {
    inicializarTodo();
  }
})();
