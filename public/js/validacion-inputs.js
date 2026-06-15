/**
 * Validación en vivo de inputs con data-validacion-tipo.
 *
 * Funciona con los inputs renderizados por el componente <x-input-validado />.
 * Bloquea caracteres no permitidos al escribir y muestra mensaje de error
 * debajo del input usando `.input-error-msg`.
 */
(function () {
    'use strict';

    var PATTERNS = {
        numero_entero:      /^\d*$/,
        numero_decimal:     /^\d*(\.\d{0,4})?$/,
        solo_texto:         /^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ\s,.\-]*$/,
        alfanumerico:       /^[A-Za-z0-9]*$/,
        codigo_ur:          /^\d{0,14}$/,
        rfc:                /^[A-ZÑ&0-9]*$/i,
        curp:               /^[A-Z0-9]*$/i,
        telefono:           /^\d{0,10}$/,
    };

    function buscarMensajeEl(input) {
        var contenedor = input.closest('.field');
        if (!contenedor) return null;
        return contenedor.querySelector('.input-error-msg');
    }

    function mostrarError(input, mensaje) {
        var msgEl = buscarMensajeEl(input);
        input.classList.add('input-invalid');
        if (msgEl) {
            msgEl.textContent = mensaje;
            msgEl.style.display = '';
        }
    }

    function limpiarError(input) {
        var msgEl = buscarMensajeEl(input);
        input.classList.remove('input-invalid');
        if (msgEl) {
            msgEl.textContent = '';
            msgEl.style.display = 'none';
        }
    }

    function validarInput(input) {
        var tipo = input.dataset.validacionTipo;
        if (!tipo || !PATTERNS[tipo]) {
            limpiarError(input);
            return true;
        }

        var valor   = input.value;
        if (valor === '') {
            limpiarError(input);
            return true;
        }

        var patron  = PATTERNS[tipo];
        if (!patron.test(valor)) {
            mostrarError(input, input.dataset.validacionMensaje || 'Valor inválido.');
            return false;
        }
        limpiarError(input);
        return true;
    }

    // Aplicar a todos los inputs con data-validacion-tipo
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t && t.dataset && t.dataset.validacionTipo) {
            validarInput(t);
        }
    });

    document.addEventListener('blur', function (e) {
        var t = e.target;
        if (t && t.dataset && t.dataset.validacionTipo) {
            validarInput(t);
        }
    }, true);
})();
