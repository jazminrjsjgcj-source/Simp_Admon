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
  <div class="check-grid-compact">
    @foreach($catGrupos as $opcion)
      <label class="check-chip">
        <input type="checkbox" name="grupos_atencion[]" value="{{ $opcion }}"
          {{ in_array($opcion, $gruposSel) ? 'checked' : '' }}>
        <span>{{ $opcion }}</span>
      </label>
    @endforeach
  </div>
</x-field-help>
