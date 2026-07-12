<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garantiza, en la base de datos, que solo haya UN periodo activo por tipo.
 *
 * ── La regla, y por qué no bastaba con el código ──────────────────────
 *
 * PeriodoService ya se ocupa de esto: cada vez que un periodo se activa, cierra los demás
 * activos de su tipo dentro de la misma transacción. Está bien pensado y funciona en el uso
 * normal.
 *
 * Pero una comprobación en código NUNCA puede garantizar unicidad bajo concurrencia. Mira
 * lo que pasa si dos administradores activan un periodo de agenda SyD al mismo tiempo:
 *
 *   Admin A                              Admin B
 *   ──────────────────────────────────────────────────────────────────
 *   BEGIN                                BEGIN
 *   cerrar activos de agenda_syd
 *   (no hay ninguno todavía)             cerrar activos de agenda_syd
 *                                        (TAMPOCO ve ninguno: el periodo
 *                                         que A está activando aún no
 *                                         está confirmado)
 *   activar P1
 *                                        activar P2
 *   COMMIT                               COMMIT
 *   ──────────────────────────────────────────────────────────────────
 *   Resultado: P1 y P2 activos a la vez. La regla se rompió.
 *
 * Ninguna de las dos transacciones ve el trabajo de la otra hasta que confirma. Es el mismo
 * problema que ya tuvimos con la homoclave y con la doble firma, y tiene la misma solución:
 * cuando la corrección depende de la concurrencia, no se la confías al código — se la
 * confías a la base.
 *
 * ── El índice ────────────────────────────────────────────────────────
 *
 * Es ÚNICO y PARCIAL: la restricción solo aplica a las filas con estatus 'activo'. Tiene
 * que ser parcial, porque sí puede haber muchos periodos 'cerrado' o 'proximo' del mismo
 * tipo — de hecho es lo normal, uno por semestre.
 *
 * ── Y por qué esto NO sustituye al código ────────────────────────────
 *
 * El índice impide el estado inválido, pero no lo resuelve con elegancia: sin la lógica del
 * servicio, activar un periodo daría un error de base de datos en vez de cerrar el anterior.
 *
 * Las dos capas hacen cosas distintas:
 *   - El servicio hace lo CORRECTO en el caso normal (cierra el anterior).
 *   - El índice impide lo IMPOSIBLE en el caso de carrera (dos activos a la vez).
 *
 * ── Aviso de portabilidad ────────────────────────────────────────────
 *
 * CREATE UNIQUE INDEX ... WHERE es sintaxis de PostgreSQL. Es la misma excepción consciente
 * que ya se hizo con el índice de firmas activas: PUNTA se despliega y se prueba en
 * PostgreSQL.
 */
return new class extends Migration
{
    private const NOMBRE_INDICE = 'uq_periodo_activo_por_tipo';

    public function up(): void
    {
        $this->abortarSiYaHayVariosActivos();

        DB::statement(
            'CREATE UNIQUE INDEX ' . self::NOMBRE_INDICE . '
             ON periodos (tipo)
             WHERE estatus = \'activo\''
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::NOMBRE_INDICE);
    }

    /**
     * Si la base YA tiene dos periodos activos del mismo tipo, el CREATE UNIQUE INDEX
     * fallaría con un error críptico de PostgreSQL.
     *
     * Mejor detectarlo aquí y parar con un mensaje que diga exactamente cuáles son, para que
     * una persona decida cuál es el bueno. Cerrar uno automáticamente NO es aceptable: el
     * periodo activo determina a qué agenda se imputan las acciones que se registran, y
     * elegir mal cambia dónde acaban.
     */
    private function abortarSiYaHayVariosActivos(): void
    {
        $duplicados = DB::table('periodos')
            ->select('tipo', DB::raw('COUNT(*) as veces'))
            ->where('estatus', 'activo')
            ->groupBy('tipo')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicados->isEmpty()) {
            return;
        }

        $detalle = $duplicados->map(function ($d) {
            $nombres = DB::table('periodos')
                ->where('estatus', 'activo')
                ->where('tipo', $d->tipo)
                ->pluck('nombre')
                ->implode('", "');

            return "  - Tipo '{$d->tipo}': {$d->veces} periodos activos → \"{$nombres}\"";
        })->implode("\n");

        throw new RuntimeException(
            "No se puede crear el índice único: ya hay varios periodos activos del mismo tipo.\n\n"
            . $detalle . "\n\n"
            . "El periodo activo determina a qué agenda se imputan las acciones que se registran. "
            . "Esta migración NO elige por su cuenta cuál cerrar. Entra al módulo de administración, "
            . "deja UNO activo por tipo, y vuelve a correr la migración."
        );
    }
};
