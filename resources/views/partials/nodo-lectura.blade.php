{{--
  Render de LECTURA de un nodo del articulado y sus hijos (recursivo).
  Modo solo-lectura: sin acciones de edición. Los contenedores (título,
  capítulo, sección, artículo) son colapsables; los derogados se muestran
  tachados con su nota, en su lugar.

  Recibe:
   - $nodo:          el RegulacionNodo
   - $hijosPorPadre: colección agrupada por parent_id (armada una vez en el show)
--}}
@php
  $hijos = $hijosPorPadre[$nodo->id] ?? collect();
  $derogado = $nodo->estaDerogado();
  $esContenedor = $hijos->isNotEmpty();
  // Encabezado legible: "Artículo 5", "Capítulo III".
  $encabezado = trim($nodo->etiquetaTipo() . ' ' . ($nodo->numero ?? ''));
  // Texto de la marca de derogación (se arma aquí para no pegar @if al texto).
  $marcaDerogado = $derogado
    ? ('Derogado' . ($nodo->derogado_nota ? ' — ' . $nodo->derogado_nota : ''))
    : null;
@endphp

<li class="lec-nodo lec-{{ $nodo->tipo }} {{ $derogado ? 'lec-derogado' : '' }}">
  @if($esContenedor)
    {{-- Contenedor: encabezado visible, hijos retraídos hasta que el usuario
         elige expandirlo. Cada nodo es independiente — el usuario lee solo
         las secciones que le interesan sin cargar todo el articulado. --}}
    <details class="lec-contenedor">
      <summary class="lec-summary">
        <span class="lec-encabezado">{{ $encabezado }}</span>
        @if($nodo->texto)
          <span class="lec-titulo">{{ \Illuminate\Support\Str::limit($nodo->texto, 120) }}</span>
        @endif
        @if($marcaDerogado)
          <span class="lec-tag-derogado">{{ $marcaDerogado }}</span>
        @endif
      </summary>

      <ul class="lec-hijos">
        @foreach($hijos as $hijo)
          @include('screens.regulaciones.partials.nodo-lectura', [
            'nodo' => $hijo,
            'hijosPorPadre' => $hijosPorPadre,
          ])
        @endforeach
      </ul>
    </details>
  @else
    {{-- Hoja: número/encabezado + texto. --}}
    <div class="lec-hoja">
      @if($encabezado !== '')
        <span class="lec-encabezado">{{ $encabezado }}</span>
      @endif
      <span class="lec-texto" style="white-space:pre-line">{{ $nodo->texto ?: '(sin texto)' }}</span>
      @if($marcaDerogado)
        <span class="lec-tag-derogado">{{ $marcaDerogado }}</span>
      @endif
    </div>
  @endif
</li>