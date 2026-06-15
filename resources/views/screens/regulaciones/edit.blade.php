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

        {{-- Índice auto-extraído --}}
        @if($regulacion->tieneIndice())
        <div class="field span-2">
          <x-field-help label="Índice de la regulación" class="span-2">
            <div class="indice-preview" style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;max-height:300px;overflow-y:auto;font-size:13px;line-height:1.6">
              @foreach($regulacion->indice as $item)
                <div style="padding-left:{{ ($item['nivel'] - 1) * 16 }}px">
                  {{ $item['titulo'] }}
                </div>
              @endforeach
            </div>
          </x-field-help>
        </div>
        @endif

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
