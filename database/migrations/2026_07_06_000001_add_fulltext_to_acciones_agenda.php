<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega un índice FULLTEXT a acciones_agenda para que el buscador global
 * pueda incluir acciones de agenda cuando el usuario filtra por regulación.
 *
 * Misma estrategia idempotente que 2026_07_04_000002_add_fulltext_indexes_for_buscador.php:
 * verifica que el índice no exista antes de crearlo, para que la migración
 * sea segura de correr cualquier número de veces.
 *
 * Columnas indexadas:
 *   - descripcion  → el objetivo/descripción de la acción (text)
 *   - meta         → la meta o indicador de éxito de la acción (string 500)
 *
 * Nota: 'tipo' (enum: simplificacion/digitalizacion) NO se incluye en el
 * índice FULLTEXT porque MySQL no permite mezclar ENUMs cortos con FULLTEXT
 * de forma fiable — además, el tipo ya viene implícito cuando el usuario
 * busca "simplificación" o "digitalización" dentro del texto de descripcion.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->crearFulltextSiNoExiste(
            'acciones_agenda',
            'ft_acciones_agenda_buscar',
            'descripcion, meta'
        );
    }

    public function down(): void
    {
        $this->eliminarFulltextSiExiste('acciones_agenda', 'ft_acciones_agenda_buscar');
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
