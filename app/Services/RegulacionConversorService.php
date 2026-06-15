<?php

namespace App\Services;

use App\Models\Regulacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio para gestionar la conversión de regulaciones jurídicas
 * (PDF / Word) a archivos Markdown citables desde los wizards.
 *
 * Flujo:
 *   1. guardarOriginal()  — almacena el archivo .pdf/.docx en storage
 *   2. convertirAMarkdown() — extrae el texto y lo guarda como .md
 *
 * En esta primera versión la conversión usa extractores básicos
 * (smalot/pdfparser para PDF, phpoffice/phpword para DOCX). Si las
 * dependencias no están instaladas, el método retorna un error
 * controlado y el registro queda en estatus 'error' con su mensaje.
 */
class RegulacionConversorService
{
    private const DIRECTORIO_ORIGINALES = 'regulaciones/originales';
    private const DIRECTORIO_MARKDOWN   = 'regulaciones/markdown';

    public function guardarOriginal(Regulacion $regulacion, UploadedFile $archivo): void
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (!in_array($extension, Regulacion::EXTENSIONES_PERMITIDAS, true)) {
            throw new \InvalidArgumentException(
                'Extensión no permitida. Use PDF, DOCX o DOC.'
            );
        }

        $nombreArchivo = $this->generarNombreArchivo($regulacion, $extension);
        $rutaOriginal  = $archivo->storeAs(self::DIRECTORIO_ORIGINALES, $nombreArchivo, 'local');

        $regulacion->update([
            'archivo_original'    => $rutaOriginal,
            'extension_original'  => $extension,
            'conversion_estatus'  => Regulacion::CONVERSION_PENDIENTE,
            'conversion_error'    => null,
        ]);
    }

    public function convertirAMarkdown(Regulacion $regulacion): bool
    {
        if (empty($regulacion->archivo_original)) {
            return false;
        }

        $regulacion->update(['conversion_estatus' => Regulacion::CONVERSION_PROCESANDO]);

        try {
            $rutaAbsoluta = Storage::disk('local')->path($regulacion->archivo_original);
            $textoCrudo   = $this->extraerTexto($rutaAbsoluta, $regulacion->extension_original);
            $markdown     = $this->formatearComoMarkdown($regulacion, $textoCrudo);

            $rutaMd = self::DIRECTORIO_MARKDOWN . '/' . $this->generarNombreArchivo($regulacion, 'md');
            Storage::disk('local')->put($rutaMd, $markdown);

            $indice = $this->extraerIndice($markdown);

            $regulacion->update([
                'archivo_markdown'   => $rutaMd,
                'conversion_estatus' => Regulacion::CONVERSION_LISTO,
                'conversion_error'   => null,
                'indice'             => !empty($indice) ? $indice : null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $regulacion->update([
                'conversion_estatus' => Regulacion::CONVERSION_ERROR,
                'conversion_error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function obtenerContenidoMarkdown(Regulacion $regulacion): ?string
    {
        if (!$regulacion->conversionListaParaCitar()) {
            return null;
        }

        return Storage::disk('local')->exists($regulacion->archivo_markdown)
            ? Storage::disk('local')->get($regulacion->archivo_markdown)
            : null;
    }

    public function eliminarArchivos(Regulacion $regulacion): void
    {
        foreach ([$regulacion->archivo_original, $regulacion->archivo_markdown] as $ruta) {
            if ($ruta && Storage::disk('local')->exists($ruta)) {
                Storage::disk('local')->delete($ruta);
            }
        }
    }

    /**
     * Extrae el índice de la regulación a partir de los encabezados Markdown.
     *
     * Recorre líneas que empiecen con # y genera un array estructurado:
     *   [
     *     ['nivel' => 1, 'titulo' => 'TÍTULO PRIMERO', 'linea' => 12],
     *     ['nivel' => 2, 'titulo' => 'Capítulo I',     'linea' => 15],
     *     ['nivel' => 3, 'titulo' => 'Artículo 1',     'linea' => 18],
     *   ]
     *
     * También detecta patrones comunes sin # como "Artículo N",
     * "TÍTULO", "CAPÍTULO", "SECCIÓN", "TRANSITORIO".
     */
    public function extraerIndice(string $markdown): array
    {
        $lineas = explode("\n", $markdown);
        $indice = [];

        foreach ($lineas as $numero => $linea) {
            $lineaTrim = trim($linea);

            // Encabezados Markdown: ## Título
            if (preg_match('/^(#{1,4})\s+(.+)$/', $lineaTrim, $m)) {
                $titulo = trim($m[2]);
                if ($this->esTituloRelevante($titulo)) {
                    $indice[] = [
                        'nivel'  => strlen($m[1]),
                        'titulo' => $titulo,
                        'linea'  => $numero + 1,
                    ];
                }
                continue;
            }

            // Patrones sin # (texto plano extraído de PDF)
            if (preg_match('/^(TÍTULO|CAPÍTULO|SECCIÓN|TRANSITORIO|Artículo\s+\d+)/iu', $lineaTrim, $m)) {
                $nivel = match (true) {
                    str_starts_with(mb_strtoupper($lineaTrim), 'TÍTULO')      => 1,
                    str_starts_with(mb_strtoupper($lineaTrim), 'CAPÍTULO')    => 2,
                    str_starts_with(mb_strtoupper($lineaTrim), 'SECCIÓN')     => 2,
                    str_starts_with(mb_strtoupper($lineaTrim), 'TRANSITORIO') => 2,
                    default                                                    => 3,
                };

                $indice[] = [
                    'nivel'  => $nivel,
                    'titulo' => Str::limit($lineaTrim, 120, '...'),
                    'linea'  => $numero + 1,
                ];
            }
        }

        return $indice;
    }

    /**
     * Filtra títulos irrelevantes como metadatos de la cabecera generada.
     */
    private function esTituloRelevante(string $titulo): bool
    {
        $excluir = ['Regulación generada automáticamente', '**Tipo:**', '**Fecha'];

        foreach ($excluir as $patron) {
            if (str_contains($titulo, $patron)) {
                return false;
            }
        }

        return mb_strlen($titulo) > 2;
    }

    /**
     * Extrae el texto del archivo según su extensión.
     * Usa librerías externas si están instaladas; si no, lanza excepción
     * controlada que queda registrada en `conversion_error`.
     */
    private function extraerTexto(string $rutaAbsoluta, string $extension): string
    {
        return match($extension) {
            'pdf'         => $this->extraerTextoPdf($rutaAbsoluta),
            'docx', 'doc' => $this->extraerTextoWord($rutaAbsoluta),
            default       => throw new \RuntimeException("Extensión no soportada: {$extension}"),
        };
    }

    private function extraerTextoPdf(string $ruta): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new \RuntimeException(
                'Librería smalot/pdfparser no instalada. Ejecute: composer require smalot/pdfparser'
            );
        }

        $parser   = new \Smalot\PdfParser\Parser();
        $documento = $parser->parseFile($ruta);
        return $documento->getText();
    }

    private function extraerTextoWord(string $ruta): string
    {
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new \RuntimeException(
                'Librería phpoffice/phpword no instalada. Ejecute: composer require phpoffice/phpword'
            );
        }

        $documento = \PhpOffice\PhpWord\IOFactory::load($ruta);
        $texto     = '';

        foreach ($documento->getSections() as $seccion) {
            foreach ($seccion->getElements() as $elemento) {
                $texto .= $this->extraerTextoDeElemento($elemento) . "\n";
            }
        }

        return $texto;
    }

    private function extraerTextoDeElemento($elemento): string
    {
        if (method_exists($elemento, 'getText')) {
            return $elemento->getText();
        }

        if (method_exists($elemento, 'getElements')) {
            $texto = '';
            foreach ($elemento->getElements() as $hijo) {
                $texto .= $this->extraerTextoDeElemento($hijo) . ' ';
            }
            return $texto;
        }

        return '';
    }

    /**
     * Da formato Markdown básico al texto crudo extraído.
     * Antepone un encabezado con metadatos de la regulación.
     */
    private function formatearComoMarkdown(Regulacion $regulacion, string $textoCrudo): string
    {
        $cabecera = $this->generarCabecera($regulacion);
        $cuerpo   = trim(preg_replace("/\n{3,}/", "\n\n", $textoCrudo));

        return $cabecera . "\n\n" . $cuerpo . "\n";
    }

    private function generarCabecera(Regulacion $regulacion): string
    {
        $lineas = [
            '# ' . $regulacion->nombre,
            '',
            '> Regulación generada automáticamente desde el archivo original.',
            '',
        ];

        if ($regulacion->tipo) {
            $lineas[] = '**Tipo:** ' . $regulacion->tipo;
        }
        if ($regulacion->fecha_publicacion) {
            $lineas[] = '**Fecha de publicación:** ' . $regulacion->fecha_publicacion->format('d/m/Y');
        }
        if ($regulacion->fecha_vigencia) {
            $lineas[] = '**Fecha de vigencia:** ' . $regulacion->fecha_vigencia->format('d/m/Y');
        }

        return implode("\n", $lineas);
    }

    private function generarNombreArchivo(Regulacion $regulacion, string $extension): string
    {
        $base = Str::slug($regulacion->nombre, '-');
        $base = Str::limit($base, 80, '');
        return $regulacion->id . '-' . $base . '.' . $extension;
    }
}
