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
            $table->string('firmante_nombre', 255)->nullable();
            $table->string('firmante_cargo',  255)->nullable();
            $table->string('firmante_email',  255)->nullable();
            $table->string('firmante_rfc',     20)->nullable();

            // Metadatos del proceso de firma
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('cadena_original')->nullable();
            $table->text('observaciones')->nullable();

            // Estructura para integración futura con autoridad certificadora
            $table->string('certificado_emisor', 255)->nullable();
            $table->string('certificado_serie',  100)->nullable();
            $table->json('metadata_firmante')->nullable();

            // Estado y revocación
            $table->string('estatus', 30)->default('activa');
            $table->timestamp('revocada_en')->nullable();
            $table->foreignId('revocada_por')->nullable()->constrained('users');
            $table->text('motivo_revocacion')->nullable();

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
