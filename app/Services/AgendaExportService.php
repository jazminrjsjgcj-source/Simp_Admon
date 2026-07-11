<?php

namespace App\Services;

use App\Models\AccionAgenda;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta la Agenda SyD rellenando las PLANTILLAS OFICIALES ATDT.
 *
 * A diferencia de generar un Excel desde cero, este servicio abre la plantilla
 * oficial (con sus 8 hojas, formato e instrucciones intactas) y solo escribe
 * los datos en la hoja de captura, desde la fila 5, respetando las 23 columnas
 * oficiales (A-W). Así el archivo descargado es idéntico al formato que pide la
 * autoridad, solo que ya lleno.
 *
 *   - exportarSimp(): Art. 23 LNETB — plantilla de simplificación
 *   - exportarDig():  Art. 24 LNETB — plantilla de digitalización
 *
 * Las plantillas base viven en resources/templates/ (parte del código fuente,
 * versionado y empaquetado con el proyecto). Si no están, el servicio lanza una
 * excepción clara indicando qué archivo falta.
 */
class AgendaExportService
{
    /** Carpeta (dentro de resources/) donde viven las plantillas oficiales. */
    private const DIR_PLANTILLAS = 'templates';

    /** Primera fila de datos en la hoja de captura (la 4 son los encabezados). */
    private const FILA_INICIO = 5;

    /** Última fila que puede traer datos de ejemplo a limpiar antes de escribir. */
    private const FILA_LIMITE_LIMPIEZA = 1000;

    public function exportarSimp(Collection $acciones): StreamedResponse
    {
        $aplican = $acciones->filter(
            fn ($a) => in_array($a->tipo, ['simplificacion', 'ambas'], true)
        );

        return $this->generarDesdePlantilla(
            $aplican,
            'plantilla_simplificacion.xlsx',
            '1. Trámites a simplificar',
            'simplificacion',
            'Agenda_Simplificacion_' . now()->format('Y-m-d') . '.xlsx',
        );
    }

    public function exportarDig(Collection $acciones): StreamedResponse
    {
        $aplican = $acciones->filter(
            fn ($a) => in_array($a->tipo, ['digitalizacion', 'ambas'], true)
        );

        return $this->generarDesdePlantilla(
            $aplican,
            'plantilla_digitalizacion.xlsx',
            '1. Trámites a digitalizar',
            'digitalizacion',
            'Agenda_Digitalizacion_' . now()->format('Y-m-d') . '.xlsx',
        );
    }

    // ─── Generación rellenando la plantilla ─────────────────────────────────

    private function generarDesdePlantilla(
        Collection $acciones,
        string $nombrePlantilla,
        string $nombreHoja,
        string $tipo,
        string $nombreDescarga,
    ): StreamedResponse {
        $rutaPlantilla = resource_path(self::DIR_PLANTILLAS . '/' . $nombrePlantilla);

        if (!is_file($rutaPlantilla)) {
            throw new RuntimeException(
                "No se encontró la plantilla oficial: {$nombrePlantilla}. " .
                'Debe estar en resources/templates/ (viene incluida con el proyecto).'
            );
        }

        $spreadsheet = IOFactory::load($rutaPlantilla);
        $hoja        = $spreadsheet->getSheetByName($nombreHoja);

        if ($hoja === null) {
            throw new RuntimeException(
                "La plantilla {$nombrePlantilla} no contiene la hoja '{$nombreHoja}'."
            );
        }

        $this->limpiarFilasEjemplo($hoja);
        $this->escribirAcciones($hoja, $acciones, $tipo);

        return $this->responderDescarga($spreadsheet, $nombreDescarga);
    }

    /**
     * Borra los datos de ejemplo que la plantilla trae de fábrica (filas 5 en
     * adelante), conservando el formato de las celdas. No elimina filas para no
     * romper combinaciones ni estilos: solo vacía el contenido de A:W.
     */
    private function limpiarFilasEjemplo($hoja): void
    {
        for ($fila = self::FILA_INICIO; $fila <= self::FILA_LIMITE_LIMPIEZA; $fila++) {
            foreach (range('A', 'W') as $col) {
                $hoja->setCellValue("{$col}{$fila}", null);
            }
        }
    }

    /**
     * Escribe una fila por acción desde la fila 5, mapeando cada acción y su
     * trámite a las 23 columnas oficiales (A-W).
     */
    private function escribirAcciones($hoja, Collection $acciones, string $tipo): void
    {
        $fila = self::FILA_INICIO;
        $num  = 1;

        foreach ($acciones as $a) {
            $t = $a->tramite;

            $hoja->setCellValue("A{$fila}", $num);
            $hoja->setCellValue("B{$fila}", $a->fecha_compromiso?->format('d/m/Y'));
            $hoja->setCellValue("C{$fila}", $t?->homoclave ?? 'N/A');
            $hoja->setCellValue("D{$fila}", $t?->nombre_oficial ?? ($a->descripcion ?? ''));
            $hoja->setCellValue("E{$fila}", $this->fundamento($t));
            $hoja->setCellValue("F{$fila}", $t?->volumen_anual);
            $hoja->setCellValue("G{$fila}", $t?->frecuencia);
            $hoja->setCellValue("H{$fila}", $this->costoBurocratico($t));
            $hoja->setCellValue("I{$fila}", $this->gruposPrioritarios($t));
            $hoja->setCellValue("J{$fila}", $this->siNo($t?->tiene_relacionados));
            $hoja->setCellValue("K{$fila}", $t?->tipo_relacion ?: 'N/A');
            $hoja->setCellValue("L{$fila}", $t?->relacionados_detalle ?: 'N/A');
            $hoja->setCellValue("M{$fila}", $t?->num_areas);
            $hoja->setCellValue("N{$fila}", $this->procesoPasos($t, 'atencion'));
            $hoja->setCellValue("O{$fila}", $this->procesoPasos($t, 'resolucion'));
            $hoja->setCellValue("P{$fila}", $this->accionesCatalogo($a, $tipo));
            $hoja->setCellValue("Q{$fila}", $a->descripcion);
            $hoja->setCellValue("R{$fila}", $a->meta);
            $hoja->setCellValue("S{$fila}", $a->indicador);
            $hoja->setCellValue("T{$fila}", $a->indicador_avance);
            $hoja->setCellValue("U{$fila}", 'No');
            $hoja->setCellValue("V{$fila}", 'N/A');
            // W (Observaciones): se deja en blanco — PUNTA no captura ese dato.

            $fila++;
            $num++;
        }
    }

    // ─── Helpers de mapeo por columna ───────────────────────────────────────

    /** Columna E: fundamento jurídico del costo (norma + artículo). */
    private function fundamento($tramite): string
    {
        if (!$tramite) {
            return '';
        }

        $partes = array_filter([
            $tramite->fj_norma,
            $tramite->fj_articulo ? 'Artículo: ' . $tramite->fj_articulo : null,
        ]);

        return implode("\n", $partes);
    }

    /** Columna H: costo burocrático total calculado, formateado como pesos. */
    private function costoBurocratico($tramite): ?string
    {
        if (!$tramite || $tramite->cbt_total === null) {
            return null;
        }

        return '$' . number_format((float) $tramite->cbt_total, 2);
    }

    /**
     * Columna I: grupo de atención prioritaria. La plantilla oficial tiene un
     * dropdown de un solo valor, pero PUNTA permite varios (grupos_atencion es
     * un array con los mismos valores del catálogo oficial LNETB). Tomamos el
     * primer grupo distinto de "No Aplica"; si no hay ninguno, "No Aplica".
     */
    private function gruposPrioritarios($tramite): string
    {
        if (!$tramite) {
            return 'No Aplica';
        }

        $grupos = $tramite->grupos_atencion;

        if (is_array($grupos)) {
            $reales = array_values(array_filter(
                $grupos,
                fn ($g) => $g && $g !== 'No Aplica'
            ));
            if (!empty($reales)) {
                return $reales[0];
            }
        }

        return 'No Aplica';
    }

    /** Columnas N y O: pasos del proceso (atención o resolución) numerados. */
    private function procesoPasos($tramite, string $tipo): string
    {
        if (!$tramite || !$tramite->relationLoaded('procesosAtencion')) {
            $pasos = $tramite?->procesosAtencion()->where('tipo', $tipo)->orderBy('paso')->get() ?? collect();
        } else {
            $pasos = $tramite->procesosAtencion->where('tipo', $tipo)->sortBy('paso');
        }

        if ($pasos->isEmpty()) {
            return '';
        }

        return $pasos->map(function ($p) {
            $numero = $p->subpaso ? "{$p->paso}.{$p->subpaso}" : "{$p->paso}";
            $texto  = $p->detalle ?: $p->accion;
            return "{$numero}. {$texto}";
        })->implode("\n");
    }

    /**
     * Columna P: nombres de las acciones del catálogo oficial (simplificación o
     * digitalización), separados por salto de línea.
     */
    private function accionesCatalogo(AccionAgenda $a, string $tipo): string
    {
        $campo = $tipo === 'simplificacion'
            ? $a->acciones_simplificacion
            : $a->acciones_digitalizacion;

        if (!is_array($campo) || empty($campo)) {
            return '';
        }

        // El JSON puede ser {'Nombre' => 'explicación'} o una lista simple.
        $nombres = array_is_list($campo) ? $campo : array_keys($campo);

        return implode("\n", $nombres);
    }

    /** Convierte un booleano/valor a "Sí"/"No" para las columnas de relación. */
    private function siNo($valor): string
    {
        return $valor ? 'Sí' : 'No';
    }

    // ─── Descarga ───────────────────────────────────────────────────────────

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
