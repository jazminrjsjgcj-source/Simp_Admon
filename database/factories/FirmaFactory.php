<?php

namespace Database\Factories;

use App\Models\Firma;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea firmas para las pruebas.
 *
 * ── AVISO IMPORTANTE ─────────────────────────────────────────────────
 *
 * Esta factory crea la FILA de la firma, pero NO calcula un hash válido: mete un
 * hash de relleno. Sirve para probar consultas, conteos y scopes ("¿cuántas firmas
 * activas tiene este trámite?", "¿ya firmó el sujeto?").
 *
 * Para cualquier prueba que tenga que ver con la VERIFICACIÓN, no uses la factory:
 * usa el servicio de verdad.
 *
 *     $firma = app(FirmaDigitalService::class)->firmar($tramite, $user, $tipo, $request);
 *
 * Solo el servicio arma la cadena original y calcula el hash correcto. Una firma
 * creada con la factory nunca verificará bien, y debe ser así: si verificara,
 * significaría que el hash no está comprobando nada.
 *
 * @extends Factory<Firma>
 */
class FirmaFactory extends Factory
{
    protected $model = Firma::class;

    public function definition(): array
    {
        $firmante = User::factory()->create();
        $tramite  = Tramite::factory()->enFirma()->create();

        return [
            'firmable_type'   => Tramite::class,
            'firmable_id'     => $tramite->id,
            'tipo'            => Firma::TIPO_ACEPTACION_SUJETO,
            'firmante_id'     => $firmante->id,
            'firmante_nombre' => $firmante->name,
            'firmante_email'  => $firmante->email,
            'fecha'           => now(),
            'hash_acuse'      => str_repeat('0', 64), // relleno: NO es un hash válido
            'cadena_original' => '{}',
            'estatus'         => Firma::ESTATUS_ACTIVA,
        ];
    }

    /** Firma del enlace en vez de la del sujeto obligado. */
    public function deEnlace(): static
    {
        return $this->state(fn () => ['tipo' => Firma::TIPO_ACEPTACION_ENLACE]);
    }

    /** Firma ya revocada: no cuenta para los conteos de firmas vigentes. */
    public function revocada(): static
    {
        return $this->state(fn () => [
            'estatus'           => Firma::ESTATUS_REVOCADA,
            'revocada_en'       => now(),
            'motivo_revocacion' => 'Firma de prueba revocada.',
        ]);
    }
}
