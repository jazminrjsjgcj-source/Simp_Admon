{{--
  Componente: <x-btn-ejemplo tipo="tramite" />

  Botón "Ejemplo de llenado" y "Limpiar" (este último oculto hasta que se usa
  el ejemplo). Toda la lógica vive en public/js/core/ejemplo-llenado.js:
  llenarEjemplo() llena los campos, muestra "Limpiar" y un toast de aviso;
  limpiarEjemplo() vacía el formulario y vuelve a ocultar "Limpiar".

  Atributos:
    tipo → 'tramite' | 'agenda_regulatoria' | 'regulacion' | 'agenda_syd'
--}}
@props(['tipo'])

<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">
  <button type="button" class="btn btn-outline btn-sm"
    onclick="llenarEjemplo('{{ $tipo }}')"
    title="Llena todos los campos con datos ficticios para pruebas">
    Ejemplo de llenado
  </button>
  <button type="button" class="btn btn-outline btn-sm"
    onclick="limpiarEjemplo()"
    style="display:none"
    id="btnLimpiarEjemplo"
    title="Vacía todos los campos del formulario">
    Limpiar
  </button>
</div>
