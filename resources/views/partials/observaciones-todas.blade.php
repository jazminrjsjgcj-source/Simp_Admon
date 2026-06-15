{{--
  Aviso de todas las observaciones agrupadas, para formularios planos
  (sin secciones visuales separadas) como el edit de agenda. (Corrección #18.)

  Espera:
    $observacionesPorSeccion → colección agrupada por sección
    $campos                  → mapa de campos por sección (de config)
--}}
@php $hayAlguna = collect($observacionesPorSeccion)->flatten()->count() > 0; @endphp

@if($hayAlguna)
  <div class="obs-aviso-todas">
    @foreach($observacionesPorSeccion as $seccion => $items)
      @include('partials.observaciones-seccion', [
        'seccion' => $seccion,
        'items'   => $items,
        'campos'  => $campos[$seccion] ?? [],
      ])
    @endforeach
  </div>
@endif
