<?php

namespace App\Services;

use App\Models\Firma;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Servicio para gestionar firmas digitales sobre registros polimórficos
 * (trámites, acciones de agenda, propuestas regulatorias).
 *
 * Responsabilidades:
 *   1. Generar la cadena original que se va a firmar (snapshot de campos clave).
 *   2. Calcular el hash de acuse (SHA-256 sobre la cadena original).
 *   3. Persistir la firma con snapshot del firmante, IP y user agent.
 *   4. Verificar la integridad de una firma (recalcular hash y comparar).
 *   5. Revocar una firma activa con motivo y trazabilidad.
 *
 * La estructura está preparada para integrar autoridad certificadora
 * externa (columnas certificado_emisor, certificado_serie, metadata_firmante)
 * cuando exista la disposición correspondiente.
 */
class FirmaDigitalService
{
    /**
     * Firma un registro polimórfico (trámite, acción, propuesta).
     *
     * @param  Model   $firmable     Registro a firmar
     * @param  User    $firmante     Usuario que firma
     * @param  string  $tipo         Tipo de firma (constantes en modelo Firma)
     * @param  Request $request      Para capturar IP y user agent
     * @param  ?string $observaciones Texto libre opcional
     */
    public function firmar(
        Model $firmable,
        User $firmante,
        string $tipo,
        Request $request,
        ?string $observaciones = null
    ): Firma {
        $cadenaOriginal = $this->generarCadenaOriginal($firmable, $firmante, $tipo);
        $hashAcuse      = $this->calcularHash($cadenaOriginal);

        return Firma::create([
            'firmable_type'    => get_class($firmable),
            'firmable_id'      => $firmable->id,
            'tipo'             => $tipo,
            'firmante_id'      => $firmante->id,
            'firmante_nombre'  => $firmante->name,
            'firmante_cargo'   => $firmante->cargo,
            'firmante_email'   => $firmante->email,
            'fecha'            => now(),
            'hash_acuse'       => $hashAcuse,
            'cadena_original'  => $cadenaOriginal,
            'ip_origen'        => $request->ip(),
            'user_agent'       => substr($request->userAgent() ?? '', 0, 500),
            'observaciones'    => $observaciones,
            'estatus'          => Firma::ESTATUS_ACTIVA,
        ]);
    }

    /**
     * Verifica que el hash de una firma sea consistente con su cadena original.
     * Si alguien manipuló la cadena original en la base, esto detecta la inconsistencia.
     */
    public function verificarIntegridad(Firma $firma): bool
    {
        if (empty($firma->cadena_original) || empty($firma->hash_acuse)) {
            return false;
        }

        return $this->calcularHash($firma->cadena_original) === $firma->hash_acuse;
    }

    /**
     * Revoca una firma activa. Una firma revocada deja de contar para los
     * conteos de firmas vigentes, pero no se elimina por trazabilidad.
     */
    public function revocar(Firma $firma, User $revocadaPor, string $motivo): void
    {
        if ($firma->estaRevocada()) {
            return;
        }

        $firma->update([
            'estatus'          => Firma::ESTATUS_REVOCADA,
            'revocada_en'      => now(),
            'revocada_por'     => $revocadaPor->id,
            'motivo_revocacion' => $motivo,
        ]);
    }

    /**
     * Devuelve las firmas activas que un registro polimórfico tiene.
     */
    public function firmasActivas(Model $firmable)
    {
        return Firma::activas()
            ->where('firmable_type', get_class($firmable))
            ->where('firmable_id',   $firmable->id)
            ->with('firmante')
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Verifica si un registro ya tiene una firma activa de cierto tipo.
     */
    public function yaFirmadoPor(Model $firmable, string $tipo): bool
    {
        return Firma::activas()
            ->where('firmable_type', get_class($firmable))
            ->where('firmable_id',   $firmable->id)
            ->delTipo($tipo)
            ->exists();
    }

    /**
     * Genera la "cadena original": representación serializada de los campos
     * clave del registro en el momento de firmar. Esta cadena es lo que se
     * hashea, así que cualquier cambio posterior al registro queda detectable
     * al verificar la firma.
     */
    private function generarCadenaOriginal(Model $firmable, User $firmante, string $tipo): string
    {
        $datos = [
            'tipo_firma'    => $tipo,
            'firmable_type' => get_class($firmable),
            'firmable_id'   => $firmable->id,
            'firmable_data' => $this->extraerDatosClaveDelFirmable($firmable),
            'firmante_id'   => $firmante->id,
            'firmante_rfc'  => $firmante->rfc ?? null,
            'firmado_en'    => now()->toIso8601String(),
        ];
           ksort($datos); return json_encode($datos, JSON_UNESCAPED_UNICODE);    
            }

    /**
     * Extrae los datos clave del modelo polimórfico para incluir en la
     * cadena original. Los campos específicos dependen del tipo.
     */
    private function extraerDatosClaveDelFirmable(Model $firmable): array
    {
        if ($firmable instanceof Tramite) {
            return [
                'homoclave'      => $firmable->homoclave,
                'nombre_oficial' => $firmable->nombre_oficial,
                'cbu_unitario'   => $firmable->cbu_unitario,
                'cbt_total'      => $firmable->cbt_total,
                'estatus'        => $firmable->estatus,
            ];
        }

        // Default: campos comunes presentes en otros modelos firmables
        return array_filter([
            'nombre'      => $firmable->nombre        ?? null,
            'descripcion' => $firmable->descripcion   ?? null,
            'estatus'     => $firmable->estatus       ?? null,
        ]);
    }

    private function calcularHash(string $cadena): string
    {
        return hash('sha256', $cadena);
    }
}
