@extends('layouts.app')
@section('title', 'Buscador')

@section('content')
<div class="page-default buscar-layout">

  {{-- COLUMNA LATERAL: búsquedas recientes del usuario.
       Vienen de la bitácora que el buscador ya escribía (tabla busqueda_log), así
       que no hay dato nuevo que guardar. Cada una es un enlace que repite esa
       búsqueda. Si el usuario no tiene historial, la columna no se dibuja. --}}
  <aside class="buscar-lateral">
    @if(!empty($busquedasRecientes))
      <div class="buscar-lateral-bloque">
        <h3 class="buscar-lateral-titulo">Búsquedas recientes</h3>
        <ul class="buscar-lateral-lista">
          @foreach($busquedasRecientes as $reciente)
            <li>
              <a href="{{ route('buscar', ['q' => $reciente]) }}"
                 class="{{ $reciente === $consulta ? 'activa' : '' }}"
                 title="{{ $reciente }}">{{ $reciente }}</a>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  </aside>

  {{-- COLUMNA PRINCIPAL: buscador, filtros y resultados --}}
  <div class="buscar-principal">

  {{-- ENCABEZADO simple. El saludo grande ("¿En qué te puedo ayudar?") vive ahora
       en el dashboard, que es el punto de entrada; esta pantalla es la de
       resultados, así que basta con el título y el acceso a la ayuda. --}}
  <div class="screen-head">
    <div>
      <h2 class="nowrap">Buscador</h2>
      <p class="nowrap">Busca en regulaciones, trámites, servicios, requisitos y fundamentos jurídicos.</p>
    </div>
    {{-- Enlace discreto: es una ayuda ocasional, no una acción principal, así que
         no debe pesar más que el propio buscador. --}}
    <button type="button" class="buscar-ayuda-link"
      onclick="document.getElementById('buscarAyuda').classList.toggle('open')">
      <i class="ti ti-help-circle"></i> ¿Cómo buscar?
    </button>
  </div>

  {{-- PANEL DE AYUDA --}}
  <div class="buscar-ayuda" id="buscarAyuda">
    <div class="buscar-ayuda-grid">

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-search"></i> Campo de búsqueda</strong>
        {{-- Este texto DECÍA lo contrario: "no necesita escribir frases completas".
             Y era verdad cuando el buscador exigía todas las palabras: una pregunta entera
             ("cuánto paga un semifijo en basura") no encontraba nada, porque ninguna ley
             contiene las palabras "cuanto" ni "paga".
             Ahora el buscador entiende preguntas: descarta las palabras de relleno y se queda
             con el tema. Así que la ayuda tenía que cambiar, o seguiría empujando a la gente
             a buscar como buscaba un sistema que ya no existe. --}}
        <p>Pregunte con sus propias palabras, como le hablaría a una persona: <code>cuánto cuesta el permiso para ambulantes</code> o <code>qué necesito para abrir un negocio</code>. También puede buscar por palabras sueltas (<code>licencia</code>, <code>ambulantes</code>, <code>construcción</code>). El buscador encuentra coincidencias en el texto de artículos, regulaciones, trámites, requisitos y fundamentos.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-filter"></i> Filtrar por regulación</strong>
        <p>Use el campo <em>"Filtrar dentro de una regulación…"</em> para restringir la búsqueda a una o varias leyes específicas. Escriba parte del nombre para encontrarla, haga clic para agregarla como chip. Puede agregar varias. Para quitar un filtro, presione la <strong>×</strong> del chip. Los resultados mostrarán solo artículos de esas leyes, más los trámites, requisitos, fundamentos y acciones de agenda vinculados a ellas.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-category"></i> Filtrar por tipo</strong>
        <p>Los botones de tipo (Articulado, Regulaciones, Trámites, Requisitos, Fundamentos, Agenda) permiten buscar solo en las fuentes que le interesen. Por defecto busca en todas. Haga clic en uno o varios para activarlos: solo los activados se consultarán. Combine con el filtro de regulación para búsquedas muy específicas, por ejemplo: <code>ambulantes</code> en la <em>Ley de Hacienda</em> solo en <em>Trámites / Servicios</em>.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-bulb"></i> Respuesta destacada</strong>
        <p>Cuando su búsqueda coincide con un término definido en alguna regulación (como <code>servicio</code>, <code>trámite</code>, <code>sujeto obligado</code>), aparece un recuadro con la definición legal, la ley de donde proviene y el artículo exacto. Esta respuesta aparece siempre, incluso cuando hay filtro de regulación activo.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-book-2"></i> Lectura de artículos</strong>
        <p>Los resultados de tipo <em>Articulado</em> se pueden abrir en un modal de lectura haciendo clic. El modal muestra el artículo completo con sus fracciones e incisos, junto con un enlace para ver la regulación completa.</p>
      </div>

      <div class="buscar-ayuda-bloque">
        <strong><i class="ti ti-sparkles"></i> Consejos</strong>
        <p>Puede preguntar con frases completas. Si no encuentra lo que busca, pruebe con otras palabras: a veces la ley usa un término distinto al de la calle (dice <em>cuota</em> donde usted diría <em>permiso</em>, o <em>residuos sólidos</em> donde usted diría <em>basura</em>). El enlace <em>"Ver todos los resultados relacionados"</em> aparece cuando el buscador enfocó automáticamente su búsqueda en una sola fuente: haga clic para ver el panorama completo.</p>
      </div>

    </div>
  </div>

  {{-- CAMPO DE BÚSQUEDA + FILTRO POR REGULACIÓN --}}
  <form method="GET" action="{{ route('buscar') }}" class="buscar-form-wrap" autocomplete="off">
    <div class="buscar-fila-principal">
      <div class="buscar-input-wrap">
        <i class="ti ti-search buscar-icono"></i>
        <input
          {{-- type=text y no search: con "search" el navegador añade SU PROPIA ✕
               encima de la nuestra y se veían dos botones de limpiar pegados. --}}
          type="text"
          name="q"
          value="{{ $consulta }}"
          placeholder="Ej. ¿quién emite licencias?, requisitos construcción, plazo uso de suelo..."
          class="buscar-input"
          autofocus>
        @if($consulta)
          <a href="{{ route('buscar') }}" class="buscar-limpiar" title="Limpiar búsqueda">&times;</a>
        @endif
      </div>
      <button type="submit" class="btn" style="flex-shrink:0">Buscar</button>
    </div>

    {{-- Selector de regulaciones: checkboxes dentro de <details> --}}
    @if($regulaciones->isNotEmpty())
      {{-- Chips de regulaciones seleccionadas --}}
      <div class="buscar-chips" id="buscarChips">
        {{-- Los chips se pintan aquí por JS; los hidden inputs también --}}
      </div>

      {{-- Input de búsqueda de regulación con dropdown flotante --}}
      <div class="buscar-reg-wrap">
        <i class="ti ti-filter buscar-reg-icono"></i>
        <input type="text" id="buscarRegInput"
               placeholder="Filtrar dentro de una regulación…"
               class="buscar-reg-input" autocomplete="off">
        <div class="buscar-reg-dropdown" id="buscarRegDropdown"></div>
      </div>
    @endif

    {{-- Filtro por tipo de fuente --}}
    @php
      $tiposDisponibles = [
        'articulo'   => ['label' => 'Articulado',        'icono' => 'ti-book-2'],
        'regulacion' => ['label' => 'Regulaciones',      'icono' => 'ti-scale'],
        'tramite'    => ['label' => 'Trámites / Servicios', 'icono' => 'ti-file-text'],
        'requisito'  => ['label' => 'Requisitos',        'icono' => 'ti-file-check'],
        'fundamento' => ['label' => 'Fundamentos',       'icono' => 'ti-gavel'],
        'agenda'     => ['label' => 'Agenda',             'icono' => 'ti-tools'],
      ];
      $tiposActivos = $tiposSeleccionados ?? [];
      $todosTipos   = empty($tiposActivos);
    @endphp
    <div class="buscar-tipos" id="buscarTiposContainer">
      @foreach($tiposDisponibles as $clave => $info)
        <button type="button" class="buscar-tipo-toggle {{ !$todosTipos && in_array($clave, $tiposActivos) ? 'activo' : '' }}"
          data-tipo="{{ $clave }}">
          <i class="ti {{ $info['icono'] }}"></i>
          {{ $info['label'] }}
        </button>
      @endforeach
      <div id="tiposHiddenContainer">
        @if(!$todosTipos)
          @foreach($tiposActivos as $t)
            <input type="hidden" name="tipos[]" value="{{ $t }}">
          @endforeach
        @endif
      </div>
    </div>

    {{-- Ver leyes de otras jurisdicciones. Apagado por defecto: el buscador del
         ciudadano filtra por jurisdicción salvo que se pida explícitamente. Lo que
         entre vendrá marcado, y el asistente lo advierte. --}}
    <label class="buscar-jurisdiccion-toggle">
      <input type="checkbox" name="otras_jurisdicciones" value="1"
             onchange="this.form.submit()" @checked(!empty($incluirOtrasJurisdicciones))>
      <span class="buscar-jurisdiccion-texto">
        Incluir leyes de otras jurisdicciones
        <small>Muestra disposiciones de otros estados o municipios, marcadas como tales. Pueden no aplicarte.</small>
      </span>
    </label>
  </form>

  {{-- ════════════════════════════════════════════════════════════════════
       RESPUESTA DESTACADA
       ════════════════════════════════════════════════════════════════════

       Hay TRES clases de respuesta, y la pantalla TIENE que distinguirlas:

         alta     → Definición curada A MANO por una persona del Ayuntamiento.
         media    → Extraída automáticamente del articulado de una regulación.
         generada → REDACTADA POR UNA INTELIGENCIA ARTIFICIAL.

       Antes las tres se pintaban igual, con una etiqueta gris que decía
       "Confianza: Generada". Eso no le dice nada a un ciudadano.

       Y el riesgo es concreto: una persona lee un texto sobre lo que tiene que pagar,
       en el portal oficial de su Ayuntamiento, y NO TIENE FORMA DE SABER que lo redactó
       una máquina. Se lo cree, porque está en la web del gobierno.

       Un ciudadano tiene derecho a saber si lo que lee lo escribió el Ayuntamiento o lo
       redactó un modelo. No es un detalle de diseño: es una cuestión de honestidad.
       ════════════════════════════════════════════════════════════════════ --}}

  {{-- RESPUESTA DE DATOS: preguntas tipo "¿cuántos trámites en borrador?".
       La verdad es el número calculado en la BD; se muestra ADEMÁS la
       interpretación, para que el usuario confirme qué se preguntó. --}}
  @if($consulta && !empty($respuestaDatos))
    @php $rd = $respuestaDatos['resultado']; $rc = $respuestaDatos['receta']; @endphp
    <div class="buscar-datos">
      @php
        $verbo = match ($rd['tipo']) {
            'conteo' => 'contar',
            'lista'  => 'listar',
            default  => 'agrupar',
        };
        // Filtros en texto legible: "estatus = borrador, naturaleza = tramite".
        $filtrosTexto = [];
        foreach (($rc['filtros'] ?? []) as $clave => $valor) {
            $filtrosTexto[] = $clave . ' = ' . $valor;
        }
        $interpretacion = $filtrosTexto ? ' con ' . implode(', ', $filtrosTexto) : '';
        $interpretacion .= !empty($rc['agrupar']) ? ', por ' . $rc['agrupar'] : '';
      @endphp
      <p class="buscar-datos-interpreta">
        Entendí: <strong>{{ $verbo }} {{ $rd['entidad'] }}</strong>{{ $interpretacion }}.
      </p>

      @if($rd['tipo'] === 'conteo')
        <div class="buscar-datos-num">{{ number_format($rd['total']) }}</div>

      @elseif($rd['tipo'] === 'lista')
        <div class="buscar-datos-num">{{ number_format($rd['total']) }} <span>en total</span></div>
        <ul class="buscar-datos-lista">
          @foreach($rd['filas'] as $f)
            <li>{{ $f['nombre'] }}</li>
          @endforeach
        </ul>
        @if($rd['total'] > count($rd['filas']))
          <p class="buscar-datos-nota">Mostrando {{ count($rd['filas']) }} de {{ number_format($rd['total']) }}.</p>
        @endif

      @elseif($rd['tipo'] === 'agrupar')
        <table class="buscar-datos-tabla">
          <tbody>
          @foreach($rd['grupos'] as $g)
            <tr><td>{{ $g['grupo'] }}</td><td>{{ number_format($g['total']) }}</td></tr>
          @endforeach
          </tbody>
        </table>
      @endif

      <p class="buscar-datos-pie">Calculado en vivo desde la base de datos.</p>
    </div>
  @endif

  @if($consulta && $respuestaDestacada)
    @php
      $confianza = $respuestaDestacada['confianza'] ?? '';

      // 'generada'    → la IA respondió la pregunta.
      // 'relacionada' → la IA NO pudo responder, pero cuenta qué SÍ dicen las regulaciones
      //                 sobre el tema. Es una respuesta A MEDIAS, y el ciudadano tiene que
      //                 saberlo: si cree que le respondieron y no fue así, se va con una
      //                 conclusión que nadie le dio.
      $esGenerada    = $confianza === 'generada';
      $esRelacionada = $confianza === 'relacionada';
      $esIA          = $esGenerada || $esRelacionada;

      // La cita bien formada.
      //
      // ANTES el blade anteponía la palabra "Artículo" a lo que le llegara:
      //
      //     , Artículo {{ $respuestaDestacada['articulo'] }}
      //
      // Y cuando la fuente era un inciso, el resultado era "Artículo Inciso e".
      // Eso no existe. Una cita mal formada en una respuesta legal es peor que ninguna:
      // el ciudadano no puede comprobarla, y el Ayuntamiento no puede defenderla.
      //
      // Ahora se usa la etiqueta TAL COMO VIENE ("Artículo 15", "Inciso e", "Fracción II"),
      // porque el buscador ya la construye completa.
      $cita = trim((string) ($respuestaDestacada['articulo'] ?? ''));
    @endphp

    <div class="buscar-destacada {{ $esIA ? 'buscar-destacada-ia' : '' }} {{ $esRelacionada ? 'buscar-destacada-parcial' : '' }}">

      @if($esRelacionada)
        {{-- Respuesta A MEDIAS.
             La IA no encontró lo que se preguntaba, pero SÍ encontró cosas del tema y las
             cuenta. Es útil —a veces muy útil— pero NO es la respuesta, y decirlo importa.

             Caso real: preguntan "¿cuánto dura el permiso de ambulantes?" y las fuentes solo
             dicen que se cobra 0.05 UMA por día. La IA lo cuenta, sin deducir nada. El ciudadano
             deduce solo que la vigencia depende de lo que pague — y esa conclusión es SUYA, no
             del Ayuntamiento.

             Si la pantalla lo pintara como una respuesta normal, esa persona se iría creyendo
             que el Ayuntamiento le dijo algo que nunca le dijo. --}}
        <div class="buscar-destacada-aviso-ia">
          <strong>No encontré una respuesta directa a tu pregunta.</strong>
          Esto es lo que sí dicen las regulaciones sobre el tema. Puede que no sea lo que
          buscabas: revisa las fuentes citadas o consulta con la dependencia.
        </div>
      @elseif($esGenerada)
        {{-- El aviso va ARRIBA, antes del texto. Debajo, la mitad de la gente ya lo habría
             leído y se lo habría creído. Un aviso solo sirve si llega antes que el dato al
             que se refiere. --}}
        <div class="buscar-destacada-aviso-ia">
          <strong>Respuesta redactada automáticamente.</strong>
          Un asistente automático resumió las regulaciones que ves abajo. No es una respuesta
          oficial del Ayuntamiento: <strong>compruébala en las fuentes citadas</strong> antes de
          tomar cualquier decisión.
        </div>
      @endif

      <div class="buscar-destacada-encabezado">
        <i class="ti ti-bulb"></i>
        @if($respuestaDestacada['termino'])
          <strong>{{ $respuestaDestacada['termino'] }}</strong>
        @endif
        <span class="buscar-destacada-confianza buscar-destacada-confianza-{{ $confianza }}">
          @if($esRelacionada)
            Información relacionada
          @elseif($esGenerada)
            Redactada por IA
          @elseif($confianza === 'alta')
            Definición oficial
          @else
            Extraída del articulado
          @endif
        </span>
      </div>

      @if(!empty($respuestaDestacada['fuera_de_jurisdiccion']))
        <span class="buscar-tag buscar-tag-jurisdiccion">⚠ Incluye una disposición de otra jurisdicción</span>
      @endif
      <p class="buscar-destacada-texto">{{ $respuestaDestacada['definicion'] }}</p>

      <div class="buscar-destacada-fuente">
        @if($respuestaDestacada['regulacion_id'])
          <a href="{{ route('regulaciones.show', $respuestaDestacada['regulacion_id']) }}"
             target="_blank" rel="noopener"
             title="Abrir la regulación en otra pestaña">
            {{ $respuestaDestacada['fuente'] }}{{ $cita ? ', ' . $cita : '' }}{{ $respuestaDestacada['fraccion'] ? ', fracción ' . $respuestaDestacada['fraccion'] : '' }}
          </a>
        @else
          <span>{{ $respuestaDestacada['fuente'] }}{{ $cita ? ', ' . $cita : '' }}</span>
        @endif
      </div>

      @if(!empty($respuestaDestacada['definiciones_adicionales']))
        <details class="buscar-destacada-adicionales">
          <summary>
            {{ count($respuestaDestacada['definiciones_adicionales']) }}
            {{ count($respuestaDestacada['definiciones_adicionales']) === 1 ? 'fuente adicional' : 'fuentes adicionales' }}
            {{ $esIA ? 'usadas en esta respuesta' : 'en otras regulaciones' }}
          </summary>
          <ul>
            @foreach($respuestaDestacada['definiciones_adicionales'] as $adicional)
              <li>
                @if($adicional['regulacion_id'])
                  {{-- Comprobar una cita no debería costar una búsqueda: el enlace lleva
                       al PDF oficial, en la página donde está ese artículo, y se abre en
                       otra pestaña para no perder los resultados que se están leyendo.
                       Si esa fuente no tiene PDF localizado, se cae al articulado. --}}
                  @php
                    $destino = $adicional['pdf_url']
                      ?? route('regulaciones.show', $adicional['regulacion_id']);
                  @endphp
                  <a href="{{ $destino }}" target="_blank" rel="noopener"
                     title="Abrir el documento oficial en otra pestaña">
                    {{ $adicional['fuente'] }}{{ $adicional['articulo'] ? ', ' . $adicional['articulo'] : '' }}
                    @if(!empty($adicional['pagina']))
                      <span class="buscar-fuente-pagina">pág. {{ $adicional['pagina'] }}</span>
                    @endif
                  </a>
                @else
                  <span>{{ $adicional['fuente'] }}</span>
                @endif
              </li>
            @endforeach
          </ul>
        </details>
      @endif
    </div>

  {{-- ════════════════════════════════════════════════════════════════════
       NO HAY RESPUESTA, Y HAY QUE DECIRLO
       ════════════════════════════════════════════════════════════════════

       El ciudadano escribe "¿cuánto DURA el permiso de ambulantes?", el buscador
       devuelve 12 resultados... y no aparece ninguna respuesta.

       ¿Qué piensa? No lo sabe. Puede pensar que el sistema está roto, que no encontró
       nada, o que tiene que leerse los doce artículos por su cuenta.

       Y el sistema SÍ SABE qué pasó: sabe que le preguntaron algo que las regulaciones
       cargadas no responden. En ese ejemplo, la Ley de Hacienda dice cuánto CUESTA el
       permiso, pero no cuánto DURA: eso lo diría otro reglamento, que no está subido.

       Callarse es el patrón que este proyecto lleva catorce bugs persiguiendo: el sistema
       sabe algo y la pantalla no lo dice.

       Y el aviso sirve a DOS personas:
         · Al ciudadano, que sabe qué hacer (leer los resultados, o preguntar).
         · Al Ayuntamiento, que se entera de que le FALTA UNA REGULACIÓN.
       ════════════════════════════════════════════════════════════════════ --}}
  @elseif($consulta && $resultados->isNotEmpty())
    <div class="buscar-destacada buscar-destacada-sin-respuesta">
      <div class="buscar-destacada-encabezado">
        <i class="ti ti-help-circle"></i>
        <strong>No encontré una respuesta clara a tu pregunta.</strong>
      </div>
      <p class="buscar-destacada-texto">
        Encontré {{ $resultados->count() }} documentos relacionados, pero ninguno responde
        exactamente lo que preguntas. Revísalos abajo, o consulta directamente con la
        dependencia.
      </p>
    </div>
  @endif

  {{-- RESULTADOS --}}
  @if($consulta)
    <div class="buscar-meta">
      {{ $resultados->count() }} resultado{{ $resultados->count() !== 1 ? 's' : '' }}
      para <strong>"{{ $consulta }}"</strong>
      @if($regulacionesFiltro && $regulacionesFiltro->isNotEmpty())
        en
        @foreach($regulacionesFiltro as $rf)
          <strong>{{ $rf->nombre }}</strong>{{ !$loop->last ? ', ' : '' }}
        @endforeach
      @endif
      <span class="buscar-tiempo">{{ $tiempo }} ms</span>
    </div>

    {{-- Modo explorar: solo aparece cuando la búsqueda fue enfocada a una
         sola fuente, para que el usuario siempre pueda ver el panorama
         completo si lo enfocado no era lo que buscaba.
         No aplica cuando hay filtro por regulación activo. --}}
    @if($modo === 'enfocado')
      <div class="buscar-modo-aviso">
        Resultados enfocados según tu pregunta.
        <a href="{{ route('buscar', ['q' => $consulta, 'todos' => 1]) }}">Ver todos los resultados relacionados</a>
      </div>
    @endif

    @if($resultados->isEmpty())
      {{-- Estado vacío (Refactoring UI §46) --}}
      <div class="buscar-vacio">
        <i class="ti ti-search-off" style="font-size:48px;color:var(--muted);display:block;margin-bottom:12px"></i>
        <strong>No se encontraron resultados</strong>
        @if($regulacionesFiltro && $regulacionesFiltro->isNotEmpty())
          <p>No se encontró "{{ $consulta }}" dentro de {{ $regulacionesFiltro->pluck('nombre')->join(', ', ' ni ') }}.</p>
          <p style="margin-top:8px">
            <a href="{{ route('buscar', ['q' => $consulta]) }}" class="btn btn-outline">
              Buscar en todas las regulaciones
            </a>
          </p>
        @else
          <p>Intenta con otras palabras o con menos términos. El buscador busca coincidencias exactas en el texto de regulaciones, trámites, servicios, requisitos y fundamentos.</p>
          <p style="font-size:12px;color:var(--muted)">Sugerencias: usa palabras clave simples como "licencia", "construcción", "catastro", "residuos".</p>
        @endif
      </div>
    @else
      @php
        // Etiqueta legible de cada tipo de resultado.
        $tipoLabels = [
          'articulo'   => 'Artículo',
          'regulacion' => 'Regulación',
          'tramite'    => 'Trámite / Servicio',
          'requisito'  => 'Requisito',
          'fundamento' => 'Fundamento jurídico',
          'agenda'     => 'Acción de agenda',
        ];

        /**
         * Resalta en el texto las palabras que se buscaron.
         *
         * Es lo que deja ver de un vistazo por qué salió ese resultado, en vez de
         * obligar a leer el párrafo entero buscando la coincidencia.
         *
         * El texto se ESCAPA primero y solo después se insertan las marcas: así el
         * contenido de la base nunca puede inyectar HTML. Se ignoran las palabras
         * de menos de tres letras (artículos, preposiciones), que resaltarían medio
         * párrafo sin aportar nada.
         */
        $resaltar = function (?string $texto) use ($consulta) {
          $texto = e((string) $texto);

          $palabras = collect(preg_split('/\s+/u', trim((string) $consulta)))
            ->filter(fn ($p) => mb_strlen($p) >= 3)
            ->map(fn ($p) => preg_quote($p, '/'))
            ->all();

          if ($palabras === []) {
            return $texto;
          }

          return preg_replace('/(' . implode('|', $palabras) . ')/iu', '<mark>$1</mark>', $texto);
        };
      @endphp

      <div class="buscar-resultados">
        @foreach($resultados as $r)
          @php
            // Extraer el ID del resultado según su tipo para la bitácora
            $resultadoId = $r['meta']['nodo_id']
              ?? $r['meta']['regulacion_id']
              ?? $r['meta']['tramite_id']
              ?? $r['meta']['requisito_id']
              ?? $r['meta']['fundamento_id']
              ?? $r['meta']['accion_id']
              ?? 0;
          @endphp
          <div class="buscar-resultado-card">
            <a href="{{ $r['url'] }}" class="buscar-resultado"
              data-tipo="{{ $r['tipo'] }}"
              data-id="{{ $resultadoId }}"
              onclick="return abrirModalDetalle('{{ $r['tipo'] }}', {{ $resultadoId }}, '{{ $r['url'] }}')"
            >
              <div class="buscar-resultado-icono">
                <i class="ti {{ $r['icono'] }}"></i>
              </div>

              <div class="buscar-resultado-contenido">
                {{-- Cabecera: de qué tipo es el resultado y en qué estado está. --}}
                <div class="buscar-resultado-cabecera">
                  <span class="buscar-categoria">{{ $tipoLabels[$r['tipo']] ?? $r['tipo'] }}</span>
                  @if(!empty($r['meta']['estatus']))
                    <span class="buscar-estado buscar-estado-{{ $r['meta']['estatus'] }}">
                      {{ ucfirst(str_replace('_', ' ', $r['meta']['estatus'])) }}
                    </span>
                  @endif
                </div>

                {{-- Título y fragmento con los términos buscados resaltados: es lo que
                     deja ver de un vistazo POR QUÉ salió este resultado. --}}
                <h3 class="buscar-resultado-titulo">{!! $resaltar($r['titulo']) !!}</h3>

                @if($r['subtitulo'])
                  <p class="buscar-resultado-subtitulo">{{ $r['subtitulo'] }}</p>
                @endif

                @if(!empty($r['fuera_de_jurisdiccion']))
                  <span class="buscar-tag buscar-tag-jurisdiccion">⚠ Otra jurisdicción — podría no aplicarte</span>
                @endif

                @if($r['fragmento'])
                  <p class="buscar-resultado-fragmento">{!! $resaltar($r['fragmento']) !!}</p>
                @endif

                {{-- Pie de datos: plazo, costo, fechas... lo secundario, en pequeño. --}}
                @php
                  $datos = [];
                  if ($r['tipo'] === 'tramite' && !empty($r['meta']['plazo'])) {
                      $datos[] = ['ti-clock', $r['meta']['plazo']];
                  }
                  if ($r['tipo'] === 'tramite' && !empty($r['meta']['cbu'])) {
                      $datos[] = ['ti-scale', 'CBU $' . number_format($r['meta']['cbu'], 2)];
                  }
                  if ($r['tipo'] === 'agenda' && !empty($r['meta']['tramite_nombre'])) {
                      $datos[] = ['ti-file-text', Str::limit($r['meta']['tramite_nombre'], 50)];
                  }
                  if ($r['tipo'] === 'agenda' && !empty($r['meta']['fecha_compromiso'])) {
                      $datos[] = ['ti-clock', \Carbon\Carbon::parse($r['meta']['fecha_compromiso'])->format('d/m/Y')];
                  }
                  if ($r['tipo'] === 'articulo' && !empty($r['meta']['pagina'])) {
                      $datos[] = ['ti-book-2', 'Pág. ' . $r['meta']['pagina']];
                  }
                @endphp
                @if($datos)
                  <div class="buscar-resultado-datos">
                    @foreach($datos as [$icono, $texto])
                      <span><i class="ti {{ $icono }}"></i>{{ $texto }}</span>
                    @endforeach
                  </div>
                @endif
              </div>
            </a>

            {{-- Pie de acciones. Va FUERA del <a> principal (que abre el modal de
                 detalle), porque un enlace no puede contener otro enlace. --}}
            <div class="buscar-resultado-pie">
              @if($r['tipo'] === 'articulo' && !empty($r['meta']['pdf_url']))
                <a href="{{ $r['meta']['pdf_url'] }}"
                  class="buscar-abrir-pdf"
                  data-pdf-url="{{ $r['meta']['pdf_url'] }}"
                  data-pdf-titulo="{{ $r['subtitulo'] ?? 'Documento oficial' }}">
                  <i class="ti ti-file-type-pdf"></i>
                  Abrir PDF{{ !empty($r['meta']['pagina']) ? ' · pág. ' . $r['meta']['pagina'] : '' }}
                </a>
              @endif

              @if($busquedaLogId)
                <div class="buscar-feedback" id="fb-{{ $loop->index }}">
                  <span class="buscar-feedback-label">¿Te sirvió?</span>
                  <button type="button" class="buscar-fb-btn buscar-fb-si"
                    onclick="enviarFeedback({{ $loop->index }}, '{{ $r['tipo'] }}', {{ $resultadoId }}, {{ json_encode($r['titulo']) }}, true)">
                    Sí
                  </button>
                  <button type="button" class="buscar-fb-btn buscar-fb-no"
                    onclick="enviarFeedback({{ $loop->index }}, '{{ $r['tipo'] }}', {{ $resultadoId }}, {{ json_encode($r['titulo']) }}, false)">
                    No
                  </button>
                </div>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  @else
    {{-- Estado inicial: sugerencias de qué buscar --}}
    <div class="buscar-sugerencias">
      <p><strong>¿Qué puedes buscar?</strong></p>
      <div class="buscar-sugerencias-grid">
        <div class="buscar-sugerencia">
          <i class="ti ti-book-2"></i>
          <strong>Artículos de regulaciones</strong>
          <small>Busca dentro del texto de los artículos, fracciones e incisos de todas las regulaciones del catálogo.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-scale"></i>
          <strong>Regulaciones</strong>
          <small>Encuentra regulaciones por nombre, materia, objetivo o palabras clave.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-file-text"></i>
          <strong>Trámites y servicios</strong>
          <small>Busca trámites y servicios por nombre, objetivo o población a la que van dirigidos.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-file-check"></i>
          <strong>Requisitos</strong>
          <small>Encuentra en qué trámites se pide un documento específico, con tiempo y costo estimado.</small>
        </div>
        <div class="buscar-sugerencia">
          <i class="ti ti-gavel"></i>
          <strong>Fundamentos jurídicos</strong>
          <small>Descubre qué ley, reglamento o artículo fundamenta cada trámite o servicio.</small>
        </div>
      </div>
    </div>
  @endif

  </div>{{-- /buscar-principal --}}
</div>

{{--
  Modal de lectura del articulado.

  Se abre al hacer clic en un resultado de búsqueda tipo "Articulado".
  El contenido se pide en JSON a /buscar/articulo/{nodo} (ver
  BuscadorController::obtenerArticulo) y se arma con JavaScript: el mismo
  patrón que ya usa el proyecto en otros lugares (por ejemplo, la
  previsualización de homoclave en el wizard de trámites).

  Reutiliza las clases .modal-backdrop / .modal / .modal-head / .modal-body
  / .modal-actions de 07-modals.css, las mismas que usa el modal de
  observaciones, con la variante .modal-articulado (definida en
  15-buscador.css) que lo hace más ancho, porque un resultado legal necesita
  más espacio de lectura que un formulario corto.
--}}
<div class="modal-backdrop" id="modalDetalle">
  <div class="modal modal-articulado">
    <div class="modal-head">
      <div>
        <h3 id="detalleTitulo">Cargando…</h3>
        <p class="modal-ref" id="detalleRef"></p>
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar"
        onclick="document.getElementById('modalDetalle').classList.remove('open')"></button>
    </div>

    <div class="modal-body" id="detalleCuerpo">
      <p style="text-align:center;color:var(--muted)">Cargando…</p>
    </div>

    <div class="modal-actions">
      <a href="#" id="detalleVerCompleto" class="btn btn-outline">Ver completo</a>
      <button type="button" class="btn"
        onclick="document.getElementById('modalDetalle').classList.remove('open')">Cerrar</button>
    </div>
  </div>
</div>

{{--
  Modal para ver el PDF ORIGINAL saltando a la página del artículo.
  El iframe carga la ruta preview con #page=N; el visor nativo del navegador
  renderiza el PDF y salta a esa página. Reutiliza las clases de modal de la app.
--}}
<div class="modal-backdrop" id="modalPdf">
  <div class="modal modal-pdf">
    {{-- Esta cabecera es el asa: se arrastra desde aquí para mover la ventana. --}}
    <div class="modal-head modal-pdf-asa" id="pdfAsa">
      <div>
        <h3 id="pdfTitulo">Documento oficial</h3>
        <p class="modal-ref">PDF original — arrastra esta barra para mover la ventana</p>
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar" onclick="cerrarModalPdf()"></button>
    </div>

    <div class="modal-body modal-pdf-body">
      <iframe id="pdfVisor" title="PDF de la regulación"></iframe>
    </div>

    <div class="modal-actions">
      <a href="#" id="pdfAbrirPestana" target="_blank" rel="noopener" class="btn btn-outline">Abrir en pestaña</a>
      <button type="button" class="btn" onclick="cerrarModalPdf()">Cerrar</button>
    </div>
  </div>
</div>

<script>
  // ── Modal del PDF original (salta a la página del artículo) ───────────
  function abrirModalPdf(url, titulo) {
    var modal  = document.getElementById('modalPdf');
    var visor  = document.getElementById('pdfVisor');
    var tit    = document.getElementById('pdfTitulo');
    var enlace = document.getElementById('pdfAbrirPestana');

    tit.textContent = titulo || 'Documento oficial';
    enlace.href = url;
    visor.src = url; // el #page=N de la url hace que el visor salte a esa página
    if (window.reiniciarPosicionPdf) window.reiniciarPosicionPdf();
    modal.classList.add('open');
  }

  function cerrarModalPdf() {
    document.getElementById('modalPdf').classList.remove('open');
    // Liberar el PDF para que no siga cargado en segundo plano.
    document.getElementById('pdfVisor').src = 'about:blank';
  }

  // ── Mover la ventana del PDF ──────────────────────────────────────────
  // Se arrastra desde la barra del título. Útil para comparar el documento con
  // los resultados que quedan detrás sin tener que cerrarlo.
  (function () {
    var ventana = document.querySelector('#modalPdf .modal-pdf');
    var asa     = document.getElementById('pdfAsa');
    if (!ventana || !asa) return;

    var arrastrando = false, inicioX = 0, inicioY = 0, x = 0, y = 0;

    asa.addEventListener('mousedown', function (e) {
      // El botón de cerrar no debe iniciar un arrastre.
      if (e.target.closest('.modal-close')) return;

      arrastrando = true;
      inicioX = e.clientX - x;
      inicioY = e.clientY - y;

      // Mientras se arrastra, el visor deja de capturar el mouse: si no, al pasar
      // sobre el PDF el puntero se "pierde" dentro del iframe y el arrastre se corta.
      document.getElementById('pdfVisor').style.pointerEvents = 'none';
      asa.classList.add('arrastrando');
      e.preventDefault();
    });

    document.addEventListener('mousemove', function (e) {
      if (!arrastrando) return;
      x = e.clientX - inicioX;
      y = e.clientY - inicioY;
      ventana.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
    });

    document.addEventListener('mouseup', function () {
      if (!arrastrando) return;
      arrastrando = false;
      document.getElementById('pdfVisor').style.pointerEvents = '';
      asa.classList.remove('arrastrando');
    });

    // Al abrirlo de nuevo vuelve al centro: si se quedó movido, alguien podría
    // abrirlo y no encontrarlo en pantalla.
    window.reiniciarPosicionPdf = function () {
      x = 0; y = 0;
      ventana.style.transform = '';
    };
  })();

  // Clic en "Abrir PDF": abre el modal. Se respeta Ctrl/Cmd/clic-medio para
  // abrir en pestaña nueva (el href sigue ahí como respaldo).
  document.addEventListener('click', function (e) {
    var lnk = e.target.closest('.buscar-abrir-pdf');
    if (!lnk) return;
    if (e.metaKey || e.ctrlKey || e.button === 1) return;
    e.preventDefault();
    abrirModalPdf(lnk.getAttribute('data-pdf-url'), lnk.getAttribute('data-pdf-titulo'));
  });

  // ── Datos para el selector de regulaciones ────────────────────────────
  var REGS = @json($regulaciones);
  var IDS_PREVIOS = @json($regulacionIds ?? []);

  // ── Selector tipo chip ────────────────────────────────────────────────
  (function () {
    var input    = document.getElementById('buscarRegInput');
    var dropdown = document.getElementById('buscarRegDropdown');
    var chipsBox = document.getElementById('buscarChips');
    if (!input) return;

    // IDs seleccionados (inicia con los que venían en la URL)
    var seleccionados = IDS_PREVIOS.map(function (id) { return parseInt(id); });

    function regPorId(id) {
      return REGS.find(function (r) { return r.id === id; });
    }

    // Pinta los chips y los hidden inputs dentro del form
    function pintarChips() {
      if (!seleccionados.length) {
        chipsBox.innerHTML = '';
        chipsBox.style.display = 'none';
        return;
      }
      chipsBox.style.display = '';
      chipsBox.innerHTML = seleccionados.map(function (id) {
        var reg = regPorId(id);
        var nombre = reg ? reg.nombre : 'Regulación #' + id;
        var tipo = reg && reg.tipo ? '<span class="buscar-chip-tipo">' + reg.tipo + '</span>' : '';
        return '<span class="buscar-chip">'
          + '<i class="ti ti-scale"></i> '
          + nombre + ' ' + tipo
          + '<button type="button" class="buscar-chip-x" data-id="' + id + '">×</button>'
          + '</span>'
          + '<input type="hidden" name="regulacion_id[]" value="' + id + '">';
      }).join('');

      chipsBox.querySelectorAll('.buscar-chip-x').forEach(function (btn) {
        btn.addEventListener('click', function () {
          seleccionados = seleccionados.filter(function (id) {
            return id !== parseInt(btn.getAttribute('data-id'));
          });
          pintarChips();
        });
      });
    }

    // Muestra el dropdown con las regulaciones que coinciden
    function mostrar() {
      var q = input.value.trim().toLowerCase();
      var lista = REGS.filter(function (r) {
        // Excluir las ya seleccionadas
        if (seleccionados.indexOf(r.id) >= 0) return false;
        if (!q) return true;
        return (r.nombre || '').toLowerCase().indexOf(q) >= 0;
      }).slice(0, 10);

      if (!lista.length) {
        dropdown.innerHTML = q
          ? '<div class="buscar-reg-item buscar-reg-vacio">Sin coincidencias</div>'
          : '';
        return;
      }

      dropdown.innerHTML = lista.map(function (r) {
        var tipo = r.tipo ? '<span class="buscar-reg-tipo">' + r.tipo + '</span>' : '';
        return '<div class="buscar-reg-item" data-id="' + r.id + '">'
          + '<span>' + r.nombre + '</span>' + tipo
          + '</div>';
      }).join('');

      dropdown.querySelectorAll('.buscar-reg-item[data-id]').forEach(function (el) {
        el.addEventListener('mousedown', function (e) {
          e.preventDefault();
          seleccionados.push(parseInt(el.getAttribute('data-id')));
          input.value = '';
          dropdown.innerHTML = '';
          pintarChips();
          input.focus();
        });
      });
    }

    input.addEventListener('focus', mostrar);
    input.addEventListener('input', mostrar);
    input.addEventListener('blur', function () {
      setTimeout(function () { dropdown.innerHTML = ''; }, 150);
    });

    // Pintar chips iniciales (regulaciones que venían en la URL)
    pintarChips();
  })();

  // ── Toggle de tipos de fuente ───────────────────────────────────────
  (function () {
    document.querySelectorAll('.buscar-tipo-toggle').forEach(function (label) {
      label.addEventListener('click', function () {
        var cb = label.querySelector('input[type="checkbox"]');
        // El click ya toggled el checkbox; solo actualizamos la clase.
        if (cb.checked) {
          label.classList.add('activo');
        } else {
          label.classList.remove('activo');
        }
      });
    });
  })();

  // ── Variables globales ──────────────────────────────────────────────
  var BUSQUEDA_LOG_ID = @json($busquedaLogId);
  var CONSULTA_ACTUAL = @json($consulta);
  var CSRF_TOKEN      = @json(csrf_token());

  // Escapa texto antes de insertarlo como innerHTML
  function esc(texto) {
    var div = document.createElement('div');
    div.textContent = texto || '';
    return div.innerHTML;
  }

  // ── Bitácora: clic y feedback ───────────────────────────────────────

  // Registrar clic en resultado (fire-and-forget)
  document.querySelectorAll('.buscar-resultado[data-tipo]').forEach(function (el) {
    el.addEventListener('click', function () {
      if (!BUSQUEDA_LOG_ID) return;
      fetch('/buscar/clic', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({
          log_id: BUSQUEDA_LOG_ID,
          tipo:   el.getAttribute('data-tipo'),
          id:     parseInt(el.getAttribute('data-id'))
        })
      }).catch(function () {});
    });
  });

  // Registrar feedback
  function enviarFeedback(idx, tipo, id, titulo, util) {
    if (!BUSQUEDA_LOG_ID) return;
    var card = document.getElementById('fb-' + idx);
    if (!card) return;

    // Cambiar visual INMEDIATAMENTE (sin esperar al servidor)
    card.innerHTML = '<span class="buscar-feedback-gracias">'
      + (util ? '👍 Útil' : '👎 No útil')
      + '</span>';

    // Enviar al servidor (fire-and-forget)
    fetch('/buscar/feedback', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
      body: JSON.stringify({
        log_id: BUSQUEDA_LOG_ID, consulta: CONSULTA_ACTUAL,
        tipo: tipo, id: id, titulo: titulo, util: util
      })
    }).catch(function () {});
  }

  // ── Modal de detalle (genérico para todos los tipos) ──────────────

  function abrirModalDetalle(tipo, id, urlCompleta) {
    var modal   = document.getElementById('modalDetalle');
    var cuerpo  = document.getElementById('detalleCuerpo');
    var titulo  = document.getElementById('detalleTitulo');
    var ref     = document.getElementById('detalleRef');
    var btnVer  = document.getElementById('detalleVerCompleto');

    titulo.textContent = 'Cargando…';
    ref.textContent = '';
    cuerpo.innerHTML = '<p style="text-align:center;color:var(--muted)">Cargando…</p>';
    btnVer.href = urlCompleta;
    modal.classList.add('open');

    fetch('/buscar/detalle/' + tipo + '/' + id, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) {
        titulo.textContent = d.titulo || 'Detalle';
        ref.textContent = d.subtitulo || '';
        if (d.url) btnVer.href = d.url;

        var html = '';

        // Campos principales como párrafos con label
        (d.campos || []).forEach(function (campo) {
          html += '<div class="detalle-campo">';
          html += '<span class="detalle-label">' + esc(campo.label) + '</span>';
          html += '<p class="articulo-parrafo">' + esc(campo.valor) + '</p>';
          html += '</div>';
        });

        // Hijos (fracciones de artículos, requisitos de trámites, etc.)
        (d.hijos || []).forEach(function (hijo) {
          html += '<div class="articulo-hijo">';
          html += '<strong>' + esc(hijo.tipo) + (hijo.numero ? ' ' + esc(hijo.numero) : '') + '</strong>';
          html += '<p class="articulo-parrafo">' + esc(hijo.texto || '') + '</p>';
          html += '</div>';
        });

        // Tags
        if (d.tags && d.tags.length) {
          html += '<div style="margin-top:12px">';
          d.tags.forEach(function (tag) {
            html += '<span class="buscar-tag">' + esc(tag) + '</span>';
          });
          html += '</div>';
        }

        cuerpo.innerHTML = html || '<p style="color:var(--muted)">Sin contenido disponible.</p>';
      })
      .catch(function () {
        titulo.textContent = 'No se pudo cargar el contenido';
        cuerpo.innerHTML = '<p style="color:var(--chip-red)">Ocurrió un error. Puede abrir el enlace directamente.</p>';
      });

    return false;
  }
  // ── Toggle de tipos de fuente (clickeable ANTES de buscar) ────────
  (function () {
    var container = document.getElementById('buscarTiposContainer');
    if (!container) return;

    container.addEventListener('click', function (e) {
      var btn = e.target.closest('.buscar-tipo-toggle');
      if (!btn) return;

      btn.classList.toggle('activo');

      // Reconstruir hidden inputs
      var hiddenBox = document.getElementById('tiposHiddenContainer');
      hiddenBox.innerHTML = '';

      container.querySelectorAll('.buscar-tipo-toggle.activo').forEach(function (el) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'tipos[]';
        input.value = el.getAttribute('data-tipo');
        hiddenBox.appendChild(input);
      });
    });
  })();

</script>
@endsection
