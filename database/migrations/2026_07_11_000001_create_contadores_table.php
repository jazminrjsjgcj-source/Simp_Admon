<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `contadores`: una sola fuente para los números de serie del sistema.
 *
 * ── El problema que resuelve ──────────────────────────────────────────
 *
 * Hasta ahora, cada vez que el sistema necesitaba "el siguiente número" (el
 * consecutivo de la homoclave, el consecutivo del folio), lo AVERIGUABA leyendo
 * lo que ya había en la tabla y sumando 1.
 *
 * Eso falla en dos escenarios reales:
 *
 *   1. Dos personas dan de alta a la vez. Las dos leen el mismo máximo, las dos
 *      se llevan el mismo número, y la segunda choca contra el índice único.
 *
 *   2. El folio se ordenaba como TEXTO. A partir del registro 1000 la serie se
 *      atascaba, porque en un diccionario "999" va después de "1000".
 *
 * ── Cómo lo resuelve ──────────────────────────────────────────────────
 *
 * En vez de AVERIGUAR el número, se PIDE. Cada serie es una fila de esta tabla,
 * y pedir un número la bloquea mientras se incrementa. El segundo que llegue
 * espera su turno y recibe un número distinto. No hay nada que ordenar ni que
 * comparar, así que no hay nada que se pueda romper en el registro 1000.
 *
 * ── Claves ────────────────────────────────────────────────────────────
 *
 *   'tramite.homoclave'                  → consecutivo global de trámites
 *   'folio:LPZ-SIM-DGGD-2026-'           → una serie por prefijo de folio
 *   'folio:LPZ-PROP-DGGD-2026-'          → (tipo + dependencia + año)
 *
 * ── Lo importante de esta migración ───────────────────────────────────
 *
 * NO basta con crear la tabla vacía. Si el contador arrancara en 0, el sistema
 * empezaría a repartir números ya usados y chocaría contra los índices únicos
 * desde el primer trámite nuevo. Por eso la migración RELLENA cada contador con
 * el máximo que ya existe en los datos actuales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contadores', function (Blueprint $table) {
            $table->id();

            // Qué serie es. Ej.: 'tramite.homoclave', 'folio:LPZ-SIM-DGGD-2026-'
            $table->string('clave', 191)->unique();

            // Último número entregado. El siguiente que se pida será valor + 1.
            $table->unsignedBigInteger('valor')->default(0);

            $table->timestamps();
        });

        $this->rellenarDesdeLosDatosActuales();
    }

    public function down(): void
    {
        Schema::dropIfExists('contadores');
    }

    /**
     * Arranca cada contador en el número más alto que YA se usó, para que el
     * sistema siga la serie donde iba y no repita identificadores.
     */
    private function rellenarDesdeLosDatosActuales(): void
    {
        $this->rellenarHomoclaveDeTramites();

        // Los cuatro modelos que usan el trait GeneraFolio, con su tabla.
        foreach (['acciones_agenda', 'propuestas_regulatorias', 'analisis_impacto_regulatorio', 'regulaciones'] as $tabla) {
            $this->rellenarFoliosDe($tabla);
        }
    }

    /**
     * El consecutivo de la homoclave es el último segmento: LPZ-T-DGGD-DSA-41 → 41.
     * Se toma el máximo de TODOS los trámites, incluidos los de la papelera: un
     * trámite borrado no libera su número (si lo liberara, dos trámites distintos
     * podrían acabar con la misma homoclave).
     */
    private function rellenarHomoclaveDeTramites(): void
    {
        if (! Schema::hasTable('tramites')) {
            return;
        }

        $maximo = 0;

        // Se lee en PHP y no con SQL para no depender de funciones de texto que
        // cambian entre MySQL y PostgreSQL. Es un recorrido de una sola vez, en la
        // migración; no en cada alta de trámite (que era justo el problema).
        DB::table('tramites')
            ->whereNotNull('homoclave')
            ->orderBy('id')
            ->chunk(500, function ($filas) use (&$maximo) {
                foreach ($filas as $fila) {
                    $ultimo = substr((string) strrchr((string) $fila->homoclave, '-'), 1);
                    if (ctype_digit($ultimo)) {
                        $maximo = max($maximo, (int) $ultimo);
                    }
                }
            });

        $this->guardarContador('tramite.homoclave', $maximo);
    }

    /**
     * Cada folio ya emitido pertenece a una serie identificada por su prefijo
     * (LPZ-SIM-DGGD-2026-). Se agrupa por prefijo y se guarda el máximo de cada uno.
     */
    private function rellenarFoliosDe(string $tabla): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        $maximosPorPrefijo = [];

        DB::table($tabla)
            ->whereNotNull('folio')
            ->orderBy('id')
            ->chunk(500, function ($filas) use (&$maximosPorPrefijo) {
                foreach ($filas as $fila) {
                    $folio = (string) $fila->folio;

                    // Separa el folio en prefijo + consecutivo por el ÚLTIMO guion.
                    $corte = strrpos($folio, '-');
                    if ($corte === false) {
                        continue;
                    }

                    $prefijo     = substr($folio, 0, $corte + 1); // incluye el guion
                    $consecutivo = substr($folio, $corte + 1);

                    if (! ctype_digit($consecutivo)) {
                        continue;
                    }

                    $actual = $maximosPorPrefijo[$prefijo] ?? 0;
                    $maximosPorPrefijo[$prefijo] = max($actual, (int) $consecutivo);
                }
            });

        foreach ($maximosPorPrefijo as $prefijo => $maximo) {
            $this->guardarContador('folio:' . $prefijo, $maximo);
        }
    }

    /**
     * Guarda un contador. Si la clave ya existe (por ejemplo, si la migración se
     * corre dos veces), se queda con el valor MÁS ALTO de los dos: nunca se
     * retrocede una serie, porque retroceder significa repetir identificadores.
     */
    private function guardarContador(string $clave, int $valor): void
    {
        $existente = DB::table('contadores')->where('clave', $clave)->value('valor');

        if ($existente === null) {
            DB::table('contadores')->insert([
                'clave'      => $clave,
                'valor'      => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($valor > (int) $existente) {
            DB::table('contadores')
                ->where('clave', $clave)
                ->update(['valor' => $valor, 'updated_at' => now()]);
        }
    }
};
