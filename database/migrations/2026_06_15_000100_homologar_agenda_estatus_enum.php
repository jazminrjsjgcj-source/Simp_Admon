<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Homologa el ENUM de `acciones_agenda.estatus` con el vocabulario de trámites.
 *
 * Antes (agenda):  borrador, en_revision, observado, aprobado, firmado, publicado
 * Después (igual que trámites): borrador, en_observacion, en_correccion, en_firma, completado
 *
 * La distinción entre "aprobado por la autoridad" (revisora/jurídico) y
 * "firmado por la dependencia" (sujeto/enlace) deja de vivir en el estatus y
 * pasa a la tabla `firmas`, igual que en trámites: ambos momentos comparten el
 * estado `en_firma` y el registro pasa a `completado` cuando se firma.
 *
 * Pasos (mismo patrón que el fix de trámites):
 * 1. Cambia la columna a VARCHAR para poder actualizar sin la restricción del ENUM.
 * 2. Migra los valores existentes al nuevo vocabulario.
 * 3. Redefine la columna como ENUM con los valores homologados.
 */
return new class extends Migration
{
    /** Mapa de valor viejo → valor nuevo. */
    private array $mapa = [
        'en_revision' => 'en_observacion', // la revisora la está observando
        'observado'   => 'en_correccion',  // tiene observaciones, se corrige
        'aprobado'    => 'en_firma',       // aprobada por autoridad, espera firma
        'firmado'     => 'en_firma',       // ya firmada (distinción vía tabla firmas)
        'publicado'   => 'completado',     // cierre
        // borrador se queda igual
    ];

    public function up(): void
    {
        // 1. Convertir a VARCHAR para poder actualizar sin restricción de ENUM.
        DB::statement("ALTER TABLE acciones_agenda MODIFY estatus VARCHAR(50) NOT NULL DEFAULT 'borrador'");

        // 2. Actualizar los valores existentes.
        foreach ($this->mapa as $viejo => $nuevo) {
            DB::table('acciones_agenda')
                ->where('estatus', $viejo)
                ->update(['estatus' => $nuevo]);
        }

        // 3. Redefinir como ENUM con los valores homologados.
        DB::statement("ALTER TABLE acciones_agenda MODIFY estatus ENUM('borrador','en_observacion','en_correccion','en_firma','completado') NOT NULL DEFAULT 'borrador'");
    }

    public function down(): void
    {
        // Mapa inverso (best-effort — los valores colapsados no se recuperan al 100%:
        // 'en_firma' regresa a 'aprobado' por defecto, no distingue el firmado previo).
        $mapaInverso = [
            'en_observacion' => 'en_revision',
            'en_correccion'  => 'observado',
            'en_firma'       => 'aprobado',
            'completado'     => 'publicado',
        ];

        DB::statement("ALTER TABLE acciones_agenda MODIFY estatus VARCHAR(50) NOT NULL DEFAULT 'borrador'");

        foreach ($mapaInverso as $nuevo => $viejo) {
            DB::table('acciones_agenda')->where('estatus', $nuevo)->update(['estatus' => $viejo]);
        }

        DB::statement("ALTER TABLE acciones_agenda MODIFY estatus ENUM('borrador','en_revision','observado','aprobado','firmado','publicado') NOT NULL DEFAULT 'borrador'");
    }
};
