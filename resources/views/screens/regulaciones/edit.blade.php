@extends('layouts.app')
@section('title', 'Editar Regulación')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar regulación</h2>
      <p class="nowrap">{{ $regulacion->nombre }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('regulaciones.show', $regulacion) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if($errors->any())
    <div class="card-body-padded u-error-box">
      <ul class="error-list">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="detalle-con-timeline">
    <div class="detalle-main">
  <form method="POST" action="{{ route('regulaciones.update', $regulacion) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="card">
      <div class="card-body-padded wizard-fields">

        {{-- Identificación --}}
        <x-field-help label="Nombre oficial de la regulación" :required="true" class="span-2">
          <input required name="nombre" value="{{ old('nombre', $regulacion->nombre) }}">
        </x-field-help>

        <x-field-help label="Tipo de regulación (registro)">
          <select name="tipo">
              <option value="">Seleccione...</option>
              @foreach(\App\Models\TipoRegulacion::activos()->get() as $tr)
                <option value="{{ $tr->nombre }}"
                  {{ old('tipo', $regulacion->tipo) === $tr->nombre ? 'selected' : '' }}>
                  {{ $tr->nombre }}
                </option>
              @endforeach
            </select>
        </x-field-help>

        <x-field-help label="Materia o ámbito de aplicación">
          <select name="materia">
            <option value="">Seleccione...</option>
            @foreach(\App\Models\Regulacion::MATERIAS as $m)
              <option {{ old('materia', $regulacion->materia) === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
        </x-field-help>

        <div class="field">
          <label>Dependencia</label>
          <select name="dependencia_id">
            <option value="">Sin asignar</option>
            @foreach($dependencias as $d)
              <option value="{{ $d->id }}" {{ old('dependencia_id', $regulacion->dependencia_id) == $d->id ? 'selected' : '' }}>
                {{ $d->nombre }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label>Fecha de publicación</label>
          <input type="date" name="fecha_publicacion" value="{{ old('fecha_publicacion', optional($regulacion->fecha_publicacion)->format('Y-m-d')) }}">
        </div>

        <div class="field">
          <label>Fecha de vigencia</label>
          <input type="date" name="fecha_vigencia" value="{{ old('fecha_vigencia', optional($regulacion->fecha_vigencia)->format('Y-m-d')) }}">
        </div>

        <div class="field">
          <label>Estatus *</label>
          <select required name="estatus">
            @foreach(\App\Models\Regulacion::ESTATUS_TODOS as $e)
              <option value="{{ $e }}" {{ old('estatus', $regulacion->estatus) === $e ? 'selected' : '' }}>
                {{ ucfirst(str_replace('_', ' ', $e)) }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- Campos Art. 153 --}}
        <x-field-help label="Objetivo de la regulación" class="span-2">
          <textarea name="objetivo" rows="3">{{ old('objetivo', $regulacion->objetivo) }}</textarea>
        </x-field-help>

        <x-field-help label="Fundamento jurídico de expedición" class="span-2">
          <textarea name="fundamento_juridico" rows="2">{{ old('fundamento_juridico', $regulacion->fundamento_juridico) }}</textarea>
        </x-field-help>

        <x-field-help label="Sector regulado">
          <select name="sector_id">
            <option value="">Seleccione...</option>
            @foreach($sectores as $s)
              <option value="{{ $s->id }}" {{ old('sector_id', $regulacion->sector_id) == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
            @endforeach
          </select>
        </x-field-help>

        <x-field-help label="Palabras clave">
          <input name="palabras_clave" value="{{ old('palabras_clave', $regulacion->palabras_clave) }}" placeholder="licencia, funcionamiento, comercio">
        </x-field-help>

        <x-field-help label="¿Deja sin efectos otra regulación?">
          <select name="deroga_otra" id="derogaSelect">
            <option value="0" {{ old('deroga_otra', $regulacion->deroga_otra ? '1' : '0') !== '1' ? 'selected' : '' }}>No</option>
            <option value="1" {{ old('deroga_otra', $regulacion->deroga_otra ? '1' : '0') === '1' ? 'selected' : '' }}>Sí</option>
          </select>
        </x-field-help>

        <div id="derogadaField" style="{{ old('deroga_otra', $regulacion->deroga_otra ? '1' : '0') === '1' ? '' : 'display:none' }}">
          <x-field-help label="Regulación que deja sin efectos" class="span-2">
            <input name="regulacion_derogada" value="{{ old('regulacion_derogada', $regulacion->regulacion_derogada) }}" placeholder="Nombre completo de la regulación que se abroga o deroga">
          </x-field-help>
        </div>

        <x-field-help label="Resumen ciudadano (regulación)" class="span-2">
          <textarea name="resumen" rows="3">{{ old('resumen', $regulacion->resumen) }}</textarea>
        </x-field-help>

        {{-- #6: Editor visual del índice --}}
        @if($regulacion->tieneIndice())
        <div class="field span-2">
          <label style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <span>Índice de la regulación</span>
            <small class="muted">Puede agregar, editar o quitar entradas. Al guardar se actualiza el índice.</small>
          </label>
          {{-- Campo oculto que viaja con el form --}}
          <input type="hidden" name="indice_json" id="indiceJsonInput">

          <div class="indice-editor" id="indiceEditor">
            {{-- Las filas se generan por JS desde los datos del modelo --}}
          </div>

          <div style="display:flex;gap:8px;margin-top:10px">
            <button type="button" class="btn btn-outline btn-sm" onclick="agregarFilaIndice(1)">+ Título</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="agregarFilaIndice(2)">+ Capítulo</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="agregarFilaIndice(3)">+ Artículo</button>
          </div>
        </div>
        @endif

        <style>
          .indice-editor { border:1px solid var(--surface-high,#e5e7eb); border-radius:8px; overflow:hidden; max-height:420px; overflow-y:auto; }
          .indice-fila { display:flex; align-items:center; gap:8px; padding:6px 10px; border-bottom:1px solid var(--surface-high,#f0f0f0); }
          .indice-fila:last-child { border-bottom:none; }
          .indice-fila-nivel { font-size:11px; color:var(--muted,#667085); background:var(--surface-high,#f3f4f6); border-radius:4px; padding:2px 6px; min-width:60px; text-align:center; }
          .indice-fila input[type="text"] { flex:1; border:none; background:transparent; font-size:13px; color:var(--text,#111); padding:2px 4px; outline:none; }
          .indice-fila input[type="text"]:focus { background:var(--surface-high,#f8f9fa); border-radius:4px; }
          .indice-fila-del { background:none; border:none; color:var(--muted,#999); cursor:pointer; font-size:16px; padding:0 4px; line-height:1; }
          .indice-fila-del:hover { color:#dc2626; }
          .indice-nivel-1 { background:var(--surface,#fff); font-weight:600; }
          .indice-nivel-2 { background:#fafafa; padding-left:20px; }
          .indice-nivel-3 { background:#f9f9f9; padding-left:36px; }
        </style>

        <script>
        (function () {
          // Datos del modelo (PHP → JS)
          var INDICE = @json($regulacion->indice ?? []);
          var ETIQUETAS = { 1: 'Título', 2: 'Capítulo', 3: 'Artículo' };

          var editor = document.getElementById('indiceEditor');
          var jsonInput = document.getElementById('indiceJsonInput');
          if (!editor) return;

          // Renderiza todas las filas.
          function renderizar() {
            editor.innerHTML = '';
            INDICE.forEach(function (item, i) {
              editor.appendChild(crearFila(item, i));
            });
            sincronizar();
          }

          // Crea una fila de edición.
          function crearFila(item, idx) {
            var div = document.createElement('div');
            div.className = 'indice-fila indice-nivel-' + item.nivel;
            var etq = document.createElement('span');
            etq.className = 'indice-fila-nivel';
            etq.textContent = ETIQUETAS[item.nivel] || 'Nivel ' + item.nivel;
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.value = item.titulo || '';
            inp.placeholder = 'Texto del ' + (ETIQUETAS[item.nivel] || 'ítem');
            inp.addEventListener('input', function () {
              INDICE[idx].titulo = this.value;
              sincronizar();
            });
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'indice-fila-del';
            del.textContent = '×';
            del.title = 'Eliminar';
            del.addEventListener('click', function () {
              INDICE.splice(idx, 1);
              renderizar();
            });
            div.appendChild(etq);
            div.appendChild(inp);
            div.appendChild(del);
            return div;
          }

          // Serializa el índice en el input oculto para que viaje con el form.
          function sincronizar() {
            jsonInput.value = JSON.stringify(INDICE);
          }

          // Agrega una fila nueva al final.
          window.agregarFilaIndice = function (nivel) {
            INDICE.push({ nivel: nivel, titulo: '', linea: 0 });
            renderizar();
            // Foco en la última fila.
            setTimeout(function () {
              var inputs = editor.querySelectorAll('input[type="text"]');
              if (inputs.length) inputs[inputs.length - 1].focus();
            }, 50);
          };

          // Inicializar al cargar.
          document.addEventListener('DOMContentLoaded', renderizar);
        })();
        </script>

        {{-- Archivo --}}
        <div class="field span-2">
          <label>Reemplazar archivo original (opcional)</label>
          <x-carga-archivos name="archivo" :multiple="false" accept=".pdf,.doc,.docx" :maxMb="10" />
          <small class="help-small">
            @if($regulacion->archivo_original)
              Archivo actual: {{ basename($regulacion->archivo_original) }} ({{ strtoupper($regulacion->extension_original) }}).
              Si sube uno nuevo, se reemplaza, se reconvierte y se regenera el índice.
            @else
              No hay archivo cargado. Suba un PDF, DOC o DOCX.
            @endif
          </small>
        </div>

      </div>
      <div class="card-actions card-actions-end">
        <a href="{{ route('regulaciones.show', $regulacion) }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn">Guardar cambios</button>
      </div>
    </div>
  </form>
    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.timeline', ['tipo' => 'regulacion', 'id' => $regulacion->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

</div>
@endsection

@push('scripts')
<script>
document.getElementById('derogaSelect').addEventListener('change', function() {
  document.getElementById('derogadaField').style.display = this.value === '1' ? '' : 'none';
});
</script>
@endpush
