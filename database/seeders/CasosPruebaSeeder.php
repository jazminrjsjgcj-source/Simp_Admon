<?php

namespace Database\Seeders;

use App\Models\AccionAgenda;
use App\Models\Role;
use App\Models\User;
use App\Services\HitoAgendaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CasosPruebaSeeder — escenario para revisar manualmente los arreglos de
 * seguridad y visibilidad (Fase 0 + visibilidad por dependencia + firmas).
 *
 * Crea DOS dependencias de prueba (A y B), cada una con sus usuarios por rol,
 * más un admin y una revisora globales, y datos en cada módulo para poder
 * recorrer la guía de pruebas:
 *   - Trámites (incluido uno "en firma" para la cola de firmas).
 *   - Un requisito con una regulación citada (para verificar que la cita
 *     persiste al editar — bug C1).
 *   - Acciones de agenda (para borrar/cambiar estatus — bugs C2/C3).
 *   - Propuestas regulatorias (para la visibilidad del jurídico por URL).
 *
 * Todo lleva el prefijo [PRUEBA] y se puede borrar sin tocar datos reales:
 *   php artisan tinker
 *   >>> (new \Database\Seeders\CasosPruebaSeeder)->borrarCasos();
 *
 * Es idempotente: al correrlo limpia primero sus propios datos y los recrea,
 * así que puede ejecutarse varias veces sin duplicar:
 *   php artisan db:seed --class=CasosPruebaSeeder
 *
 * NOTA: las firmas no se siembran (su hash se genera en el flujo real). Para
 * probar la revocación de firmas (C5), firma primero un trámite "en firma"
 * desde la interfaz y luego intenta revocarlo con otro rol.
 */
class CasosPruebaSeeder extends Seeder
{
    private const PREFIJO  = '[PRUEBA] ';
    private const PASSWORD = 'punta2026';

    /** Códigos de dependencia que se usan como A y B (deben existir). */
    private const CODIGO_DEP_A = '110';
    private const CODIGO_DEP_B = '111';

    public function run(): void
    {
        // 1) Limpia cualquier corrida anterior (idempotencia).
        $this->borrarCasos();

        // 2) Garantiza que existan permisos y roles del ACL (idempotente).
        //    Sin esto, la revisora no tendría el permiso 'aprobar' y no sería
        //    tratada como transversal por las reglas de visibilidad.
        $this->call(AclSeeder::class);

        // 3) Resuelve las dos dependencias de prueba.
        $depA = $this->dependenciaPorCodigo(self::CODIGO_DEP_A);
        $depB = $this->dependenciaPorCodigo(self::CODIGO_DEP_B);

        if (!$depA || !$depB) {
            $this->command?->error('CasosPruebaSeeder: faltan las dependencias de prueba. Corre primero el seeder base.');
            return;
        }

        // 4) Crea los usuarios de prueba y les asigna su rol en el ACL.
        $this->crearUsuariosDePrueba($depA->id, $depB->id);

        // 5) Crea el escenario de datos en cada dependencia, usando como autor
        //    al enlace de esa dependencia (created_by + dependencia_id coherentes).
        $this->crearEscenario($depA->id, 'enlace.a@punta.test', 'A');
        $this->crearEscenario($depB->id, 'enlace.b@punta.test', 'B');

        $this->command?->info('CasosPruebaSeeder: escenario [PRUEBA] creado en dos dependencias.');
    }

    /* ----------------------------------------------------------------------
     | Usuarios
     |----------------------------------------------------------------------*/

    private function crearUsuariosDePrueba(int $depAId, int $depBId): void
    {
        $usuarios = [
            ['name' => '[PRUEBA] Enlace A',   'email' => 'enlace.a@punta.test',   'rol' => 'enlace',   'dependencia_id' => $depAId],
            ['name' => '[PRUEBA] Sujeto A',   'email' => 'sujeto.a@punta.test',   'rol' => 'sujeto',   'dependencia_id' => $depAId],
            ['name' => '[PRUEBA] Jurídico A', 'email' => 'juridico.a@punta.test', 'rol' => 'juridico', 'dependencia_id' => $depAId],
            ['name' => '[PRUEBA] Enlace B',   'email' => 'enlace.b@punta.test',   'rol' => 'enlace',   'dependencia_id' => $depBId],
            ['name' => '[PRUEBA] Sujeto B',   'email' => 'sujeto.b@punta.test',   'rol' => 'sujeto',   'dependencia_id' => $depBId],
            ['name' => '[PRUEBA] Admin',      'email' => 'admin.prueba@punta.test',    'rol' => 'admin',    'dependencia_id' => $depAId],
            ['name' => '[PRUEBA] Revisora',   'email' => 'revisora.prueba@punta.test', 'rol' => 'revisora', 'dependencia_id' => $depAId],
        ];

        foreach ($usuarios as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                array_merge($u, [
                    'cargo'      => 'Usuario de prueba',
                    'password'   => Hash::make(self::PASSWORD),
                    'activo'     => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );

            // Asignación del rol en el ACL (además de la columna `rol`), para que
            // tienePermiso() funcione. syncWithoutDetaching no duplica al re-correr.
            $usuario = User::where('email', $u['email'])->first();
            $rol     = Role::where('codigo', $u['rol'])->first();
            if ($usuario && $rol) {
                $usuario->roles()->syncWithoutDetaching([$rol->id]);
                $usuario->olvidarPermisosCache();
            }
        }
    }

    /* ----------------------------------------------------------------------
     | Escenario de datos por dependencia
     |----------------------------------------------------------------------*/

    private function crearEscenario(int $depId, string $emailAutor, string $etiqueta): void
    {
        $autorId = DB::table('users')->where('email', $emailAutor)->value('id');

        // Una regulación de la dependencia, para citarla en un requisito.
        $regulacionId = $this->crearRegulacion($depId, $autorId, $etiqueta);

        // Trámite editable: lleva un requisito con la regulación citada (C1).
        $tramiteEditableId = $this->crearTramite($depId, $autorId, "Trámite editable {$etiqueta}", 'en_observacion');
        $this->crearRequisitoConCita($tramiteEditableId, $regulacionId);

        // Trámite "en firma": alimenta la cola de firmas (lista + cruce de firmas).
        $this->crearTramite($depId, $autorId, "Trámite en firma {$etiqueta}", 'en_firma');

        // Acción de agenda: para borrar / cambiar estatus (C2, C3) y visibilidad.
        $this->crearAccion($depId, $autorId, $etiqueta);

        // Propuesta regulatoria: para la visibilidad del jurídico por URL.
        $this->crearPropuesta($depId, $autorId, $etiqueta);
    }

    private function crearRegulacion(int $depId, ?int $autorId, string $etiqueta): int
    {
        return DB::table('regulaciones')->insertGetId([
            'nombre'            => self::PREFIJO . "Reglamento citable {$etiqueta}",
            'tipo'              => 'Reglamento',
            'dependencia_id'    => $depId,
            'fecha_publicacion' => now()->subYear()->toDateString(),
            'fecha_vigencia'    => now()->subYear()->addMonth()->toDateString(),
            'estatus'           => $this->estatus($this->valoresEnum('regulaciones', 'estatus'), 'vigente'),
            'resumen'           => 'Regulación de prueba para citarla desde un requisito.',
            'created_by'        => $autorId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function crearTramite(int $depId, ?int $autorId, string $nombre, string $estatusDeseado): int
    {
        $estatus = $this->estatus($this->valoresEnum('tramites', 'estatus'), $estatusDeseado);

        return DB::table('tramites')->insertGetId([
            'nombre_oficial'            => self::PREFIJO . $nombre,
            'dependencia_id'            => $depId,
            'unidad_id'                 => null,
            'servidor_publico'          => 'Titular de prueba',
            'tiene_homoclave'           => true,
            'homoclave'                 => 'PRUEBA-' . $depId . '-' . substr(md5($nombre), 0, 5),
            'objetivo'                  => 'Trámite de prueba para revisar el flujo y la visibilidad.',
            'poblacion_objetivo'        => 'Ciudadanía',
            'dirigido_a'                => 'ambas',
            'frecuencia'                => 'anual',
            'volumen_anual'             => 100,
            'plazo_resolucion_cantidad' => 5,
            'plazo_resolucion_unidad'   => 'habiles',
            'num_areas'                 => 1,
            'areas_participantes'       => 'Ventanilla',
            'visitas_requeridas'        => 1,
            'tiempo_traslado_horas'     => 1,
            'tiempo_espera_horas'       => 1,
            'tiempo_atencion_horas'     => 1,
            'monto_derechos'            => 250.00,
            'nivel_digitalizacion'      => 2,
            'estatus'                   => $estatus,
            'created_by'                => $autorId,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
    }

    private function crearRequisitoConCita(int $tramiteId, int $regulacionId): void
    {
        $requisitoId = DB::table('requisitos')->insertGetId([
            'tramite_id'        => $tramiteId,
            'orden'             => 1,
            'nombre'            => 'Identificación oficial (con regulación citada)',
            'original'          => true,
            'copia'             => true,
            'tipo_presentacion' => 'documento',
            'dias_estimados'    => 1,
            'horas_estimadas'   => 1,
            'minutos_estimados' => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Esta es la cita que el bug C1 perdía al editar. Debe seguir presente
        // tras abrir el trámite en "editar" y guardar.
        DB::table('requisito_regulacion')->insert([
            'requisito_id'      => $requisitoId,
            'regulacion_id'     => $regulacionId,
            'articulo_fraccion' => 'Art. 5, fracción II',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function crearAccion(int $depId, ?int $autorId, string $etiqueta): void
    {
        $estatus = $this->estatus($this->valoresEnum('acciones_agenda', 'estatus'), 'borrador');

        $id = DB::table('acciones_agenda')->insertGetId([
            'tipo'             => 'simplificacion',
            'descripcion'      => self::PREFIJO . "Acción de mejora {$etiqueta}",
            'meta'             => 'Reducir el costo burocrático.',
            'fecha_inicio'     => now()->toDateString(),
            'fecha_compromiso' => now()->addMonths(2)->toDateString(),
            'responsable'      => 'Responsable de prueba',
            'dependencia_id'   => $depId,
            'indicador'        => 'Porcentaje de reducción',
            'estatus'          => $estatus,
            'created_by'       => $autorId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Sembrar los hitos igual que el flujo real (vía el mismo servicio).
        $accion = AccionAgenda::find($id);
        if ($accion) {
            app(HitoAgendaService::class)->sembrarHitos($accion);
        }
    }

    private function crearPropuesta(int $depId, ?int $autorId, string $etiqueta): void
    {
        $estatus = $this->estatus($this->valoresEnum('propuestas_regulatorias', 'estatus'), 'borrador');

        DB::table('propuestas_regulatorias')->insertGetId([
            'folio'              => 'PROP-PRUEBA-' . $depId,
            'nombre'             => self::PREFIJO . "Propuesta regulatoria {$etiqueta}",
            'tipo_regulacion'    => 'Reglamento',
            'dependencia_id'     => $depId,
            'fecha_tentativa'    => now()->addMonth()->toDateString(),
            'justificacion'      => 'Propuesta de prueba para revisar la visibilidad por dependencia.',
            'costo_burocratico'  => 15000.00,
            'poblacion_afectada' => 'Comerciantes',
            'determinacion_air'  => 'pendiente',
            'estatus'            => $estatus,
            'created_by'         => $autorId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /* ----------------------------------------------------------------------
     | Limpieza
     |----------------------------------------------------------------------*/

    /**
     * Borra TODO lo que crea este seeder (datos [PRUEBA] y usuarios @punta.test).
     * Orden seguro: primero los registros que apuntan a trámites, luego los
     * trámites (que cascadean requisitos, citas, fundamento y ficha), luego las
     * regulaciones, y al final los usuarios de prueba.
     */
    public function borrarCasos(): void
    {
        DB::table('acciones_agenda')->where('descripcion', 'like', self::PREFIJO . '%')->delete();
        DB::table('propuestas_regulatorias')->where('nombre', 'like', self::PREFIJO . '%')->delete();
        DB::table('tramites')->where('nombre_oficial', 'like', self::PREFIJO . '%')->delete();
        DB::table('regulaciones')->where('nombre', 'like', self::PREFIJO . '%')->delete();

        DB::table('users')->where('email', 'like', '%@punta.test')->delete();
    }

    /* ----------------------------------------------------------------------
     | Utilidades (mismas que el DemoSeeder, para no truncar si cambia el enum)
     |----------------------------------------------------------------------*/

    private function dependenciaPorCodigo(string $codigo): ?object
    {
        return DB::table('dependencias')->where('codigo', $codigo)->first();
    }

    private function valoresEnum(string $tabla, string $columna): array
    {
        try {
            $col = DB::select("SHOW COLUMNS FROM {$tabla} WHERE Field = ?", [$columna]);
            if (empty($col)) return [];
            preg_match_all("/'([^']+)'/", $col[0]->Type, $m);
            return $m[1] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function estatus(array $validos, string $deseado): string
    {
        if (empty($validos)) return $deseado;
        return in_array($deseado, $validos, true) ? $deseado : ($validos[0] ?? 'borrador');
    }
}
