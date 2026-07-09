{{--
  Grupos de Atención Prioritaria del trámite (Art. 19 fracc. III LNETB).

  #15: Es característica del trámite, no de la agenda. La agenda los lee
  para priorizar (Art. 27 fracc. II y Art. 29 fracc. V); cuando se vincula
  un trámite a una agenda, los grupos se precargan automáticamente.

  La Etapa de operación originalmente vivía aquí, pero se movió al paso 2
  (junto a "¿A quién va dirigido?") porque depende lógicamente de él.

  Recibe:
    $gruposSel : array de grupos prioritarios ya seleccionados
--}}
@php
  $gruposSel = $gruposSel ?? [];

  $catGrupos = [
    'No Aplica',
    'Niñas, niños y adolescentes',
    'Mujeres',
    'Personas mayores',
    'Personas con discapacidad',
    'Personas pertenecientes a pueblos y comunidades indígenas o afrodescendientes',
    'Personas pertenecientes a la comunidad LGBTTTI',
    'Personas migrantes o refugiadas',
    'Personas víctimas de violaciones a derechos humanos',
    'Personas en situación de calle',
    'Personas periodistas y defensoras de DDHH',
  ];
@endphp

<x-field-help label="Grupos de atención prioritaria" class="span-2">
  <div class="check-grid-compact" id="gruposAtencionGrid">
    @foreach($catGrupos as $opcion)
      <label class="check-chip">
        <input type="checkbox" name="grupos_atencion[]" value="{{ $opcion }}"
          {{ in_array($opcion, $gruposSel) ? 'checked' : '' }}>
        <span>{{ $opcion }}</span>
      </label>
    @endforeach
  </div>
</x-field-help>

<script>
{{-- #43: "No Aplica" debe ser excluyente — no tiene sentido marcarlo junto
     con grupos reales. Este script se auto-ejecuta cada vez que el partial
     se incluye (create y edit), y usa un guard por id de grid para no
     duplicar el listener si el partial se renderiza más de una vez en la
     misma página. --}}
(function () {
  var grid = document.getElementById('gruposAtencionGrid');
  if (!grid || grid.dataset.exclusividadNoAplica) return;
  grid.dataset.exclusividadNoAplica = '1';

  var checks = grid.querySelectorAll('input[type="checkbox"]');
  var noAplica = Array.from(checks).find(function (c) { return c.value === 'No Aplica'; });
  if (!noAplica) return;

  grid.addEventListener('change', function (e) {
    if (e.target.type !== 'checkbox') return;

    if (e.target === noAplica) {
      // Marcar "No Aplica" desmarca todos los demás.
      if (noAplica.checked) {
        checks.forEach(function (c) { if (c !== noAplica) c.checked = false; });
      }
    } else if (e.target.checked) {
      // Marcar cualquier otro grupo desmarca "No Aplica".
      noAplica.checked = false;
    }
  });
})();
</script>
