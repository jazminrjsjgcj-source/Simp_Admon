<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la tabla `firmas` con la estructura técnica completa para
 * el módulo de firma digital de acuses y aceptaciones.
 *
 * La estructura está diseñada para soportar:
 *   - Firmas internas (aceptación de enlace y sujeto obligado)
 *   - Firmas con certificado externo en el futuro (la columna
 *     `certificado_emisor` y `metadata_firmante` permite registrar
 *     emisor, número de serie, RFC del firmante, etc.)
 *
 * No se exponen estos conceptos en la UI; solo es estructura técnica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmas', function (Blueprint $table) {
            // Columnas existentes: id, firmable_*, tipo, firmante_id, fecha, hash_acuse

            // Datos del firmante en el momento de firmar (snapshot)
            $table->string('firmante_nombre', 255)->nullable()->after('firmante_id');
            $table->string('firmante_cargo',  255)->nullable()->after('firmante_nombre');
            $table->string('firmante_email',  255)->nullable()->after('firmante_cargo');
            $table->string('firmante_rfc',     20)->nullable()->after('firmante_email');

            // Metadatos del proceso de firma
            $table->string('ip_origen', 45)->nullable()->after('hash_acuse');
            $table->string('user_agent', 500)->nullable()->after('ip_origen');
            $table->text('cadena_original')->nullable()->after('user_agent');
            $table->text('observaciones')->nullable()->after('cadena_original');

            // Estructura para integración futura con autoridad certificadora
            $table->string('certificado_emisor', 255)->nullable()->after('observaciones');
            $table->string('certificado_serie',  100)->nullable()->after('certificado_emisor');
            $table->json('metadata_firmante')->nullable()->after('certificado_serie');

            // Estado y revocación
            $table->enum('estatus', ['activa', 'revocada'])->default('activa')->after('metadata_firmante');
            $table->timestamp('revocada_en')->nullable()->after('estatus');
            $table->foreignId('revocada_por')->nullable()->after('revocada_en')->constrained('users');
            $table->text('motivo_revocacion')->nullable()->after('revocada_por');

            $table->index(['firmable_type', 'firmable_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::table('firmas', function (Blueprint $table) {
            $table->dropForeign(['revocada_por']);
            $table->dropIndex(['firmable_type', 'firmable_id', 'estatus']);
            $table->dropColumn([
                'firmante_nombre', 'firmante_cargo', 'firmante_email', 'firmante_rfc',
                'ip_origen', 'user_agent', 'cadena_original', 'observaciones',
                'certificado_emisor', 'certificado_serie', 'metadata_firmante',
                'estatus', 'revocada_en', 'revocada_por', 'motivo_revocacion',
            ]);
        });
    }
};
