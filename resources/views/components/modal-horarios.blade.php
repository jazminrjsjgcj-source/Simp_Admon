@props([
    'name' => 'portal_horario',      // input visible (resumen legible)
    'jsonName' => 'horarios_json',    // input hidden (estructura JSON)
    'label' => 'Horarios de atención',
    'valorResumen' => '',             // resumen precargado (al editar)
    'valorJson' => '',                // JSON precargado (al editar)
])

{{--
  Modal de horarios de atención reutilizable (flujo de 3 pasos).

  Misma UI y misma estructura de datos que el modal embebido en
  tramites/create. Su lógica vive en public/js/horarios.js (autocontenido), que
  debe cargarse en la vista que use este componente:

    @push('scripts')
      <script src="{{ asset('js/horarios.js') }}"></script>
    @endpush

  Uso:
    <x-modal-horarios />
    <x-modal-horarios name="portal_horario" json-name="horarios_json" />
--}}

{{-- Trigger: campo de resumen (solo lectura) + botón Configurar --}}
<div class="horario-trigger">
  <input id="horarioResumen" name="{{ $name }}" readonly
    placeholder="Haga clic en 'Configurar' para establecer horarios"
    value="{{ $valorResumen }}" onclick="abrirHorarios()">
  <input type="hidden" id="horariosJson" name="{{ $jsonName }}" value="{{ $valorJson }}">
  <button type="button" class="btn btn-outline btn-sm" onclick="abrirHorarios()"
    style="white-space:nowrap">Configurar</button>
</div>

{{-- Modal --}}
<div id="modalHorarios">
  <div class="horario-modal-inner">
    <h3 style="margin:0 0 4px">Horarios de atención</h3>
    <p style="margin:0 0 18px;font-size:13px;color:#6b7280">Configure en tres pasos los días y horarios en que se atiende el trámite.</p>

    {{-- PASO 1: horario base --}}
    <div class="horario-paso">
      <div class="horario-paso-num">1</div>
      <div class="horario-paso-cuerpo">
        <h4 class="horario-paso-titulo">Elija el horario base</h4>
        <p class="horario-paso-ayuda">Este horario se aplica a todos los días que marque abajo. Después puede ajustar un día concreto si tiene horario diferente.</p>
        <div class="horario-base-inputs">
          <label>Apertura <input type="time" id="horarioBaseInicio" value="09:00" onchange="setHorarioBase('inicio', this.value)"></label>
          <label>Cierre <input type="time" id="horarioBaseFin" value="15:00" onchange="setHorarioBase('fin', this.value)"></label>
        </div>
      </div>
    </div>

    {{-- PASO 2: días aplicables --}}
    <div class="horario-paso">
      <div class="horario-paso-num">2</div>
      <div class="horario-paso-cuerpo">
        <h4 class="horario-paso-titulo">Marque los días aplicables</h4>
        <div id="horarioChips" class="horario-chips"><!-- chips generados por JS --></div>
        <div class="horario-accesos">
          <span class="horario-accesos-label">Accesos rápidos:</span>
          <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('lv')">Lun – Vie</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('ls')">Lun – Sáb</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="horarioPreset('todos')">Todos</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="horarioLimpiar()">Limpiar</button>
        </div>
      </div>
    </div>

    {{-- PASO 3: vista previa editable --}}
    <div class="horario-paso">
      <div class="horario-paso-num">3</div>
      <div class="horario-paso-cuerpo">
        <h4 class="horario-paso-titulo">Vista previa</h4>
        <p class="horario-paso-ayuda">Revise los días marcados. Puede editar el horario de un día concreto si difiere del base (por ejemplo, sábado reducido).</p>
        <div id="horarioPreview" class="horario-preview"><!-- generado por JS --></div>
      </div>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px">
      <button type="button" class="btn btn-outline" onclick="cerrarHorarios()">Cancelar</button>
      <button type="button" class="btn btn-success" onclick="guardarHorarios()">Aplicar</button>
    </div>
  </div>
</div>
