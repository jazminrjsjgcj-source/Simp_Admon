<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acuse — {{ $tramite->homoclave ?? 'Sin folio' }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      font-size: 12px;
      line-height: 1.5;
      color: #1a1a2e;
      padding: 32px 48px;
      max-width: 800px;
      margin: 0 auto;
    }

    /* Header */
    .acuse-header {
      text-align: center;
      border-bottom: 3px solid #9d0b4f;
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    .acuse-header h1 { font-size: 16px; font-weight: 800; color: #9d0b4f; margin-bottom: 2px; }
    .acuse-header h2 { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 2px; }
    .acuse-header p  { font-size: 11px; color: #666; }

    /* Secciones */
    .acuse-section { margin-bottom: 20px; }
    .acuse-section-title {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #9d0b4f;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 4px;
      margin-bottom: 10px;
    }

    /* Grid de datos */
    .acuse-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px 24px;
    }
    .acuse-grid.single { grid-template-columns: 1fr; }
    .acuse-field { display: flex; gap: 6px; }
    .acuse-field .label { color: #666; min-width: 140px; flex-shrink: 0; }
    .acuse-field .value { font-weight: 600; color: #1a1a2e; }

    /* Tabla de requisitos */
    .acuse-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .acuse-table th { background: #f8f8fa; font-weight: 700; text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; }
    .acuse-table td { padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 11px; }

    /* Firmas */
    .acuse-firmas { margin-top: 16px; }
    .acuse-firma-item {
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 10px 12px;
      margin-bottom: 8px;
      background: #fcfcfd;
    }
    .acuse-firma-item .firma-tipo { font-weight: 700; font-size: 11px; color: #9d0b4f; }
    .acuse-firma-item .firma-dato { font-size: 11px; color: #444; }

    /* Footer */
    .acuse-footer {
      margin-top: 32px;
      padding-top: 16px;
      border-top: 2px solid #9d0b4f;
      text-align: center;
      font-size: 10px;
      color: #888;
    }
    .acuse-footer .hash { font-family: monospace; font-size: 9px; color: #666; word-break: break-all; }

    /* Impresión */
    @media print {
      body { padding: 16px 24px; }
      .no-print { display: none; }
    }

    /* Botón de imprimir (solo en pantalla) */
    .print-bar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      background: #1a1a2e;
      color: white;
      padding: 10px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      z-index: 100;
    }
    .print-bar a, .print-bar button {
      background: #9d0b4f;
      color: white;
      border: none;
      padding: 6px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 12px;
      text-decoration: none;
    }
    .print-bar button:hover { opacity: 0.9; }
  </style>
</head>
<body>

  {{-- Barra de acciones (no se imprime) --}}
  <div class="print-bar no-print">
    <span>Acuse de trámite — {{ $tramite->homoclave ?? 'Sin folio' }}</span>
    <div style="display:flex;gap:8px">
      <a href="{{ route('tramites.show', $tramite) }}">Volver al trámite</a>
      <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
  </div>

  <div style="padding-top:52px">

    {{-- HEADER INSTITUCIONAL --}}
    <div class="acuse-header">
      <h1>Sistema PUNTA</h1>
      <h2>H. Ayuntamiento de La Paz, B.C.S.</h2>
      <p>Plataforma Única de Normalización de Trámites Administrativos</p>
      <p style="margin-top:8px;font-size:12px;font-weight:700">ACUSE DE REGISTRO DE TRÁMITE</p>
    </div>

    {{-- IDENTIFICACIÓN --}}
    <div class="acuse-section">
      <div class="acuse-section-title">Identificación del trámite</div>
      <div class="acuse-grid">
        <div class="acuse-field"><span class="label">Homoclave:</span><span class="value">{{ $tramite->homoclave ?? 'Sin folio' }}</span></div>
        <div class="acuse-field"><span class="label">Estatus:</span><span class="value">{{ ucfirst(str_replace('_', ' ', $tramite->estatus)) }}</span></div>
        <div class="acuse-field"><span class="label">Dependencia:</span><span class="value">{{ $tramite->dependencia->nombre ?? '' }}</span></div>
        <div class="acuse-field"><span class="label">Fecha de registro:</span><span class="value">{{ $tramite->created_at->format('d/m/Y H:i') }}</span></div>
      </div>
      <div class="acuse-grid single" style="margin-top:6px">
        <div class="acuse-field"><span class="label">Nombre oficial:</span><span class="value">{{ $tramite->nombre_oficial }}</span></div>
      </div>
    </div>

    {{-- INFORMACIÓN GENERAL --}}
    <div class="acuse-section">
      <div class="acuse-section-title">Información general</div>
      <div class="acuse-grid">
        <div class="acuse-field"><span class="label">Dirigido a:</span><span class="value">{{ ucfirst($tramite->dirigido_a ?? 'Ambas') }}</span></div>
        <div class="acuse-field"><span class="label">Volumen anual:</span><span class="value">{{ number_format($tramite->volumen_anual ?? 0) }}</span></div>
        <div class="acuse-field"><span class="label">Plazo resolución:</span><span class="value">@plazo($tramite->plazo_resolucion_cantidad, $tramite->plazo_resolucion_unidad)</span></div>
        <div class="acuse-field"><span class="label">Digitalización:</span><span class="value">{{ $tramite->nivel_digitalizacion ?? '' }} / 5</span></div>
      </div>
      @if($tramite->objetivo)
        <div class="acuse-grid single" style="margin-top:6px">
          <div class="acuse-field"><span class="label">Objetivo:</span><span class="value">{{ $tramite->objetivo }}</span></div>
        </div>
      @endif
    </div>

    {{-- COSTOS --}}
    <div class="acuse-section">
      <div class="acuse-section-title">Costo burocrático</div>
      <div class="acuse-grid">
        <div class="acuse-field"><span class="label">CBD (directo):</span><span class="value">${{ number_format($tramite->cbd_directo ?? 0, 2) }}</span></div>
        <div class="acuse-field"><span class="label">CBI (indirecto):</span><span class="value">${{ number_format($tramite->cbi_indirecto ?? 0, 2) }}</span></div>
        <div class="acuse-field"><span class="label">CBU (unitario):</span><span class="value">${{ number_format($tramite->cbu_unitario ?? 0, 2) }}</span></div>
        <div class="acuse-field"><span class="label">CBT (total anual):</span><span class="value">${{ number_format($tramite->cbt_total ?? 0, 2) }}</span></div>
        <div class="acuse-field"><span class="label">Impacto:</span><span class="value">{{ ucfirst($tramite->impacto ?? 'No determinado') }}</span></div>
        <div class="acuse-field"><span class="label">Resultado AIR:</span><span class="value">{{ ucfirst(str_replace('_', ' ', $tramite->resultado_air ?? 'No determinado')) }}</span></div>
      </div>
    </div>

    {{-- REQUISITOS --}}
    @if($tramite->requisitos->count())
    <div class="acuse-section">
      <div class="acuse-section-title">Requisitos ({{ $tramite->requisitos->count() }})</div>
      <table class="acuse-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Requisito</th>
            <th>Tipo</th>
            <th>Costo</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tramite->requisitos as $i => $r)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>{{ $r->nombre }}</td>
              <td>{{ $r->tipo ?? '' }}</td>
              <td>{{ $r->tiene_costo ? '$' . number_format($r->costo_requisito ?? 0, 2) : 'Sin costo' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif

    {{-- FIRMAS --}}
    @php $firmasActivas = $tramite->firmas->where('estatus', 'activa'); @endphp
    @if($firmasActivas->count())
    <div class="acuse-section">
      <div class="acuse-section-title">Firmas registradas ({{ $firmasActivas->count() }})</div>
      <div class="acuse-firmas">
        @foreach($firmasActivas as $firma)
          <div class="acuse-firma-item">
            <div class="firma-tipo">{{ $firma->tipoLegible() }}</div>
            <div class="firma-dato">
              {{ $firma->firmante_nombre ?? $firma->firmante->name ?? '' }}
              {{ $firma->firmante_cargo ? ' — ' . $firma->firmante_cargo : '' }}
            </div>
            <div class="firma-dato">{{ $firma->fecha?->format('d/m/Y H:i') }}</div>
            @if($firma->hash_acuse)
              <div class="firma-dato" style="font-family:monospace;font-size:9px;color:#888;margin-top:4px">
                Hash: {{ $firma->hash_acuse }}
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="acuse-footer">
      <p>Documento generado por el Sistema PUNTA — {{ now()->format('d/m/Y H:i:s') }}</p>
      <p>H. Ayuntamiento de La Paz, Baja California Sur</p>
      @if($tramite->homoclave)
        <p style="margin-top:8px">Folio de verificación: <span class="hash">{{ $tramite->homoclave }}-{{ md5($tramite->id . $tramite->created_at) }}</span></p>
      @endif
    </div>

  </div>

</body>
</html>
