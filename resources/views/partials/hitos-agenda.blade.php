{{--
  Hitos de avance de una acción de agenda (Grupo 3: evidencia + visto bueno).

  Flujo por hito:
    1. El ENLACE sube evidencia (archivo) → el hito queda "pendiente" de visto bueno.
       Es flexible: puede subir evidencia de cualquier hito, sin orden.
    2. La REVISORA revisa la evidencia y aprueba o rechaza (con motivo).
       Aprobado → completado. Rechazado → vuelve al enlace para corregir.

  Estados (estado_aprobacion): sin_evidencia · pendiente · aprobado · rechazado.

  Espera:
    $agenda       → la acción de agenda
    $hitos        → colección de HitoAgenda ordenada por 'orden'
    $porcentaje   → entero 0-100 (cuenta solo hitos aprobados)
    $puedeMarcar  → bool: el usuario es enlace de la dependencia (sube evidencia)
    $puedeAprobar → bool: el usuario es revisora (da visto bueno)
    $ayudas       → mapa [clave => texto de ayuda]
--}}
@php
  $hitos        = $hitos        ?? collect();
  $porcentaje   = $porcentaje   ?? 0;
  $puedeMarcar  = $puedeMarcar  ?? false;
  $puedeAprobar = $puedeAprobar ?? false;
  $ayudas       = $ayudas       ?? [];

  $etiquetaEstado = [
    'sin_evidencia' => 'Sin evidencia',
    'pendiente'     => 'Pendiente de visto bueno',
    'aprobado'      => 'Aprobado',
    'rechazado'     => 'Rechazado',
  ];
@endphp

@if($hitos->isNotEmpty())
  <div class="hitos-avance">
    {{-- Barra de progreso (solo hitos aprobados) --}}
    <div class="hitos-progreso-head">
      <span>Avance de implementación</span>
      <strong>{{ $porcentaje }}%</strong>
    </div>
    <div class="hitos-barra">
      <div class="hitos-barra-relleno" style="width: {{ $porcentaje }}%"></div>
    </div>

    <ul class="hitos-lista">
      @foreach($hitos as $hito)
        @php
          $estado = $hito->estado_aprobacion ?? 'sin_evidencia';
          $tieneEvidencia = !empty($hito->evidencia_archivo);
        @endphp
        <li class="hito-item hito-estado-{{ $estado }}">
          <span class="hito-check" aria-hidden="true">
            @if($estado === 'aprobado') ✓
            @elseif($estado === 'pendiente') ⏳
            @elseif($estado === 'rechazado') ✕
            @else ○ @endif
          </span>

          <div class="hito-cuerpo">
            <div class="hito-titulo-fila">
              <span class="hito-nombre">{{ $hito->nombre }}</span>
              @if(!empty($ayudas[$hito->clave]))
                <span class="hito-ayuda-icono" tabindex="0">?
                  <span class="hito-tooltip">{{ $ayudas[$hito->clave] }}</span>
                </span>
              @endif
              <span class="hito-badge hito-badge-{{ $estado }}">{{ $etiquetaEstado[$estado] ?? $estado }}</span>
            </div>

            {{-- Evidencia subida y datos de aprobación --}}
            @if($tieneEvidencia)
              <span class="hito-meta">
                Evidencia:
                <a href="{{ route('agenda.hito.evidencia.descargar', ['agenda' => $agenda->id, 'hito' => $hito->id]) }}">
                  {{ $hito->evidencia_nombre ?? 'archivo' }}
                </a>
                @if($hito->completadoPor) · subido por {{ $hito->completadoPor->name }} @endif
              </span>
            @endif

            @if($estado === 'aprobado' && $hito->aprobadoPor)
              <span class="hito-meta">
                Aprobado por {{ $hito->aprobadoPor->name }}
                @if($hito->fecha_aprobacion) el {{ \Carbon\Carbon::parse($hito->fecha_aprobacion)->format('d/m/Y') }} @endif
              </span>
            @endif

            @if($estado === 'rechazado')
              <span class="hito-meta hito-rechazo">
                Rechazado
                @if($hito->aprobadoPor) por {{ $hito->aprobadoPor->name }}@endif:
                «{{ $hito->motivo_rechazo }}»
              </span>
            @endif

            {{-- ENLACE: subir / reemplazar evidencia (en cualquier estado salvo aprobado) --}}
            @if($puedeMarcar && $estado !== 'aprobado')
              <form method="POST"
                    action="{{ route('agenda.hito.evidencia', ['agenda' => $agenda->id, 'hito' => $hito->id]) }}"
                    enctype="multipart/form-data" class="hito-form-evidencia">
                @csrf
                <x-carga-archivos name="evidencia" :required="true"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" :maxMb="10" />
                <button type="submit" class="btn btn-sm">
                  {{ $tieneEvidencia ? 'Reemplazar evidencia' : 'Subir evidencia' }}
                </button>
              </form>
            @endif

            {{-- REVISORA: aprobar / rechazar (solo si está pendiente) --}}
            @if($puedeAprobar && $estado === 'pendiente')
              <div class="hito-vistobueno">
                <form method="POST"
                      action="{{ route('agenda.hito.aprobar', ['agenda' => $agenda->id, 'hito' => $hito->id]) }}"
                      class="hito-form-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                </form>
                <form method="POST"
                      action="{{ route('agenda.hito.rechazar', ['agenda' => $agenda->id, 'hito' => $hito->id]) }}"
                      class="hito-form-inline hito-form-rechazo">
                  @csrf
                  <input type="text" name="motivo_rechazo" maxlength="500"
                         placeholder="Motivo del rechazo" class="hito-input-motivo">
                  <button type="submit" class="btn btn-sm btn-danger"
                          onclick="return confirmarAccion(this, '¿Rechazar la evidencia de este hito?')">Rechazar</button>
                </form>
              </div>
            @endif
          </div>
        </li>
      @endforeach
    </ul>

    @if($porcentaje >= 100)
      <p class="hitos-listo">Todos los hitos están aprobados.</p>
    @endif
  </div>
@else
  <div class="cal-empty-state">Esta acción aún no tiene hitos de avance.</div>
@endif

<style>
  .hito-badge { font-size:11px; padding:2px 8px; border-radius:999px; margin-left:8px; }
  .hito-badge-sin_evidencia { background:var(--surface-high,#eee); color:var(--muted,#667085); }
  .hito-badge-pendiente { background:#fef3c7; color:#92400e; }
  .hito-badge-aprobado  { background:#dcfce7; color:#166534; }
  .hito-badge-rechazado { background:#fee2e2; color:#991b1b; }
  .hito-rechazo { color:#991b1b; }
  .hito-form-evidencia { margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .hito-vistobueno { margin-top:8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .hito-form-inline { display:flex; gap:6px; align-items:center; }
  .hito-input-motivo { padding:5px 8px; border:1px solid var(--surface-high,#ddd); border-radius:6px; font-size:13px; min-width:180px; }
</style>