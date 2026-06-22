@extends('layouts.app')
@section('title', 'Nueva Propuesta Regulatoria')

@section('content')
{{--
  #B7: este archivo reemplaza una copia residual del wizard de Agenda SyD
  que estaba aquí por error y enviaba los datos a la ruta equivocada.
  El layout sigue el patrón del edit.blade.php hermano (acordeón de 4
  secciones con los 17 campos del Anexo Art. 29 LNETB).

  Recibe:
    $dependencias  : colección para el select de dependencia responsable
--}}
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

<div class="page-body">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Nueva Propuesta Regulatoria</h2>
      <p class="nowrap">Inscripción en la Agenda Regulatoria (Art. 29 LNETB).</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if($errors->any())
    <div class="toast toast-error u-toast-inline-top">
      <strong>Corrija los siguientes campos:</strong>
      <ul style="margin:8px 0 0 16px">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('propuestas.store') }}" enctype="multipart/form-data" id="propuestaForm">
    @csrf
    {{-- El campo 'accion' lo escribe el JS según el botón pulsado:
         'borrador' → guarda y queda editable; 'enviar' → cambia estatus a CONSULTA. --}}
    <input type="hidden" name="accion" id="accionCampo" value="borrador">

    <div class="acordeon-tramite">

      {{-- ============================================================
           SECCIÓN 1: Datos generales (Campos 1-4 del Anexo Art. 29)
           ============================================================ --}}
      <section class="acc-seccion abierta" data-acc="1">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Datos generales de la propuesta</span>
          <span class="acc-sub">Campos 1 al 4 del Anexo</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="1. Nombre completo del responsable" :required="true">
              <input name="responsable_nombre" value="{{ old('responsable_nombre', auth()->user()->name) }}" placeholder="Nombre completo">
            </x-field-help>
            <x-field-help label="1.1 Cargo del responsable" :required="true">
              <input name="responsable_cargo" value="{{ old('responsable_cargo', auth()->user()->cargo) }}" placeholder="Ej. Director de Normatividad">
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
                @foreach(['Comercio','Desarrollo Urbano','Protección Civil','Seguridad','Medio Ambiente','Hacienda','Gobierno','Digitalización','Otra'] as $opt)
                  <option {{ old('materia') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
              </select>
            </x-field-help>
            <x-field-help label="4. Nombre preliminar de la Propuesta Regulatoria" :required="true" class="span-2">
              <input name="nombre" value="{{ old('nombre') }}" placeholder="Ej. Reglamento de Comercio Ambulante" required>
            </x-field-help>
            <x-field-help label="Dependencia responsable" :required="true">
              <select name="dependencia_id" id="dependenciaSel" onchange="actualizarSujetoObligado()">
                <option value="">Seleccione...</option>
                @foreach($dependencias as $dep)
                  <option value="{{ $dep->id }}" {{ old('dependencia_id', auth()->user()->dependencia_id) == $dep->id ? 'selected' : '' }}>
                    {{ $dep->nombre }}
                  </option>
                @endforeach
              </select>
            </x-field-help>
            {{-- Sujeto Obligado: el titular vigente de la dependencia seleccionada.
                 Se carga inicialmente con la dependencia del usuario y se puede
                 refrescar al cambiar dependencia. --}}
            @php
              $depPorDefecto  = old('dependencia_id', auth()->user()->dependencia_id);
              $sujetoObligado = $depPorDefecto ? \App\Models\SujetoObligado::vigenteDe((int) $depPorDefecto) : null;
            @endphp
            <div class="field">
              <label>Sujeto Obligado</label>
              <input type="text" id="sujetoObligadoNombre"
                value="{{ $sujetoObligado?->nombre ?? 'Seleccione una dependencia' }}"
                disabled class="u-input-disabled">
              <input type="hidden" name="sujeto_obligado_id" id="sujetoObligadoId" value="{{ $sujetoObligado?->id }}">
              <input type="hidden" name="sujeto_obligado_nombre" id="sujetoObligadoNombreHidden" value="{{ $sujetoObligado?->nombre }}">
              <small class="help-small" id="sujetoObligadoCargo">{{ $sujetoObligado?->cargo ?? '' }}</small>
            </div>
          </div>
          <div class="assist-box">
            Estos datos identifican el instrumento regulatorio y a la persona responsable.
            Son los campos mínimos exigidos por el Art. 29 LNETB para inscribir la propuesta en la Agenda Regulatoria.
          </div>
        </div>
      </section>

      {{-- ============================================================
           SECCIÓN 2: Justificación, problemática y alternativas (5-9)
           ============================================================ --}}
      <section class="acc-seccion" data-acc="2">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Justificación, problemática y alternativas</span>
          <span class="acc-sub">Campos 5 al 9</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="5. Sectores o grupos potencialmente impactados" :required="true" class="span-2">
              <textarea name="sectores_impactados" rows="2" placeholder="Describa qué sectores económicos, grupos sociales o tipos de regulados se verán afectados...">{{ old('sectores_impactados') }}</textarea>
            </x-field-help>
            <x-field-help label="6. Fecha tentativa de presentación" :required="true">
              <input name="fecha_tentativa" type="date" value="{{ old('fecha_tentativa') }}">
            </x-field-help>
            <div class="field">
              <label>Año de agenda</label>
              <input value="{{ now()->year }}" readonly class="u-input-disabled">
            </div>
            <x-field-help label="7. Justificación para emitir la propuesta" :required="true" class="span-2">
              <textarea name="justificacion" rows="3" placeholder="Razón por la cual se considera necesario emitir esta regulación...">{{ old('justificacion') }}</textarea>
            </x-field-help>
            <x-field-help label="8. Problemática que se pretende resolver" :required="true" class="span-2">
              <textarea name="problematica" rows="4" placeholder="Describa el problema público específico que la regulación busca atender...">{{ old('problematica') }}</textarea>
            </x-field-help>
            <x-field-help label="9. Alternativas consideradas" :required="true" class="span-2">
              <textarea name="alternativas" rows="3" placeholder="¿Qué otras opciones se evaluaron antes de optar por la regulación?">{{ old('alternativas') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">
            Los campos 8 y 9 son insumos directos del Análisis de Impacto Regulatorio (Art. 38 LNETB).
            Entre más precisa sea la descripción aquí, menos trabajo adicional requerirá el AIR en la siguiente etapa.
          </div>
        </div>
      </section>

      {{-- ============================================================
           SECCIÓN 3: Beneficios, costos e impactos (10-16)
           ============================================================ --}}
      <section class="acc-seccion" data-acc="3">
        <button type="button" class="acc-cabecera" onclick="toggleAcc(this)">
          <span class="acc-titulo">Beneficios, costos e impactos</span>
          <span class="acc-sub">Campos 10 al 16</span>
          <span class="acc-flecha">▾</span>
        </button>
        <div class="acc-cuerpo">
          <div class="wizard-fields">
            <x-field-help label="10. Posibles beneficios que generará" :required="true" class="span-2">
              <textarea name="beneficios" rows="3" placeholder="Beneficios esperados para la ciudadanía, la administración o el ambiente regulado...">{{ old('beneficios') }}</textarea>
            </x-field-help>
            <x-field-help label="11. ¿Genera nuevos costos burocráticos?" :required="true">
              <select name="genera_costos_burocraticos" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('genera_costos_burocraticos') === '1' ? 'selected' : '' }}>Sí</option>
                <option value="0" {{ old('genera_costos_burocraticos') === '0' ? 'selected' : '' }}>No</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="11.1 Descripción de los costos burocráticos" :required="true" class="span-2">
              <textarea name="costos_burocraticos" rows="2" placeholder="Si genera costos: ¿cuáles, sobre quiénes, en qué momento?">{{ old('costos_burocraticos') }}</textarea>
            </x-field-help>
            <x-field-help label="12. ¿Crea, modifica o elimina trámites existentes?" :required="true">
              <select name="impacta_tramites_existentes" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('impacta_tramites_existentes') === '1' ? 'selected' : '' }}>Sí</option>
                <option value="0" {{ old('impacta_tramites_existentes') === '0' ? 'selected' : '' }}>No</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="12.1 Trámites y servicios en los que impacta" :required="true" class="span-2">
              <textarea name="tramites_impacta" rows="2" placeholder="Liste los trámites o servicios que se crean, modifican o eliminan...">{{ old('tramites_impacta') }}</textarea>
            </x-field-help>
            <x-field-help label="13. Acciones de simplificación asociadas" :required="true" class="span-2">
              <textarea name="acciones_simplificacion" rows="2" placeholder="¿Qué acciones del catálogo del Art. 23 LNETB se ven afectadas?">{{ old('acciones_simplificacion') }}</textarea>
            </x-field-help>
            <x-field-help label="14. Acciones de digitalización asociadas" :required="true" class="span-2">
              <textarea name="acciones_digitalizacion" rows="2" placeholder="¿Qué acciones del catálogo del Art. 24 LNETB se ven afectadas?">{{ old('acciones_digitalizacion') }}</textarea>
            </x-field-help>
            <x-field-help label="15. Fundamento jurídico" :required="true" class="span-2">
              <textarea name="fundamento_juridico" rows="3" placeholder="Ley, reglamento o disposición que faculta a emitir esta propuesta...">{{ old('fundamento_juridico') }}</textarea>
            </x-field-help>
            <x-field-help label="16. ¿Impacta en comercio o inversión?" :required="true">
              <select name="impacta_comercio_inversion" required>
                <option value="">Seleccione...</option>
                <option value="1" {{ old('impacta_comercio_inversion') === '1' ? 'selected' : '' }}>Sí</option>
                <option value="0" {{ old('impacta_comercio_inversion') === '0' ? 'selected' : '' }}>No</option>
              </select>
            </x-field-help>
            <div class="field"><!-- espaciador grid --></div>
            <x-field-help label="16.1 Descripción del impacto en comercio o inversión" class="span-2">
              <textarea name="impacto_comercio" rows="2" placeholder="Describa cómo afecta al comercio, la inversión o la competitividad...">{{ old('impacto_comercio') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">
            Las respuestas a los campos 11, 12 y 16 son las que el sistema usa para determinar si esta propuesta requiere un Análisis de Impacto Regulatorio conforme al Art. 35 LNETB.
            Una respuesta incorrecta puede omitir un AIR necesario o generar uno innecesario.
          </div>
        </div>
      </section>

      {{-- ============================================================
           SECCIÓN 4: Anexos y observaciones (Campo 17)
           ============================================================ --}}
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
                <option value="no" {{ old('presenta_proyecto', 'no') === 'no' ? 'selected' : '' }}>No</option>
                <option value="si" {{ old('presenta_proyecto') === 'si' ? 'selected' : '' }}>Sí</option>
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
              <textarea name="observaciones" rows="3" placeholder="Comentarios adicionales para la revisión...">{{ old('observaciones') }}</textarea>
            </x-field-help>
          </div>
          <div class="assist-box">
            Al guardar como borrador podrá editar la propuesta más tarde.
            Al enviar a revisión, la propuesta entra a Consulta Pública y se genera el folio (Art. 28 LNETB).
          </div>
        </div>
      </section>

    </div>{{-- /acordeon-tramite --}}

    {{-- Acciones del formulario --}}
    <div class="card-actions card-actions-end">
      <a href="{{ route('agenda-regulatoria.index') }}" class="btn btn-outline">Cancelar</a>
      <button type="button" class="btn" id="btnBorrador">Guardar como borrador</button>
      <button type="button" class="btn btn-success" id="btnEnviar">Enviar a revisión</button>
    </div>

  </form>

  {{-- Modal de confirmación al enviar a revisión (no se puede deshacer) --}}
  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Enviar a revisión?</h3>
      <p>La propuesta entrará a Consulta Pública (Art. 28 LNETB) y no podrá editarse libremente. Se asignará un folio.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar antes</button>
        <button type="button" class="btn btn-success" id="btnConfirmEnviar">Sí, enviar a revisión</button>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function () {

  // Acordeón: abre/cierra una sección al hacer clic en su cabecera.
  window.toggleAcc = function (boton) {
    var seccion = boton.closest('.acc-seccion');
    if (seccion) seccion.classList.toggle('abierta');
  };

  // Sujeto Obligado dinámico: al cambiar la dependencia, refresca los hidden y
  // la etiqueta visual. Como no hay endpoint AJAX de sujetos-obligados aquí, el
  // valor inicial (de la dependencia precargada) basta — y si cambian la
  // dependencia, lo marcamos como "pendiente" para que el backend lo resuelva.
  window.actualizarSujetoObligado = function () {
    var depSel  = document.getElementById('dependenciaSel');
    var label   = document.getElementById('sujetoObligadoNombre');
    var hiddenId = document.getElementById('sujetoObligadoId');
    var hiddenNombre = document.getElementById('sujetoObligadoNombreHidden');
    var cargo   = document.getElementById('sujetoObligadoCargo');
    if (!depSel) return;
    if (!depSel.value) {
      label.value = 'Seleccione una dependencia';
      hiddenId.value = '';
      hiddenNombre.value = '';
      cargo.textContent = '';
      return;
    }
    // Si la dependencia cambia, el sujeto obligado se resolverá en backend al
    // crear la propuesta (con SujetoObligado::vigenteDe()). Aquí avisamos al
    // usuario para evitar confusión.
    label.value = 'Se asignará el titular vigente de esta dependencia';
    hiddenId.value = '';
    hiddenNombre.value = '';
    cargo.textContent = '';
  };

  // Botón "Guardar como borrador": envía con accion=borrador.
  document.getElementById('btnBorrador').addEventListener('click', function () {
    document.getElementById('accionCampo').value = 'borrador';
    document.getElementById('propuestaForm').submit();
  });

  // Botón "Enviar a revisión": pide confirmación antes (acción irreversible).
  document.getElementById('btnEnviar').addEventListener('click', function () {
    document.getElementById('confirmModal').classList.add('open');
  });
  document.getElementById('btnConfirmEnviar').addEventListener('click', function () {
    document.getElementById('accionCampo').value = 'enviar';
    document.getElementById('propuestaForm').submit();
  });
  document.getElementById('confirmModal').addEventListener('click', function (e) {
    if (e.target === this) this.classList.remove('open');
  });

  // Limpieza de errores en vivo al editar un campo (mismo patrón que edit).
  document.querySelectorAll('input,select,textarea').forEach(function (f) {
    f.addEventListener('input',  function () {
      this.style.borderColor = '';
      var err = this.parentElement.querySelector('.field-error');
      if (err) err.remove();
    });
    f.addEventListener('change', function () {
      this.style.borderColor = '';
      var err = this.parentElement.querySelector('.field-error');
      if (err) err.remove();
    });
  });

})();
</script>
@endpush
