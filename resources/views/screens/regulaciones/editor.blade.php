@extends('layouts.app')
@section('title', 'Editor — ' . $regulacion->nombre)

@section('content')
<style>
  /* Editor jerárquico de regulaciones. Reutiliza tokens del proyecto
     (--primary, --muted, --border, --radius). */
  .editor-arbol, .nodo-hijos { list-style:none; margin:0; padding:0; }
  .nodo-hijos { margin-left:22px; border-left:1px dashed var(--border); padding-left:10px; }
  .nodo { margin:3px 0; }
  .nodo-fila {
    display:flex; align-items:flex-start; gap:8px;
    padding:6px 8px; border:1px solid var(--border); border-radius:var(--radius);
    background:#fff;
  }
  .nodo-fila:hover { border-color:var(--primary); }
  .nodo.arrastrando > .nodo-fila { opacity:.4; }
  .nodo.drop-target > .nodo-fila { border-color:var(--primary); background:var(--surface-tint); }
  .nodo-grip { cursor:grab; color:var(--muted-light); font-size:14px; user-select:none; line-height:1.55; flex-shrink:0; }
  .nodo-tipo {
    font-size:14px; text-transform:uppercase; letter-spacing:.03em;
    color:var(--primary); font-weight:600; white-space:nowrap;
    line-height:1.55; flex-shrink:0;
  }
  .nodo-numero { font-size:14px; font-weight:600; color:var(--text); white-space:nowrap; line-height:1.55; flex-shrink:0; }
  .nodo-texto { flex:1; min-width:0; font-size:14px; color:var(--text); overflow-wrap:anywhere; line-height:1.55; }
  .nodo-nota { font-size:13px; color:var(--muted); font-style:italic; white-space:nowrap; }
  .nodo-acciones { display:flex; gap:4px; flex-shrink:0; }
  .btn-nodo {
    font-size:12px; padding:3px 8px; border:1px solid var(--border);
    border-radius:var(--radius-sm); background:#fff; color:var(--muted); cursor:pointer;
  }
  .btn-nodo:hover { border-color:var(--primary); color:var(--primary); }
  .btn-nodo-danger:hover { border-color:#b42318; color:#b42318; }
  .nodo-derogado > .nodo-fila { background:#fafafa; }
  .nodo-derogado > .nodo-fila .nodo-numero,
  .nodo-derogado > .nodo-fila .nodo-texto { text-decoration:line-through; color:var(--muted-light); }

  /* Panel de edición/creación inline */
  .editor-panel {
    margin:10px 0 6px; padding:14px;
    border:1px solid var(--primary); border-radius:var(--radius); background:var(--surface-tint);
  }
  .editor-panel h4 { margin:0 0 10px; font-size:14px; color:var(--primary); }
  .editor-panel .campo { margin-bottom:10px; }
  .editor-panel label { display:block; font-size:12px; color:var(--muted); margin-bottom:4px; }
  .editor-panel input[type=text], .editor-panel textarea, .editor-panel select {
    width:100%; padding:7px 9px; border:1px solid var(--border); border-radius:var(--radius-sm);
  }
  .editor-panel textarea { min-height:160px; resize:vertical; line-height:1.5; }
  .editor-panel-acciones { display:flex; gap:8px; justify-content:flex-end; }
  .editor-ayuda { font-size:12px; color:var(--muted); margin:6px 0 14px; }

  /* Layout de dos columnas: índice lateral + contenido. */
  .editor-layout { display:flex; gap:18px; align-items:flex-start; }
  .editor-indice {
    flex:0 0 240px; position:sticky; top:12px; max-height:calc(100vh - 40px);
    overflow:auto; border:1px solid var(--border); border-radius:var(--radius);
    background:#fff; padding:10px;
  }
  .editor-indice h3 { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
  .editor-indice-grupo { font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:var(--muted-light); margin:10px 0 4px; }
  .editor-indice-item {
    display:block; padding:6px 8px; border-radius:var(--radius-sm); margin-bottom:2px;
    font-size:13px; color:var(--text); text-decoration:none; cursor:pointer;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .editor-indice-item:hover { background:var(--surface-tint); }
  .editor-indice-item.activo { background:var(--primary); color:#fff; font-weight:600; }
  .editor-contenido { flex:1; min-width:0; }

  /* Rótulo del capítulo que se está editando. */
  .editor-activa-rotulo { font-size:14px; font-weight:600; color:var(--primary); margin:6px 0 10px; }
</style>

<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editor de articulado</h2>
      <p class="nowrap">{{ $regulacion->nombre }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('regulaciones.show', $regulacion) }}" class="btn btn-outline">Volver</a>
      <a href="{{ route('regulaciones.papelera', $regulacion) }}" class="btn btn-outline">Papelera</a>
      {{-- #11: Re-estructurar vive aquí porque descarta los ajustes manuales
           del editor; mejor que el usuario lo vea junto al trabajo que va a
           perder, no a un clic desde la pantalla de lectura. --}}
      <form method="POST" action="{{ route('regulaciones.estructurar', $regulacion) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline"
          onclick="return confirmarAccion(this, 'Se releerá el archivo original y se volverá a construir el articulado desde cero (puede tardar unos segundos). Atención: esto descarta los ajustes manuales que hayas hecho en el editor.', '¿Re-estructurar el articulado?')">
          Re-estructurar
        </button>
      </form>
      <button type="button" class="btn" data-accion="agregar-raiz"
        data-tipos="{{ \App\Models\RegulacionNodo::TIPO_TITULO }}">
        Agregar título
      </button>
    </div>
  </div>

  <p class="editor-ayuda">
    <strong>Agregar título</strong> crea un nuevo bloque en la raíz. Para agregar capítulos, secciones,
    artículos o fracciones, usa el botón <strong>«+ dentro»</strong> del elemento padre donde quieras colocarlo.
    Arrastra los elementos para reordenarlos, o doble clic para editar.
  </p>

  @if($unidades->isEmpty())
    <div class="card"><p class="text-muted-sm">Esta regulación aún no tiene elementos. Usa «Agregar título» para empezar.</p></div>
  @else
    @php
      $activa = $unidades[$indiceActivo];
    @endphp

    <div class="editor-layout">

      {{-- Índice lateral: salta a cualquier capítulo (recarga la página). --}}
      <nav class="editor-indice">
        <h3>Capítulos</h3>
        @php $grupoPrevio = null; @endphp
        @foreach($unidades as $i => $u)
          @if($u['grupo'] !== $grupoPrevio)
            <div class="editor-indice-grupo">{{ $u['grupo'] }}</div>
            @php $grupoPrevio = $u['grupo']; @endphp
          @endif
          <a class="editor-indice-item {{ $i === $indiceActivo ? 'activo' : '' }}"
             href="{{ route('regulaciones.editor', ['regulacion' => $regulacion, 'unidad' => $u['id']]) }}">
            {{ $u['etiqueta'] }}
          </a>
        @endforeach
      </nav>

      <div class="editor-contenido">
        {{-- Solo el capítulo seleccionado: editable --}}
        <div class="editor-activa-rotulo">Editando: {{ $activa['grupo'] }} · {{ $activa['etiqueta'] }}</div>
        <ul class="editor-arbol" id="editorArbol">
          @include('screens.regulaciones.partials.nodo-arbol', [
            'nodo' => $activa['nodo'],
            'hijosPorPadre' => $hijosPorPadre,
            'regulacion' => $regulacion,
          ])
        </ul>
      </div>
    </div>
  @endif

  {{-- Modal de edición/creación de nodos --}}
  <div class="confirm-modal-backdrop" id="editorPanel">
    <div class="confirm-modal" id="editorPanelInner" style="max-width:480px">
      <h3 id="editorPanelTitulo">Agregar elemento</h3>
      <p id="editorPanelDesc"></p>

      <div id="campoTipo">
        <label for="panelTipo" style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">Tipo de elemento</label>
        <select id="panelTipo" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px"></select>
        <p id="panelJerarquia" style="font-size:12px;color:var(--muted);margin:4px 0 0"></p>
      </div>

      <div id="campoNumero">
        <p id="numAutoMsg" style="font-size:13px;color:var(--muted);margin:0">
          El número se asigna automáticamente.
        </p>
        <div id="numEditWrap" style="display:none">
          <label for="panelNumero" style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">Número</label>
          <input type="text" id="panelNumero" maxlength="60"
                 style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
        </div>
      </div>

      <div>
        <label for="panelTexto" style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">Texto del elemento</label>
        <textarea id="panelTexto" placeholder="Contenido del elemento"
                  style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;min-height:100px;max-height:60vh;line-height:1.6;resize:vertical;overflow-y:auto"></textarea>
      </div>

      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" id="panelCancelar">Cancelar</button>
        <button type="button" class="btn" id="panelGuardar">Guardar</button>
      </div>
    </div>
  </div>

  {{-- Formularios ocultos para acciones POST/PUT/DELETE que el JS dispara --}}
  <form method="POST" id="formStore" action="{{ route('regulaciones.nodos.store', $regulacion) }}" class="hidden">
    @csrf
    <input type="hidden" name="parent_id" id="storeParent">
    <input type="hidden" name="tipo" id="storeTipo">
    <input type="hidden" name="numero" id="storeNumero">
    <input type="hidden" name="texto" id="storeTexto">
  </form>

  <form method="POST" id="formUpdate" class="hidden">
    @csrf
    @method('PUT')
    <input type="hidden" name="numero" id="updateNumero">
    <input type="hidden" name="texto" id="updateTexto">
  </form>

  <form method="POST" id="formMover" class="hidden">
    @csrf
    @method('PUT')
    <input type="hidden" name="parent_id" id="moverParent">
    <input type="hidden" name="orden" id="moverOrden">
  </form>

  <form method="POST" id="formDerogar" class="hidden">
    @csrf
    <input type="hidden" name="nota" id="derogarNota">
  </form>

  <form method="POST" id="formEliminar" class="hidden">
    @csrf
    @method('DELETE')
  </form>

  {{-- Modal de DEROGACIÓN: recoge la nota antes de enviar el formulario. --}}
  <div class="confirm-modal-backdrop" id="modalDerogar">
    <div class="confirm-modal" style="max-width:480px">
      <h3>Derogar elemento</h3>
      <p>El elemento permanecerá en su lugar, tachado. Puedes restaurarlo en cualquier momento.</p>
      <div>
        <label for="modalDerogarNota" style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">
          Nota de la derogación <span style="font-weight:400">(opcional)</span>
        </label>
        <input type="text" id="modalDerogarNota" maxlength="255"
               placeholder='Ej.: "Reforma DOF 12/03/2024"'
               style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
      </div>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" id="modalDerogarCancelar">Cancelar</button>
        <button type="button" class="btn" id="modalDerogarConfirmar">Derogar</button>
      </div>
    </div>
  </div>

  {{-- Modal de ELIMINACIÓN: avisa el impacto y exige escribir ELIMINAR. --}}
  <div class="confirm-modal-backdrop" id="modalEliminar">
    <div class="confirm-modal" style="max-width:480px">
      <h3 id="modalEliminarTitulo">Eliminar elemento</h3>
      <p id="modalEliminarTexto"></p>
      <div>
        <label for="modalEliminarConfirmInput"
               style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">
          Para confirmar, escribe <strong>ELIMINAR</strong>
        </label>
        <input type="text" id="modalEliminarConfirmInput" placeholder="ELIMINAR"
               style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
      </div>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" id="modalEliminarCancelar">Cancelar</button>
        <button type="button" class="btn danger" id="modalEliminarConfirmar" disabled>Eliminar</button>
      </div>
    </div>
  </div>

</div>

@php
  $etiquetasTipo = \App\Models\RegulacionNodo::ETIQUETAS_TIPO;
  // Mapa de anidamiento con etiquetas legibles para que JS muestre la jerarquía.
  $anidamientoLegible = [];
  foreach (\App\Models\RegulacionNodo::ANIDAMIENTO as $padre => $hijos) {
      if (!empty($hijos)) {
          $anidamientoLegible[$padre] = array_map(
              fn($t) => $etiquetasTipo[$t] ?? $t,
              $hijos,
          );
      }
  }
@endphp
<script>
  window.EDITOR_REG = {
    etiquetasTipo: @json($etiquetasTipo),
    anidamiento: @json($anidamientoLegible),
    rutaMoverBase: "{{ url('regulaciones/nodos') }}",
  };
</script>
@endsection

@push('scripts')
<script src="{{ asset('js/regulacion-editor.js') }}?v={{ filemtime(public_path('js/regulacion-editor.js')) }}"></script>
@endpush