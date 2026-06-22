<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\UnidadValorReferencia;

/**
 * Concepto de pago de derechos ligado a un trámite.
 *
 * Son cobros fiscales (ej. "Derecho de inspección: $250") independientes
 * del costo público del trámite. Un trámite puede tener varios.
 */
class TramiteDerecho extends Model
{
    protected $table   = 'tramite_derechos';

    /**
     * Columnas asignables en masa (sin id ni timestamps). 'unidad' y
     * 'es_variable' las agrega la migración add_unidad_es_variable (bug #B4);
     * las fj_* vienen de add_fundamento. Reconstruido desde las migraciones
     * de tramite_derechos.
     */
    protected $fillable = [
        'tramite_id',
        'concepto',
        'monto',
        'unidad',
        'es_variable',
        'fj_norma',
        'fj_capitulo',
        'fj_articulo',
    ];

    protected $casts = [
        'monto'       => 'decimal:2',
        'es_variable' => 'boolean',
    ];

    /** Valor de la unidad cuando el derecho se expresa en pesos. */
    public const UNIDAD_PESOS = 'pesos';
    /** Valor de la unidad cuando el derecho se expresa en UMA. */
    public const UNIDAD_UMA = 'UMA';

    /**
     * El trámite al que pertenece este concepto de derecho.
     */
    public function tramite()
    {
        return $this->belongsTo(Tramite::class);
    }

    /**
     * Convierte el JSON de derechos enviado por los wizards (campo
     * `derechos_json`) en una lista limpia de conceptos. Centraliza el parseo
     * para que los wizards de trámite y de agenda consuman la misma lógica.
     *
     * @param  string|null $json  Contenido del campo derechos_json.
     * @return array<int, array{concepto: string, monto: float, unidad: string, es_variable: bool}>
     */
    public static function parsearJson(?string $json): array
    {
        $lista = json_decode($json ?? '[]', true);
        if (!is_array($lista)) {
            return [];
        }

        $derechos = [];
        foreach ($lista as $fila) {
            $concepto = trim($fila['concepto'] ?? '');
            if ($concepto === '') {
                continue;
            }
            $unidad = ($fila['unidad'] ?? self::UNIDAD_PESOS) === self::UNIDAD_UMA
                ? self::UNIDAD_UMA
                : self::UNIDAD_PESOS;
            $derechos[] = [
                'concepto'    => $concepto,
                'monto'       => floatval($fila['monto'] ?? 0),
                'unidad'      => $unidad,
                'es_variable' => !empty($fila['es_variable']),
                'fj_norma'    => $fila['fj_norma']    ?? null,
                'fj_capitulo' => $fila['fj_capitulo'] ?? null,
                'fj_articulo' => $fila['fj_articulo'] ?? null,
            ];
        }
        return $derechos;
    }

    /**
     * Suma todos los derechos en PESOS, convirtiendo los que están en UMA
     * con el valor vigente. Es el único lugar donde se hace la conversión,
     * para que el wizard de trámite y el controlador usen el mismo cálculo
     * y la fórmula del costo burocrático no se duplique.
     *
     * @param  array<int, array{monto: float, unidad?: string}> $derechos
     * @return float  Total en pesos.
     */
    public static function totalEnPesos(array $derechos): float
    {
        $valorUma = self::valorUmaVigente();

        return collect($derechos)->sum(function ($d) use ($valorUma) {
            $monto  = floatval($d['monto'] ?? 0);
            $unidad = $d['unidad'] ?? self::UNIDAD_PESOS;
            return $unidad === self::UNIDAD_UMA ? $monto * $valorUma : $monto;
        });
    }

    /**
     * Valor en pesos de una UMA, según el registro activo más reciente.
     * Si no hay valor cargado, devuelve 0 (no inventa un valor).
     */
    public static function valorUmaVigente(): float
    {
        $registro = UnidadValorReferencia::where('unidad', UnidadValorReferencia::UMA)
            ->where('activo', true)
            ->orderByDesc('anio')
            ->first();

        return $registro ? (float) $registro->valor_pesos : 0.0;
    }
}
