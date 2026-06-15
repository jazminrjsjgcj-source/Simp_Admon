@extends('layouts.app')
@section('title', 'Detalle de firmas')

@php
  $titulo = $modelo->nombre_oficial ?? $modelo->descripcion ?? $modelo->nombre ?? 'Registro #' . $modelo->id;

  // El lenguaje cambia según el rol: la revisora y el jurídico APRUEBAN
  // (visto bueno con hash), el sujeto y el enlace FIRMAN (firma oficial).
  $esAprobador = auth()->user()->isAnyRol(['revisora', 'juridico']);
  $accionVerbo = $esAprobador ? 'Aprobar' : 'Firmar';
  $accionSust  = $esAprobador ? 'aprobación' : 'firma';
@endphp

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $titulo }}</h2>
      <p class="nowrap">Firmas registradas y disponibles para este registro.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('firmas.index') }}" class="btn btn-outline">Volver al listado</a>
    </div>
  </div>

  {{-- Firmas actuales --}}
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Firmas registradas</h3>
        <p>Cada firma incluye hash de acuse para verificar su integridad.</p>
      </div>
    </div>

    <div class="card-body-padded">
      @if($firmas->isEmpty())
        <p class="text-muted-sm">Aún no hay firmas registradas para este registro.</p>
      @else
        @foreach($firmas as $firma)
          @php $integra = $integridades[$firma->id] ?? false; @endphp

          <div class="card mb-4" style="background:#fbfcfe">
            <div class="card-body-padded">

              <div class="row-center mb-4">
                <strong>{{ $firma->tipoLegible() }}</strong>
                <span class="actions-right">
                  @if($integra)
                    <span class="chip chip-success">Íntegra</span>
                  @else
                    <span class="chip chip-red">Inconsistente</span>
                  @endif
                </span>
              </div>

              <div class="modal-grid">
                <div class="modal-data-item">
                  <span>Firmante</span>
                  <strong>{{ $firma->firmante_nombre ?? $firma->firmante->name ?? '—' }}</strong>
                </div>
                <div class="modal-data-item">
                  <span>Cargo</span>
                  <strong>{{ $firma->firmante_cargo ?? '—' }}</strong>
                </div>
                <div class="modal-data-item">
                  <span>Fecha</span>
                  <strong>{{ $firma->fecha?->format('d/m/Y H:i') ?? '—' }}</strong>
                </div>
                <div class="modal-data-item">
                  <span>IP</span>
                  <strong><code class="help-small">{{ $firma->ip_origen ?? '—' }}</code></strong>
                </div>
                <div class="modal-data-item span-2">
                  <span>Hash de acuse</span>
                  <strong><code class="help-small" style="word-break:break-all">{{ $firma->hash_acuse }}</code></strong>
                </div>
                @if($firma->observaciones)
                  <div class="modal-data-item span-2">
                    <span>Observaciones</span>
                    <strong>{{ $firma->observaciones }}</strong>
                  </div>
                @endif
              </div>

              @if(auth()->user()->isAnyRol(['admin']))
                <div class="section-divided">
                  <details>
                    <summary class="help-small" style="cursor:pointer">Revocar esta firma</summary>
                    <form method="POST" action="{{ route('firmas.revocar', $firma) }}" class="mt-3">
                      @csrf
                      <div class="field">
                        <label class="label-meta">Motivo de la revocación *</label>
                        <textarea required name="motivo" rows="2" minlength="10" placeholder="Explique el motivo de la revocación..."></textarea>
                      </div>
                      <button type="submit" class="btn btn-outline btn-sm mt-2">Revocar firma</button>
                    </form>
                  </details>
                </div>
              @endif

            </div>
          </div>
        @endforeach
      @endif
    </div>
  </div>

  {{-- Acción: registrar nueva firma --}}
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Registrar firma</h3>
        <p>Confirme su {{ $accionSust }} del registro. Queda sellada con hash.</p>
      </div>
    </div>

    <form method="POST" action="{{ route('firmas.firmar', ['tipo' => $tipo, 'id' => $modelo->id]) }}" id="formFirmar">
      @csrf
      <div class="card-body-padded wizard-fields">

        <div class="field span-2">
          <label>Tipo de {{ $accionSust }} *</label>
          <select required name="tipo_firma">
            <option value="">Seleccione...</option>
            @if(auth()->user()->isAnyRol(['enlace','admin']))
              <option value="aceptacion_enlace">Aceptación del enlace</option>
            @endif
            @if(auth()->user()->isAnyRol(['sujeto','enlace','admin']))
              <option value="aceptacion_sujeto">Aceptación del sujeto obligado</option>
            @endif
            @if(auth()->user()->isAnyRol(['revisora','admin']))
              <option value="aprobacion_revisora">Aprobación del revisor</option>
            @endif
            @if(auth()->user()->isAnyRol(['juridico','admin']))
              <option value="aprobacion_juridico">Aprobación de jurídico</option>
            @endif
            @if(auth()->user()->isAnyRol(['admin']))
              <option value="firma_fisica">Firma física (registro manual)</option>
            @endif
          </select>
        </div>

        <div class="field span-2">
          <label>Observaciones</label>
          <textarea name="observaciones" rows="3" placeholder="Notas opcionales sobre esta firma..."></textarea>
        </div>

      </div>
      <div class="card-actions card-actions-end">
        <button type="button" class="btn" onclick="document.getElementById('confirmFirmar').classList.add('open')">
          Registrar {{ $accionSust }}
        </button>
      </div>
    </form>
  </div>

  {{-- Modal de confirmación --}}
  <div class="confirm-modal-backdrop" id="confirmFirmar">
    <div class="confirm-modal">
      <h3>¿Confirmar {{ $accionSust }}?</h3>
      <p>Al confirmar, se generará un hash de acuse con sus datos, IP y fecha. Quedará registrada permanentemente y podrá verificarse en cualquier momento.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmFirmar').classList.remove('open')">Revisar</button>
        <button type="button" class="btn" onclick="document.getElementById('formFirmar').submit()">Sí, {{ strtolower($accionVerbo) }}</button>
      </div>
    </div>
  </div>

</div>
@endsection
