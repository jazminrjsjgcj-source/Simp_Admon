<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
  .page { padding: 30px 40px; }

  /* Encabezado institucional */
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #9d0b4f; padding-bottom: 12px; margin-bottom: 20px; }
  .header-left { }
  .header-left h1 { font-size: 16px; color: #9d0b4f; margin: 0 0 2px; }
  .header-left p { font-size: 10px; color: #666; margin: 0; }
  .header-right { text-align: right; font-size: 9px; color: #666; }
  .header-right strong { color: #1a1a1a; font-size: 11px; display: block; }

  /* Secciones */
  .section { margin-bottom: 18px; }
  .section-title { font-size: 12px; font-weight: bold; color: #9d0b4f; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e5e5; padding-bottom: 4px; margin-bottom: 8px; }

  /* Grid de datos */
  .data-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  .data-grid td { padding: 4px 8px; font-size: 10px; vertical-align: top; border: 0.5px solid #e5e5e5; }
  .data-grid .label { font-weight: bold; color: #666; text-transform: uppercase; font-size: 9px; letter-spacing: 0.03em; width: 30%; background: #fafafa; }
  .data-grid .value { color: #1a1a1a; }

  /* Diagrama */
  .diagram-container { border: 1px solid #e5e5e5; border-radius: 4px; padding: 16px; margin: 10px 0; text-align: center; background: #fefefe; min-height: 200px; }
  .diagram-container p { font-size: 10px; color: #999; font-style: italic; }
  .diagram-code { font-family: 'Courier New', monospace; font-size: 9px; text-align: left; white-space: pre-wrap; background: #f8f8f8; padding: 12px; border-radius: 4px; line-height: 1.4; }

  /* Firmas */
  .signatures { display: flex; gap: 40px; margin-top: 16px; }
  .signature-block { flex: 1; border: 0.5px solid #e5e5e5; border-radius: 4px; padding: 12px; }
  .signature-block h4 { font-size: 10px; color: #9d0b4f; text-transform: uppercase; margin: 0 0 8px; letter-spacing: 0.03em; }
  .signature-block .sig-field { font-size: 10px; margin-bottom: 4px; }
  .signature-block .sig-field span { color: #666; }
  .signature-block .sig-field strong { color: #1a1a1a; }
  .sig-hash { font-family: 'Courier New', monospace; font-size: 8px; color: #999; word-break: break-all; margin-top: 6px; }

  /* Verificación */
  .verification { display: flex; align-items: flex-start; gap: 16px; border: 1px solid #e5e5e5; border-radius: 4px; padding: 12px; margin-top: 16px; background: #fafafa; }
  .verification-data { flex: 1; }
  .verification-data p { font-size: 9px; margin-bottom: 3px; color: #666; }
  .verification-data p strong { color: #1a1a1a; }
  .verification-data .hash { font-family: 'Courier New', monospace; font-size: 8px; word-break: break-all; }

  /* Leyenda */
  .disclaimer { margin-top: 20px; padding-top: 12px; border-top: 1px solid #e5e5e5; font-size: 9px; color: #999; text-align: center; line-height: 1.4; }

  /* Footer */
  .footer { position: fixed; bottom: 20px; left: 40px; right: 40px; font-size: 8px; color: #bbb; text-align: center; border-top: 0.5px solid #e5e5e5; padding-top: 6px; }
</style>
</head>
<body>
<div class="page">

  {{-- ═══ ENCABEZADO INSTITUCIONAL ═══ --}}
  <div class="header">
    <div class="header-left">
      <h1>PUNTA — Sistema de Regulación Municipal</h1>
      <p>H. Ayuntamiento de La Paz, Baja California Sur</p>
      <p>Diagrama oficial de reingeniería</p>
    </div>
    <div class="header-right">
      <strong>{{ $folio }}</strong>
      Emitido: {{ $fechaEmision->format('d/m/Y H:i') }}
    </div>
  </div>

  {{-- ═══ DATOS GENERALES ═══ --}}
  <div class="section">
    <div class="section-title">Datos generales del trámite o servicio</div>
    <table class="data-grid">
      <tr><td class="label">Nombre</td><td class="value">{{ $tramite->nombre_oficial }}</td></tr>
      <tr><td class="label">Naturaleza</td><td class="value">{{ $tramite->naturalezaLegible() }}</td></tr>
      <tr><td class="label">Tipo</td><td class="value">{{ $tramite->tipoLegible() }}</td></tr>
      <tr><td class="label">Dependencia</td><td class="value">{{ $tramite->dependencia->nombre ?? '—' }}</td></tr>
      <tr><td class="label">Unidad administrativa</td><td class="value">{{ $tramite->unidad->nombre ?? '—' }}</td></tr>
      <tr><td class="label">Homoclave</td><td class="value">{{ $tramite->homoclave ?? '—' }}</td></tr>
    </table>
  </div>

  {{-- ═══ DATOS DE REINGENIERÍA ═══ --}}
  <div class="section">
    <div class="section-title">Reingeniería TO-BE</div>
    <table class="data-grid">
      <tr><td class="label">Versión</td><td class="value">v{{ $reingenieria->version }}</td></tr>
      <tr><td class="label">Origen</td><td class="value">{{ $reingenieria->origen === 'agenda' ? 'Agenda de Digitalización' : 'Reingeniería directa justificada' }}</td></tr>
      <tr><td class="label">Estado</td><td class="value">{{ $reingenieria->estadoLegible() }}</td></tr>
      <tr><td class="label">Tipo de diagrama</td><td class="value">TO-BE (proceso propuesto)</td></tr>
      @if($reingenieria->esDirecta() && $reingenieria->justificacion)
        <tr><td class="label">Justificación</td><td class="value">{{ $reingenieria->justificacion }}</td></tr>
      @endif
    </table>
  </div>

  {{-- ═══ DIAGRAMA ═══ --}}
  <div class="section">
    <div class="section-title">Diagrama del proceso</div>
    <div class="diagram-container">
      <p>Diagrama generado por PUNTA desde el flujo TO-BE estructurado</p>
      <div class="diagram-code">{{ $diagrama->contenido_mermaid }}</div>
    </div>
  </div>

  {{-- ═══ FIRMAS DIGITALES ═══ --}}
  <div class="section">
    <div class="section-title">Firmas digitales de la reingeniería</div>
    <table style="width:100%;border-collapse:collapse">
      <tr>
        <td style="width:50%;vertical-align:top;padding-right:12px">
          <div class="signature-block">
            <h4>Firma del Enlace</h4>
            @if($firmaEnlace)
              <div class="sig-field"><span>Nombre:</span> <strong>{{ $firmaEnlace->firmante_nombre }}</strong></div>
              <div class="sig-field"><span>Cargo:</span> <strong>{{ $firmaEnlace->firmante_cargo ?? '—' }}</strong></div>
              <div class="sig-field"><span>Fecha:</span> <strong>{{ $firmaEnlace->fecha->format('d/m/Y H:i') }}</strong></div>
              <div class="sig-hash">Hash: {{ $firmaEnlace->hash_acuse }}</div>
            @else
              <p style="color:#999;font-size:10px">Pendiente</p>
            @endif
          </div>
        </td>
        <td style="width:50%;vertical-align:top;padding-left:12px">
          <div class="signature-block">
            <h4>Firma del Sujeto Obligado</h4>
            @if($firmaSujeto)
              <div class="sig-field"><span>Nombre:</span> <strong>{{ $firmaSujeto->firmante_nombre }}</strong></div>
              <div class="sig-field"><span>Cargo:</span> <strong>{{ $firmaSujeto->firmante_cargo ?? '—' }}</strong></div>
              <div class="sig-field"><span>Fecha:</span> <strong>{{ $firmaSujeto->fecha->format('d/m/Y H:i') }}</strong></div>
              <div class="sig-hash">Hash: {{ $firmaSujeto->hash_acuse }}</div>
            @else
              <p style="color:#999;font-size:10px">Pendiente</p>
            @endif
          </div>
        </td>
      </tr>
    </table>
  </div>

  {{-- ═══ VERIFICACIÓN ═══ --}}
  <div class="verification">
    @if($qrSvg)
      <div style="flex-shrink:0">{!! $qrSvg !!}</div>
    @endif
    <div class="verification-data">
      <p><strong>Folio:</strong> {{ $folio }}</p>
      <p><strong>Hash de reingeniería:</strong> <span class="hash">{{ $reingenieria->hash_reingenieria ?? '—' }}</span></p>
      <p><strong>Hash del diagrama:</strong> <span class="hash">{{ $diagrama->hash_diagrama ?? '—' }}</span></p>
      <p><strong>Hash del PDF:</strong> <span class="hash">{{ $hashPdf }}</span></p>
      <p><strong>Fecha de emisión:</strong> {{ $fechaEmision->format('d/m/Y H:i:s') }}</p>
    </div>
  </div>

  {{-- ═══ LEYENDA ═══ --}}
  <div class="disclaimer">
    Este diagrama corresponde a la versión firmada de la reingeniería del trámite o servicio indicado.
    Cualquier modificación posterior requiere generar una nueva versión y recabar nuevamente las firmas correspondientes.
    <br>
    Documento generado por PUNTA — Sistema de Regulación Municipal del H. Ayuntamiento de La Paz, B.C.S.
  </div>

</div>

<div class="footer">
  PUNTA — {{ $folio }} — Página 1 de 1 — {{ $fechaEmision->format('d/m/Y') }}
</div>
</body>
</html>
