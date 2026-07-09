@extends('layouts.app')
@section('title', 'Digitalización — ' . $tramite->nombre_oficial)

@section('content')
<div class="page-default">

  {{-- VOLVER --}}
  <div style="margin-bottom:12px">
    <a href="{{ route('digitalizacion.index') }}" class="btn btn-outline" style="font-size:12px">← Biblioteca</a>
  </div>

  {{-- HEADER --}}
  <div class="card card-pad" style="margin-bottom:20px">
    <div class="row-center" style="margin-bottom:8px;flex-wrap:wrap;gap:8px">
      <span class="badge {{ $tramite->esServicio() ? 'badge-info' : 'badge-default' }}">{{ $tramite->naturalezaLegible() }}</span>
      <span class="chip chip-flujo-{{ $tramite->flujo_estado }}">{{ $tramite->flujoEstadoLegible() }}</span>
      <span class="chip chip-dig-{{ $tramite->digitalizacion_estado }}">{{ $tramite->digitalizacionEstadoLegible() }}</span>
      @if($tramite->homoclave)
        <span style="font-family:monospace;font-size:12px;color:var(--muted)">{{ $tramite->homoclave }}</span>
      @endif
    </div>
    <h2 style="margin:0 0 4px;color:var(--primary)">{{ $tramite->nombre_oficial }}</h2>
    <p style="margin:0;color:var(--muted);font-size:13px">
      {{ $tramite->dependencia->nombre ?? '—' }}
      @if($tramite->unidad) · {{ $tramite->unidad->nombre }} @endif
    </p>
  </div>

  {{-- PESTAÑAS --}}
  @php
    $tab = request('tab', 'resumen');
    $tabs = [
      'resumen'       => ['label' => 'Resumen',       'icono' => 'ti-info-circle'],
      'flujo'         => ['label' => 'Flujo',          'icono' => 'ti-git-branch'],
      'reingenieria'  => ['label' => 'Reingeniería',   'icono' => 'ti-refresh'],
      'diagrama'      => ['label' => 'Diagrama',       'icono' => 'ti-chart-dots-3'],
      'descargas'     => ['label' => 'Descargas',      'icono' => 'ti-download'],
    ];
  @endphp
  <div class="dshow-tabs">
    @foreach($tabs as $clave => $info)
      <a href="{{ route('digitalizacion.show', [$tramite, 'tab' => $clave]) }}"
         class="dshow-tab {{ $tab === $clave ? 'activo' : '' }}">
        <i class="ti {{ $info['icono'] }}"></i>
        {{ $info['label'] }}
      </a>
    @endforeach
  </div>

  {{-- CONTENIDO DE LA PESTAÑA --}}
  <div class="dshow-content">

    {{-- ALERTA DE CAMBIO POST-FIRMA --}}
    @if($tramite->digitalizacion_estado === \App\Models\Tramite::DIG_REQUIERE_REVISION)
      <div class="dshow-alerta-cambio">
        <div class="dshow-alerta-icono"><i class="ti ti-alert-triangle"></i></div>
        <div class="dshow-alerta-body">
          <strong>Cambio detectado después de la firma</strong>
          <p>El proceso fue modificado después de firmar la reingeniería
            @if($tramite->reingenieriaActiva)
              v{{ $tramite->reingenieriaActiva->version }}
            @endif
            . Debe generarse una nueva versión y recabar nuevamente las firmas.</p>
          @if(auth()->user()->tienePermiso('digitalizacion.reingenieria'))
            <form method="POST" action="{{ route('digitalizacion.reingenieria.nuevaVersion', $tramite) }}" style="margin-top:8px">
              @csrf
              <button type="submit" class="btn btn-sm"
                onclick="return confirm('¿Crear una nueva versión de reingeniería? Se copiará el flujo TO-BE anterior como base.')">
                Crear nueva versión
              </button>
            </form>
          @endif
        </div>
      </div>
    @endif

    {{-- ═══ RESUMEN ═══ --}}
    @if($tab === 'resumen')
      <div class="card">
        <div class="review-section-head"><div><h3>Datos generales</h3><p>Identificación del trámite o servicio.</p></div></div>
        <div class="review-data-grid">
          <div><span>Nombre oficial</span><strong>{{ $tramite->nombre_oficial }}</strong></div>
          <div><span>Tipo</span><strong>{{ $tramite->tipoLegible() }}</strong></div>
          <div><span>Dependencia</span><strong>{{ $tramite->dependencia->nombre ?? '—' }}</strong></div>
          <div><span>Unidad administrativa</span><strong>{{ $tramite->unidad->nombre ?? '—' }}</strong></div>
          <div><span>Objetivo</span><strong>{{ $tramite->objetivo ?? '—' }}</strong></div>
          <div><span>Población objetivo</span><strong>{{ $tramite->poblacion_objetivo ?? '—' }}</strong></div>
          <div><span>Plazo de resolución</span><strong>{{ $tramite->plazo_resolucion_cantidad ? $tramite->plazo_resolucion_cantidad . ' ' . $tramite->plazo_resolucion_unidad : '—' }}</strong></div>
          <div><span>Nivel de digitalización</span><strong>{{ $tramite->nivel_digitalizacion ?? 'No registrado' }}</strong></div>
        </div>
      </div>

      {{-- Resumen de estado de digitalización --}}
      <div class="card" style="margin-top:16px">
        <div class="review-section-head"><div><h3>Estado de digitalización</h3><p>Progreso del trámite en el flujo de digitalización.</p></div></div>
        <div class="review-data-grid">
          <div>
            <span>Estado del flujo (AS-IS)</span>
            <strong><span class="chip chip-flujo-{{ $tramite->flujo_estado }}">{{ $tramite->flujoEstadoLegible() }}</span></strong>
          </div>
          <div>
            <span>Origen de digitalización</span>
            <strong>
              @if($tramite->digitalizacion_origen === 'agenda')
                <span class="chip chip-blue">Agenda</span>
              @elseif($tramite->digitalizacion_origen === 'directa')
                <span class="chip chip-amber">Reingeniería directa</span>
              @else
                Sin asignar
              @endif
            </strong>
          </div>
          <div>
            <span>Reingeniería</span>
            <strong>
              @if($tramite->reingenieriaActiva)
                <span class="chip chip-reing-{{ $tramite->reingenieriaActiva->estado }}">
                  {{ $tramite->reingenieriaActiva->estadoLegible() }}
                </span>
                (v{{ $tramite->reingenieriaActiva->version }})
              @else
                Sin reingeniería
              @endif
            </strong>
          </div>
          <div>
            <span>Digitalización</span>
            <strong><span class="chip chip-dig-{{ $tramite->digitalizacion_estado }}">{{ $tramite->digitalizacionEstadoLegible() }}</span></strong>
          </div>
          <div>
            <span>¿Puede iniciar digitalización?</span>
            <strong>
              @if($tramite->puedeIniciarDigitalizacion())
                <span class="chip chip-green">Sí</span>
              @else
                <span class="chip chip-gray">No — requiere flujo aprobado y reingeniería firmada</span>
              @endif
            </strong>
          </div>
          @if($tramite->reingenieriaActiva?->agenda_accion_id)
            <div class="u-span-2">
              <span>Acción de agenda vinculada</span>
              <strong>
                @php $accionVinculada = $tramite->reingenieriaActiva->accionAgenda; @endphp
                @if($accionVinculada)
                  {{ $accionVinculada->folio ?? 'Acción #'.$accionVinculada->id }}
                  — {{ ucfirst($accionVinculada->tipo) }}
                  · {{ ucfirst(str_replace('_', ' ', $accionVinculada->estatus)) }}
                @else
                  Acción eliminada
                @endif
              </strong>
            </div>
          @endif
        </div>

        {{-- Acciones de digitalización --}}
        @if(auth()->user()->tienePermiso('digitalizacion.digitalizar'))
          <div style="padding:0 20px 16px;display:flex;gap:10px;flex-wrap:wrap">
            @if($tramite->puedeIniciarDigitalizacion() && $tramite->digitalizacion_estado === \App\Models\Tramite::DIG_NO_INICIADA)
              <form method="POST" action="{{ route('digitalizacion.iniciar', $tramite) }}">
                @csrf
                <button type="submit" class="btn"
                  onclick="return confirm('¿Iniciar la digitalización? Se validará el checklist completo.')">
                  <i class="ti ti-player-play"></i> Iniciar digitalización
                </button>
              </form>
            @elseif($tramite->digitalizacion_estado === \App\Models\Tramite::DIG_LISTA)
              <form method="POST" action="{{ route('digitalizacion.iniciar', $tramite) }}">
                @csrf
                <button type="submit" class="btn">
                  <i class="ti ti-player-play"></i> Iniciar digitalización
                </button>
              </form>
            @elseif($tramite->digitalizacion_estado === \App\Models\Tramite::DIG_EN_DIGITALIZACION)
              <form method="POST" action="{{ route('digitalizacion.completar', $tramite) }}">
                @csrf
                <button type="submit" class="btn"
                  onclick="return confirm('¿Marcar como digitalizado? Esta acción indica que el trámite ya fue configurado en el sistema digital.')">
                  <i class="ti ti-check"></i> Completar digitalización
                </button>
              </form>
            @elseif($tramite->digitalizacion_estado === \App\Models\Tramite::DIG_DIGITALIZADO)
              <span class="chip chip-green" style="padding:8px 16px;font-size:13px">
                <i class="ti ti-check"></i> Digitalizado
              </span>
            @endif

            @if(!$tramite->puedeIniciarDigitalizacion() && !in_array($tramite->digitalizacion_estado, [\App\Models\Tramite::DIG_EN_DIGITALIZACION, \App\Models\Tramite::DIG_DIGITALIZADO]))
              <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px">
                <i class="ti ti-info-circle"></i>
                Checklist para iniciar:
                @if(!$tramite->tieneFlujoAprobado()) <span class="chip chip-red" style="font-size:10px">Flujo no aprobado</span> @else <span class="chip chip-green" style="font-size:10px">Flujo ✓</span> @endif
                @if(!$tramite->reingenieriaActiva?->estaFirmada()) <span class="chip chip-red" style="font-size:10px">Reingeniería no firmada</span> @else <span class="chip chip-green" style="font-size:10px">Reingeniería ✓</span> @endif
              </div>
            @endif
          </div>
        @endif
        </div>
      </div>

    {{-- ═══ FLUJO ═══ --}}
    @elseif($tab === 'flujo')
      <div class="card">
        <div class="review-section-head">
          <div>
            <h3>Flujo del proceso actual (AS-IS)</h3>
            <p>
              Estado: <span class="chip chip-flujo-{{ $tramite->flujo_estado }}">{{ $tramite->flujoEstadoLegible() }}</span>
              @if($tramite->flujo_aprobado_en)
                · Aprobado {{ $tramite->flujo_aprobado_en->format('d/m/Y') }}
              @endif
            </p>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            {{-- Botón: Iniciar levantamiento --}}
            @if($tramite->flujo_estado === \App\Models\Tramite::FLUJO_SIN_FLUJO && auth()->user()->tienePermiso('tramites.editar'))
              <form method="POST" action="{{ route('flujo.iniciar', $tramite) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-sm">Iniciar levantamiento</button>
              </form>
            @endif

            {{-- Botón: Enviar a revisión --}}
            @if(in_array($tramite->flujo_estado, [\App\Models\Tramite::FLUJO_EN_CAPTURA, \App\Models\Tramite::FLUJO_OBSERVADO]) && auth()->user()->tienePermiso('tramites.editar'))
              <form method="POST" action="{{ route('flujo.enviarRevision', $tramite) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-sm"
                  onclick="return confirm('¿Enviar el flujo a revisión?')">
                  Enviar a revisión
                </button>
              </form>
            @endif

            {{-- Botón: Aprobar flujo --}}
            @if($tramite->flujo_estado === \App\Models\Tramite::FLUJO_EN_REVISION && auth()->user()->tienePermiso('tramites.aprobar'))
              <form method="POST" action="{{ route('flujo.aprobar', $tramite) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-sm"
                  onclick="return confirm('¿Aprobar el flujo? Esto habilita la reingeniería TO-BE.')">
                  Aprobar flujo
                </button>
              </form>
              <button type="button" class="btn btn-outline btn-sm"
                onclick="document.getElementById('modalObservarFlujo').classList.add('open')">
                Observar
              </button>
            @endif

            {{-- Link: Editar pasos en el wizard --}}
            @if(in_array($tramite->flujo_estado, [\App\Models\Tramite::FLUJO_EN_CAPTURA, \App\Models\Tramite::FLUJO_OBSERVADO, \App\Models\Tramite::FLUJO_SIN_FLUJO]))
              @if(auth()->user()->tienePermiso('tramites.editar') && $tramite->puedeSerEditado())
                <a href="{{ route('tramites.edit', $tramite) }}" class="btn btn-outline btn-sm">
                  Editar pasos en wizard
                </a>
              @endif
            @endif
          </div>
        </div>

        @if($tramite->procesosAtencion->isEmpty())
          <div style="padding:32px;text-align:center;color:var(--muted)">
            <i class="ti ti-git-branch" style="font-size:40px;display:block;margin-bottom:8px"></i>
            <strong>Sin flujo capturado</strong>
            <p style="font-size:12px">El enlace debe capturar el proceso de atención en el wizard del trámite.</p>
          </div>
        @else
          {{-- Pasos enriquecidos --}}
          <div style="padding:16px 20px">
            @foreach($tramite->procesosAtencion->sortBy('orden') as $paso)
              <div class="flujo-paso {{ $paso->subpaso > 0 ? 'flujo-subpaso' : '' }}">
                <div class="flujo-paso-num">
                  <i class="ti {{ $paso->iconoTipoPaso() }}"></i>
                  {{ $paso->numeroLegible() }}
                </div>
                <div class="flujo-paso-body">
                  <div class="flujo-paso-header">
                    <strong>{{ $paso->accion ?? 'Sin acción' }}</strong>
                    <span class="flujo-paso-tipo">{{ $paso->tipoPasoLegible() }}</span>
                    @if($paso->es_digital)
                      <span class="flujo-paso-digital">Digital</span>
                    @endif
                  </div>
                  @if($paso->detalle)
                    <p class="flujo-paso-detalle">{{ $paso->detalle }}</p>
                  @endif
                  <div class="flujo-paso-meta">
                    @if($paso->actor)
                      <span><i class="ti ti-user"></i> {{ $paso->actor }}</span>
                    @endif
                    @if($paso->area)
                      <span><i class="ti ti-building"></i> {{ $paso->area }}</span>
                    @endif
                    @if($paso->duracion_estimada)
                      <span><i class="ti ti-clock"></i> {{ $paso->duracion_estimada }}</span>
                    @endif
                  </div>
                  @if($paso->entrada || $paso->salida)
                    <div class="flujo-paso-io">
                      @if($paso->entrada)
                        <span class="flujo-io-in">Entrada: {{ $paso->entrada }}</span>
                      @endif
                      @if($paso->salida)
                        <span class="flujo-io-out">Salida: {{ $paso->salida }}</span>
                      @endif
                    </div>
                  @endif
                </div>
              </div>
            @endforeach
          </div>

          {{-- Resumen del flujo --}}
          <div style="padding:0 20px 16px;display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--muted)">
            <span>{{ $tramite->procesosAtencion->count() }} pasos</span>
            <span>{{ $tramite->procesosAtencion->where('es_digital', true)->count() }} digitalizables</span>
            <span>{{ $tramite->procesosAtencion->whereNotNull('actor')->pluck('actor')->unique()->count() }} actores</span>
          </div>
        @endif
      </div>

      {{-- Modal: Observar flujo --}}
      @if($tramite->flujo_estado === \App\Models\Tramite::FLUJO_EN_REVISION)
        <div class="modal-backdrop" id="modalObservarFlujo">
          <div class="modal">
            <form method="POST" action="{{ route('flujo.observar', $tramite) }}">
              @csrf
              <div class="modal-head">
                <div><h3>Observar flujo</h3><p>Indique qué debe corregirse.</p></div>
                <button type="button" class="modal-close" aria-label="Cerrar"
                  onclick="document.getElementById('modalObservarFlujo').classList.remove('open')"></button>
              </div>
              <div class="modal-body">
                <div class="field">
                  <label>Observación *</label>
                  <textarea name="observacion_flujo" rows="4" required
                    placeholder="Describa qué debe corregir el enlace en el levantamiento del flujo."></textarea>
                </div>
              </div>
              <div class="modal-actions">
                <button type="button" class="btn btn-outline"
                  onclick="document.getElementById('modalObservarFlujo').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn">Enviar observación</button>
              </div>
            </form>
          </div>
        </div>
      @endif

    {{-- ═══ REINGENIERÍA ═══ --}}
    @elseif($tab === 'reingenieria')
      @if($tramite->reingenieriaActiva)
        @php $reing = $tramite->reingenieriaActiva; @endphp
        <div class="card">
          <div class="review-section-head">
            <div>
              <h3>Reingeniería TO-BE — Versión {{ $reing->version }}</h3>
              <p>{{ $reing->estadoLegible() }} · Origen: {{ $reing->origen === 'agenda' ? 'Agenda' : 'Directa' }}</p>
            </div>
            @if(!$reing->estaFirmada() && auth()->user()->tienePermiso('digitalizacion.reingenieria'))
              <div style="display:flex;gap:8px">
                <a href="{{ route('digitalizacion.reingenieria.editar', [$tramite, $reing]) }}" class="btn btn-outline btn-sm">Editar TO-BE</a>
                @if($reing->flujo_to_be && $reing->estado !== \App\Models\Reingenieria::ESTADO_PENDIENTE_FIRMAS)
                  <form method="POST" action="{{ route('digitalizacion.reingenieria.enviarFirma', [$tramite, $reing]) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-sm" onclick="return confirm('¿Enviar la reingeniería a firma? El Enlace y el Sujeto Obligado deberán firmar.')">
                      Enviar a firma
                    </button>
                  </form>
                @endif
              </div>
            @endif
            @if($reing->estado === \App\Models\Reingenieria::ESTADO_PENDIENTE_FIRMAS)
              <a href="{{ route('firmas.mostrar', ['tipo' => 'reingenieria', 'id' => $reing->id]) }}" class="btn btn-sm">
                Ir a firmar
              </a>
            @endif
          </div>

          <div class="review-data-grid">
            <div><span>Estado</span><strong><span class="chip chip-reing-{{ $reing->estado }}">{{ $reing->estadoLegible() }}</span></strong></div>
            <div><span>Versión</span><strong>v{{ $reing->version }}</strong></div>
            <div><span>Origen</span><strong>{{ $reing->origen === 'agenda' ? 'Agenda de Digitalización' : 'Reingeniería directa' }}</strong></div>
            @if($reing->esDirecta())
              <div><span>Motivo</span><strong>{{ ucfirst(str_replace('_', ' ', $reing->motivo_directa ?? '—')) }}</strong></div>
              <div class="u-span-2"><span>Justificación</span><strong>{{ $reing->justificacion ?? '—' }}</strong></div>
            @endif
            @if($reing->hash_reingenieria)
              <div class="u-span-2"><span>Hash de reingeniería</span><strong style="font-family:monospace;font-size:11px;word-break:break-all">{{ $reing->hash_reingenieria }}</strong></div>
            @endif
          </div>
        </div>

        {{-- Firmas --}}
        <div class="card" style="margin-top:16px">
          <div class="review-section-head"><div><h3>Firmas de la reingeniería</h3><p>Se requieren las firmas del Enlace y del Sujeto Obligado.</p></div></div>
          <div class="review-data-grid">
            @php
              $firmaEnlace = $reing->firmas->where('tipo', 'aceptacion_enlace')->where('estatus', 'activa')->first();
              $firmaSujeto = $reing->firmas->where('tipo', 'aceptacion_sujeto')->where('estatus', 'activa')->first();
            @endphp
            <div>
              <span>Firma del Enlace</span>
              @if($firmaEnlace)
                <strong class="chip chip-green">
                  Firmado por {{ $firmaEnlace->firmante_nombre }}
                  · {{ $firmaEnlace->fecha->format('d/m/Y H:i') }}
                </strong>
              @else
                <strong class="chip chip-amber">Pendiente</strong>
              @endif
            </div>
            <div>
              <span>Firma del Sujeto Obligado</span>
              @if($firmaSujeto)
                <strong class="chip chip-green">
                  Firmado por {{ $firmaSujeto->firmante_nombre }}
                  · {{ $firmaSujeto->fecha->format('d/m/Y H:i') }}
                </strong>
              @else
                <strong class="chip chip-amber">Pendiente</strong>
              @endif
            </div>
          </div>
        </div>

        {{-- Flujo TO-BE estructurado --}}
        @if($reing->flujo_to_be)
          <div class="card" style="margin-top:16px">
            <div class="review-section-head"><div><h3>Flujo TO-BE</h3><p>Proceso propuesto después de la reingeniería.</p></div></div>
            <div class="review-list">
              @foreach($reing->flujo_to_be as $idx => $paso)
                <article>
                  <b>{{ $idx + 1 }}</b>
                  <div>
                    <strong>{{ $paso['accion'] ?? $paso['nombre'] ?? 'Paso ' . ($idx + 1) }}</strong>
                    @if(!empty($paso['detalle']))
                      <p style="margin:4px 0 0;font-size:12px;color:var(--muted)">{{ $paso['detalle'] }}</p>
                    @endif
                  </div>
                  <span>{{ ucfirst($paso['tipo'] ?? '') }}</span>
                </article>
              @endforeach
            </div>
          </div>
        @endif

      @else
        {{-- Sin reingeniería --}}
        <div class="card">
          <div style="padding:40px;text-align:center">
            <i class="ti ti-refresh" style="font-size:40px;color:var(--muted);display:block;margin-bottom:8px"></i>
            <strong>Sin reingeniería</strong>
            <p style="color:var(--muted);font-size:12px;margin:8px 0 16px">
              @if(!$tramite->tieneFlujoAprobado())
                El trámite necesita un flujo aprobado antes de iniciar la reingeniería.
              @else
                El flujo está aprobado. Puede crear una reingeniería TO-BE.
              @endif
            </p>
            @if($tramite->tieneFlujoAprobado() && auth()->user()->tienePermiso('digitalizacion.reingenieria'))
              <a href="{{ route('digitalizacion.reingenieria.crear', $tramite) }}" class="btn">Crear reingeniería</a>
            @endif
          </div>
        </div>
      @endif

    {{-- ═══ DIAGRAMA ═══ --}}
    @elseif($tab === 'diagrama')
      @php
        $diagrama = $tramite->reingenieriaActiva?->diagramas?->first();
      @endphp
      <div class="card">
        <div class="review-section-head">
          <div><h3>Diagrama</h3><p>Visualización Mermaid / Draw.io del flujo.</p></div>
          @if($tramite->reingenieriaActiva?->estaFirmada() && auth()->user()->tienePermiso('digitalizacion.diagrama'))
            <div style="display:flex;gap:8px">
              @if(!$diagrama)
                <form method="POST" action="{{ route('digitalizacion.diagrama.generar', $tramite) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm">Generar Mermaid</button>
                </form>
              @else
                <form method="POST" action="{{ route('digitalizacion.diagrama.generar', $tramite) }}">
                  @csrf
                  <button type="submit" class="btn btn-outline btn-sm">Regenerar</button>
                </form>
              @endif
            </div>
          @endif
        </div>

        @if(!$tramite->reingenieriaActiva?->estaFirmada())
          <div style="padding:40px;text-align:center;color:var(--muted)">
            <i class="ti ti-lock" style="font-size:40px;display:block;margin-bottom:8px"></i>
            <strong>Reingeniería no firmada</strong>
            <p style="font-size:12px">El diagrama solo se puede generar después de que la reingeniería esté firmada por Enlace y Sujeto Obligado.</p>
          </div>
        @elseif(!$diagrama || !$diagrama->tieneMermaid())
          <div style="padding:40px;text-align:center;color:var(--muted)">
            <i class="ti ti-chart-dots-3" style="font-size:40px;display:block;margin-bottom:8px"></i>
            <strong>Sin diagrama generado</strong>
            <p style="font-size:12px">Haga clic en "Generar Mermaid" para crear el diagrama desde el flujo TO-BE.</p>
          </div>
        @else
          {{-- Vista previa Mermaid renderizada --}}
          <div class="dshow-mermaid" id="mermaidPreview"></div>

          {{-- Código fuente colapsable --}}
          <details style="padding:0 20px 12px">
            <summary style="font-size:12px;color:var(--muted);cursor:pointer">Ver código Mermaid</summary>
            <pre class="dshow-mermaid-code">{{ $diagrama->contenido_mermaid }}</pre>
          </details>

          {{-- Draw.io embebido (colapsable) --}}
          <details style="padding:0 20px 16px">
            <summary style="font-size:12px;color:var(--muted);cursor:pointer">Abrir en editor visual (Draw.io)</summary>
            <div style="margin-top:8px;border:1px solid var(--surface-high);border-radius:var(--radius);overflow:hidden">
              <iframe
                id="drawioFrame"
                src="https://embed.diagrams.net/?embed=1&proto=json&spin=1&ui=min&noSaveBtn=1&noExitBtn=1"
                style="width:100%;height:500px;border:0;">
              </iframe>
            </div>
            <p style="font-size:11px;color:var(--muted);margin:6px 0 0">
              El editor visual es solo para ajustes de presentación. Los cambios de lógica deben hacerse en la reingeniería.
            </p>
          </details>

          {{-- Botones de descarga --}}
          <div class="dshow-descargas">
            <a href="{{ route('digitalizacion.diagrama.descargar', [$diagrama, 'formato' => 'png']) }}" class="btn btn-outline btn-sm"><i class="ti ti-photo"></i> PNG</a>
            <a href="{{ route('digitalizacion.diagrama.descargar', [$diagrama, 'formato' => 'svg']) }}" class="btn btn-outline btn-sm"><i class="ti ti-vector"></i> SVG</a>
            @if($tramite->reingenieriaActiva->firmasCompletas())
              <a href="{{ route('digitalizacion.diagrama.descargar', [$diagrama, 'formato' => 'pdf']) }}" class="btn btn-sm"><i class="ti ti-file-text"></i> PDF oficial</a>
            @else
              <button class="btn btn-sm" disabled title="Requiere firmas completas de Enlace y Sujeto Obligado">
                <i class="ti ti-lock"></i> PDF oficial
              </button>
            @endif
          </div>
        @endif
      </div>

    {{-- ═══ DESCARGAS ═══ --}}
    @elseif($tab === 'descargas')
      <div class="card">
        <div class="review-section-head"><div><h3>Historial de descargas</h3><p>Bitácora de todas las descargas realizadas.</p></div></div>
        @php
          $descargas = $tramite->diagramas->flatMap->descargas->sortByDesc('created_at');
        @endphp
        @if($descargas->isEmpty())
          <div style="padding:32px;text-align:center;color:var(--muted)">
            <i class="ti ti-download-off" style="font-size:40px;display:block;margin-bottom:8px"></i>
            <strong>Sin descargas registradas</strong>
          </div>
        @else
          <div style="padding:0 20px 16px">
            <table style="width:100%">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Usuario</th>
                  <th>Formato</th>
                  <th>Hash</th>
                </tr>
              </thead>
              <tbody>
                @foreach($descargas as $d)
                  <tr>
                    <td style="font-size:12px">{{ \Carbon\Carbon::parse($d->created_at)->format('d/m/Y H:i') }}</td>
                    <td style="font-size:12px">{{ $d->usuario->name ?? '—' }}</td>
                    <td><span class="chip chip-gray" style="text-transform:uppercase">{{ $d->formato }}</span></td>
                    <td style="font-family:monospace;font-size:10px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis">{{ $d->hash_archivo_generado ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    @endif

  </div>
</div>

<style>
  /* Alerta de cambio post-firma */
  .dshow-alerta-cambio {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: #fef3c7;
    border: 1.5px solid #f59e0b;
    border-radius: var(--radius);
    margin-bottom: 16px;
  }
  .dshow-alerta-icono {
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    background: #f59e0b;
    color: white;
    display: grid;
    place-items: center;
    font-size: 18px;
    flex-shrink: 0;
  }
  .dshow-alerta-body strong {
    display: block;
    font-size: 13px;
    color: #92400e;
    margin-bottom: 4px;
  }
  .dshow-alerta-body p {
    font-size: 12px;
    color: #78350f;
    margin: 0;
    line-height: 1.5;
  }

  /* Flujo de pasos enriquecido */
  .flujo-paso {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--surface-high);
  }
  .flujo-paso:last-child { border-bottom: none; }
  .flujo-subpaso { margin-left: 32px; }
  .flujo-paso-num {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    background: var(--surface-tint);
    color: var(--primary);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    gap: 1px;
  }
  .flujo-paso-num > i { font-size: 14px; }
  .flujo-paso-body { flex: 1; min-width: 0; }
  .flujo-paso-header { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
  .flujo-paso-header strong { font-size: 13px; color: var(--text); }
  .flujo-paso-tipo {
    font-size: 10px;
    padding: 1px 7px;
    border-radius: var(--radius-pill);
    background: var(--surface-low);
    color: var(--muted);
    font-weight: 600;
  }
  .flujo-paso-digital {
    font-size: 10px;
    padding: 1px 7px;
    border-radius: var(--radius-pill);
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 600;
  }
  .flujo-paso-detalle { font-size: 12px; color: var(--muted); margin: 0 0 6px; line-height: 1.5; }
  .flujo-paso-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 11px;
    color: var(--muted);
  }
  .flujo-paso-meta > span { display: inline-flex; align-items: center; gap: 3px; }
  .flujo-paso-meta i { font-size: 13px; }
  .flujo-paso-io {
    display: flex;
    gap: 12px;
    margin-top: 6px;
    font-size: 11px;
    flex-wrap: wrap;
  }
  .flujo-io-in { color: #065f46; }
  .flujo-io-out { color: #1d4ed8; }

  /* Pestañas */
  .dshow-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--surface-high);
    overflow-x: auto;
  }
  .dshow-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    white-space: nowrap;
    transition: all .15s ease;
  }
  .dshow-tab:hover { color: var(--text); }
  .dshow-tab.activo {
    color: var(--primary);
    border-bottom-color: var(--primary);
  }
  .dshow-tab > i { font-size: 16px; }

  /* Chips de estado */
  .chip-flujo-sin_flujo,
  .chip-dig-no_iniciada       { background: var(--surface-low, #f3f4f6); color: var(--muted); }
  .chip-flujo-flujo_en_captura,
  .chip-flujo-flujo_en_revision,
  .chip-dig-lista_para_digitalizacion,
  .chip-reing-en_reingenieria,
  .chip-reing-aprobada_para_firma,
  .chip-reing-pendiente_firmas,
  .chip-dig-en_digitalizacion { background: #fef3c7; color: #92400e; }
  .chip-flujo-flujo_observado,
  .chip-reing-reingenieria_observada,
  .chip-dig-requiere_revision_por_cambio { background: #fee2e2; color: #991b1b; }
  .chip-flujo-flujo_aprobado,
  .chip-reing-reingenieria_firmada,
  .chip-dig-digitalizado      { background: #d1fae5; color: #065f46; }

  /* Mermaid preview */
  .dshow-mermaid { padding: 20px; overflow-x: auto; }
  .dshow-mermaid-code {
    background: var(--surface-low, #f8f8f6);
    border: 1px solid var(--surface-high);
    border-radius: var(--radius);
    padding: 16px;
    font-size: 12px;
    font-family: monospace;
    white-space: pre-wrap;
    line-height: 1.6;
    color: var(--text);
  }

  /* Descargas */
  .dshow-descargas {
    display: flex;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid var(--surface-high);
    flex-wrap: wrap;
  }

  /* Helpers */
  .u-span-2 { grid-column: span 2; }
  .chip-blue { background: #dbeafe; color: #1d4ed8; }
  .chip-amber { background: #fef3c7; color: #92400e; }
  .chip-green { background: #d1fae5; color: #065f46; }
  .chip-gray { background: var(--surface-low, #f3f4f6); color: var(--muted); }

  @media (max-width: 640px) {
    .dshow-tabs { gap: 0; }
    .dshow-tab { padding: 8px 12px; font-size: 12px; }
    .review-data-grid { grid-template-columns: 1fr; }
    .u-span-2 { grid-column: span 1; }
  }
</style>

{{-- Mermaid.js para renderizar diagramas --}}
@if($tab === 'diagrama' && isset($diagrama) && $diagrama?->tieneMermaid())
<script type="module">
  import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

  mermaid.initialize({
    startOnLoad: false,
    theme: 'base',
    fontFamily: 'Arial, sans-serif',
    themeVariables: {
      primaryColor: '#f3e8ed',
      primaryBorderColor: '#9d0b4f',
      primaryTextColor: '#3d3d3a',
      lineColor: '#73726c',
      secondaryColor: '#dbeafe',
      tertiaryColor: '#fef3c7',
    },
  });

  var mermaidCode = @json($diagrama->contenido_mermaid);
  var container = document.getElementById('mermaidPreview');

  try {
    var { svg } = await mermaid.render('mermaid-diagram', mermaidCode);
    container.innerHTML = svg;
    container.querySelector('svg').style.maxWidth = '100%';
    container.querySelector('svg').style.height = 'auto';
  } catch (e) {
    container.innerHTML = '<p style="color:var(--muted);padding:20px;text-align:center">No se pudo renderizar el diagrama. Verifique el código Mermaid.</p>'
      + '<pre class="dshow-mermaid-code">' + mermaidCode + '</pre>';
  }

  // ── Draw.io: cargar el Mermaid como XML al abrir el editor ──────
  var drawioFrame = document.getElementById('drawioFrame');
  if (drawioFrame) {
    window.addEventListener('message', function (evt) {
      if (!evt.data) return;
      try {
        var msg = typeof evt.data === 'string' ? JSON.parse(evt.data) : evt.data;
        if (msg.event === 'init') {
          // Draw.io está listo: enviarle un diagrama vacío para iniciar
          drawioFrame.contentWindow.postMessage(JSON.stringify({
            action: 'load',
            xml: '<mxGraphModel><root><mxCell id="0"/><mxCell id="1" parent="0"/></root></mxGraphModel>',
          }), '*');
        }
      } catch (e) {}
    });
  }
</script>
@endif
@endsection
