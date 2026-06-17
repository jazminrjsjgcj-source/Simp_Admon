<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Concepto de pago de derechos ligado a un trámite.
 *
 * Son cobros fiscales (ej. "Derecho de inspección: $250") independientes
 * del costo público del trámite. Un trámite puede tener varios.
 */
class TramiteDerecho extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'tramite_derechos';

    protected $casts = [
        'monto' => 'decimal:2',
    ];

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
     * @return array<int, array{concepto: string, monto: float}>
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
            $derechos[] = [
                'concepto' => $concepto,
                'monto'    => floatval($fila['monto'] ?? 0),
            ];
        }
        return $derechos;
    }
}
