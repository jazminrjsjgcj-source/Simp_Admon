<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea el ENUM de `tramites.estatus` con las constantes del modelo Tramite.
 *
 * El ENUM original tenía: borrador, en_revision, observado, corregido, aprobado, firmado, publicado
 * El modelo usa:          borrador, en_observacion, en_correccion, en_firma, completado
 *
 * Pasos:
 * 1. Cambia temporalmente la columna a VARCHAR para poder actualizar los datos.
 * 2. Migra los valores existentes al nuevo vocabulario.
 * 3. Redefine la columna como ENUM con los valores correctos.
 */
return new class extends Migration
{
    /** Mapa de valor viejo → valor nuevo. */
    private array $mapa = [
        'en_revision' => 'en_observacion',
        'observado'   => 'en_correccion',
        'corregido'   => 'en_correccion',
        'aprobado'    => 'completado',
        'firmado'     => 'completado',
        'publicado'   => 'completado',
        // borrador se queda igual
    ];

    public function up(): void
    {
        // 1. Convertir a VARCHAR para poder actualizar sin restricción de ENUM
        DB::statement("ALTER TABLE tramites MODIFY estatus VARCHAR(50) NOT NULL DEFAULT 'borrador'");

        // 2. Actualizar los valores existentes
        foreach ($this->mapa as $viejo => $nuevo) {
            DB::table('tramites')
                ->where('estatus', $viejo)
                ->update(['estatus' => $nuevo]);
        }

        // 3. Redefinir como ENUM con los valores correctos
        DB::statement("ALTER TABLE tramites MODIFY estatus ENUM('borrador','en_observacion','en_correccion','en_firma','completado') NOT NULL DEFAULT 'borrador'");
    }

    public function down(): void
    {
        // Mapa inverso (best-effort — los valores colapsados no se pueden recuperar)
        $mapaInverso = [
            'en_observacion' => 'en_revision',
            'en_correccion'  => 'observado',
            'en_firma'       => 'firmado',
            'completado'     => 'aprobado',
        ];

        DB::statement("ALTER TABLE tramites MODIFY estatus VARCHAR(50) NOT NULL DEFAULT 'borrador'");

        foreach ($mapaInverso as $nuevo => $viejo) {
            DB::table('tramites')->where('estatus', $nuevo)->update(['estatus' => $viejo]);
        }

        DB::statement("ALTER TABLE tramites MODIFY estatus ENUM('borrador','en_revision','observado','corregido','aprobado','firmado','publicado') NOT NULL DEFAULT 'borrador'");
    }
};
