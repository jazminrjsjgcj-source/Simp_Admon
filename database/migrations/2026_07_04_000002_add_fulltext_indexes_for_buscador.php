<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega índices FULLTEXT para el buscador global de PUNTA.
 *
 * MySQL FULLTEXT permite búsquedas por relevancia con MATCH(...) AGAINST(...)
 * que devuelven un score numérico. Este score es la base del algoritmo de
 * ranking del BuscadorService: los resultados con mayor coincidencia textual
 * aparecen primero.
 *
 * Se crean índices en las 5 tablas que alimentan el buscador:
 *   - regulacion_nodos.texto      → artículos, fracciones, incisos
 *   - regulaciones (nombre+resumen+objetivo+palabras_clave+materia)
 *   - tramites (nombre_oficial+objetivo+poblacion_objetivo)
 *   - requisitos.nombre           → nombre del documento requerido
 *   - fundamento_juridico (normativa_nombre+articulo_fraccion+resumen)
 *
 * IDEMPOTENCIA — por qué esta versión verifica antes de crear:
 * Las sentencias ALTER TABLE en MySQL hacen commit automático e individual,
 * sin participar de una transacción. Si una migración con varias ALTER TABLE
 * seguidas falla a la mitad, las sentencias anteriores quedan aplicadas de
 * forma permanente en la base de datos, pero Laravel nunca marca la
 * migración como completada. Volver a correr "php artisan migrate" en ese
 * estado falla de nuevo con "Duplicate key name" en las tablas que sí se
 * alcanzaron a crear. Por eso cada índice se crea solo si `indiceExiste()`
 * confirma que todavía no está — así la migración se puede ejecutar
 * cualquier número de veces sin fallar ni por ausencia ni por duplicado.
 *
 * NOTA DE ESTA VERSIÓN: reemplaza una versión anterior no idempotente que
 * causó el error "SQLSTATE[HY000]: 1191 Can't find FULLTEXT index matching
 * the column list" en una sesión previa. Si los 5 índices ya existen en tu
 * base de datos (búsqueda ya funcionando), esta migración no hace nada al
 * correrla — es segura de instalar en cualquier momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->crearFulltextSiNoExiste('regulacion_nodos', 'ft_nodos_texto', 'texto');

        $this->crearFulltextSiNoExiste(
            'regulaciones',
            'ft_regulaciones_buscar',
            'nombre, resumen, objetivo, palabras_clave, materia'
        );

        $this->crearFulltextSiNoExiste(
            'tramites',
            'ft_tramites_buscar',
            'nombre_oficial, objetivo, poblacion_objetivo'
        );

        $this->crearFulltextSiNoExiste('requisitos', 'ft_requisitos_nombre', 'nombre');

        $this->crearFulltextSiNoExiste(
            'fundamento_juridico',
            'ft_fundamentos_buscar',
            'normativa_nombre, articulo_fraccion, resumen'
        );
    }

    public function down(): void
    {
        $this->eliminarFulltextSiExiste('regulacion_nodos', 'ft_nodos_texto');
        $this->eliminarFulltextSiExiste('regulaciones', 'ft_regulaciones_buscar');
        $this->eliminarFulltextSiExiste('tramites', 'ft_tramites_buscar');
        $this->eliminarFulltextSiExiste('requisitos', 'ft_requisitos_nombre');
        $this->eliminarFulltextSiExiste('fundamento_juridico', 'ft_fundamentos_buscar');
    }

    private function indiceExiste(string $tabla, string $indice): bool
    {
        $resultado = DB::selectOne(
            'SELECT COUNT(*) as total
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [$tabla, $indice]
        );

        return $resultado->total > 0;
    }

    private function crearFulltextSiNoExiste(string $tabla, string $indice, string $columnas): void
    {
        if ($this->indiceExiste($tabla, $indice)) {
            return;
        }

        DB::statement("ALTER TABLE {$tabla} ADD FULLTEXT INDEX {$indice} ({$columnas})");
    }

    private function eliminarFulltextSiExiste(string $tabla, string $indice): void
    {
        if (! $this->indiceExiste($tabla, $indice)) {
            return;
        }

        DB::statement("ALTER TABLE {$tabla} DROP INDEX {$indice}");
    }
};
