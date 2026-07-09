<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita la "papelera" del articulado: en vez de borrar un nodo de la base de
 * datos, se marca con deleted_at (soft delete de Laravel). El nodo desaparece del
 * editor pero puede restaurarse durante un periodo, o limpiarse después.
 *
 * Nota: la FK parent_id sigue con cascadeOnDelete, pero esa cascada solo se
 * dispara en un DELETE físico (forceDelete / limpieza), no en el soft delete.
 * Por eso, al mandar a la papelera, los hijos se marcan explícitamente en código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->softDeletes(); // columna deleted_at nullable
        });
    }

    public function down(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
