<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensancha requisitos.tipo_presentacion de ENUM a VARCHAR(150) para permitir
 * "original y copia" simultáneos (hallazgo #44).
 *
 * El formulario de requisitos ahora ofrece checkboxes (original / copia /
 * digital) que pueden marcarse a la vez. El servicio los guarda como lista
 * separada por comas (ej. "original,copia"). Un ENUM solo admite UN valor de
 * su lista, por lo que un CSV daba "Data truncated". VARCHAR(150) es holgado
 * para las combinaciones posibles y conserva la compatibilidad con los valores
 * heredados de un solo tipo ('documento', 'comprobante', etc.) que siguen en
 * la tabla y en los seeders.
 *
 * Debe correr DESPUÉS de 2026_06_26_000000_alter_requisitos_tipo_presentacion_enum
 * (el timestamp mayor lo garantiza).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `requisitos` MODIFY `tipo_presentacion` VARCHAR(150) NULL"
        );
    }

    /**
     * Revierte a ENUM. Advertencia: si alguna fila guardó un CSV multivalor
     * (ej. "original,copia"), al volver a ENUM MySQL la truncará a NULL porque
     * ese texto no es un valor válido del ENUM. Es el comportamiento esperado
     * de un rollback destructivo; se conserva la misma lista de valores que
     * dejó la migración de junio-26 para no divergir.
     */
    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `requisitos` MODIFY `tipo_presentacion` "
            . "ENUM('original','copia','digital','documento','formato','comprobante','producto_tramite') NULL"
        );
    }
};
