<?php

namespace App\Services;

use App\Models\Diagrama;
use App\Models\Reingenieria;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el PDF oficial institucional del diagrama de reingeniería.
 *
 * El PDF incluye:
 *   1. Encabezado institucional
 *   2. Datos generales del trámite/servicio
 *   3. Datos de la reingeniería (origen, versión, hash)
 *   4. Diagrama visual (imagen del Mermaid renderizado)
 *   5. Bloque de firmas digitales (enlace + sujeto obligado)
 *   6. QR de verificación + hashes
 *   7. Leyenda institucional
 *
 * IMPORTANTE: Draw.io NO genera este PDF. Este servicio es la única
 * fuente de verdad para el documento oficial.
 *
 * Requisitos previos:
 *   - La reingeniería debe tener firmas completas (enlace + sujeto)
 *   - El diagrama debe existir y tener contenido Mermaid
 *   - Se usa DomPDF (via barryvdh/laravel-dompdf) para generar el PDF
 *
 * Para instalar DomPDF si no está:
 *   composer require barryvdh/laravel-dompdf
 */
class PdfDiagramaService
{
    /**
     * Genera el PDF oficial y devuelve el path del archivo generado.
     *
     * @param  Diagrama $diagrama  El diagrama con su reingeniería y trámite cargados.
     * @return string              Path absoluto del PDF generado.
     * @throws \RuntimeException   Si faltan firmas o datos requeridos.
     */
    public function generar(Diagrama $diagrama): string
    {
        // ── Verificar que DomPDF está instalado ──────────────────────────
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new \RuntimeException(
                'El paquete barryvdh/laravel-dompdf no está instalado. '
                . 'Ejecute: composer install (ya está en composer.json) '
                . 'o: composer require barryvdh/laravel-dompdf'
            );
        }

        $diagrama->loadMissing([
            'reingenieria.firmas.firmante',
            'reingenieria.tramite.dependencia',
            'reingenieria.tramite.unidad',
        ]);

        $reing   = $diagrama->reingenieria;
        $tramite = $reing->tramite;

        // ── Validaciones ─────────────────────────────────────────────────
        if (!$reing->firmasCompletas()) {
            throw new \RuntimeException('La reingeniería no tiene firmas completas.');
        }

        if (!$diagrama->tieneMermaid()) {
            throw new \RuntimeException('El diagrama no tiene contenido Mermaid.');
        }

        // ── Datos para la plantilla ──────────────────────────────────────
        $firmaEnlace = $reing->firmas
            ->where('tipo', 'aceptacion_enlace')
            ->where('estatus', 'activa')
            ->first();

        $firmaSujeto = $reing->firmas
            ->where('tipo', 'aceptacion_sujeto')
            ->where('estatus', 'activa')
            ->first();

        $hashPdf = hash('sha256', json_encode([
            'reingenieria_id' => $reing->id,
            'diagrama_id'     => $diagrama->id,
            'hash_reing'      => $reing->hash_reingenieria,
            'hash_diagrama'   => $diagrama->hash_diagrama,
            'generado_en'     => now()->toIso8601String(),
        ]));

        $folio = sprintf('PUNTA-DIG-%s-%d-v%d',
            $tramite->homoclave ?? $tramite->id,
            $reing->id,
            $reing->version
        );

        // ── Generar QR ───────────────────────────────────────────────────
        $urlVerificacion = route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria']);
        $qrSvg = '';
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            try {
                $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                    ->size(120)
                    ->margin(0)
                    ->generate($urlVerificacion);
            } catch (\Throwable $e) {
                // Sin QR si falla — no bloquear el PDF
                $qrSvg = '';
            }
        }

        // ── Renderizar PDF ───────────────────────────────────────────────
        $html = view('pdf.diagrama-oficial', [
            'tramite'        => $tramite,
            'reingenieria'   => $reing,
            'diagrama'       => $diagrama,
            'firmaEnlace'    => $firmaEnlace,
            'firmaSujeto'    => $firmaSujeto,
            'hashPdf'        => $hashPdf,
            'folio'          => $folio,
            'qrSvg'          => $qrSvg,
            'fechaEmision'   => now(),
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait');

        $filename = "diagrama-oficial-{$folio}.pdf";
        $path = storage_path("app/private/diagramas/pdf/{$filename}");

        // Asegurar que el directorio existe
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }
}
