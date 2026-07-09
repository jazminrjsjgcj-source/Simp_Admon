<?php

namespace Database\Seeders;

use App\Models\Diagrama;
use App\Models\Firma;
use App\Models\Reingenieria;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Crea un usuario digitalizador + datos de prueba completos para
 * recorrer todo el módulo de digitalización en sus 7 fases.
 *
 * Uso:
 *   php artisan db:seed --class=AclSeeder          ← primero (crea el rol)
 *   php artisan db:seed --class=DigitalizadorSeeder ← después (usuario + datos)
 *
 * Credenciales:
 *   Email:    digitalizador@punta.test
 *   Password: password
 *
 * Crea 8 trámites de prueba, cada uno en un estado diferente del flujo
 * de digitalización para poder probar todas las pantallas y acciones.
 *
 * Prefijo [DIG-DEMO] en todos los nombres para borrarlos fácil.
 */
class DigitalizadorSeeder extends Seeder
{
    private const P = '[DIG-DEMO] ';

    public function run(): void
    {
        $this->borrarDemo();

        $depId = DB::table('dependencias')->value('id');
        if (!$depId) {
            $this->command?->error('No hay dependencias. Corre DemoSeeder primero.');
            return;
        }

        $unidadId = DB::table('unidades_administrativas')
            ->where('dependencia_id', $depId)->value('id');

        $periodoId = DB::table('periodos')
            ->where('estatus', 'activo')->where('tipo', 'agenda_syd')->value('id');

        // ── 1. USUARIO DIGITALIZADOR ─────────────────────────────────
        $user = User::updateOrCreate(
            ['email' => 'digitalizador@punta.test'],
            [
                'name'           => 'Digitalizador de Prueba',
                'password'       => Hash::make('password'),
                'cargo'          => 'Digitalizador de Trámites y Servicios',
                'rol'            => 'digitalizador',
                'dependencia_id' => $depId,
                'unidad_id'      => $unidadId,
                'activo'         => true,
            ]
        );
        $userId = $user->id;

        // ── CONECTAR CON ROL ACL ─────────────────────────────────────
        // Sin esto, tienePermiso() no encuentra nada porque busca en
        // user_role → roles → role_permiso → permisos.
        $rolAcl = Role::where('codigo', 'digitalizador')->first();
        if ($rolAcl) {
            // Sync sin detach para no quitar otros roles si los tuviera
            if (!$user->roles()->where('roles.id', $rolAcl->id)->exists()) {
                $user->roles()->attach($rolAcl->id);
            }
            // Limpiar cache de permisos
            $user->olvidarPermisosCache();
            $this->command?->info('  ✓ Usuario vinculado al rol ACL digitalizador (user_role)');
        } else {
            $this->command?->warn('  ⚠ Rol "digitalizador" no existe en tabla roles. ¿Corriste AclSeeder?');
        }

        // Necesitamos un enlace y un sujeto para las firmas
        $enlaceId = DB::table('users')->where('rol', 'enlace')->where('activo', true)->value('id');
        $sujetoId = DB::table('users')->where('rol', 'sujeto')->where('activo', true)->value('id');

        // ── 2. TRÁMITES EN DISTINTOS ESTADOS ─────────────────────────
        $tramites = $this->crearTramites($depId, $unidadId, $userId, $periodoId);

        // ── 3. PASOS DE FLUJO ────────────────────────────────────────
        foreach ($tramites as $t) {
            $this->crearPasos($t['id']);
        }

        // ── 4. REINGENIERÍAS Y FIRMAS ────────────────────────────────
        // T3: reingeniería en proceso (sin firmar)
        $this->crearReingenieria($tramites[2]['id'], 'agenda', 'en_reingenieria', $userId);

        // T4: reingeniería pendiente de firmas (directa)
        $this->crearReingenieria($tramites[3]['id'], 'directa', 'pendiente_firmas', $userId, [
            'motivo_directa'   => 'urgencia_operativa',
            'justificacion'    => 'El área de Tesorería requiere digitalizar este trámite antes del cierre del ejercicio fiscal.',
            'area_solicitante' => 'Tesorería Municipal',
        ]);

        // T5: reingeniería firmada (lista para diagrama)
        $r5 = $this->crearReingenieria($tramites[4]['id'], 'agenda', 'reingenieria_firmada', $userId);
        if ($enlaceId && $sujetoId) {
            $this->crearFirma($r5, $enlaceId, 'aceptacion_enlace');
            $this->crearFirma($r5, $sujetoId, 'aceptacion_sujeto');
        }

        // T6: con diagrama generado
        $r6 = $this->crearReingenieria($tramites[5]['id'], 'agenda', 'reingenieria_firmada', $userId);
        if ($enlaceId && $sujetoId) {
            $this->crearFirma($r6, $enlaceId, 'aceptacion_enlace');
            $this->crearFirma($r6, $sujetoId, 'aceptacion_sujeto');
        }
        $this->crearDiagrama($tramites[5]['id'], $r6);

        // T7: en digitalización
        $r7 = $this->crearReingenieria($tramites[6]['id'], 'directa', 'reingenieria_firmada', $userId, [
            'motivo_directa'   => 'instruccion_institucional',
            'justificacion'    => 'Instrucción del Cabildo para digitalizar trámites de alto impacto.',
        ]);
        if ($enlaceId && $sujetoId) {
            $this->crearFirma($r7, $enlaceId, 'aceptacion_enlace');
            $this->crearFirma($r7, $sujetoId, 'aceptacion_sujeto');
        }
        $this->crearDiagrama($tramites[6]['id'], $r7);

        // T8: digitalizado
        $r8 = $this->crearReingenieria($tramites[7]['id'], 'agenda', 'reingenieria_firmada', $userId);
        if ($enlaceId && $sujetoId) {
            $this->crearFirma($r8, $enlaceId, 'aceptacion_enlace');
            $this->crearFirma($r8, $sujetoId, 'aceptacion_sujeto');
        }
        $this->crearDiagrama($tramites[7]['id'], $r8);

        // ── 5. RESUMEN ───────────────────────────────────────────────
        $this->command?->info('');
        $this->command?->info('  ┌──────────────────────────────────────────────────┐');
        $this->command?->info('  │  Email:    digitalizador@punta.test              │');
        $this->command?->info('  │  Password: password                              │');
        $this->command?->info('  └──────────────────────────────────────────────────┘');
        $this->command?->info('');
        $this->command?->info('  8 trámites de prueba:');
        $this->command?->info('    T1  Sin flujo levantado');
        $this->command?->info('    T2  Flujo aprobado, sin reingeniería');
        $this->command?->info('    T3  Reingeniería en proceso');
        $this->command?->info('    T4  Reingeniería directa, pendiente firmas');
        $this->command?->info('    T5  Reingeniería firmada, sin diagrama');
        $this->command?->info('    T6  Diagrama generado, listo para digitalizar');
        $this->command?->info('    T7  En digitalización');
        $this->command?->info('    T8  Digitalizado');
        $this->command?->info('');
    }

    // ═══════════════════════════════════════════════════════════════
    // Creación de datos
    // ═══════════════════════════════════════════════════════════════

    private function crearTramites(int $depId, ?int $unidadId, int $userId, ?int $periodoId): array
    {
        $datos = [
            ['Solicitud de Permiso de Venta Ambulante',       'sin_flujo',       'no_iniciada',       null],
            ['Licencia de Uso de Suelo Comercial',            'flujo_aprobado',  'no_iniciada',       null],
            ['Constancia de Residencia Municipal',            'flujo_aprobado',  'no_iniciada',       'agenda'],
            ['Pago de Impuesto Predial en Línea',             'flujo_aprobado',  'no_iniciada',       'directa'],
            ['Solicitud de Alineamiento y Número Oficial',    'flujo_aprobado',  'lista_para_digitalizacion', 'agenda'],
            ['Registro de Nacimiento Extemporáneo',           'flujo_aprobado',  'lista_para_digitalizacion', 'agenda'],
            ['Permiso de Construcción de Obra Mayor',         'flujo_aprobado',  'en_digitalizacion', 'directa'],
            ['Constancia de No Adeudo Fiscal',                'flujo_aprobado',  'digitalizado',      'agenda'],
        ];

        $tramites = [];
        foreach ($datos as $i => [$nombre, $flujo, $dig, $origen]) {
            $id = DB::table('tramites')->insertGetId([
                'nombre_oficial'            => self::P . $nombre,
                'naturaleza'                => $i % 3 === 0 ? 'servicio' : 'tramite',
                'dependencia_id'            => $depId,
                'unidad_id'                 => $unidadId,
                'servidor_publico'          => 'Servidor Demo ' . ($i + 1),
                'tiene_homoclave'           => true,
                'homoclave'                 => 'LPZ-DIG-TEST-' . ($i + 1),
                'objetivo'                  => 'Trámite de prueba #' . ($i + 1) . ' para validar el módulo del digitalizador.',
                'poblacion_objetivo'        => 'Ciudadanos del municipio de La Paz',
                'dirigido_a'                => 'ambas',
                'volumen_anual'             => rand(200, 5000),
                'plazo_resolucion_cantidad' => rand(1, 15),
                'plazo_resolucion_unidad'   => 'habiles',
                'num_areas'                 => rand(1, 4),
                'nivel_digitalizacion'      => $i <= 1 ? 0 : min($i, 5),
                'estatus'                   => 'completado',
                'flujo_estado'              => $flujo,
                'digitalizacion_estado'     => $dig,
                'digitalizacion_origen'     => $origen,
                'flujo_aprobado_en'         => $flujo === 'flujo_aprobado' ? now()->subDays(10 - $i) : null,
                'periodo_id'                => $periodoId,
                'created_by'                => $userId,
                'created_at'                => now()->subDays(30 - $i * 3),
                'updated_at'                => now()->subDays($i),
            ]);
            $tramites[] = ['id' => $id, 'nombre' => $nombre];
        }

        return $tramites;
    }

    private function crearPasos(int $tramiteId): void
    {
        $pasos = [
            ['Ciudadano presenta solicitud en ventanilla',   'Ciudadano',        'paso',         '15 min',  true,  'Documentos originales',       'Folio de recepción'],
            ['Ventanilla verifica documentación completa',   'Ventanilla Única', 'paso',         '10 min',  false, 'Solicitud con folio',         'Expediente completo'],
            ['¿Documentación completa?',                     'Ventanilla Única', 'decision',     '5 min',   false, 'Expediente',                  'Dictamen documental'],
            ['Área técnica revisa y dictamina',              'Área técnica',     'paso',         '3 días',  false, 'Expediente completo',         'Dictamen técnico'],
            ['Pago de derechos en caja',                     'Ciudadano',        'pago',         '10 min',  true,  'Orden de pago',               'Comprobante de pago'],
            ['Director firma resolución',                    'Director',         'resolutivo',   '1 día',   false, 'Dictamen + comprobante',      'Resolución firmada'],
            ['Notificación al ciudadano',                    'Sistema',          'notificacion', 'Inmediato', true, 'Resolución firmada',          'Aviso enviado'],
            ['Entrega de documento oficial',                 'Ventanilla Única', 'entrega',      '5 min',   false, 'Resolución + identificación', 'Documento entregado'],
        ];

        foreach ($pasos as $i => [$accion, $actor, $tipo, $duracion, $digital, $entrada, $salida]) {
            DB::table('proceso_atencion')->insert([
                'tramite_id'        => $tramiteId,
                'tipo'              => $i >= 5 ? 'resolucion' : 'atencion',
                'paso'              => $i + 1,
                'subpaso'           => 0,
                'accion'            => $accion,
                'detalle'           => null,
                'area'              => $actor,
                'tipo_paso'         => $tipo,
                'actor'             => $actor,
                'duracion_estimada' => $duracion,
                'es_digital'        => $digital,
                'entrada'           => $entrada,
                'salida'            => $salida,
                'orden'             => $i + 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    private function crearReingenieria(int $tramiteId, string $origen, string $estado, int $userId, array $extra = []): int
    {
        $flujoToBe = [
            ['accion' => 'Ciudadano llena formulario en línea',           'tipo' => 'paso',         'detalle' => 'Portal web con validación en tiempo real'],
            ['accion' => 'Sistema valida documentos automáticamente',     'tipo' => 'paso',         'detalle' => 'OCR + verificación contra padrón'],
            ['accion' => '¿Documentación válida?',                        'tipo' => 'decision',     'detalle' => 'Reglas de negocio automatizadas'],
            ['accion' => 'Pago en línea',                                 'tipo' => 'pago',         'detalle' => 'Pasarela de pagos bancarios'],
            ['accion' => 'Firma electrónica del director',                'tipo' => 'resolutivo',   'detalle' => 'e.firma o firma digital PUNTA'],
            ['accion' => 'Notificación automática por correo y WhatsApp', 'tipo' => 'notificacion', 'detalle' => 'Con PDF adjunto y QR de verificación'],
        ];

        $hash = $estado === 'reingenieria_firmada'
            ? hash('sha256', json_encode($flujoToBe))
            : null;

        return DB::table('reingenierias')->insertGetId(array_merge([
            'tramite_id'        => $tramiteId,
            'origen'            => $origen,
            'version'           => 1,
            'estado'            => $estado,
            'flujo_to_be'       => json_encode($flujoToBe),
            'hash_reingenieria' => $hash,
            'firmado_en'        => $estado === 'reingenieria_firmada' ? now()->subDays(3) : null,
            'created_by'        => $userId,
            'created_at'        => now()->subDays(7),
            'updated_at'        => now()->subDays(2),
        ], $extra));
    }

    private function crearFirma(int $reingenieriaId, int $firmanteId, string $tipo): void
    {
        $firmante = DB::table('users')->find($firmanteId);
        if (!$firmante) return;

        DB::table('firmas')->insert([
            'firmable_type'    => 'App\\Models\\Reingenieria',
            'firmable_id'      => $reingenieriaId,
            'tipo'             => $tipo,
            'firmante_id'      => $firmanteId,
            'firmante_nombre'  => $firmante->name,
            'firmante_cargo'   => $firmante->cargo ?? 'Sin cargo',
            'firmante_email'   => $firmante->email,
            'fecha'            => now()->subDays(2),
            'hash_acuse'       => hash('sha256', $reingenieriaId . $tipo . $firmanteId . now()),
            'cadena_original'  => "reingenieria|{$reingenieriaId}|{$tipo}|{$firmanteId}",
            'estatus'          => 'activa',
            'ip_origen'        => '127.0.0.1',
            'user_agent'       => 'DigitalizadorSeeder',
            'created_at'       => now()->subDays(2),
            'updated_at'       => now()->subDays(2),
        ]);
    }

    private function crearDiagrama(int $tramiteId, int $reingenieriaId): void
    {
        $mermaid = <<<'MERMAID'
flowchart TD
    INICIO([Inicio])
    P0["Ciudadano llena formulario en línea"]
    INICIO --> P0
    P1["Sistema valida documentos automáticamente"]
    P0 --> P1
    P2{{"¿Documentación válida?"}}
    P1 --> P2
    P3[/"Pago en línea"/]
    P2 --> P3
    P4(["Firma electrónica del director"])
    P3 --> P4
    P5["Notificación automática por correo y WhatsApp"]
    P4 --> P5
    P5 --> FIN([Fin])
MERMAID;

        DB::table('diagramas')->insert([
            'tramite_id'        => $tramiteId,
            'reingenieria_id'   => $reingenieriaId,
            'tipo_diagrama'     => 'to_be',
            'contenido_mermaid' => $mermaid,
            'hash_diagrama'     => hash('sha256', $mermaid),
            'estado'            => 'diagrama_generado',
            'created_by'        => DB::table('users')->where('email', 'digitalizador@punta.test')->value('id'),
            'created_at'        => now()->subDays(1),
            'updated_at'        => now()->subDays(1),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Limpieza
    // ═══════════════════════════════════════════════════════════════

    public function borrarDemo(): void
    {
        $ids = DB::table('tramites')
            ->where('nombre_oficial', 'like', self::P . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $reingIds = DB::table('reingenierias')->whereIn('tramite_id', $ids)->pluck('id');

            if ($reingIds->isNotEmpty()) {
                DB::table('descargas_diagrama')->whereIn('reingenieria_id', $reingIds)->delete();
                DB::table('diagramas')->whereIn('reingenieria_id', $reingIds)->delete();
                DB::table('firmas')
                    ->where('firmable_type', 'App\\Models\\Reingenieria')
                    ->whereIn('firmable_id', $reingIds)
                    ->delete();
                DB::table('reingenierias')->whereIn('id', $reingIds)->delete();
            }

            DB::table('proceso_atencion')->whereIn('tramite_id', $ids)->delete();
            DB::table('requisitos')->whereIn('tramite_id', $ids)->delete();
            DB::table('fundamento_juridico')->whereIn('tramite_id', $ids)->delete();
            DB::table('ficha_portal')->whereIn('tramite_id', $ids)->delete();
            DB::table('tramite_derechos')->whereIn('tramite_id', $ids)->delete();
            DB::table('observaciones')
                ->where('observable_type', 'App\\Models\\Tramite')
                ->whereIn('observable_id', $ids)
                ->delete();
            DB::table('tramites')->whereIn('id', $ids)->delete();
        }

        $this->command?->info('  ✓ Datos [DIG-DEMO] borrados');
    }
}
