@extends('layouts.app')
@section('title', 'Subir Regulación')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Registrar regulación</h2>
      <p class="nowrap">Registro conforme al Art. 153 de los Lineamientos LNETB.</p>
    </div>
    <div class="head-actions">
      <x-btn-ejemplo tipo="regulacion" />
      <a href="{{ route('regulaciones.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST" action="{{ route('regulaciones.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="card">
      <div class="card-body-padded wizard-fields">

        {{-- Identificación --}}
        <x-field-help label="Nombre oficial de la regulación" :required="true" class="span-2">
          <input required name="nombre" value="{{ old('nombre') }}" placeholder="Ej. Reglamento de Construcción Municipal">
        </x-field-help>

        <x-field-help label="Tipo de regulación (registro)">
          <select name="tipo">
            <option value="">Seleccione...</option>
            @foreach(\App\Models\TipoRegulacion::activos()->get() as $tr)
              <option value="{{ $tr->nombre }}" {{ old('tipo') === $tr->nombre ? 'selected' : '' }}>{{ $tr->nombre }}</option>
            @endforeach
          </select>
        </x-field-help>

        <x-field-help label="Materia o ámbito de aplicación">
          <select name="materia">
            <option value="">Seleccione...</option>
            @foreach(\App\Models\Regulacion::MATERIAS as $m)
              <option {{ old('materia') === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
        </x-field-help>

        <div class="field">
          <label>Dependencia responsable</label>
          <input type="text" value="{{ auth()->user()->dependencia->nombre ?? '' }}" disabled class="u-input-disabled">
        </div>

        <div class="field">
          <label>Fecha de publicación</label>
          <input type="date" name="fecha_publicacion" value="{{ old('fecha_publicacion') }}">
        </div>

        <div class="field">
          <label>Fecha de vigencia</label>
          <input type="date" name="fecha_vigencia" value="{{ old('fecha_vigencia') }}">
        </div>

        {{-- Campos nuevos Art. 153 --}}
        <x-field-help label="Objetivo de la regulación" class="span-2">
          <textarea name="objetivo" rows="3" placeholder="¿Para qué existe esta regulación? ¿Qué problema resuelve?">{{ old('objetivo') }}</textarea>
        </x-field-help>

        <x-field-help label="Fundamento jurídico de expedición" class="span-2">
          <textarea name="fundamento_juridico" rows="2" placeholder="Ej.: Art. 115 fracc. II CPEUM; Art. 42 Ley Orgánica del Municipio Libre de B.C.S.">{{ old('fundamento_juridico') }}</textarea>
        </x-field-help>

        <x-field-help label="Sector regulado">
          <select name="sector_id">
            <option value="">Seleccione...</option>
            @foreach($sectores as $s)
              <option value="{{ $s->id }}" {{ old('sector_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
            @endforeach
          </select>
        </x-field-help>

        <x-field-help label="Palabras clave">
          <input name="palabras_clave" value="{{ old('palabras_clave') }}" placeholder="licencia, funcionamiento, comercio, establecimiento">
        </x-field-help>

        <x-field-help label="¿Deja sin efectos otra regulación?">
          <select name="deroga_otra" id="derogaSelect">
            <option value="0" {{ old('deroga_otra') !== '1' ? 'selected' : '' }}>No</option>
            <option value="1" {{ old('deroga_otra') === '1' ? 'selected' : '' }}>Sí</option>
          </select>
        </x-field-help>

        <div id="derogadaField" class="span-2" style="{{ old('deroga_otra') === '1' ? '' : 'display:none' }}">
          <x-field-help label="Regulación que deja sin efectos">
            <input name="regulacion_derogada" value="{{ old('regulacion_derogada') }}" placeholder="Nombre completo de la regulación que se abroga o deroga">
          </x-field-help>
        </div>

        <x-field-help label="Resumen ciudadano (regulación)" class="span-2">
          <textarea name="resumen" rows="3" placeholder="Descripción breve para entender la regulación...">{{ old('resumen') }}</textarea>
        </x-field-help>

        {{-- Archivo --}}
        <div class="field span-2">
          <label>Archivo original *</label>
          <x-carga-archivos name="archivo" :multiple="false" accept=".pdf,.doc,.docx" :maxMb="10" :required="true" />
          <small class="help-small">El índice se extraerá automáticamente al convertir a Markdown.</small>
        </div>

      </div>
      <div class="assist-box">Al guardar, el archivo se convierte a Markdown citable y se extrae el índice de la regulación automáticamente. Podrá editar el índice desde la ficha de la regulación si la extracción no fue precisa.</div>
      <div class="card-actions card-actions-end">
        <a href="{{ route('regulaciones.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn">Registrar regulación</button>
      </div>
    </div>
  </form>

</div>
@endsection

@push('scripts')
<script>
(function () {
  var sel = document.getElementById('derogaSelect');
  var campo = document.getElementById('derogadaField');
  if (!sel || !campo) return;
  sel.addEventListener('change', function () {
    campo.style.display = this.value === '1' ? '' : 'none';
  });
})();
</script>
@endpush
