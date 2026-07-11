<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices de búsqueda de texto completo para el buscador global de PUNTA.
 *
 * PostgreSQL no tiene FULLTEXT como MySQL: usa índices GIN sobre una expresión
 * to_tsvector(...), y las consultas buscan con el operador @@ (ver BuscadorService).
 * El diccionario 'spanish' hace que la búsqueda entienda raíces de palabras
 * (buscar "regulación" también encuentra "regulaciones") e ignore palabras vacías
 * (de, la, el...).
 *
 * coalesce(columna, '') evita que un NULL anule toda la expresión: si una columna
 * viene vacía, se toma como cadena vacía y el resto sigue contando.
 *
 * Las seis tablas que alimentan el buscador:
 *   regulacion_nodos, regulaciones, tramites, requisitos,
 *   fundamento_juridico y acciones_agenda.
 */
return new class extends Migration
{
    /**
     * Tabla => [nombre del índice, columnas que entran a la búsqueda].
     */
    private array $indices = [
        'regulacion_nodos'    => ['ft_nodos_texto',            ['texto']],
        'regulaciones'        => ['ft_regulaciones_buscar',    ['nombre', 'resumen', 'objetivo', 'palabras_clave', 'materia']],
        'tramites'            => ['ft_tramites_buscar',        ['nombre_oficial', 'objetivo', 'poblacion_objetivo']],
        'requisitos'          => ['ft_requisitos_nombre',      ['nombre']],
        'fundamento_juridico' => ['ft_fundamentos_buscar',     ['normativa_nombre', 'articulo_fraccion', 'resumen']],
        'acciones_agenda'     => ['ft_acciones_agenda_buscar', ['descripcion', 'meta']],
    ];

    public function up(): void
    {
        foreach ($this->indices as $tabla => [$indice, $columnas]) {
            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON %s USING GIN (to_tsvector(\'spanish\', %s))',
                $indice,
                $tabla,
                $this->expresion($columnas)
            ));
        }
    }

    public function down(): void
    {
        foreach ($this->indices as [$indice, $columnas]) {
            DB::statement("DROP INDEX IF EXISTS {$indice}");
        }
    }

    /**
     * Concatena las columnas en un solo texto para indexar, protegiendo los NULL.
     * Ej.: coalesce(nombre,'') || ' ' || coalesce(resumen,'')
     */
    private function expresion(array $columnas): string
    {
        return collect($columnas)
            ->map(fn (string $c) => "coalesce({$c}, '')")
            ->implode(" || ' ' || ");
    }
};
