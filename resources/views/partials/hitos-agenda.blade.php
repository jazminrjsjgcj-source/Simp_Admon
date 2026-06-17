{{--
  Hitos de avance de una acción de agenda.

  Lista vertical de hitos con barra de progreso automática. El "Diagnóstico"
  aparece ya completado. El enlace solo puede marcar el SIGUIENTE hito pendiente
  (avance lineal): los anteriores salen bloqueados y marcados, los futuros salen
  bloqueados y vacíos. Cada hito muestra su tooltip de ayuda, y los completados
  muestran fecha y quién los marcó.

  Espera:
    $agenda         → la acción de agenda
    $hitos          → colección de HitoAgenda ordenada por 'orden'
    $porcentaje     → entero 0-100
    $siguienteId    → id del único hito marcable (o null)
    $puedeMarcar    → bool: si el usuario puede marcar hitos
    $ayudas         → mapa [clave => texto de ayuda] para los tooltips
--}}
@php
  $hitos       = $hitos       ?? collect();
  $porcentaje  = $porcentaje  ?? 0;
  $siguienteId = $siguienteId ?? null;
  $puedeMarcar = $puedeMarcar ?? false;
  $ayudas      = $ayudas      ?? [];
@endphp

@if($hitos->isNotEmpty())
  <div class="hitos-avance">
    {{-- Barra de progreso --}}
    <div class="hitos-progreso-head">
      <span>Avance de implementación</span>
      <strong>{{ $porcentaje }}%</strong>
    </div>
    <div class="hitos-barra">
      <div class="hitos-barra-relleno" style="width: {{ $porcentaje }}%"></div>
    </div>

    {{-- Lista vertical de hitos --}}
    <ul class="hitos-lista">
      @foreach($hitos as $hito)
        @php
          $esCompletado = (bool) $hito->completado;
          $esSiguiente  = $siguienteId && $hito->id === $siguienteId;
          $estado       = $esCompletado ? 'completado' : ($esSiguiente ? 'siguiente' : 'bloqueado');
        @endphp
        <li class="hito-item hito-{{ $estado }}">
          <span class="hito-check" aria-hidden="true">
            @if($esCompletado) ✓ @elseif($esSiguiente) ○ @else &nbsp; @endif
          </span>

          <div class="hito-cuerpo">
            <div class="hito-titulo-fila">
              <span class="hito-nombre">{{ $hito->nombre }}</span>
              @if(!empty($ayudas[$hito->clave]))
                <span class="hito-ayuda-icono" tabindex="0">?
                  <span class="hito-tooltip">{{ $ayudas[$hito->clave] }}</span>
                </span>
              @endif
            </div>

            @if($esCompletado)
              <span class="hito-meta">
                {{ $hito->fecha_completado ? \Carbon\Carbon::parse($hito->fecha_completado)->format('d/m/Y') : '' }}
                @if($hito->completadoPor) · {{ $hito->completadoPor->name }} @endif
              </span>
            @endif
          </div>

          {{-- Botón para marcar: solo en el siguiente hito y si tiene permiso --}}
          @if($esSiguiente && $puedeMarcar)
            <form method="POST" action="{{ route('agenda.hito.marcar', ['agenda' => $agenda->id, 'hito' => $hito->id]) }}" class="hito-form">
              @csrf
              <button type="submit" class="btn btn-sm hito-btn-marcar">Marcar completado</button>
            </form>
          @endif
        </li>
      @endforeach
    </ul>

    @if($porcentaje >= 100)
      <p class="hitos-listo">Todos los hitos están completados.</p>
    @endif
  </div>
@else
  <div class="cal-empty-state">Esta acción aún no tiene hitos de avance.</div>
@endif
