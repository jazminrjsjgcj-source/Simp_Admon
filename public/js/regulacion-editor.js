/**
 * Editor jerárquico de regulaciones (Capa 4).
 *
 * Maneja la interacción de la pantalla regulaciones/{id}/editor:
 *  - Editar un nodo (número/texto) en un panel inline.
 *  - Agregar elementos en la raíz o dentro de otro (con tipos válidos y número
 *    sugerido por el backend).
 *  - Derogar (con nota), restaurar y eliminar.
 *  - Reordenar y mover por arrastrar y soltar (drag & drop nativo).
 *
 * Todas las acciones se envían como formularios POST/PUT/DELETE clásicos (no
 * fetch), reutilizando los forms ocultos de la vista y el flujo de Laravel con
 * CSRF; el servidor responde con redirect y la página se refresca. Es simple,
 * robusto y consistente con el resto del proyecto.
 */
(function () {
  'use strict';

  const cfg = window.EDITOR_REG || {};
  const panel = document.getElementById('editorPanel');
  if (!panel) return;

  const panelTitulo = document.getElementById('editorPanelTitulo');
  const panelDesc   = document.getElementById('editorPanelDesc');
  const campoTipo   = document.getElementById('campoTipo');
  const selTipo     = document.getElementById('panelTipo');
  const inpNumero   = document.getElementById('panelNumero');
  const inpTexto    = document.getElementById('panelTexto');
  const panelJerarquia = document.getElementById('panelJerarquia');
  const numAutoMsg  = document.getElementById('numAutoMsg');
  const numEditWrap = document.getElementById('numEditWrap');

  // Estado del modal: 'editar' o 'crear'.
  let modo = null;
  let urlUpdate = null;     // para editar
  let parentParaCrear = null;

  // ── Mostrar qué puede ir DENTRO del tipo seleccionado ────────────────────
  function mostrarJerarquia(tipo) {
    if (!panelJerarquia || !cfg.anidamiento) return;
    var hijos = cfg.anidamiento[tipo];
    if (hijos && hijos.length > 0) {
      panelJerarquia.textContent = 'Dentro puede contener: ' + hijos.join(', ') + '.';
    } else {
      panelJerarquia.textContent = 'Este tipo no contiene sub-elementos.';
    }
  }

  const panelInner  = document.getElementById('editorPanelInner');

  // ── Abrir modal para EDITAR ──────────────────────────────────────────────
  function abrirEditar(btn) {
    modo = 'editar';
    urlUpdate = btn.dataset.url;
    panelTitulo.textContent = 'Editar elemento';
    panelDesc.textContent = 'Modifica el número o texto de este elemento.';
    campoTipo.classList.add('hidden');
    panelJerarquia.textContent = '';
    // Editar: modal amplio para ver todo el texto.
    panelInner.style.maxWidth = '800px';
    panelInner.style.width = '90%';
    inpTexto.style.minHeight = '240px';
    numAutoMsg.style.display = 'none';
    numEditWrap.style.display = '';
    inpNumero.value = btn.dataset.numero || '';
    inpTexto.value  = btn.dataset.texto || '';
    // Auto-ajustar la altura del textarea al contenido.
    inpTexto.style.height = 'auto';
    inpTexto.style.height = inpTexto.scrollHeight + 'px';
    mostrarModal();
  }

  // ── Abrir modal para CREAR (raíz o hijo) ─────────────────────────────────
  function abrirCrear(tiposCsv, parentId, nodoRef) {
    modo = 'crear';
    parentParaCrear = parentId || '';
    panelTitulo.textContent = parentId ? 'Agregar elemento dentro' : 'Agregar elemento';
    panelDesc.textContent = parentId
      ? 'Selecciona el tipo de elemento que deseas agregar.'
      : 'Agrega un título en la raíz de la regulación.';
    campoTipo.classList.remove('hidden');

    // Crear: modal compacto, sin campo de número.
    panelInner.style.maxWidth = '480px';
    panelInner.style.width = '';
    inpTexto.style.minHeight = '100px';
    inpTexto.style.height = '';
    numAutoMsg.style.display = '';
    numEditWrap.style.display = 'none';
    inpNumero.value = '';

    // Poblar el selector solo con los tipos permitidos en ese contexto.
    var tipos = (tiposCsv || '').split(',').filter(Boolean);
    selTipo.innerHTML = '';
    tipos.forEach(function (t) {
      var op = document.createElement('option');
      op.value = t;
      op.textContent = (cfg.etiquetasTipo && cfg.etiquetasTipo[t]) || t;
      selTipo.appendChild(op);
    });

    inpTexto.value = '';
    mostrarJerarquia(selTipo.value);
    mostrarModal();
  }

  // Al cambiar el tipo en el selector, actualizar jerarquía.
  selTipo.addEventListener('change', function () {
    if (modo === 'crear') {
      mostrarJerarquia(selTipo.value);
    }
  });

  // Auto-ajustar la altura del textarea al escribir.
  inpTexto.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
  });

  // ── Mostrar/ocultar el modal ─────────────────────────────────────────────
  function mostrarModal() {
    panel.classList.add('open');
    setTimeout(function () {
      (modo === 'crear' ? selTipo : inpTexto).focus();
    }, 50);
  }

  function ocultarPanel() {
    panel.classList.remove('open');
    modo = null; urlUpdate = null; parentParaCrear = null;
  }

  // Cerrar modal al dar clic en el backdrop (fuera del cuadro).
  panel.addEventListener('click', function (e) {
    if (e.target === panel) ocultarPanel();
  });

  // ── Guardar el panel ───────────────────────────────────────────────────────
  function guardarPanel() {
    // No se puede guardar sin texto.
    if (!inpTexto.value.trim()) {
      inpTexto.style.borderColor = '#dc2626';
      inpTexto.placeholder = 'Este campo es obligatorio';
      inpTexto.focus();
      return;
    }
    inpTexto.style.borderColor = '';

    if (modo === 'editar') {
      document.getElementById('updateNumero').value = inpNumero.value;
      document.getElementById('updateTexto').value  = inpTexto.value;
      const form = document.getElementById('formUpdate');
      form.action = urlUpdate;
      form.submit();
    } else if (modo === 'crear') {
      document.getElementById('storeParent').value = parentParaCrear;
      document.getElementById('storeTipo').value   = selTipo.value;
      document.getElementById('storeNumero').value = inpNumero.value;
      document.getElementById('storeTexto').value  = inpTexto.value;
      document.getElementById('formStore').submit();
    }
  }

  // ── Modal de DEROGACIÓN ────────────────────────────────────────────────────
  const modalDerogar          = document.getElementById('modalDerogar');
  const modalDerogarNota      = document.getElementById('modalDerogarNota');
  const modalDerogarConfirmar = document.getElementById('modalDerogarConfirmar');
  const modalDerogarCancelar  = document.getElementById('modalDerogarCancelar');
  let _urlDerogar = null;

  function derogar(btn) {
    _urlDerogar = btn.dataset.url;
    modalDerogarNota.value = '';
    modalDerogar.classList.add('open');
    modalDerogarNota.focus();
  }

  modalDerogarConfirmar.addEventListener('click', function () {
    document.getElementById('derogarNota').value = modalDerogarNota.value.trim();
    const form = document.getElementById('formDerogar');
    form.action = _urlDerogar;
    modalDerogar.classList.remove('open');
    form.submit();
  });

  modalDerogarCancelar.addEventListener('click', function () {
    modalDerogar.classList.remove('open');
    _urlDerogar = null;
  });

  // ── Modal de ELIMINACIÓN ───────────────────────────────────────────────────
  const modalEliminar          = document.getElementById('modalEliminar');
  const modalEliminarTitulo    = document.getElementById('modalEliminarTitulo');
  const modalEliminarTexto     = document.getElementById('modalEliminarTexto');
  const modalEliminarInput     = document.getElementById('modalEliminarConfirmInput');
  const modalEliminarConfirmar = document.getElementById('modalEliminarConfirmar');
  const modalEliminarCancelar  = document.getElementById('modalEliminarCancelar');
  let _btnEliminar = null;

  // El botón Eliminar solo se habilita cuando el usuario escribe "ELIMINAR"
  // en MAYÚSCULAS exactas. La comparación es sensible a mayúsculas a propósito:
  // obliga a un acto deliberado antes de un borrado permanente, igual que las
  // confirmaciones de borrado de GitHub. Se conserva trim() para no penalizar
  // un espacio accidental al inicio o final.
  modalEliminarInput.addEventListener('input', function () {
    const ok = this.value.trim() === 'ELIMINAR';
    modalEliminarConfirmar.disabled = !ok;
    modalEliminarInput.style.borderColor = this.value.length > 0
      ? (ok ? 'var(--primary)' : '#b42318')
      : 'var(--border)';
  });

  function eliminar(btn) {
    _btnEliminar = btn;
    const etiqueta = btn.dataset.etiqueta || 'este elemento';
    const nodo     = btn.closest('.nodo');
    const numHijos = nodo ? contarDescendientes(nodo) : 0;

    modalEliminarTitulo.textContent = 'Eliminar ' + etiqueta;

    let texto = 'Esta acción es permanente y no se puede deshacer.';
    if (numHijos > 0) {
      texto = 'Esto también eliminará ' + numHijos + ' elemento(s) que contiene '
            + '(fracciones, incisos, párrafos).\n\n' + texto;
    }
    texto += ' Si solo quieres ocultarlo conservando su lugar, usa «Derogar».';
    modalEliminarTexto.textContent = texto;

    modalEliminarInput.value = '';
    modalEliminarInput.style.borderColor = 'var(--border)';
    modalEliminarConfirmar.disabled = true;
    modalEliminar.classList.add('open');
    modalEliminarInput.focus();
  }

  modalEliminarConfirmar.addEventListener('click', function () {
    if (!_btnEliminar) return;
    const form = document.getElementById('formEliminar');
    form.action = _btnEliminar.dataset.url;
    modalEliminar.classList.remove('open');
    form.submit();
  });

  modalEliminarCancelar.addEventListener('click', function () {
    modalEliminar.classList.remove('open');
    _btnEliminar = null;
  });

  // Cerrar modales al hacer clic en el backdrop (fuera del cuadro).
  [modalDerogar, modalEliminar].forEach(function (m) {
    m.addEventListener('click', function (e) {
      if (e.target === m) m.classList.remove('open');
    });
  });

  // ── Delegación de clics en las acciones de los nodos ───────────────────────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-accion]');
    if (!btn) return;

    switch (btn.dataset.accion) {
      case 'editar':       abrirEditar(btn); break;
      case 'agregar-hijo': abrirCrear(btn.dataset.tipos, btn.dataset.parent, btn.closest('.nodo')); break;
      case 'agregar-raiz': abrirCrear(btn.dataset.tipos, ''); break;
      case 'derogar':      derogar(btn); break;
      case 'eliminar':     eliminar(btn); break;
    }
  });

  document.getElementById('panelGuardar').addEventListener('click', guardarPanel);
  document.getElementById('panelCancelar').addEventListener('click', ocultarPanel);

  // Doble clic sobre la fila de un nodo = editar (atajo).
  document.addEventListener('dblclick', function (e) {
    const fila = e.target.closest('.nodo-fila');
    if (!fila) return;
    const btnEditar = fila.querySelector('[data-accion="editar"]');
    if (btnEditar) abrirEditar(btnEditar);
  });

  // ── Arrastrar y soltar (drag & drop nativo) ────────────────────────────────
  let arrastrado = null;
  let destinoMarcado = null; // nodo con .drop-target actual (evita escanear el DOM)

  // Quita la marca del destino actual sin recorrer todo el árbol.
  function limpiarDestino() {
    if (destinoMarcado) {
      destinoMarcado.classList.remove('drop-target');
      destinoMarcado = null;
    }
  }

  document.addEventListener('dragstart', function (e) {
    const nodo = e.target.closest('.nodo');
    if (!nodo) return;
    arrastrado = nodo;
    nodo.classList.add('arrastrando');
    e.dataTransfer.effectAllowed = 'move';
  });

  document.addEventListener('dragend', function () {
    if (arrastrado) arrastrado.classList.remove('arrastrando');
    limpiarDestino();
    arrastrado = null;
  });

  document.addEventListener('dragover', function (e) {
    const destino = e.target.closest('.nodo');
    if (!destino || destino === arrastrado) return;
    e.preventDefault(); // permite el drop
    // Si el destino no cambió, no tocar el DOM (dragover se dispara sin parar).
    if (destino === destinoMarcado) return;
    limpiarDestino();
    destino.classList.add('drop-target');
    destinoMarcado = destino;
  });

  document.addEventListener('drop', function (e) {
    const destino = e.target.closest('.nodo');
    limpiarDestino();
    if (!destino || !arrastrado || destino === arrastrado) return;
    e.preventDefault();

    // Decisión de colocación según dónde se soltó:
    //  - Cerca del borde superior de la fila: como HERMANO ANTERIOR del destino.
    //  - En el cuerpo del destino: como HIJO (al final) del destino.
    const fila = destino.querySelector('.nodo-fila');
    const rect = fila.getBoundingClientRect();
    const enBordeSuperior = (e.clientY - rect.top) < rect.height / 2;

    const idArrastrado = arrastrado.dataset.id;
    let nuevoParent, nuevoOrden;

    if (enBordeSuperior) {
      // Hermano anterior: mismo padre que el destino, en su posición.
      nuevoParent = parentIdDe(destino);
      nuevoOrden  = ordenEntreHermanos(destino);
    } else {
      // Hijo del destino, al final.
      nuevoParent = destino.dataset.id;
      nuevoOrden  = contarHijos(destino) + 1;
    }

    enviarMover(idArrastrado, nuevoParent, nuevoOrden);
  });

  function parentIdDe(nodoEl) {
    const padreUl = nodoEl.parentElement;            // <ul class="nodo-hijos"> o #editorArbol
    const padreNodo = padreUl.closest('.nodo');
    return padreNodo ? padreNodo.dataset.id : '';    // '' = raíz
  }

  function ordenEntreHermanos(nodoEl) {
    const hermanos = Array.from(nodoEl.parentElement.children).filter(c => c.classList.contains('nodo'));
    return hermanos.indexOf(nodoEl) + 1; // 1-based
  }

  function contarHijos(nodoEl) {
    const ul = nodoEl.querySelector(':scope > .nodo-hijos');
    if (!ul) return 0;
    return Array.from(ul.children).filter(c => c.classList.contains('nodo')).length;
  }

  // Cuenta TODOS los descendientes del nodo (subárbol completo, a cualquier
  // profundidad), no solo los hijos directos. Se usa para advertir cuántos
  // elementos arrastrará un borrado permanente. querySelectorAll busca solo
  // dentro del nodo, así que no se cuenta a sí mismo.
  function contarDescendientes(nodoEl) {
    return nodoEl.querySelectorAll('.nodo').length;
  }

  function enviarMover(idNodo, parentId, orden) {
    var url = cfg.rutaMoverBase + '/' + idNodo + '/mover';
    var csrf = document.querySelector('meta[name="csrf-token"]');

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf ? csrf.content : '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ parent_id: parentId || '', orden: orden }),
    })
    .then(function (r) {
      if (!r.ok) throw new Error('Error ' + r.status);
      // Éxito: el DOM ya refleja la posición nueva (el nodo se movió
      // visualmente al soltarlo). No se recarga la página.
    })
    .catch(function () {
      // Si falla (red, permisos, validación), recargar para sincronizar
      // el DOM con el estado real del servidor.
      window.location.reload();
    });
  }
})();
