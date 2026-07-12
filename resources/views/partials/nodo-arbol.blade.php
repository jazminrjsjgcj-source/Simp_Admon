{{--
  Renderiza un nodo del árbol y, recursivamente, sus hijos. Recibe:
   - $nodo:        el RegulacionNodo a pintar
   - $hijosPorPadre: colección agrupada por parent_id (para no consultar la BD
     dentro del bucle; se arma una sola vez en la vista del editor)
   - $regulacion:  la regulación dueña (para las rutas)

  Un nodo derogado se muestra atenuado y tachado, pero permanece en su lugar.
--}}
@php
  $hijos = $hijosPorPadre[$nodo->id] ?? collect();
  $derogado = $nodo->estaDerogado();
@endphp

<li class="nodo {{ $derogado ? 'nodo-derogado' : '' }}"
    data-id="{{ $nodo->id }}"
    data-tipo="{{ $nodo->tipo }}"
    draggable="true">

  <div class="nodo-fila">
    <span class="nodo-grip" title="Arrastrar para mover" aria-hidden="true">⠿</span>

    <span class="nodo-tipo">{{ $nodo->etiquetaTipo() }}</span>

    @if($nodo->numero)
      <span class="nodo-numero">{{ $nodo->numero }}</span>
    @endif

    <span class="nodo-texto" title="{{ $derogado ? 'Elemento derogado' : 'Doble clic para editar' }}">
      {{ \Illuminate\Support\Str::limit($nodo->texto, 160) ?: '(sin texto)' }}
    </span>

    @if($derogado && $nodo->derogado_nota)
      <span class="nodo-nota">{{ $nodo->derogado_nota }}</span>
    @endif

    <span class="nodo-acciones">
      {{-- Editar (abre el panel de edición inline en JS) --}}
      <button type="button" class="btn-nodo" data-accion="editar"
        data-numero="{{ $nodo->numero }}"
        data-texto="{{ $nodo->texto }}"
        data-url="{{ route('regulaciones.nodos.update', $nodo) }}"
        title="Editar">Editar</button>

      {{-- Agregar hijo (solo si el tipo admite hijos) --}}
      @if(!empty(\App\Models\RegulacionNodo::ANIDAMIENTO[$nodo->tipo] ?? []))
        <button type="button" class="btn-nodo" data-accion="agregar-hijo"
          data-parent="{{ $nodo->id }}"
          data-tipos="{{ implode(',', \App\Models\RegulacionNodo::ANIDAMIENTO[$nodo->tipo]) }}"
          title="Agregar elemento dentro">+ dentro</button>
      @endif

      {{-- Derogar / Restaurar --}}
      @if($derogado)
        <form method="POST" action="{{ route('regulaciones.nodos.restaurar', $nodo) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn-nodo" title="Restaurar">Restaurar</button>
        </form>
      @else
        <button type="button" class="btn-nodo btn-nodo-danger" data-accion="derogar"
          data-url="{{ route('regulaciones.nodos.derogar', $nodo) }}"
          title="Marcar como derogado">Derogar</button>
      @endif

      {{-- Eliminar (borrado real, para errores de captura) --}}
      <button type="button" class="btn-nodo btn-nodo-danger" data-accion="eliminar"
        data-url="{{ route('regulaciones.nodos.destroy', $nodo) }}"
        data-etiqueta="{{ trim($nodo->etiquetaTipo() . ' ' . $nodo->numero) }}"
        title="Eliminar definitivamente">Eliminar</button>
    </span>
  </div>

  {{-- Hijos: lista anidada (sangría por CSS). Recursión. --}}
  @if($hijos->isNotEmpty())
    <ul class="nodo-hijos">
      @foreach($hijos as $hijo)
        @include('screens.regulaciones.partials.nodo-arbol', [
          'nodo' => $hijo,
          'hijosPorPadre' => $hijosPorPadre,
          'regulacion' => $regulacion,
        ])
      @endforeach
    </ul>
  @endif
</li>
