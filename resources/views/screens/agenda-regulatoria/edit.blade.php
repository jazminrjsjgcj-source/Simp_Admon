@extends('layouts.app')
@section('title', 'Editar Propuesta')

@section('content')
<style>
  .acc-seccion { border:1px solid var(--surface-high); border-radius:var(--radius); margin-bottom:12px; overflow:hidden; background:var(--surface); }
  .acc-cabecera { width:100%; display:flex; align-items:center; gap:10px; padding:14px 16px; background:transparent; border:none; cursor:pointer; text-align:left; }
  .acc-titulo { font-size:15px; font-weight:600; color:var(--text); }
  .acc-sub { font-size:12px; color:var(--muted); flex:1; }
  .acc-flecha { font-size:14px; color:var(--muted); transition:transform .2s; }
  .acc-seccion.abierta .acc-flecha { transform:rotate(180deg); }
  .acc-cuerpo { display:none; padding:4px 16px 18px; border-top:1px solid var(--surface-high); }
  .acc-seccion.abierta .acc-cuerpo { display:block; }
  .acc-final { margin:16px 0; }
</style>
@php $d = $detalles ?? []; @endphp
<div class="page-body">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar Propuesta Regulatoria</h2>
      <p class="nowrap">{{ $propuesta->folio ?? 'Propuesta' }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <div class="detalle-con-timeline">
    <div class="detalle-main">
  <form method="POST" action="{{ route('propuestas.update', $propuesta) }}" enctype="multipart/form-data" id="propuestaForm">
    @csrf
    @method('PUT')

    {{-- Observaciones a atender (#18) --}}
    @include('partials.observaciones-todas', [
      'observacionesPorSeccion' => $observacionesPorSeccion,
      'campos'                  => $camposObservables,
    ])

    <div class="acordeon-tramite">

        {{-- SECCIÓN 1: Datos generales --}}
        <section class="acc-seccion abierta" data-acc="1">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Datos generales de la propuesta</span>
            <span class="acc-sub">Campos 1 al 4 del Anexo</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="1. Nombre completo del responsable" :required="true">
              <input name="responsable_nombre" value="{{ old('responsable_nombre', $d['responsable_nombre'] ?? auth()->user()->name) }}" placeholder="Nombre completo">
            </x-field-help>
            <x-field-help label="1.1 Cargo del responsable" :required="true">
              <input name="responsable_cargo" value="{{ old('responsable_cargo', $d['responsable_cargo'] ?? auth()->user()->cargo) }}" placeholder="Ej. Director de Normatividad">
            </x-field-help>
            <x-field-help label="2. Tipo de regulación" :required="true">
              <select name="tipo_regulacion">
                <option value="">Seleccione...</option>
                @foreach(\App\Models\TipoRegulacion::activos()->get() as $tr)
                  <option value="{{ $tr->nombre }}"
                    {{ old('tipo_regulacion', $propuesta->tipo_regulacion) === $tr->nombre ? 'selected' : '' }}>
                    {{ $tr->nombre }}
                  </option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="3. Materia sobre la cual versará" :required="true">
              <select name="materia">
                <option value="">Seleccione...</option>
                @foreach(['Comercio','Desarrollo Urbano','Protección Civil','Seguridad','Medio Ambiente','Hacienda','Gobierno','Digitalización','Otra'] as $opt)
                  <option {{ old('materia', $d['materia'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="4. Nombre preliminar de la Propuesta Regulatoria" :required="true" class="span-2">
              <input name="nombre" value="{{ old('nombre', $propuesta->nombre) }}" placeholder="Ej. Reglamento de Comercio Ambulante">
            </x-field-help>
            <x-field-help label="Dependencia responsable" :required="true">
              <select name="dependencia_id">
                <option value="">Seleccione...</option>
                @foreach($dependencias as $dep)
                  <option value="{{ $dep->id }}" {{ old('dependencia_id', $propuesta->dependencia_id) == $dep->id ? 'selected' : '' }}>{{ $dep->nombre }}</option>
                @endforeach
              </select>
            </x-field-help>
            @php
              $sujetoObligado = \App\Models\SujetoObligado::vigenteDe($propuesta->dependencia_id);
            @endphp
            <div class="field">
              <label>Sujeto Obligado</label>
              <input type="text"
                value="{{ $sujetoObligado?->nombre ?? 'Sin titular registrado' }}"
                disabled class="u-input-disabled">
              <input type="hidden" name="sujeto_obligado_id" value="{{ $sujetoObligado?->id }}">
              <input type="hidden" name="sujeto_obligado_nombre" value="{{ $sujetoObligado?->nombre }}">
              @if($sujetoObligado?->cargo)
                <small class="help-small">{{ $sujetoObligado->cargo }}</small>
              @endif
            </div>
          </div>
          <div class="assist-box">Estos datos identifican el instrumento regulatorio y a la persona responsable. Son los campos mínimos exigidos por el Art. 29 LNETB para inscribir la propuesta en la Agenda Regulatoria.</div>
        </div>
        </section>

        {{-- SECCIÓN 2: Justificación --}}
        <section class="acc-seccion" data-acc="2">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Justificación, problemática y alternativas</span>
            <span class="acc-sub">Campos 5 al 9</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="5. Sectores o grupos potencialmente impactados" :required="true" class="span-2">
              <textarea name="sectores_impactados" rows="2">{{ old('sectores_impactados', $d['sectores_impactados'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="6. Fecha tentativa de presentación" :required="true">
              <input name="fecha_tentativa" type="date" value="{{ old('fecha_tentativa', $propuesta->fecha_tentativa ? \Carbon\Carbon::parse($propuesta->fecha_tentativa)->format('Y-m-d') : '') }}">
            </x-field-help>
            <div class="field">
              <label>Año de agenda</label>
              <input value="{{ now()->year }}" readonly>
            </div>
            <x-field-help label="7. Justificación para emitir la propuesta" :required="true" class="span-2">
              <textarea name="justificacion" rows="3">{{ old('justificacion', $d['justificacion'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="8. Problemática que se pretende resolver" :required="true" class="span-2">
              <textarea name="problematica" rows="4">{{ old('problematica', $d['problematica'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="9. Alternativas consideradas" :required="true" class="span-2">
              <textarea name="alternativas" rows="3">{{ old('alternativas', $d['alternativas'] ?? '') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">Los campos 8 y 9 son insumos directos del Análisis de Impacto Regulatorio (Art. 38 LNETB). Entre más precisa sea la descripción aquí, menos trabajo adicional requerirá el AIR en la siguiente etapa.</div>
        </div>
        </section>

        {{-- SECCIÓN 3: Impactos --}}
        <section class="acc-seccion" data-acc="3">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Beneficios, costos e impactos</span>
            <span class="acc-sub">Campos 10 al 16</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="10. Posibles beneficios que generará" :required="true" class="span-2">
              <textarea name="beneficios" rows="3">{{ old('beneficios', $d['beneficios'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="11. ¿Genera nuevos costos burocráticos?" :required="true">
              <select name="genera_costos_burocraticos" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('genera_costos_burocraticos', $propuesta->genera_costos_burocraticos ? '1' : '0') === '1' ? 'selected' : '' }}>Sí</option>
                <option value="0" {{ old('genera_costos_burocraticos', $propuesta->genera_costos_burocraticos ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="11.1 Descripción de los costos burocráticos" :required="true" class="span-2">
              <textarea name="costos_burocraticos" rows="2">{{ old('costos_burocraticos', $d['costos_burocraticos'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="12. ¿Crea, modifica o elimina trámites existentes?" :required="true">
              <select name="impacta_tramites_existentes" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('impacta_tramites_existentes', $propuesta->impacta_tramites_existentes ? '1' : '0') === '1' ? 'selected' : '' }}>Sí</option>
                <option value="0" {{ old('impacta_tramites_existentes', $propuesta->impacta_tramites_existentes ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="12.1 Trámites y servicios en los que impacta" :required="true" class="span-2">
              <textarea name="tramites_impacta" rows="2">{{ old('tramites_impacta', $d['tramites_impacta'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="13. Acciones de simplificación asociadas" :required="true" class="span-2">
              <textarea name="acciones_simplificacion" rows="2">{{ old('acciones_simplificacion', $d['acciones_simplificacion'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="14. Acciones de digitalización asociadas" :required="true" class="span-2">
              <textarea name="acciones_digitalizacion" rows="2">{{ old('acciones_digitalizacion', $d['acciones_digitalizacion'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="15. Fundamento jurídico" :required="true" class="span-2">
              <textarea name="fundamento_juridico" rows="3">{{ old('fundamento_juridico', $d['fundamento_juridico'] ?? '') }}</textarea>
            </x-field-help>
            <x-field-help label="16. ¿Impacta en comercio o inversión?" :required="true">
              <select name="impacta_comercio_inversion" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('impacta_comercio_inversion', $propuesta->impacta_comercio_inversion ? '1' : '0') === '1' ? 'selected' : '' }}>Sí</option>
                <option value="0" {{ old('impacta_comercio_inversion', $propuesta->impacta_comercio_inversion ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="16.1 Descripción del impacto en comercio o inversión" class="span-2">
              <textarea name="impacto_comercio" rows="2">{{ old('impacto_comercio', $d['impacto_comercio'] ?? '') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">Las respuestas a los campos 11, 12 y 16 son las que el sistema usa para determinar si esta propuesta requiere un Análisis de Impacto Regulatorio conforme al Art. 35 LNETB. Una respuesta incorrecta puede omitir un AIR necesario o generar uno innecesario.</div>
        </div>
        </section>

        {{-- SECCIÓN 4: Anexos --}}
        <section class="acc-seccion" data-acc="4">
          <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
            <span class="acc-titulo">Anexos y observaciones</span>
            <span class="acc-sub">Campo 17</span>
            <span class="acc-flecha">▾</span>
          </button>
          <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="17. ¿Presenta proyecto de Propuesta Regulatoria?">
              <select name="presenta_proyecto">
                <option value="no" {{ ($d['presenta_proyecto'] ?? 'no') === 'no' ? 'selected' : '' }}>No</option>
                <option value="si"  {{ ($d['presenta_proyecto'] ?? '') === 'si' ? 'selected' : '' }}>Sí</option>
              </select>
            </x-field-help>
            <div class="field">
              <label>Número de anexos complementarios</label>
              <input name="num_anexos" type="number" min="0" max="9" value="{{ old('num_anexos', 0) }}">
            </div>
            <x-field-help label="Anexos complementarios" class="span-2">
              <x-carga-archivos name="archivo_propuesta" :multiple="true" accept=".pdf,.docx,.xlsx,.jpg,.png" :maxMb="10" />
            </x-field-help>
            <x-field-help label="Observaciones y/o comentarios" class="span-2">
              <textarea name="observaciones" rows="3">{{ old('observaciones', $d['observaciones'] ?? '') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">Al guardar, los cambios se aplican a la propuesta de inmediato. Si ya tiene una determinación de AIR iniciada, verifique que los campos 11, 12 y 16 sigan siendo consistentes con la nueva información.</div>
          </div>
        </section>

        {{-- ACCIONES --}}
        <div class="card-actions card-actions-end">
          <a href="{{ route('propuestas.show', $propuesta) }}" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-success" id="btnGuardar">Guardar cambios</button>
        </div>

    </div>{{-- /acordeon-tramite --}}
  </form>
    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.observaciones-checklist', [
        'observacionesPorSeccion' => $observacionesPorSeccion,
        'campos'                  => $camposObservables,
      ])
      @include('partials.timeline', ['tipo' => 'propuesta', 'id' => $propuesta->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Guardar cambios?</h3>
      <p>Los cambios se aplicarán a la propuesta registrada.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar</button>
        <button type="button" class="btn btn-success" id="btnConfirmGuardar">Guardar cambios</button>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
  window.toggleAcc = function (boton) {
    var seccion = boton.closest('.acc-seccion');
    if (seccion) seccion.classList.toggle('abierta');
  };

  // Guardar: pide confirmación antes de enviar.
  document.getElementById('btnGuardar').addEventListener('click', function(e){
    e.preventDefault();
    document.getElementById('confirmModal').classList.add('open');
  });
  document.getElementById('btnConfirmGuardar').addEventListener('click', function(){
    document.getElementById('propuestaForm').submit();
  });
  document.getElementById('confirmModal').addEventListener('click', function(e){
    if(e.target===this) this.style.display='none';
  });

  // Limpieza de errores en vivo al editar un campo.
  document.querySelectorAll('input,select,textarea').forEach(function(f){
    f.addEventListener('input',  function(){ this.style.borderColor=''; var e=this.parentElement.querySelector('.field-error'); if(e) e.remove(); });
    f.addEventListener('change', function(){ this.style.borderColor=''; var e=this.parentElement.querySelector('.field-error'); if(e) e.remove(); });
  });
})();
</script>
@endpush
