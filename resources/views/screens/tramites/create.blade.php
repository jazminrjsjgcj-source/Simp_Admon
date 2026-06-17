@extends('layouts.app')
@section('title', 'Nuevo Trámite')

@section('content')
<div class="page-body">

  <div class="screen-head">
    <div><h2 class="nowrap">Registrar Nuevo Trámite</h2><p class="nowrap">Paso <span id="stepLabel">1</span> de 7</p></div>
    <div class="head-actions"><x-btn-ejemplo tipo="tramite" /></div>
  </div>

  <form method="POST" action="{{ route('tramites.store') }}" id="tramiteForm">
    @csrf
    <div class="wizard-shell">

      <div class="wizard-stepper" id="tramiteStepper">
        <div class="wizard-step active" data-step="1"><span class="wizard-dot"></span><strong>Identificación</strong><small>Datos base</small></div>
        <div class="wizard-step"        data-step="2"><span class="wizard-dot"></span><strong>Información</strong><small>Objetivo</small></div>
        <div class="wizard-step"        data-step="3"><span class="wizard-dot"></span><strong>Operación</strong><small>ATDT</small></div>
        <div class="wizard-step"        data-step="4"><span class="wizard-dot"></span><strong>Requisitos</strong><small>Documentos</small></div>
        <div class="wizard-step"        data-step="5"><span class="wizard-dot"></span><strong>Fundamento</strong><small>Normativa</small></div>
        <div class="wizard-step"        data-step="6"><span class="wizard-dot"></span><strong>Portal</strong><small>Ciudadanía</small></div>
        <div class="wizard-step"        data-step="7"><span class="wizard-dot"></span><strong>Confirmar</strong><small>Guardar</small></div>
      </div>

      <div class="wizard-panel">

        {{-- PASO 1: Identificación --}}
        <div class="wizard-content active" data-panel="1">
          <div class="wizard-panel-head">
            <span class="wizard-panel-icon"></span>
            <div>
              <h3>Identificación del trámite</h3>
              <p>Nombre, unidad responsable y clave del trámite.</p>
            </div>
          </div>

          <div class="wizard-fields">

            {{-- Nombre oficial: texto libre con longitud máxima --}}
            <div class="field span-2">
              <label for="nombre_oficial">Nombre oficial del trámite *</label>
              <input id="nombre_oficial" name="nombre_oficial" type="text" required
                     maxlength="500"
                     placeholder="Ej. Licencia de Funcionamiento Comercial Tipo A"
                     value="{{ old('nombre_oficial') }}">
              <small class="help-small">Nombre completo según la disposición que lo crea.</small>
            </div>

            {{-- Tipo de trámite desde catálogo --}}
            <div class="field">
              <label for="tipo_tramite_id">Tipo de trámite</label>
              <select id="tipo_tramite_id" name="tipo_tramite_id">
                <option value="">— Seleccione tipo —</option>
                @foreach(\App\Models\TipoTramite::activos()->get() as $tt)
                  <option value="{{ $tt->id }}"
                    {{ old('tipo_tramite_id') == $tt->id ? 'selected' : '' }}
                    title="{{ $tt->descripcion }}">
                    {{ $tt->nombre }}
                  </option>
                @endforeach
              </select>
              <small class="help-small">Clasifica el trámite (Licencia, Permiso, Registro…).</small>
            </div>

            {{-- Dependencia: se toma del perfil del usuario, no se puede cambiar --}}
            @php
              $miDependencia   = auth()->user()->dependencia;
              $misUnidades     = \App\Models\UnidadAdministrativa::where('dependencia_id', auth()->user()->dependencia_id)
                                    ->orderBy('nombre')->get();
              $unidadAutoId    = $misUnidades->count() === 1 ? $misUnidades->first()->id : null;
            @endphp
            <input type="hidden" name="dependencia_id" value="{{ auth()->user()->dependencia_id }}">

            <div class="field">
              <label>Dependencia</label>
              <input type="text" value="{{ $miDependencia?->nombre ?? 'Sin dependencia asignada' }}" disabled class="u-input-disabled">
              <small class="help-small">Asignada desde tu perfil de usuario. Contacta al administrador para cambiarla.</small>
            </div>

            {{-- Fase F.1: Unidad administrativa auto-selección --}}
            <div class="field">
              <label for="unidad_id">Unidad administrativa</label>
              @if($unidadAutoId)
                <input type="hidden" name="unidad_id" value="{{ $unidadAutoId }}">
                <input type="text" value="{{ $misUnidades->first()->nombre }}" disabled class="u-input-readonly">
                <small class="help-small">Seleccionada automáticamente.</small>
              @elseif($misUnidades->count() > 1)
                <select id="unidad_id" name="unidad_id">
                  <option value="">— Seleccione unidad —</option>
                  @foreach($misUnidades as $uni)
                    <option value="{{ $uni->id }}" {{ old('unidad_id') == $uni->id ? 'selected' : '' }}>
                      {{ $uni->codigo }} — {{ $uni->nombre }}
                    </option>
                  @endforeach
                </select>
                @error('unidad_id')<span class="field-error">{{ $message }}</span>@enderror
              @else
                <input type="text" value="Sin unidades activas para esta dependencia" disabled style="background:#f3f4f6;color:#9ca3af">
              @endif
            </div>

            {{-- La homoclave se genera de dependencia + unidad administrativa.
                 La previsualización en vivo está en el script al final. --}}

            {{-- Sujeto Obligado: titular de la dependencia del usuario (auto, solo lectura) --}}
            @php
              $sujetoObligado = \App\Models\SujetoObligado::vigenteDe(auth()->user()->dependencia_id);
            @endphp
            <div class="field">
              <label>Sujeto Obligado</label>
              <input type="text"
                value="{{ $sujetoObligado?->nombre ?? 'Sin titular registrado' }}"
                disabled class="u-input-disabled">
              <input type="hidden" name="sujeto_obligado_id" value="{{ $sujetoObligado?->id }}">
              {{-- servidor_publico conserva el nombre del titular para el guardado existente --}}
              <input type="hidden" name="servidor_publico" value="{{ $sujetoObligado?->nombre }}">
              @if($sujetoObligado?->cargo)
                <small class="help-small">{{ $sujetoObligado->cargo }}</small>
              @elseif(!$sujetoObligado)
                <small class="help-small">Tu dependencia no tiene titular registrado. Pídele al administrador que lo agregue en Catálogos → Sujetos obligados.</small>
              @endif
            </div>

            {{-- Enlace: la persona que captura (usuario logueado, auto, solo lectura) --}}
            <div class="field">
              <label>Enlace</label>
              <input type="text" value="{{ auth()->user()->name }}" disabled class="u-input-disabled">
              <input type="hidden" name="enlace_id" value="{{ auth()->id() }}">
              <small class="help-small">Persona que registra el trámite.</small>
            </div>

            {{-- Homoclave: se genera automáticamente al elegir la unidad administrativa --}}
            <div class="field">
              <label for="homoclave_input">Homoclave</label>
              <input id="homoclave_input" name="homoclave" type="text"
                     maxlength="50"
                     placeholder="Se generará al elegir la unidad administrativa"
                     value="{{ old('homoclave') }}"
                     readonly
                     class="u-input-readonly">
              <small class="help-small">Se genera automáticamente: {{ config('punta.prefijo_homoclave', 'LPZ') }}-(siglas dependencia)-(siglas unidad)-(consecutivo).</small>
            </div>

          </div>
        </div>

        {{-- PASO 2: Información general --}}
        <div class="wizard-content" data-panel="2">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Información general</h3><p>Objetivo, población, sector económico y plazos.</p></div></div>
          <div class="wizard-fields">
            <div class="field span-2">
              <label>Objetivo del trámite *</label>
              <textarea required name="objetivo" rows="4" placeholder="Describa qué resuelve o qué beneficio otorga...">{{ old('objetivo') }}</textarea>
            </div>
            <div class="field">
              <label>Población objetivo</label>
              <select name="dirigido_a">
                <option value="ambas"  {{ old('dirigido_a','ambas')==='ambas'?'selected':'' }}>Personas físicas y morales</option>
                <option value="fisica" {{ old('dirigido_a')==='fisica'?'selected':'' }}>Solo personas físicas</option>
                <option value="moral"  {{ old('dirigido_a')==='moral'?'selected':'' }}>Solo personas morales</option>
              </select>
            </div>
            <div class="field">
              <label>Frecuencia</label>
              <select name="frecuencia">
                <option {{ old('frecuencia')==='Alta'?'selected':'' }}>Alta</option>
                <option {{ old('frecuencia')==='Media'?'selected':'' }}>Media</option>
                <option {{ old('frecuencia')==='Baja'?'selected':'' }}>Baja</option>
                <option {{ old('frecuencia')==='Eventual'?'selected':'' }}>Eventual</option>
              </select>
            </div>

            {{-- Sector y subsector económico SCIAN --}}
            <x-selector-scian :sector="old('sector_id')" :subsector="old('subsector_id')" />

            <x-input-validado tipo="numero_entero" name="volumen_anual" label="Volumen anual estimado *" min="0" placeholder="Ej. 1250" :value="old('volumen_anual')" />
            <div class="field">
              <label>Plazo máximo de resolución</label>
              <div class="split-fields">
                <input name="plazo_resolucion_cantidad" type="number" min="0" step="1" inputmode="numeric" placeholder="Cantidad" value="{{ old('plazo_resolucion_cantidad') }}">
                <select name="plazo_resolucion_unidad">
                  <option value="habiles"   {{ old('plazo_resolucion_unidad','habiles')==='habiles'?'selected':'' }}>Días hábiles</option>
                  <option value="naturales" {{ old('plazo_resolucion_unidad')==='naturales'?'selected':'' }}>Días naturales</option>
                  <option value="meses"     {{ old('plazo_resolucion_unidad')==='meses'?'selected':'' }}>Meses</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- PASO 3: Operación ATDT --}}
        <div class="wizard-content" data-panel="3">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Operación y costos burocráticos</h3><p>Capture el esfuerzo que representa para la ciudadanía (metodología ATDT).</p></div></div>
          <div class="wizard-fields">
            <x-input-validado tipo="numero_entero" name="num_areas" label="Número de áreas que participan" min="0" placeholder="Ej. 3" :value="old('num_areas')" />
            <div class="field">
              <label>Áreas que participan</label>
              <input name="areas_participantes" placeholder="Ej. Ventanilla, Tesorería, Protección Civil" value="{{ old('areas_participantes') }}">
            </div>
            <x-input-validado tipo="numero_entero" name="visitas_requeridas" label="Visitas requeridas" min="0" placeholder="0" :value="old('visitas_requeridas')" />
            <div class="field">
              <label>Nivel de digitalización</label>
              <select name="nivel_digitalizacion">
                <option value="1" {{ old('nivel_digitalizacion',1)==1?'selected':'' }}>1 — Presencial completo</option>
                <option value="2" {{ old('nivel_digitalizacion')==2?'selected':'' }}>2 — Descarga de formatos</option>
                <option value="3" {{ old('nivel_digitalizacion')==3?'selected':'' }}>3 — Envío digital parcial</option>
                <option value="4" {{ old('nivel_digitalizacion')==4?'selected':'' }}>4 — Digital con pago en línea</option>
                <option value="5" {{ old('nivel_digitalizacion')==5?'selected':'' }}>5 — 100% en línea</option>
              </select>
            </div>
            <x-field-help label="Monto de derechos (pesos)">
              <input type="number" id="montoDerechosCalc" name="monto_derechos_display" readonly
                value="{{ old('monto_derechos', 0) }}"
                style="background:var(--surface-low);cursor:not-allowed">
              <small class="campo-nota">Se calcula del total de "Pago de derechos" en la ficha ciudadana.</small>
            </x-field-help>
            <x-input-validado tipo="numero_entero" name="copias_cantidad" label="Número de copias requeridas" min="0" placeholder="0" :value="old('copias_cantidad', 0)" />
            <x-input-validado tipo="numero_decimal" name="copias_precio" label="Precio por copia (pesos)" min="0" step="0.01" placeholder="1.50" :value="old('copias_precio', 1.50)" />
          </div>
          <div class="assist-box" style="margin-top:16px">
            <strong>Fórmula ATDT:</strong> CBD = Derechos + Copias + Requisitos con costo · CBI = Tiempo requisitos + Tiempo resolución · CBU = CBD + CBI · CBT = CBU × Volumen anual.
          </div>
        </div>

        {{-- PASO 4: Requisitos --}}
        <div class="wizard-content" data-panel="4">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Requisitos</h3><p>Registre cada documento necesario de forma clara.</p></div></div>
          <div id="reqContainer">
            <article class="requirement-card">
              <strong>Requisito 1</strong>
              <div class="wizard-fields">
                <x-field-help label="Nombre del requisito" class="span-2">
                  <input name="requisitos[0][nombre]" placeholder="Ej. Identificación oficial vigente">
                </x-field-help>
                <x-field-help label="¿Se presenta en original?">
                  <select name="requisitos[0][original]"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select>
                </x-field-help>
                <x-field-help label="¿Se presenta en copia?">
                  <select name="requisitos[0][copia]"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select>
                </x-field-help>
                <x-field-help label="Tiempo de obtención">
                  <div class="split-fields split-fields-labeled">
                    <div><label class="split-label">Días háb.</label><input name="requisitos[0][dias]" type="number" min="0" max="365" value="0"></div>
                    <div><label class="split-label">Horas</label><input name="requisitos[0][horas]" type="number" min="0" max="7" value="0"></div>
                    <div><label class="split-label">Minutos</label><input name="requisitos[0][minutos]" type="number" min="0" max="59" value="0"></div>
                  </div>
                </x-field-help>
                <x-field-help label="Observaciones para publicación" class="span-2">
                  <textarea name="requisitos[0][observaciones]" rows="2" placeholder="Vigencia, formato, dependencia emisora..."></textarea>
                </x-field-help>
              </div>
            </article>
          </div>
          <div class="section-actions section-actions-start mt-3">
            <button type="button" class="btn btn-outline btn-sm" onclick="agregarRequisito()">+ Agregar requisito</button>
          </div>
        </div>

        {{-- PASO 5: Fundamento jurídico --}}
        <div class="wizard-content" data-panel="5">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Fundamento jurídico</h3><p>Normativa que da origen al trámite. Puede citar del catálogo de regulaciones o escribir manualmente.</p></div></div>
          <div class="wizard-fields">

            {{-- Citar desde el catálogo de regulaciones (si hay regulaciones convertidas) --}}
            <x-citar-regulacion :selected="old('regulacion_id')" label="Citar regulación del catálogo (opcional)" />

            <div class="field span-2">
              <label>Nombre de la norma (si no está en el catálogo)</label>
              <input name="fundamento_normativa" placeholder="Ej. Reglamento de Comercio del Municipio de La Paz" value="{{ old('fundamento_normativa') }}">
              <small class="help-small">Si ya citó del catálogo, puede dejar este campo vacío.</small>
            </div>
            <div class="field">
              <label>Tipo de norma</label>
              <select name="fundamento_tipo">
                <option value="">Seleccione...</option>
                <option {{ old('fundamento_tipo')==='Reglamento'?'selected':'' }}>Reglamento</option>
                <option {{ old('fundamento_tipo')==='Lineamiento'?'selected':'' }}>Lineamiento</option>
                <option {{ old('fundamento_tipo')==='Manual'?'selected':'' }}>Manual</option>
                <option {{ old('fundamento_tipo')==='Acuerdo'?'selected':'' }}>Acuerdo</option>
                <option {{ old('fundamento_tipo')==='Ley'?'selected':'' }}>Ley</option>
              </select>
            </div>
            <div class="field">
              <label>Artículo y fracción</label>
              <input name="fundamento_articulo" placeholder="Ej. Artículo 45, Fracción II" value="{{ old('fundamento_articulo') }}">
            </div>
            <div class="field span-2">
              <label>Resumen ciudadano del fundamento</label>
              <textarea name="fundamento_resumen" rows="3" placeholder="Explique de forma simple por qué existe este trámite...">{{ old('fundamento_resumen') }}</textarea>
            </div>
          </div>
        </div>

        {{-- PASO 6: Portal ciudadano --}}
        <div class="wizard-content" data-panel="6">
          <div class="wizard-panel-head"><span class="wizard-panel-icon"></span><div><h3>Ficha para portal ciudadano</h3><p>Información visible para la ciudadanía.</p></div></div>
          <div class="wizard-fields">
            <x-field-help label="Nombre del documento resultado">
              <input name="portal_nombre" placeholder="Nombre ciudadano del trámite" value="{{ old('portal_nombre') }}">
            </x-field-help>
            <x-field-help label="Resultado que se obtiene">
              <input name="portal_resultado" placeholder="Ej. Licencia de funcionamiento, constancia, permiso" value="{{ old('portal_resultado') }}">
            </x-field-help>
            <x-field-help label="Modalidad de atención">
              <select name="portal_modalidad">
                <option value="Presencial" {{ old('portal_modalidad')==='Presencial'?'selected':'' }}>Presencial</option>
                <option value="En línea"   {{ old('portal_modalidad')==='En línea'?'selected':'' }}>En línea</option>
                <option value="Mixta"      {{ old('portal_modalidad')==='Mixta'?'selected':'' }}>Mixta</option>
              </select>
            </x-field-help>
            <x-field-help label="Objetivo del trámite" class="span-2">
              <textarea name="portal_descripcion" rows="3" placeholder="Descripción accesible para la ciudadanía...">{{ old('portal_descripcion') }}</textarea>
            </x-field-help>
            <x-field-help label="Costo público">
              <div class="costo-grupo">
                <select id="costoTipo" onchange="actualizarCosto()">
                  <option value="gratuito" {{ old('costo_tipo') === 'con_costo' ? '' : 'selected' }}>Gratuito</option>
                  <option value="con_costo" {{ old('costo_tipo') === 'con_costo' ? 'selected' : '' }}>Con costo</option>
                </select>
                <input type="number" id="costoMonto" min="0" step="0.01"
                  placeholder="0.00" value="{{ old('costo_monto', 0) }}"
                  oninput="actualizarCosto()">
                <span class="costo-moneda">MXN</span>
              </div>
              {{-- Lo que se guarda: texto legible generado del selector + monto --}}
              <input type="hidden" name="portal_costo" id="costoTexto" value="{{ old('portal_costo', 'Gratuito') }}">
              <input type="hidden" name="costo_tipo" id="costoTipoHidden" value="{{ old('costo_tipo', 'gratuito') }}">
              <input type="hidden" name="costo_monto" id="costoMontoHidden" value="{{ old('costo_monto', 0) }}">
            </x-field-help>

            {{-- Pago de derechos: conceptos de cobro ligados al trámite,
                 independientes del costo público. Un trámite puede ser
                 gratuito y aun así tener derechos por pagar. --}}
            <x-field-help label="Pago de derechos" class="span-2">
              <div id="derechosLista" class="derechos-lista">
                {{-- filas generadas por JS --}}
              </div>
              <div class="derechos-pie">
                <button type="button" class="btn btn-outline btn-sm" onclick="agregarDerecho()">+ Agregar derecho</button>
                <span class="derechos-total">Total derechos: <strong id="derechosTotal">$0.00 MXN</strong></span>
              </div>
              <input type="hidden" name="derechos_json" id="derechosJson" value="{{ old('derechos_json', '[]') }}">
            </x-field-help>
            {{-- Fase F.4: Horarios de atención estructurados --}}
            <x-field-help label="Horarios de atención">
              <div style="display:flex;gap:8px;align-items:center">
                <input id="horarioResumen" name="portal_horario" readonly
                  placeholder="Haga clic en 'Configurar' para establecer horarios"
                  value="{{ old('portal_horario') }}"
                  style="background:#f9fafb;flex:1;cursor:pointer"
                  onclick="abrirHorarios()">
                <input type="hidden" id="horariosJson" name="horarios_json" value="{{ old('horarios_json') }}">
                <button type="button" class="btn btn-outline btn-sm" onclick="abrirHorarios()" style="white-space:nowrap">Configurar</button>
              </div>
            </x-field-help>
            <x-field-help label="Teléfono">
              <input name="portal_telefono" placeholder="(612) 123-4567" value="{{ old('portal_telefono') }}">
            </x-field-help>
            <x-field-help label="Correo">
              <input name="portal_correo" type="email" placeholder="tramites@lapaz.gob.mx" value="{{ old('portal_correo') }}">
            </x-field-help>
          </div>
        </div>

        {{-- PASO 7: Confirmación --}}
        <div class="wizard-content" data-panel="7">
          <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:32px 16px">
            <div style="width:56px;height:56px;border-radius:999px;background:#16a34a;display:grid;place-items:center;margin:0 0 16px;font-size:22px;font-weight:800;color:white">✓</div>
            <h3 style="margin:0 0 8px">Confirmar trámite</h3>
            <p style="margin:0 0 20px;color:#667085">Revise la información antes de guardar el registro.</p>
            <div class="assist-box" style="max-width:600px;width:100%;text-align:left">
              <strong>Guardar como borrador:</strong> guarda el avance sin enviarlo a revisión. Podrá editarlo y enviarlo después.<br>
              <strong>Guardar y enviar:</strong> guarda y envía a revisión. El trámite será visible para Jurídico, Sujeto Obligado y Revisora.
            </div>
          </div>
        </div>

        {{-- NAVEGACIÓN --}}
        <div class="wizard-foot card-actions">
          <button type="button" class="btn btn-outline hidden" id="btnAtras" onclick="wizardNav(-1)">Atrás</button>
          <div class="form-actions form-actions-end">
            <a href="{{ route('tramites.index') }}" class="btn btn-outline">Cancelar</a>
            <button type="button" class="btn" id="btnSig" onclick="wizardNav(1)">Siguiente</button>
            <button type="submit" class="btn btn-outline hidden" id="btnBorrador" name="accion" value="borrador">Guardar como borrador</button>
            <button type="submit" class="btn btn-success hidden" id="btnGuardar" name="accion" value="enviar">Guardar y enviar</button>
          </div>
        </div>

      </div>
    </div>
  </form>

</div>
{{-- Fase F.4: Modal de horarios de atención --}}
  <div id="modalHorarios">
    <div class="horario-modal-inner">
      <h3 style="margin:0 0 4px">Horarios de atención</h3>
      <p style="margin:0 0 16px;font-size:13px;color:#6b7280">Configure los días y horarios en que se atiende el trámite.</p>

      <div class="horario-accesos">
        <span style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;align-self:center">Accesos rápidos:</span>
        <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('lv')">Lun – Vie</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('ls')">Lun – Sáb</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('todos')">Todos los días</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="horarioLimpiar()">Limpiar</button>
      </div>

      <div id="horariosGrid">
        <!-- generado por JS -->
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-outline" onclick="cerrarHorarios()">Cancelar</button>
        <button type="button" class="btn btn-success" onclick="guardarHorarios()">Aplicar</button>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
<script>
// #8 Costo público: sincroniza selector + monto con los campos ocultos.
function actualizarCosto() {
  var tipo  = document.getElementById('costoTipo');
  var monto = document.getElementById('costoMonto');
  if (!tipo || !monto) return;

  var esGratuito = tipo.value === 'gratuito';
  if (esGratuito) {
    monto.value = 0;
    monto.disabled = true;
  } else {
    monto.disabled = false;
  }

  var valor = parseFloat(monto.value) || 0;
  var texto = esGratuito ? 'Gratuito' : ('$' + valor.toFixed(2) + ' MXN');

  document.getElementById('costoTexto').value      = texto;
  document.getElementById('costoTipoHidden').value = tipo.value;
  document.getElementById('costoMontoHidden').value= valor;
}
document.addEventListener('DOMContentLoaded', actualizarCosto);

// Pago de derechos: lista dinámica de conceptos (concepto + monto).
var _derechos = [];

function renderDerechos() {
  var cont = document.getElementById('derechosLista');
  if (!cont) return;
  cont.innerHTML = '';

  if (_derechos.length === 0) {
    cont.innerHTML = '<p class="derechos-vacio">Sin conceptos de derechos. Si el trámite no cobra derechos, déjalo vacío.</p>';
  }

  _derechos.forEach(function (d, i) {
    var fila = document.createElement('div');
    fila.className = 'derecho-fila';
    fila.innerHTML =
      '<input type="text" placeholder="Concepto (ej. Derecho de inspección)" value="' + (d.concepto || '').replace(/"/g, '&quot;') + '" oninput="setDerecho(' + i + ', \'concepto\', this.value)">' +
      '<input type="number" min="0" step="0.01" placeholder="0.00" value="' + (d.monto || 0) + '" oninput="setDerecho(' + i + ', \'monto\', this.value)">' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="quitarDerecho(' + i + ')">Quitar</button>';
    cont.appendChild(fila);
  });

  // Recalcular total y sincronizar el hidden.
  var total = _derechos.reduce(function (s, d) { return s + (parseFloat(d.monto) || 0); }, 0);
  document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
  document.getElementById('derechosJson').value = JSON.stringify(_derechos);
  sincronizarMontoDerechos(total);
}

// Refleja el total de derechos en el campo (solo lectura) del paso de
// costos burocráticos, para que el usuario vea de dónde sale.
function sincronizarMontoDerechos(total) {
  var campo = document.getElementById('montoDerechosCalc');
  if (campo) campo.value = total.toFixed(2);
}

function agregarDerecho() {
  _derechos.push({ concepto: '', monto: 0 });
  renderDerechos();
}

function quitarDerecho(i) {
  _derechos.splice(i, 1);
  renderDerechos();
}

function setDerecho(i, campo, valor) {
  if (_derechos[i]) {
    _derechos[i][campo] = campo === 'monto' ? (parseFloat(valor) || 0) : valor;
    // Solo actualizar total/hidden sin re-render (para no perder el foco).
    var total = _derechos.reduce(function (s, d) { return s + (parseFloat(d.monto) || 0); }, 0);
    document.getElementById('derechosTotal').textContent = '$' + total.toFixed(2) + ' MXN';
    document.getElementById('derechosJson').value = JSON.stringify(_derechos);
    sincronizarMontoDerechos(total);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  try {
    var inicial = JSON.parse(document.getElementById('derechosJson').value || '[]');
    if (Array.isArray(inicial)) _derechos = inicial;
  } catch (e) { _derechos = []; }
  renderDerechos();
});
</script>

<script>
(function () {
  var cur = 1, total = 7;

  /**
   * Previsualiza la homoclave en vivo a partir de la dependencia (fija
   * del perfil) y la unidad administrativa seleccionada. Llama al
   * endpoint que arma el formato LPZ-(siglas dep)-(siglas unidad)-(N).
   */
  (function previsualizarHomoclave() {
    var depInput   = document.querySelector('input[name="dependencia_id"]');
    var unidadEl   = document.querySelector('[name="unidad_id"]');
    var homoclave  = document.getElementById('homoclave_input');
    if (!depInput || !unidadEl || !homoclave) return;

    function actualizar() {
      var depId = depInput.value;
      var uniId = unidadEl.value;
      if (!depId || !uniId) {
        homoclave.value = '';
        return;
      }
      fetch('/api/homoclave/previsualizar?dependencia_id=' + depId + '&unidad_id=' + uniId, {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (data && data.homoclave) {
            homoclave.value = data.homoclave;
          }
        })
        .catch(function () { /* silencioso: si falla, el backend la genera al guardar */ });
    }

    // Si la unidad es un select, recalcula al cambiar.
    if (unidadEl.tagName === 'SELECT') {
      unidadEl.addEventListener('change', actualizar);
    }
    // Dispara una vez al cargar (cubre el caso de unidad auto-seleccionada).
    actualizar();
  })();

  var required = {
    1: [
      { name: 'nombre_oficial',         label: 'Nombre oficial' },
      { name: 'unidad_id',              label: 'Unidad administrativa' },
    ],
    2: [
      { name: 'objetivo',               label: 'Objetivo del trámite' },
      { name: 'volumen_anual',          label: 'Volumen anual estimado' },
    ],
    3: [
      { name: 'monto_derechos',          label: 'Monto de derechos' },
      { name: 'plazo_resolucion_cantidad',label: 'Plazo de resolución' },
    ],
    4: [],
    5: [],
    6: [],
  };

  function clearErrors(panel) {
    panel.querySelectorAll('.field-error').forEach(function(e){ e.remove(); });
    panel.querySelectorAll('.field-error-input').forEach(function(el){
      el.classList.remove('field-error-input');
      el.style.borderColor = '';
    });
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
    var first = null;
    rules.forEach(function(r) {
      var field = panel.querySelector('[name="'+r.name+'"]');
      if (!field) return;
      if (!field.value || !field.value.trim()) {
        showError(field, r.label + ' es obligatorio.');
        if (!first) first = field;
        ok = false;
      }
    });
    if (first) first.focus();
    return ok;
  }

  // Registra qué pasos ya fueron completados
  var completed = {};

  function go(step) {
    document.querySelectorAll('[data-panel]').forEach(function(p){
      p.classList.toggle('active', parseInt(p.dataset.panel) === step);
    });
    document.querySelectorAll('[data-step]').forEach(function(s){
      var n = parseInt(s.dataset.step);
      var esCompleto = (completed[n] === true) && (n !== step);
      s.classList.toggle('active',    n === step);
      s.classList.toggle('done',      esCompleto);
      s.classList.toggle('completed', esCompleto);
    });
    document.getElementById('stepLabel').textContent = step;

    var btnAtras    = document.getElementById('btnAtras');
    var btnSig      = document.getElementById('btnSig');
    var btnGuardar  = document.getElementById('btnGuardar');
    var btnBorrador = document.getElementById('btnBorrador');

    // Mostrar/ocultar botones
    if (step > 1)      { btnAtras.classList.remove('hidden'); }
    else               { btnAtras.classList.add('hidden'); }
    if (step < total)  { btnSig.classList.remove('hidden'); btnGuardar.classList.add('hidden'); btnBorrador.classList.add('hidden'); }
    else               { btnSig.classList.add('hidden');    btnGuardar.classList.remove('hidden'); btnBorrador.classList.remove('hidden'); }

    cur = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  window.wizardNav = function(d) {
    // Al avanzar: validar campos requeridos del paso actual
    if (d > 0) {
      if (!validateStep(cur)) return;
      completed[cur] = true;
    }
    var n = cur + d;
    if (n >= 1 && n <= total) go(n);
  };

  // Limpiar error al escribir
  document.querySelectorAll('input, select, textarea').forEach(function(f) {
    f.addEventListener('input',  function(){ this.style.borderColor=''; var e=this.parentElement.querySelector('.field-error'); if(e) e.remove(); });
    f.addEventListener('change', function(){ this.style.borderColor=''; var e=this.parentElement.querySelector('.field-error'); if(e) e.remove(); });
  });

  // Dependencia → Unidades
  var depSel = document.getElementById('depSelect');
  var uniSel = document.getElementById('unidadSelect');
  if (depSel && uniSel) {
    var allOpts = Array.from(uniSel.querySelectorAll('option[data-dep]'));
    depSel.addEventListener('change', function() {
      var depId = this.value;
      uniSel.innerHTML = '<option value="">Seleccione unidad...</option>';
      allOpts.forEach(function(opt) {
        if (opt.dataset.dep === depId) uniSel.appendChild(opt.cloneNode(true));
      });
    });
  }

  // Requisitos dinámicos
  window.agregarRequisito = function() {
    var container = document.getElementById('reqContainer');
    var i = container.querySelectorAll('article.requirement-card').length;
    var a = document.createElement('article');
    a.className = 'requirement-card';
    a.innerHTML = '<strong class="req-titulo">Requisito '+(i+1)+'</strong>'
      +'<div class="wizard-fields">'
      +'<div class="field span-2"><label>Nombre del requisito</label>'
      +'<input name="requisitos['+i+'][nombre]" placeholder="Nombre del requisito"></div>'
      +'<div class="field"><label>¿Se presenta en original?</label>'
      +'<select name="requisitos['+i+'][original]"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select></div>'
      +'<div class="field"><label>¿Se presenta en copia?</label>'
      +'<select name="requisitos['+i+'][copia]"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select></div>'
      +'<div class="field"><label>Tiempo de obtención</label>'
      +'<div class="split-fields split-fields-labeled">'
      +'<div><label class="split-label">Días háb.</label><input name="requisitos['+i+'][dias]" type="number" min="0" max="365" value="0"></div>'
      +'<div><label class="split-label">Horas</label><input name="requisitos['+i+'][horas]" type="number" min="0" max="7" value="0"></div>'
      +'<div><label class="split-label">Minutos</label><input name="requisitos['+i+'][minutos]" type="number" min="0" max="59" value="0"></div>'
      +'</div></div>'
      +'<div class="field span-2"><label>Observaciones</label>'
      +'<textarea name="requisitos['+i+'][observaciones]" rows="2"></textarea></div>'
      +'</div>'
      +'<div class="section-actions mt-2">'
      +'<button type="button" class="btn btn-outline btn-sm danger" onclick="eliminarRequisito(this)">Eliminar requisito</button>'
      +'</div>';
    container.appendChild(a);
  };

  window.eliminarRequisito = function(btn) {
    btn.closest('article').remove();
    renumerarRequisitos();
  };

  function renumerarRequisitos() {
    var cards = document.querySelectorAll('#reqContainer article.requirement-card');
    cards.forEach(function(card, idx) {
      // Actualizar título visible
      var titulo = card.querySelector('.req-titulo, strong');
      if (titulo) titulo.textContent = 'Requisito ' + (idx + 1);

      // Actualizar name attributes para mantener índices secuenciales
      card.querySelectorAll('input, select, textarea').forEach(function(input) {
        if (input.name) {
          input.name = input.name.replace(/requisitos\[\d+\]/, 'requisitos[' + idx + ']');
        }
      });
    });
  }
  go(1);
})();


  // ─── Fase F.4: Horarios de atención ────────────────────────────
  var DIAS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  var HORA_DEFAULT_INICIO = '09:00', HORA_DEFAULT_FIN = '15:00';
  var horariosData = (function () {
    var raw = document.getElementById('horariosJson').value;
    try { return raw ? JSON.parse(raw) : {}; } catch(e) { return {}; }
  })();

  function renderHorariosGrid() {
    var grid = document.getElementById('horariosGrid');
    grid.innerHTML = '';
    DIAS.forEach(function (dia, i) {
      var row = document.createElement('div');
      row.className = 'horario-row';
      var activo = horariosData[dia] ? horariosData[dia].activo : false;
      var ini = horariosData[dia] ? horariosData[dia].inicio : HORA_DEFAULT_INICIO;
      var fin = horariosData[dia] ? horariosData[dia].fin    : HORA_DEFAULT_FIN;
      row.innerHTML =
        '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;width:110px">'
        + '<input type="checkbox" onchange="toggleDia(\''+dia+'\')" ' + (activo ? 'checked' : '') + '>'
        + '<span style="font-size:13px;font-weight:' + (i < 5 ? '700' : '400') + '">' + dia + '</span>'
        + '</label>'
        + '<div style="display:flex;flex-direction:column;gap:2px">'
        + '<label class="u-meta-label">Apertura</label>'
        + '<input type="time" id="ini_'+i+'" value="'+ini+'" ' + (activo ? '' : 'disabled') + ' class="u-text-sm" onchange="updateHora(\''+dia+'\',\'inicio\',this.value)">'
        + '</div>'
        + '<div style="display:flex;flex-direction:column;gap:2px">'
        + '<label class="u-meta-label">Cierre</label>'
        + '<input type="time" id="fin_'+i+'" value="'+fin+'" ' + (activo ? '' : 'disabled') + ' class="u-text-sm" onchange="updateHora(\''+dia+'\',\'fin\',this.value)">'
        + '</div>';
      grid.appendChild(row);
    });
  }

  function toggleDia(dia) {
    var activo = !horariosData[dia]?.activo;
    if (!horariosData[dia]) horariosData[dia] = { inicio: HORA_DEFAULT_INICIO, fin: HORA_DEFAULT_FIN };
    horariosData[dia].activo = activo;
    renderHorariosGrid();
  }

  function updateHora(dia, campo, valor) {
    if (!horariosData[dia]) horariosData[dia] = { activo: true, inicio: HORA_DEFAULT_INICIO, fin: HORA_DEFAULT_FIN };
    horariosData[dia][campo] = valor;
  }

  function horarioPreset(tipo) {
    var dias = tipo === 'lv' ? DIAS.slice(0,5) : tipo === 'ls' ? DIAS.slice(0,6) : DIAS;
    DIAS.forEach(function (d) {
      horariosData[d] = { activo: dias.includes(d), inicio: HORA_DEFAULT_INICIO, fin: HORA_DEFAULT_FIN };
    });
    renderHorariosGrid();
  }

  function horarioLimpiar() {
    horariosData = {};
    renderHorariosGrid();
  }

  function guardarHorarios() {
    var activos = DIAS.filter(function (d) { return horariosData[d]?.activo; });
    document.getElementById('horariosJson').value = JSON.stringify(horariosData);
    // Generar resumen legible
    var resumen = '';
    if (activos.length === 7) {
      resumen = 'Lun–Dom ' + horariosData[activos[0]].inicio + '–' + horariosData[activos[0]].fin + ' hrs';
    } else if (activos.length === 5 && JSON.stringify(activos) === JSON.stringify(DIAS.slice(0,5))) {
      resumen = 'Lun–Vie ' + horariosData['Lunes'].inicio + '–' + horariosData['Lunes'].fin + ' hrs';
    } else if (activos.length > 0) {
      resumen = activos.map(function (d) {
        return d.substring(0,3) + ' ' + horariosData[d].inicio + '–' + horariosData[d].fin;
      }).join(', ');
    }
    document.getElementById('horarioResumen').value = resumen;
    cerrarHorarios();
  }

  function abrirHorarios() { renderHorariosGrid(); document.getElementById('modalHorarios').classList.add('open'); }
  function cerrarHorarios() { document.getElementById('modalHorarios').classList.remove('open'); }


</script>
@endpush