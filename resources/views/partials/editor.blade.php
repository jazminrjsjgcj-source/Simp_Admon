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
      <button type="button" class="btn" data-accion="agregar-raiz"
        data-tipos="{{ implode(',', \App\Models\RegulacionNodo::tiposEnRaiz()) }}">
        Agregar elemento
      </button>
    </div>
  </div>

  <p class="editor-ayuda">
    Edita un capítulo a la vez: elígelo en el índice de la izquierda. Arrastra los
    elementos para reordenarlos, o doble clic para editar. Los elementos derogados
    permanecen tachados en su lugar.
  </p>

  @if($unidades->isEmpty())
    <div class="card"><p class="text-muted-sm">Esta regulación aún no tiene elementos. Usa «Agregar elemento» para empezar.</p></div>
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

  {{-- Panel de edición/creación (oculto hasta que se active) --}}
  <div class="editor-panel hidden" id="editorPanel">
    <h4 id="editorPanelTitulo">Editar elemento</h4>

    <div class="campo" id="campoTipo">
      <label for="panelTipo">Tipo</label>
      <select id="panelTipo"></select>
    </div>
    <div class="campo">
      <label for="panelNumero">Número o etiqueta <span class="text-muted-sm">(sugerido, editable)</span></label>
      <input type="text" id="panelNumero" maxlength="60" placeholder="Ej. 1, III, a">
    </div>
    <div class="campo">
      <label for="panelTexto">Texto</label>
      <textarea id="panelTexto" placeholder="Contenido del elemento"></textarea>
    </div>

    <div class="editor-panel-acciones">
      <button type="button" class="btn btn-outline" id="panelCancelar">Cancelar</button>
      <button type="button" class="btn" id="panelGuardar">Guardar</button>
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

</div>

@php
  // Etiquetas legibles de tipo para que el JS las muestre en el selector.
  $etiquetasTipo = \App\Models\RegulacionNodo::ETIQUETAS_TIPO;
@endphp
<script>
  window.EDITOR_REG = {
    etiquetasTipo: @json($etiquetasTipo),
    rutaMoverBase: "{{ url('regulaciones/nodos') }}",
  };
</script>
@endsection

@push('scripts')
<script src="{{ asset('js/regulacion-editor.js') }}?v={{ filemtime(public_path('js/regulacion-editor.js')) }}"></script>
@endpush
