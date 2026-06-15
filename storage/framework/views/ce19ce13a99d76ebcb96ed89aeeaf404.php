
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tipo']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['tipo']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
  <button type="button" class="btn btn-outline btn-sm"
    onclick="llenarEjemplo('<?php echo e($tipo); ?>')"
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
<?php /**PATH C:\laragon\www\punta\resources\views/components/btn-ejemplo.blade.php ENDPATH**/ ?>