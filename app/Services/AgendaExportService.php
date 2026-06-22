<?php

namespace App\Services;

use App\Models\AccionAgenda;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta la Agenda SyD a Excel en los dos formatos del instrumento oficial ATDT:
 *
 *   - exportarSimp(): Art. 23 LNETB — acciones de simplificación
 *   - exportarDig():  Art. 24 LNETB — acciones de digitalización
 *
 * Las acciones con tipo='ambas' aparecen en ambos archivos.
 * Cada hoja tiene 23 columnas fieles al formato oficial.
 */
class AgendaExportService
{
    /**
     * Exporta acciones de SIMPLIFICACIÓN (Art. 23).
     * Incluye acciones de tipo 'simplificacion' o 'ambas'.
     */
    public function exportarSimp(Collection $acciones): StreamedResponse
    {
        $aplican = $acciones->filter(
            fn ($a) => in_array($a->tipo, ['simplificacion', 'ambas'], true)
        );

        return $this->generar(
            $aplican,
            'agenda_simplificacion_' . now()->format('Y-m-d') . '.xlsx',
            'Acciones de Simplificación (Art. 23)',
            'simplificacion'
        );
    }

    /**
     * Exporta acciones de DIGITALIZACIÓN (Art. 24).
     * Incluye acciones de tipo 'digitalizacion' o 'ambas'.
     */
    public function exportarDig(Collection $acciones): StreamedResponse
    {
        $aplican = $acciones->filter(
            fn ($a) => in_array($a->tipo, ['digitalizacion', 'ambas'], true)
        );

        return $this->generar(
            $aplican,
            'agenda_digitalizacion_' . now()->format('Y-m-d') . '.xlsx',
            'Acciones de Digitalización (Art. 24)',
            'digitalizacion'
        );
    }

    // ─── Generación del archivo ─────────────────────────────────────────────

    /**
     * Construye el Spreadsheet, escribe encabezados y filas, y devuelve un
     * StreamedResponse que dispara la descarga en el navegador.
     */
    private function generar(Collection $acciones, string $nombreArchivo, string $titulo, string $tipo): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $hoja        = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Agenda');

        $this->escribirEncabezado($hoja, $titulo);
        $this->escribirColumnas($hoja);
        $this->escribirFilas($hoja, $acciones, $tipo);
        $this->ajustarAnchos($hoja);

        return $this->responderDescarga($spreadsheet, $nombreArchivo);
    }

    /** Título del documento en la fila 1 (combinada A1:W1). */
    private function escribirEncabezado($hoja, string $titulo): void
    {
        $hoja->setCellValue('A1', $titulo);
        $hoja->mergeCells('A1:W1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Encabezados de las 23 columnas en la fila 2. */
    private function escribirColumnas($hoja): void
    {
        $columnas = [
            'A2' => 'No.',
            'B2' => 'Folio',
            'C2' => 'Dependencia',
            'D2' => 'Nombre del Trámite o Servicio',
            'E2' => 'Tipo de acción',
            'F2' => 'Descripción de la acción',
            'G2' => 'Meta esperada',
            'H2' => 'Acciones (catálogo oficial)',
            'I2' => 'Justificación / explicación',
            'J2' => 'Indicador',
            'K2' => 'Indicador de avance (%)',
            'L2' => 'Nivel actual',
            'M2' => 'Nivel meta',
            'N2' => 'Fecha de inicio',
            'O2' => 'Fecha compromiso',
            'P2' => 'Responsable',
            'Q2' => 'Estatus',
            'R2' => 'Hitos completados',
            'S2' => 'Total de hitos',
            'T2' => 'Avance hitos (%)',
            'U2' => 'Periodo',
            'V2' => 'Creado por',
            'W2' => 'Fecha de creación',
        ];

        foreach ($columnas as $celda => $texto) {
            $hoja->setCellValue($celda, $texto);
        }

        // Estilo de encabezados.
        $hoja->getStyle('A2:W2')->getFont()->setBold(true);
        $hoja->getStyle('A2:W2')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E8E8EA');
        $hoja->getStyle('A2:W2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);
        $hoja->getRowDimension(2)->setRowHeight(35);
    }

    /** Una fila por acción, leyendo los datos del modelo. */
    private function escribirFilas($hoja, Collection $acciones, string $tipo): void
    {
        $fila = 3;
        $num  = 1;

        foreach ($acciones as $a) {
            $folio        = $a->folio ?? 'AGD-' . str_pad($a->id, 3, '0', STR_PAD_LEFT);
            $acc          = $this->leerAcciones($a, $tipo);
            $totalHitos   = $a->hitos?->count() ?? 0;
            $completos    = $a->hitos?->where('completado', true)->count() ?? 0;
            $avanceHitos  = $totalHitos > 0 ? round(($completos / $totalHitos) * 100) : 0;

            $hoja->setCellValue("A{$fila}", $num);
            $hoja->setCellValue("B{$fila}", $folio);
            $hoja->setCellValue("C{$fila}", $a->dependencia?->nombre ?? '—');
            $hoja->setCellValue("D{$fila}", $a->tramite?->nombre_oficial ?? '—');
            $hoja->setCellValue("E{$fila}", ucfirst($a->tipo));
            $hoja->setCellValue("F{$fila}", $a->descripcion);
            $hoja->setCellValue("G{$fila}", $a->meta);
            $hoja->setCellValue("H{$fila}", $acc['nombres']);
            $hoja->setCellValue("I{$fila}", $acc['explicacion']);
            $hoja->setCellValue("J{$fila}", $a->indicador);
            $hoja->setCellValue("K{$fila}", $a->indicador_avance);
            $hoja->setCellValue("L{$fila}", $a->nivel_actual);
            $hoja->setCellValue("M{$fila}", $a->nivel_meta);
            $hoja->setCellValue("N{$fila}", $a->fecha_inicio?->format('d/m/Y'));
            $hoja->setCellValue("O{$fila}", $a->fecha_compromiso?->format('d/m/Y'));
            $hoja->setCellValue("P{$fila}", $a->responsable);
            $hoja->setCellValue("Q{$fila}", ucfirst(str_replace('_', ' ', $a->estatus)));
            $hoja->setCellValue("R{$fila}", $completos);
            $hoja->setCellValue("S{$fila}", $totalHitos);
            $hoja->setCellValue("T{$fila}", $avanceHitos . '%');
            $hoja->setCellValue("U{$fila}", $a->periodo?->nombre ?? '—');
            $hoja->setCellValue("V{$fila}", $a->creador?->name ?? '—');
            $hoja->setCellValue("W{$fila}", $a->created_at?->format('d/m/Y H:i'));

            $hoja->getRowDimension($fila)->setRowHeight(-1); // alto automático
            $fila++;
            $num++;
        }

        // Bordes a toda la tabla.
        $ultimaFila = $fila - 1;
        if ($ultimaFila >= 3) {
            $hoja->getStyle("A2:W{$ultimaFila}")->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $hoja->getStyle("A3:W{$ultimaFila}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
        }
    }

    /**
     * Extrae el catálogo de acciones y su explicación para el tipo dado.
     * Las acciones se guardan como JSON {'Nombre acción' => 'explicación...'},
     * así que devolvemos los nombres separados por '; ' y la explicación junta.
     */
    private function leerAcciones(AccionAgenda $a, string $tipo): array
    {
        $campo = $tipo === 'simplificacion'
            ? $a->acciones_simplificacion
            : $a->acciones_digitalizacion;

        if (!is_array($campo) || empty($campo)) {
            return ['nombres' => '—', 'explicacion' => ''];
        }

        $nombres      = [];
        $explicaciones = [];
        foreach ($campo as $nombre => $explicacion) {
            $nombres[] = $nombre;
            if (!empty($explicacion)) {
                $explicaciones[] = $nombre . ': ' . $explicacion;
            }
        }

        return [
            'nombres'     => implode('; ', $nombres),
            'explicacion' => implode("\n", $explicaciones),
        ];
    }

    /** Anchos de columna razonables para que el archivo se lea sin scroll constante. */
    private function ajustarAnchos($hoja): void
    {
        $anchos = [
            'A' => 5,  'B' => 14, 'C' => 22, 'D' => 30, 'E' => 14,
            'F' => 35, 'G' => 25, 'H' => 30, 'I' => 40, 'J' => 25,
            'K' => 12, 'L' => 10, 'M' => 10, 'N' => 12, 'O' => 14,
            'P' => 22, 'Q' => 14, 'R' => 10, 'S' => 10, 'T' => 12,
            'U' => 16, 'V' => 20, 'W' => 18,
        ];
        foreach ($anchos as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
    }

    /** Streamed response con headers de descarga XLSX. */
    private function responderDescarga(Spreadsheet $spreadsheet, string $nombreArchivo): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new WriterXlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
