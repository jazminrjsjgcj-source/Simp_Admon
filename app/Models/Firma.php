<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Firma digital de acuse / aceptación.
 *
 * Modelo polimórfico: una firma pertenece a un trámite, una acción de
 * agenda, o una propuesta regulatoria. Cada firma tiene un hash único
 * que sella la información firmada en el momento.
 *
 * Estructura preparada técnicamente para integración futura con
 * autoridad certificadora externa (campos certificado_emisor,
 * certificado_serie, metadata_firmante).
 */
class Firma extends Model
{
    protected $table   = 'firmas';
    protected $guarded = ['id'];

    /**
     * Tipos de firma soportados.
     *
     * Hay DOS categorías conceptuales:
     *
     *  (A) FIRMA OFICIAL — la validación del documento por parte de quien
     *      es dueño del trámite. Hoy se hace con hash SHA-256, pero estos
     *      dos tipos son los que en el futuro llevarán FIEL (e.firma del SAT).
     *      El espacio para la FIEL (certificado, RFC, cadena, sello) aplica
     *      ÚNICAMENTE a estos dos tipos. Ver tipoUsaFiel() más abajo.
     *        - TIPO_ACEPTACION_SUJETO  (titular / sujeto obligado)
     *        - TIPO_ACEPTACION_ENLACE  (enlace de simplificación)
     *
     *  (B) APROBACIÓN — el visto bueno de la autoridad revisora. Es un
     *      hash común y SE QUEDA en hash (no lleva FIEL). El jurídico es
     *      un aprobador OPCIONAL: puede dar su visto bueno, pero su firma
     *      no bloquea el avance del flujo.
     *        - TIPO_APROBACION_REVISORA
     *        - TIPO_APROBACION_JURIDICO (opcional)
     */
    public const TIPO_ACEPTACION_SUJETO    = 'aceptacion_sujeto';
    public const TIPO_ACEPTACION_ENLACE    = 'aceptacion_enlace';
    public const TIPO_APROBACION_REVISORA  = 'aprobacion_revisora';
    public const TIPO_APROBACION_JURIDICO  = 'aprobacion_juridico';
    public const TIPO_FIRMA_FISICA         = 'firma_fisica';

    /**
     * Indica si un tipo de firma corresponde a la FIRMA OFICIAL (categoría A),
     * es decir, la que en el futuro usará FIEL en lugar de hash.
     *
     * FIEL FUTURA — cuando se integre la e.firma:
     *   Si tipoUsaFiel($tipo) es true, en vez de calcular el hash con
     *   FirmaDigitalService::firmar(), el flujo deberá:
     *     1. Pedir al usuario su .cer y .key + contraseña (o token del SAT).
     *     2. Generar el sello con la llave privada sobre la cadena original.
     *     3. Guardar en las columnas ya preparadas: certificado_emisor,
     *        certificado_serie, firmante_rfc y metadata_firmante (sello,
     *        cadena original, fecha del certificado).
     *   Para revisora y jurídico (categoría B) esto NO aplica: siguen con hash.
     */
    public static function tipoUsaFiel(string $tipo): bool
    {
        return in_array($tipo, [
            self::TIPO_ACEPTACION_SUJETO,
            self::TIPO_ACEPTACION_ENLACE,
        ], true);
    }

    /** Estatus. */
    public const ESTATUS_ACTIVA   = 'activa';
    public const ESTATUS_REVOCADA = 'revocada';

    protected $casts = [
        'fecha'              => 'datetime',
        'revocada_en'        => 'datetime',
        'metadata_firmante'  => 'array',
    ];

    // ========== Relaciones ==========

    public function firmable()
    {
        return $this->morphTo();
    }

    public function firmante()
    {
        return $this->belongsTo(User::class, 'firmante_id');
    }

    public function revocadaPor()
    {
        return $this->belongsTo(User::class, 'revocada_por');
    }

    // ========== Scopes ==========

    public function scopeActivas($query)
    {
        return $query->where('estatus', self::ESTATUS_ACTIVA);
    }

    public function scopeRevocadas($query)
    {
        return $query->where('estatus', self::ESTATUS_REVOCADA);
    }

    public function scopeDelTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // ========== Helpers ==========

    public function estaActiva(): bool
    {
        return $this->estatus === self::ESTATUS_ACTIVA;
    }

    public function estaRevocada(): bool
    {
        return $this->estatus === self::ESTATUS_REVOCADA;
    }

    public function tipoLegible(): string
    {
        return match($this->tipo) {
            self::TIPO_ACEPTACION_SUJETO   => 'Aceptación del sujeto obligado',
            self::TIPO_ACEPTACION_ENLACE   => 'Aceptación del enlace',
            self::TIPO_APROBACION_REVISORA => 'Aprobación del revisor',
            self::TIPO_APROBACION_JURIDICO => 'Aprobación de jurídico',
            self::TIPO_FIRMA_FISICA        => 'Firma física',
            default                        => ucfirst(str_replace('_', ' ', $this->tipo)),
        };
    }
}
