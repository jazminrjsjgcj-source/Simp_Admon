@extends('layouts.app')
@section('title', 'Nueva Propuesta Regulatoria')

@section('content')
<div class="page-body">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Registrar Propuesta Regulatoria</h2>
      <p class="nowrap">Paso <span id="propuestaStepLabel">1</span> de 4</p>
    </div>
    <div class="head-actions"><x-btn-ejemplo tipo="agenda_regulatoria" /></div>
  </div>

  <form method="POST" action="{{ route('propuestas.store') }}" enctype="multipart/form-data" id="propuestaForm">
    @csrf
   <div class="card"><div class="card-body" id="propuestaWizard">
      <div class="wizard-sidebar" id="propuestaSidebar">
        <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Responsable</strong><small>Rubros 1-4</small></div>
        <div class="wizard-step"        data-step="2"><span class="wizard-dot"></span><strong>Justificación</strong><small>Rubros 5-9</small></div>
        <div class="wizard-step"        data-step="3"><span class="wizard-dot"></span><strong>Impactos</strong><small>Rubros 10-16</small></div>
        <div class="wizard-step"        data-step="4"><span class="wizard-dot"></span><strong>Anexos</strong><small>Rubro 17</small></div>
      </div>

      <div class="wizard-panel">

        {{-- PASO 1: Datos generales --}}
        <div class="wizard-content active" data-panel="1">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Datos generales de la propuesta</h3><p>Campos 1 al 4 del Anexo de Agenda Regulatoria.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="1. Nombre completo del responsable" :required="true">
              <input type="text" value="{{ auth()->user()->name }}" disabled class="u-input-disabled">
              <input type="hidden" name="responsable_nombre" value="{{ auth()->user()->name }}">
            </x-field-help>
            <x-field-help label="1.1 Cargo del responsable" :required="true">
              <input type="text" value="{{ auth()->user()->cargo ?? '—' }}" disabled class="u-input-disabled">
              <input type="hidden" name="responsable_cargo" value="{{ auth()->user()->cargo }}">
            </x-field-help>
            <x-field-help label="2. Tipo de regulación" :required="true">
              <select name="tipo_regulacion">
                <option value="">Seleccione...</option>
                @foreach(\App\Models\TipoRegulacion::activos()->get() as $tr)
                  <option value="{{ $tr->nombre }}"
                    {{ old('tipo_regulacion') === $tr->nombre ? 'selected' : '' }}>
                    {{ $tr->nombre }}
                  </option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="3. Materia sobre la cual versará" :required="true">
              <select name="materia">
                <option value="">Seleccione...</option>
                <option>Comercio</option><option>Desarrollo Urbano</option>
                <option>Protección Civil</option><option>Seguridad</option>
                <option>Medio Ambiente</option><option>Hacienda</option>
                <option>Gobierno</option><option>Digitalización</option><option>Otra</option>
              </select>
            </x-field-help>
            <x-field-help label="4. Nombre preliminar de la Propuesta Regulatoria" :required="true" class="span-2">
              <input name="nombre" placeholder="Ej. Reglamento de Comercio Ambulante del Municipio de La Paz" value="{{ old('nombre') }}">
            </x-field-help>
            <input type="hidden" name="dependencia_id" value="{{ auth()->user()->dependencia_id }}">
            <x-field-help label="Dependencia">
              <input type="text" value="{{ auth()->user()->dependencia->nombre ?? '—' }}" disabled class="u-input-disabled">
            </x-field-help>
            @php
              $sujetoObligado = \App\Models\SujetoObligado::vigenteDe(auth()->user()->dependencia_id);
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
              @elseif(!$sujetoObligado)
                <small class="help-small">Tu dependencia no tiene un titular registrado. Pídele al administrador que lo agregue en Catálogos → Sujetos obligados.</small>
              @endif
            </div>
          </div>
          <div class="assist-box">Estos datos identifican el instrumento regulatorio y a la persona responsable. Son los campos mínimos exigidos por el Art. 29 LNETB para inscribir la propuesta en la Agenda Regulatoria.</div>
        </div>

        {{-- PASO 2: Justificación --}}
        <div class="wizard-content" data-panel="2">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Justificación, problemática y alternativas</h3><p>Campos 5 al 9 del Anexo de Agenda Regulatoria.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="5. Sectores o grupos potencialmente impactados" :required="true" class="span-2">
              <textarea name="sectores_impactados" rows="2" placeholder="Ej. Comercio informal, turismo, economía local, ciudadanía usuaria...">{{ old('sectores_impactados') }}</textarea>
            </x-field-help>
            {{-- PENDIENTE: selects de sector/subsector comentados temporalmente (ver lista de pendientes #3) --}}
            {{-- <x-selector-scian :sector="old('sector_id')" :subsector="old('subsector_id')" /> --}}
            {{-- <div class="field" style="display:none"></div> --}}
            <x-field-help label="6. Fecha tentativa de presentación" :required="true">
              <input name="fecha_tentativa" type="date" value="{{ old('fecha_tentativa') }}">
            </x-field-help>
            <div class="field">
              <label>Año de agenda</label>
              <input value="{{ now()->year }}" readonly>
            </div>
            <x-field-help label="7. Justificación para emitir la propuesta" :required="true" class="span-2">
              <textarea name="justificacion" rows="3" placeholder="Explique por qué es necesaria la emisión de la propuesta regulatoria...">{{ old('justificacion') }}</textarea>
            </x-field-help>
            <x-field-help label="8. Problemática que se pretende resolver" :required="true" class="span-2">
              <textarea name="problematica" rows="4" placeholder="Describa el problema público que origina la necesidad de esta regulación...">{{ old('problematica') }}</textarea>
            </x-field-help>
            <x-field-help label="9. Alternativas consideradas" :required="true" class="span-2">
              <textarea name="alternativas" rows="3" placeholder="Describa alternativas regulatorias y no regulatorias evaluadas...">{{ old('alternativas') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">Los campos 8 y 9 son insumos directos del Análisis de Impacto Regulatorio (Art. 38 LNETB). Entre más precisa sea la descripción aquí, menos trabajo adicional requerirá el AIR en la siguiente etapa.</div>
        </div>

        {{-- PASO 3: Beneficios, costos e impactos --}}
        <div class="wizard-content" data-panel="3">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Beneficios, costos e impactos</h3><p>Campos 10 al 16 del Anexo de Agenda Regulatoria.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="10. Posibles beneficios que generará" :required="true" class="span-2">
              <textarea name="beneficios" rows="3" placeholder="Beneficios para la ciudadanía, sectores impactados y administración pública...">{{ old('beneficios') }}</textarea>
            </x-field-help>
            <x-field-help label="11. ¿Genera nuevos costos burocráticos?" :required="true">
              <select name="genera_costos_burocraticos" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('genera_costos_burocraticos') === '1' ? 'selected' : '' }}>Sí — la regulación genera nuevos trámites, requisitos, tiempos o cargas económicas</option>
                <option value="0" {{ old('genera_costos_burocraticos') === '0' ? 'selected' : '' }}>No — no genera costos burocráticos adicionales a los existentes</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="11.1 Descripción de los costos burocráticos" :required="true" class="span-2">
              <textarea name="costos_burocraticos" rows="2" placeholder="Describa los nuevos trámites, requisitos, tiempos o cargas que generará...">{{ old('costos_burocraticos') }}</textarea>
            </x-field-help>
            <x-field-help label="12. ¿Crea, modifica o elimina trámites existentes?" :required="true">
              <select name="impacta_tramites_existentes" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('impacta_tramites_existentes') === '1' ? 'selected' : '' }}>Sí — crea, modifica o elimina uno o más trámites o servicios</option>
                <option value="0" {{ old('impacta_tramites_existentes') === '0' ? 'selected' : '' }}>No — no afecta los trámites o servicios existentes</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="12.1 Trámites y servicios en los que impacta" :required="true" class="span-2">
              <textarea name="tramites_impacta" rows="2" placeholder="Indique cuáles trámites o servicios crea, modifica o elimina; si no impacta, señale Ninguno...">{{ old('tramites_impacta') }}</textarea>
            </x-field-help>
            <x-field-help label="13. Acciones de simplificación asociadas" :required="true" class="span-2">
              <textarea name="acciones_simplificacion" rows="2" placeholder="Describa acciones de simplificación asociadas o indique Otra/Ninguna...">{{ old('acciones_simplificacion') }}</textarea>
            </x-field-help>
            <x-field-help label="14. Acciones de digitalización asociadas" :required="true" class="span-2">
              <textarea name="acciones_digitalizacion" rows="2" placeholder="Describa acciones de digitalización asociadas o indique Otra/Ninguna...">{{ old('acciones_digitalizacion') }}</textarea>
            </x-field-help>
            <x-field-help label="15. Fundamento jurídico" :required="true" class="span-2">
              <textarea name="fundamento_juridico" rows="3" placeholder="Artículos y disposiciones legales que facultan la emisión de esta regulación...">{{ old('fundamento_juridico') }}</textarea>
            </x-field-help>
            <x-citar-regulacion label="Regulaciones relacionadas del catálogo (opcional)" />
            <x-field-help label="16. ¿Impacta en comercio o inversión?" :required="true">
              <select name="impacta_comercio_inversion" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('impacta_comercio_inversion') === '1' ? 'selected' : '' }}>Sí — afecta actividades comerciales, de inversión o competencia económica</option>
                <option value="0" {{ old('impacta_comercio_inversion') === '0' ? 'selected' : '' }}>No — no tiene impacto directo en comercio o inversión</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="16.1 Descripción del impacto en comercio o inversión" class="span-2">
              <textarea name="impacto_comercio" rows="2" placeholder="Describa el impacto en comercio o inversión; si no aplica, señale No aplica...">{{ old('impacto_comercio') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">Las respuestas a los campos 11, 12 y 16 son las que el sistema usa para determinar si esta propuesta requiere un Análisis de Impacto Regulatorio conforme al Art. 35 LNETB. Una respuesta incorrecta puede omitir un AIR necesario o generar uno innecesario.</div>
        </div>

        {{-- PASO 4: Anexos --}}
        <div class="wizard-content" data-panel="4">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Anexos y observaciones</h3><p>Campo 17 del Anexo. La plataforma admite hasta nueve archivos complementarios.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="17. ¿Presenta proyecto de Propuesta Regulatoria?">
              <select name="presenta_proyecto">
                <option value="no">No</option>
                <option value="si">Sí</option>
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
              <textarea name="observaciones" rows="3" placeholder="Agregue comentarios relevantes o indique documentación adicional...">{{ old('observaciones') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">Al guardar, la propuesta queda inscrita en la Agenda Regulatoria en estatus <strong>borrador</strong>. Desde la ficha de la propuesta podrá iniciar la determinación de AIR o exención. La Autoridad de Simplificación recibirá notificación cuando la propuesta pase a consulta pública.</div>
        </div>

        {{-- NAVEGACIÓN --}}
        <div class="wizard-actions">
          <button type="button" class="btn btn-outline" id="btnAtras" onclick="wizardNav(-1)">Atrás</button>
          <div class="wizard-actions-right">
            <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
            <button type="button" class="btn" id="btnSiguiente" onclick="wizardNav(1)">Siguiente</button>
            <button type="submit" name="accion" value="borrador" class="btn btn-outline" id="btnBorrador">Guardar borrador</button>
            <button type="submit" name="accion" value="enviar" class="btn btn-success" id="btnGuardar">Guardar y enviar</button>
          </div>
        </div>

      </div>
    </div></div>
  </form>

</div>
@endsection

@push('scripts')
<script>
(function () {
  var current = 1;
  var total   = 4;

  var required = {
    1: [
      { name: 'tipo_regulacion',    label: '2. Tipo de regulación' },
      { name: 'materia',            label: '3. Materia sobre la cual versará' },
      { name: 'nombre',             label: '4. Nombre preliminar de la Propuesta' },
    ],
    2: [
      { name: 'sectores_impactados', label: '5. Sectores o grupos potencialmente impactados' },
      { name: 'fecha_tentativa',     label: '6. Fecha tentativa de presentación' },
      { name: 'justificacion',       label: '7. Justificación para emitir la propuesta' },
      { name: 'problematica',        label: '8. Problemática que se pretende resolver' },
      { name: 'alternativas',        label: '9. Alternativas consideradas' },
    ],
    3: [
      { name: 'beneficios',                  label: '10. Posibles beneficios' },
      { name: 'genera_costos_burocraticos',   label: '11. ¿Genera nuevos costos burocráticos?' },
      { name: 'costos_burocraticos',          label: '11.1 Descripción de los costos burocráticos' },
      { name: 'impacta_tramites_existentes',  label: '12. ¿Crea, modifica o elimina trámites existentes?' },
      { name: 'tramites_impacta',             label: '12.1 Trámites y servicios en los que impacta' },
      { name: 'acciones_simplificacion',      label: '13. Acciones de simplificación' },
      { name: 'acciones_digitalizacion',      label: '14. Acciones de digitalización' },
      { name: 'fundamento_juridico',          label: '15. Fundamento jurídico' },
      { name: 'impacta_comercio_inversion',   label: '16. ¿Impacta en comercio o inversión?' },
    ],
  };

  function clearErrors(panel) {
    panel.querySelectorAll('.field-error').forEach(function(e){ e.remove(); });
    panel.querySelectorAll('.field-error-input').forEach(function(el){ el.classList.remove('field-error-input'); });
  }

  function showError(field, msg) {
    field.classList.add('field-error-input');
    field.style.borderColor = '#dc2626';
    var err = document.createElement('p');
    err.className = 'field-error';
    err.textContent = msg;
    err.style.cssText = 'color:#dc2626;font-size:12px;margin:4px 0 0;';
    field.parentElement.appendChild(err);
  }

  function validateStep(step) {
    var panel = document.querySelector('[data-panel="'+step+'"]');
    if (!panel) return true;
    clearErrors(panel);
    var rules = required[step];
    if (!rules) return true;
    var ok = true;
    var firstInvalid = null;
    rules.forEach(function(r) {
      var field = panel.querySelector('[name="'+r.name+'"]');
      if (!field) return;
      var val = field.value ? field.value.trim() : '';
      if (!val || val === '') {
        showError(field, r.label + ' es obligatorio.');
        if (!firstInvalid) firstInvalid = field;
        ok = false;
      }
    });
    if (firstInvalid) firstInvalid.focus();
    return ok;
  }

  function clearFieldError(field) {
    field.style.borderColor = '';
    var err = field.parentElement.querySelector('.field-error');
    if (err) err.remove();
    field.classList.remove('field-error-input');
  }

  function goTo(step) {
    document.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('active', parseInt(p.dataset.panel) === step);
    });
    document.querySelectorAll('[data-step]').forEach(function (s) {
      var n = parseInt(s.dataset.step);
      s.classList.toggle('active',    n === step);
      s.classList.toggle('completed', n < step);
    });
    document.getElementById('propuestaStepLabel').textContent = step;
    document.getElementById('btnAtras').style.display     = step > 1     ? '' : 'none';
    document.getElementById('btnSiguiente').style.display = step < total ? '' : 'none';
    document.getElementById('btnBorrador').style.display  = step === total ? '' : 'none';
    document.getElementById('btnGuardar').style.display   = step === total ? '' : 'none';
    current = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  window.wizardNav = function (dir) {
    if (dir > 0 && !validateStep(current)) return;
    var next = current + dir;
    if (next >= 1 && next <= total) goTo(next);
  };

  document.querySelectorAll('input, select, textarea').forEach(function(f){
    f.addEventListener('input', function(){ clearFieldError(this); });
    f.addEventListener('change', function(){ clearFieldError(this); });
  });

  goTo(1);
})();
</script>
@endpush
