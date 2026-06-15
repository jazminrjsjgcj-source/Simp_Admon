{{--
  Componente: <x-btn-ejemplo tipo="tramite" />

  Muestra dos botones (Ejemplo de llenado + Limpiar) y un aviso
  de que los datos son ficticios. El JS llena todos los campos
  del formulario sin guardar ni enviar.

  Atributos:
    tipo → 'tramite' | 'agenda_regulatoria' | 'regulacion' | 'agenda_syd'
--}}
@props(['tipo'])

<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
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

<div id="aviso-ejemplo" class="assist-box" style="display:none;margin-top:8px;background:#FEF9C3;border-color:#F59E0B">
  <strong>Datos de ejemplo.</strong> Esta información es ficticia y solo sirve para pruebas. Revise y modifique antes de guardar.
</div>

<script>
// Mostrar botón Limpiar cuando se llena el ejemplo
(function () {
  var _origLlenar = window.llenarEjemplo;
  if (!_origLlenar) return;
  window.llenarEjemplo = function (tipo) {
    _origLlenar(tipo);
    var btn = document.getElementById('btnLimpiarEjemplo');
    if (btn) btn.style.display = '';
  };
})();
</script>
