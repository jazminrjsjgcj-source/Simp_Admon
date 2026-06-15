<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 'aprobacion_juridico' al ENUM `firmas.tipo`.
 *
 * El código (FirmaController y el modelo Firma con TIPO_APROBACION_JURIDICO)
 * ya usa este tipo para la firma/aprobación de jurídico, pero el ENUM original
 * de la tabla solo tenía cuatro valores:
 *   aceptacion_sujeto, aceptacion_enlace, aprobacion_revisora, firma_fisica
 * Sin este valor, al intentar guardar una firma de jurídico la base de datos
 * la rechaza. Esta migración añade el quinto valor permitido.
 *
 * No migra datos: solo amplía los valores que el ENUM acepta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE firmas MODIFY tipo ENUM(" .
            "'aceptacion_sujeto', 'aceptacion_enlace', 'aprobacion_revisora', " .
            "'aprobacion_juridico', 'firma_fisica') NOT NULL"
        );
    }

    public function down(): void
    {
        // Si ya hay firmas de jurídico, no se puede revertir sin perder datos.
        $existen = DB::table('firmas')->where('tipo', 'aprobacion_juridico')->exists();
        if ($existen) {
            throw new \RuntimeException(
                'No se puede revertir: existen firmas con tipo aprobacion_juridico. ' .
                'Reasigne o elimine esas firmas antes de revertir esta migración.'
            );
        }

        DB::statement(
            "ALTER TABLE firmas MODIFY tipo ENUM(" .
            "'aceptacion_sujeto', 'aceptacion_enlace', 'aprobacion_revisora', " .
            "'firma_fisica') NOT NULL"
        );
    }
};
