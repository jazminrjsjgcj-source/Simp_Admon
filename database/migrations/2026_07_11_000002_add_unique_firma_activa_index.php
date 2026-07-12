<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Impide, a nivel de base de datos, que un registro tenga DOS firmas ACTIVAS
 * del mismo tipo.
 *
 * ── El problema ──────────────────────────────────────────────────────
 *
 * FirmaDigitalService::firmar() ya comprobaba, antes de firmar, si el registro
 * tenía una firma activa de ese tipo (yaFirmadoPor). Pero esa comprobación es
 * "consultar y luego escribir": entre las dos cosas pasa un instante.
 *
 * Si un usuario da doble clic en "Firmar" —o la red reintenta la petición—, las
 * dos llegan al servidor a la vez, las dos consultan, las dos ven que no hay
 * firma, y las dos firman. El registro acaba con dos firmas activas idénticas.
 *
 * Consecuencia real: el trámite se auto-completa al detectar las firmas del
 * sujeto y del enlace. Con firmas duplicadas, ese conteo se descuadra y el
 * observer se dispara dos veces.
 *
 * ── La solución ──────────────────────────────────────────────────────
 *
 * Una comprobación en código nunca puede garantizar unicidad bajo concurrencia.
 * Solo la base de datos puede. Este índice hace que el segundo INSERT falle,
 * pase lo que pase.
 *
 * Es un índice ÚNICO PARCIAL: la restricción solo aplica a las filas con
 * estatus 'activa'. Tiene que ser parcial porque un registro sí puede tener
 * varias firmas REVOCADAS del mismo tipo (se firmó, se revocó, se volvió a
 * firmar, se volvió a revocar). Un índice único normal sobre las cuatro
 * columnas lo impediría.
 *
 * ── Aviso de portabilidad ────────────────────────────────────────────
 *
 * CREATE UNIQUE INDEX ... WHERE es sintaxis de PostgreSQL. El resto del proyecto
 * evita a propósito la sintaxis exclusiva de un motor, así que esto es una
 * excepción consciente: PUNTA se despliega en PostgreSQL y las pruebas corren
 * ahí (ver phpunit.xml). MySQL no tiene índices parciales; si algún día hubiera
 * que soportarlo, la alternativa sería una columna generada.
 */
return new class extends Migration
{
    private const NOMBRE_INDICE = 'uq_firma_activa_por_tipo';

    public function up(): void
    {
        $this->abortarSiYaHayDuplicados();

        DB::statement(
            'CREATE UNIQUE INDEX ' . self::NOMBRE_INDICE . '
             ON firmas (firmable_type, firmable_id, tipo)
             WHERE estatus = \'activa\''
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::NOMBRE_INDICE);
    }

    /**
     * Si la base YA tiene firmas duplicadas (creadas antes de que existiera este
     * índice), el CREATE UNIQUE INDEX fallaría con un error críptico de PostgreSQL.
     *
     * Es mejor detectarlo aquí y parar con un mensaje que diga exactamente qué
     * registros están duplicados, para que una persona decida cuál firma vale.
     * Borrar firmas automáticamente NO es aceptable: son actos jurídicos.
     */
    private function abortarSiYaHayDuplicados(): void
    {
        $duplicados = DB::table('firmas')
            ->select('firmable_type', 'firmable_id', 'tipo', DB::raw('COUNT(*) as veces'))
            ->where('estatus', 'activa')
            ->groupBy('firmable_type', 'firmable_id', 'tipo')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicados->isEmpty()) {
            return;
        }

        $detalle = $duplicados
            ->map(fn ($d) => "  - {$d->firmable_type} #{$d->firmable_id}, tipo '{$d->tipo}': {$d->veces} firmas activas")
            ->implode("\n");

        throw new RuntimeException(
            "No se puede crear el índice único: la tabla `firmas` ya tiene firmas activas duplicadas.\n\n"
            . $detalle . "\n\n"
            . "Una firma es un acto jurídico: esta migración NO las borra por su cuenta. "
            . "Revisa esos registros, decide cuál firma es la válida, revoca la otra "
            . "(estatus = 'revocada') y vuelve a correr la migración."
        );
    }
};
