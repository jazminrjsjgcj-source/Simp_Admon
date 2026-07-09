{{--
  Vista de paginación de PUNTA.

  Reemplaza la vista por defecto de Laravel para TODA la aplicación (se
  registra en AppServiceProvider::boot() con Paginator::defaultView()).
  Cualquier ->links() del proyecto usa esta vista automáticamente.

  Por qué el texto "Anterior"/"Siguiente" está escrito aquí directamente,
  en vez de usar __('pagination.previous') como hace la vista original de
  Laravel: el proyecto no tiene ningún archivo lang/{idioma}/pagination.php
  (ni en español ni en inglés como respaldo). Cuando Laravel no encuentra
  una traducción en ningún idioma disponible, su comportamiento documentado
  es devolver la CLAVE tal cual en vez de un texto — por eso se veía
  literalmente "pagination.previous" en pantalla. Escribiendo el texto
  aquí directamente, el componente funciona siempre, sin depender de que
  exista ningún archivo de idioma.

  $paginator y $elements los arma Laravel automáticamente antes de renderizar
  esta vista — no hay que pasarlos desde el controlador ni desde la vista
  que llama a ->links(). $elements es un arreglo donde cada posición es:
    - un string "..." (separador cuando hay muchas páginas), o
    - un arreglo asociativo [numeroDePagina => url, ...] con un bloque
      continuo de páginas.
--}}
@if ($paginator->hasPages())
  <nav class="punta-paginacion" aria-label="Paginación">
    <ul class="punta-paginacion-lista">

      {{-- Botón "Anterior" --}}
      @if ($paginator->onFirstPage())
        <li class="punta-paginacion-item punta-paginacion-item-lateral punta-paginacion-disabled" aria-disabled="true">
          <span>Anterior</span>
        </li>
      @else
        <li class="punta-paginacion-item punta-paginacion-item-lateral">
          <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        </li>
      @endif

      {{-- Números de página, con "..." cuando hay demasiadas para mostrar todas --}}
      @foreach ($elements as $elemento)
        @if (is_string($elemento))
          <li class="punta-paginacion-item punta-paginacion-puntos" aria-hidden="true">
            <span>{{ $elemento }}</span>
          </li>
        @endif

        @if (is_array($elemento))
          @foreach ($elemento as $pagina => $url)
            @if ($pagina == $paginator->currentPage())
              <li class="punta-paginacion-item">
                <span class="punta-paginacion-actual" aria-current="page">
                  {{ $pagina }}
                  <span class="punta-paginacion-punto"></span>
                </span>
              </li>
            @else
              <li class="punta-paginacion-item">
                <a href="{{ $url }}">{{ $pagina }}</a>
              </li>
            @endif
          @endforeach
        @endif
      @endforeach

      {{-- Botón "Siguiente" --}}
      @if ($paginator->hasMorePages())
        <li class="punta-paginacion-item punta-paginacion-item-lateral">
          <a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
        </li>
      @else
        <li class="punta-paginacion-item punta-paginacion-item-lateral punta-paginacion-disabled" aria-disabled="true">
          <span>Siguiente</span>
        </li>
      @endif

    </ul>
  </nav>
@endif
