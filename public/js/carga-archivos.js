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
})();
