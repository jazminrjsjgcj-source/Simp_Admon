<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase C.2 — Tablas de catálogos editables.
 *
 * - tipos_regulacion : alimenta los selects de propuestas y regulaciones.
 * - tipos_tramite    : clasificación de los trámites (licencia, permiso, etc.).
 *
 * Ambas tienen soft-toggle (activo) para no perder histórico.
 * Se insertan los valores que antes estaban hardcodeados en las vistas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tipos de regulación
        Schema::create('tipos_regulacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        // 2. Tipos de trámite
        Schema::create('tipos_tramite', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        // FK en tramites (nullable para no romper registros existentes)
        Schema::table('tramites', function (Blueprint $table) {
            $table->foreignId('tipo_tramite_id')
                  ->nullable()
                  
                  ->constrained('tipos_tramite')
                  ->nullOnDelete();
        });

        // Seed: valores que antes estaban hardcodeados
        DB::table('tipos_regulacion')->insert([
            ['nombre' => 'Reglamento',          'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Acuerdo',             'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Lineamiento',         'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Manual',              'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Circular',            'orden' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Reglas de Operación', 'orden' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ley',                 'orden' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Norma',               'orden' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Otro',                'orden' => 9, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('tipos_tramite')->insert([
            ['nombre' => 'Licencia',             'descripcion' => 'Autorización para ejercer una actividad.', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Permiso',              'descripcion' => 'Autorización temporal o específica.',      'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Registro',             'descripcion' => 'Inscripción en un padrón oficial.',        'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Certificado',          'descripcion' => 'Documento que certifica un hecho.',        'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Constancia',           'descripcion' => 'Documento que hace constar un dato.',      'orden' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Autorización',         'descripcion' => 'Aprobación formal de una solicitud.',      'orden' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Inscripción',          'descripcion' => 'Alta en un registro o padrón.',            'orden' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Aviso',                'descripcion' => 'Notificación sin respuesta.',              'orden' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Dictamen',             'descripcion' => 'Evaluación técnica oficial.',              'orden' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Otro',                 'descripcion' => null,                                       'orden' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->dropForeign(['tipo_tramite_id']);
            $table->dropColumn('tipo_tramite_id');
        });
        Schema::dropIfExists('tipos_tramite');
        Schema::dropIfExists('tipos_regulacion');
    }
};
