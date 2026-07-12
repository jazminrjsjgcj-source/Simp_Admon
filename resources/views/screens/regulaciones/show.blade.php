@extends('layouts.app')
@section('title', $regulacion->nombre)

@section('content')
@php
  use App\Models\Regulacion;

  // El estado de la conversión gobierna casi toda esta pantalla: qué botones se ven, qué
  // avisos se muestran, y si la página tiene que refrescarse sola.
  //
  // Antes se comparaba con cadenas a mano ('listo', 'error', 'pendiente') repartidas por el
  // blade. Ahora se usan las constantes del modelo: un typo en una constante no compila; un
  // typo en una cadena se guarda tan tranquilo y rompe la pantalla en silencio.
  $conv        = $regulacion->conversion_estatus;
  $convirtiendo = $conv === Regulacion::CONVERSION_PROCESANDO;
  $convLista   = $conv === Regulacion::CONVERSION_LISTO;
  $convError   = $conv === Regulacion::CONVERSION_ERROR;
@endphp
<style>
  .indice-nav { display:flex; flex-direction:column; gap:2px; }
  .indice-nav-item { font-size:13px; padding:4px 8px; border-radius:4px; }
  .indice-nav-nivel-1 { font-weight:700; color:var(--text,#111); padding-left:0; }
  .indice-nav-nivel-2 { color:var(--text,#333); padding-left:16px; }
  .indice-nav-nivel-3 { color:var(--muted,#667085); padding-left:32px; }
  .indice-nav-nivel-4 { color:var(--muted,#667085); padding-left:48px; font-size:12px; }

  /* Lectura del articulado estructurado (Capa 5) */
  .lec-arbol, .lec-hijos { list-style:none; margin:0; padding:0; }
  .lec-hijos { margin-left:18px; border-left:1px solid var(--border); padding-left:14px; }
  .lec-nodo { margin:6px 0; }
  .lec-summary { padding:4px 0; }
  .lec-encabezado { font-weight:600; color:var(--primary); margin-right:6px; }
  .lec-titulo { color:var(--text); }
  .lec-hoja { padding:3px 0; line-height:1.6; }
  .lec-texto { color:var(--text); }
  .lec-derogado .lec-encabezado, .lec-derogado .lec-texto, .lec-derogado .lec-titulo {
    text-decoration:line-through; color:var(--muted-light);
  }
  .lec-tag-derogado {
    display:inline-block; font-size:11px; color:var(--muted); font-style:italic;
    margin-left:8px; text-decoration:none;
  }
</style>
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $regulacion->nombre }}</h2>
      <p class="nowrap">
        {{ $regulacion->tipo ?? 'Regulación' }}
        @if($regulacion->dependencia) — {{ $regulacion->dependencia->nombre }} @endif
      </p>
    </div>
    <div class="head-actions">
      <a href="{{ route('regulaciones.index') }}" class="btn btn-outline">Volver</a>
      @if(auth()->user()->puedeEditarRegulacion($regulacion))
        {{-- Estructurar: construye el árbol de artículos para el editor
             jerárquico. Solo si ya se convirtió el contenido y aún no se ha
             estructurado. --}}
        {{-- Mientras se convierte, este botón NO se muestra: estructurar dispara una
             reconversión, y encolar otra sobre una que ya está corriendo no tiene sentido.
             En su lugar se enseña que hay trabajo en marcha. --}}
        @if($convirtiendo)
          <button type="button" class="btn btn-outline" disabled>Convirtiendo…</button>
        @elseif($convLista && !$regulacion->estructurada)
          <form method="POST" action="{{ route('regulaciones.estructurar', $regulacion) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline"
              onclick="return confirmarAccion(this, 'Se releerá el archivo original y se construirá la estructura de artículos desde cero. El trabajo ocurre en segundo plano: puedes seguir usando el sistema mientras tanto.', '¿Estructurar el articulado?')">
              Estructurar articulado
            </button>
          </form>
        @endif
        {{-- Si ya está estructurada, acceso directo al editor jerárquico.
             Re-estructurar (acción destructiva) se movió al header del editor:
             vive con los datos que afecta para evitar pulsaciones accidentales
             desde la pantalla de lectura. --}}
        @if($regulacion->estructurada)
          <a href="{{ route('regulaciones.editor', $regulacion) }}" class="btn">Abrir editor</a>
        @endif
      @endif
    </div>
  </div>

  <div class="detalle-con-timeline">
    <div class="detalle-main">

  {{-- Metadatos --}}
  <div class="card">
    <div class="panel-head">
      <div><h3>Datos generales</h3></div>
      @if(auth()->user()->puedeEditarRegulacion($regulacion))
        <a href="{{ route('regulaciones.edit', $regulacion) }}" class="btn btn-outline btn-sm">Editar</a>
      @endif
    </div>
    <div class="card-body-padded">
      <div class="wizard-fields">
        <div>
          <span class="label-meta">Estatus</span>
          <strong>{{ ucfirst(str_replace('_', ' ', $regulacion->estatusEfectivo())) }}</strong>
          @if($regulacion->estaVencida() && $regulacion->estatus === \App\Models\Regulacion::ESTATUS_VIGENTE)
            <small class="text-muted-sm">Su fecha de vigencia ({{ $regulacion->fecha_vigencia->format('d/m/Y') }}) ya pasó.</small>
          @endif
        </div>
        <div>
          <span class="label-meta">Conversión</span>
          <strong>{{ ucfirst($regulacion->conversion_estatus) }}</strong>
        </div>
        <div>
          <span class="label-meta">Publicación</span>
          <strong>{{ $regulacion->fecha_publicacion?->format('d/m/Y') ?? '—' }}</strong>
        </div>
        <div>
          <span class="label-meta">Vigente hasta</span>
          <strong>{{ $regulacion->fecha_vigencia?->format('d/m/Y') ?? '—' }}</strong>
        </div>
      </div>

      @if($regulacion->resumen)
        <div class="section-divided">
          <span class="label-meta">Resumen ciudadano</span>
          <p class="text-muted-sm">{{ $regulacion->resumen }}</p>
        </div>
      @endif
    </div>
  </div>

  {{-- Conversión del archivo: vista previa, descargas y estado de conversión.
       Única tarjeta para todo lo relacionado al archivo — no hay tarjeta
       separada de "Archivo de la regulación" (§10 Clean Code: una responsabilidad). --}}
  @if($regulacion->archivo_original)
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Conversión del archivo</h3>
        <p>Vista previa, descargas y estado de la extracción de contenido.</p>
      </div>
      <div class="head-actions">
        @if(auth()->user()->puedeEditarRegulacion($regulacion) && !$convirtiendo)
          <form method="POST" action="{{ route('regulaciones.reintentar', $regulacion) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm"
              onclick="return confirmarAccion(this, 'Se volverá a leer el archivo original y se reemplazará el contenido extraído actual. El trabajo ocurre en segundo plano.', '¿Reconvertir el archivo?')">
              Reintentar conversión
            </button>
          </form>
        @endif
      </div>
    </div>

    {{-- ── ESTADO DE LA CONVERSIÓN ──

         Antes este bloque contemplaba 'error' y 'pendiente'. NO contemplaba 'procesando',
         porque la conversión era síncrona y ese estado no llegaba a verse nunca.

         Ahora la conversión la hace un worker en segundo plano, así que 'procesando' es
         justo lo que el usuario ve SIEMPRE después de subir un archivo. Sin este bloque,
         llegaría a la ficha, no vería nada, y supondría que el sistema no hizo nada. --}}
    @if($convirtiendo)
      <div class="card-body-padded">
        <div class="assist-box" style="border-color:#fbbf24;background:#fffbeb;color:#92400e;display:flex;align-items:center;gap:12px">
          <strong style="font-size:18px">⏳</strong>
          <div>
            <strong>Convirtiendo el archivo…</strong><br>
            Se está extrayendo el texto del documento. Puede tardar desde unos segundos hasta
            un par de minutos, según el tamaño del archivo.
            <span class="label-meta">Esta página se actualizará sola cuando termine. Puedes irte y volver.</span>
          </div>
        </div>
      </div>

      {{-- Refresco automático.

           Cada 5 segundos, no cada uno: un PDF grande tarda minutos, y recargar la página
           sesenta veces por minuto solo carga el servidor sin que el usuario gane nada.

           Se detiene solo, porque cuando la conversión termine el estado ya no será
           'procesando' y este bloque entero desaparecerá del HTML. No hace falta cancelar
           nada: el bucle vive solo mientras la condición de arriba sea cierta. --}}
      <script>
        setTimeout(function () { window.location.reload(); }, 5000);
      </script>

    @elseif($convError)
      <div class="card-body-padded">
        <div class="assist-box" style="border-color:var(--chip-red);background:var(--chip-red-bg)">
          <strong>Error en la conversión:</strong>
          {{ $regulacion->conversion_error ?? 'No se pudo extraer el contenido del archivo.' }}
        </div>
      </div>

    @elseif($conv === Regulacion::CONVERSION_PENDIENTE)
      <div class="card-body-padded">
        <div class="assist-box">
          La conversión está pendiente. Si no arranca sola en unos segundos, use el botón
          «Reintentar conversión».
          <span class="label-meta">
            Si esto se queda así, lo más probable es que el worker de la cola no esté
            corriendo (docker compose up -d).
          </span>
        </div>
      </div>
    @endif

    {{-- Acciones: vista previa y descarga --}}
    <div class="card-body-padded" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
      <button type="button" class="btn btn-sm" id="btnToggleVisor"
        onclick="var f=document.getElementById('visorInline');var ifr=f.querySelector('iframe');f.classList.toggle('hidden');if(ifr.src.indexOf('regulaciones')===-1)ifr.src='{{ route('regulaciones.preview', $regulacion) }}';this.textContent=f.classList.contains('hidden')?'Ver aquí':'Ocultar'">
        Ver aquí
      </button>
      <a href="{{ route('regulaciones.preview', $regulacion) }}" target="_blank" class="btn btn-outline btn-sm">Abrir en pestaña</a>
      <button type="button" class="btn btn-outline btn-sm"
        onclick="document.getElementById('modalDescargarReg').classList.add('open')">
        Descargar
      </button>
    </div>

    {{-- Visor inline (iframe) — empieza oculto, se abre con "Ver aquí" --}}
    <div id="visorInline" class="hidden">
      <iframe style="width:100%;height:75vh;border:none;border-top:1px solid var(--border);display:block"
              src="about:blank"
              title="Vista previa de {{ $regulacion->nombre }}"></iframe>
    </div>
  </div>

  {{-- Modal de descarga: botones dinámicos según el tipo del archivo original
       (PDF vs Word) y el rol del usuario (Markdown solo para admin/revisora).
       Regla de negocio: enlace y jurídico no pueden descargar el Markdown porque
       es un formato técnico/interno, no el documento oficial. --}}
  @php
    $esWord           = in_array($regulacion->extension_original, ['doc', 'docx']);
    $esPdf            = $regulacion->extension_original === 'pdf';
    $tieneConv        = $convLista && $regulacion->archivo_markdown;
    $puedeVerMd       = auth()->user()->veVariasDependencias(); // admin y revisora
    // $tieneLibreOffice viene del controlador (show() lo inyecta). Si LibreOffice
    // no está disponible, el botón Word para PDFs no se muestra para no generar
    // botones que producirían un error al hacer clic.
  @endphp
  <div class="modal-backdrop" id="modalDescargarReg">
    <div class="modal" style="width:min(440px,96vw)">
      <div class="modal-head">
        <div>
          <h3>Descargar regulación</h3>
          <p class="modal-ref">{{ Str::limit($regulacion->nombre, 60) }}</p>
        </div>
        <button type="button" class="modal-close"
          onclick="document.getElementById('modalDescargarReg').classList.remove('open')"></button>
      </div>
      <div class="modal-body">
        <p style="color:var(--muted);font-size:13px;margin:0 0 4px">
          Seleccione el formato en el que desea descargar el archivo:
        </p>

        {{-- Botón 1: siempre visible — descarga el archivo tal como fue subido. --}}
        <a href="{{ route('regulaciones.descargar', $regulacion) }}"
           class="btn" style="width:100%;justify-content:center">
          @if($esWord)
            <i class="ti ti-file-word"></i>
            Descargar Word original ({{ strtoupper($regulacion->extension_original) }})
          @else
            <i class="ti ti-file-type-pdf"></i>
            Descargar PDF original
          @endif
        </a>

        {{-- Botón 2: "Descargar como PDF" — solo aparece si el archivo es Word.
             Si el original ya es PDF, el Botón 1 ya lo descarga. --}}
        @if($esWord && $tieneConv)
          <a href="{{ route('regulaciones.descargar-pdf', $regulacion) }}"
             class="btn btn-outline" style="width:100%;justify-content:center">
            <i class="ti ti-file-type-pdf"></i>
            Descargar como PDF
          </a>
        @endif

        {{-- Botón 3: "Descargar como Word" — solo para PDFs originales cuando
             LibreOffice está disponible. Usa la conversión inversa (PDF → DOCX).
             Si el original ya es Word, el Botón 1 ya lo descarga. --}}
        @if($esPdf && ($tieneLibreOffice ?? false))
          <a href="{{ route('regulaciones.descargar-docx', $regulacion) }}"
             class="btn btn-outline" style="width:100%;justify-content:center">
            <i class="ti ti-file-word"></i>
            Descargar como Word (DOCX)
          </a>
        @endif

        {{-- Botón 4: Markdown — solo para admin y revisora; no para enlace ni jurídico.
             El Markdown es un archivo técnico/interno de extracción de texto,
             no el documento oficial. Solo es útil para quienes revisan la calidad
             de la conversión o alimentan otras herramientas. --}}
        @if($puedeVerMd && $tieneConv)
          <a href="{{ route('regulaciones.descargar-md', $regulacion) }}"
             class="btn btn-outline" style="width:100%;justify-content:center">
            <i class="ti ti-markdown"></i>
            Descargar Markdown (texto extraído)
          </a>
        @endif

        {{-- Si el PDF original es el único formato y el usuario no puede ver Markdown
             ni LibreOffice está disponible, indicar que solo hay una opción. --}}
        @if($esPdf && !$puedeVerMd && !($tieneLibreOffice ?? false))
          <p style="color:var(--muted);font-size:12px;text-align:center;margin:8px 0 0">
            Este es el único formato disponible para descargar.
          </p>
        @endif

        {{-- Si la conversión no está lista, explicar qué opciones faltarán cuando lo esté. --}}
        @if(!$tieneConv && $esWord)
          <div style="text-align:center;padding:8px;color:var(--muted);font-size:13px">
            Las opciones adicionales (PDF generado y Markdown) estarán disponibles
            cuando la conversión esté completa.
          </div>
        @endif
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline btn-sm"
          onclick="document.getElementById('modalDescargarReg').classList.remove('open')">Cerrar</button>
      </div>
    </div>
  </div>
  @endif

  {{-- ── LA ESTRUCTURACIÓN FALLÓ ──

       Va ANTES del articulado, no después: si el usuario tiene que bajar hasta el final para
       enterarse de que su articulado no existe, no se entera.

       Este aviso rompe un silencio muy concreto. Desde que la estructuración ocurre en segundo
       plano, un fallo se comportaba así: el usuario daba a "Estructurar articulado", veía "la
       página se actualizará sola cuando termine", el job fallaba, se escribía una línea en el
       log... y ya.

       La conversión SÍ había ido bien, así que la regulación se veía normal, con su botón de
       "Estructurar" invitando a darle otra vez. Nada indicaba que algo hubiera fallado. El
       usuario recargaba, y recargaba, y suponía que el sistema seguía trabajando.

       Fíjate en que el mensaje NO dice "hubo un error". Dice qué pasó y QUÉ HACER: capturar el
       articulado a mano, o reintentar la conversión, según el caso. Un mensaje que no lleva a
       una acción concreta es casi tan inútil como el silencio — el usuario sabe que algo se
       rompió y sigue sin saber qué hacer. --}}
  @if($regulacion->estructuracion_error)
  <div class="card">
    <div class="card-body-padded">
      <div class="assist-box" style="border-color:var(--chip-red);background:var(--chip-red-bg)">
        <strong>No se pudo construir el articulado.</strong><br>
        {{ $regulacion->estructuracion_error }}
        @if(auth()->user()->puedeEditarRegulacion($regulacion) && $regulacion->estructurada)
          <br><span class="label-meta">
            Puedes capturarlo a mano en el
            <a href="{{ route('regulaciones.editor', $regulacion) }}" style="color:var(--chip-red);text-decoration:underline">editor</a>.
          </span>
        @endif
      </div>
    </div>
  </div>
  @endif

  {{-- Articulado estructurado: empieza cerrado porque puede ser muy largo
       (200+ artículos). El usuario hace clic para expandirlo cuando lo necesita. --}}
  @if($regulacion->estructurada)
    @php
      $nodosLectura = $regulacion->nodos()->orderBy('orden')->get();
      $hijosPorPadre = $nodosLectura->groupBy('parent_id');
      $raicesLectura = $hijosPorPadre[null] ?? collect();
    @endphp
    <div class="card">
      <details>
        <summary class="panel-head" style="cursor:pointer;list-style:none">
          <div>
            <h3>Articulado <small style="font-weight:400;color:var(--muted)">({{ $nodosLectura->count() }} elementos — clic para expandir)</small></h3>
            <p>Estructura completa. Los elementos derogados se muestran tachados.</p>
          </div>
          @if(auth()->user()->puedeEditarRegulacion($regulacion))
            <a href="{{ route('regulaciones.editor', $regulacion) }}" class="btn btn-outline btn-sm" onclick="event.stopPropagation()">Editar articulado</a>
          @endif
        </summary>
        <div class="card-body-padded reg-scroll-contenido">
          @if($raicesLectura->isEmpty())
            <p class="text-muted-sm">El articulado está vacío. Usa el editor para capturarlo.</p>
          @else
            <ul class="lec-arbol">
              @foreach($raicesLectura as $raiz)
                @include('screens.regulaciones.partials.nodo-lectura', [
                  'nodo' => $raiz,
                  'hijosPorPadre' => $hijosPorPadre,
                ])
              @endforeach
            </ul>
          @endif
        </div>
      </details>
    </div>
  @endif

  {{-- Índice (respaldo para regulaciones NO estructuradas): también cerrado. --}}
  @if(!$regulacion->estructurada && $regulacion->tieneIndice())
  <div class="card">
    <details>
      <summary class="panel-head" style="cursor:pointer;list-style:none">
        <div>
          <h3>Índice de la regulación <small style="font-weight:400;color:var(--muted)">(clic para expandir)</small></h3>
          <p>Estructura de capítulos, títulos y artículos.</p>
        </div>
      </summary>
      <div class="card-body-padded reg-scroll-contenido">
        <nav class="indice-nav">
          @foreach($regulacion->indice as $item)
            @php
              $clases = ['indice-nav-item', 'indice-nav-nivel-' . ($item['nivel'] ?? 1)];
            @endphp
            <div class="{{ implode(' ', $clases) }}">
              {{ $item['titulo'] }}
            </div>
          @endforeach
        </nav>
      </div>
    </details>
  </div>
  @endif

    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.timeline', ['tipo' => 'regulacion', 'id' => $regulacion->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

</div>
@endsection
