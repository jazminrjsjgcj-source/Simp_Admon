<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DemoSeeder — datos de demostración completos.
 *
 * Crea registros realistas en los cinco módulos (trámites, agenda SyD,
 * propuestas regulatorias, regulaciones y AIR), llenando todos los campos
 * relevantes y dejando ejemplos en CADA estatus del flujo de cada módulo.
 *
 * Todos los nombres llevan el prefijo [DEMO] para poder borrarlos sin tocar
 * datos reales:  (new \Database\Seeders\DemoSeeder)->borrarDemo();
 *
 * Los enums de estatus se leen de la BD en vez de codificarse, para que el
 * seeder no truene si el esquema cambia.
 */
class DemoSeeder extends Seeder
{
    private const PREFIJO = '[DEMO] ';

    /**
     * Lee los valores válidos de una columna enum directamente de la BD.
     * Si la columna es VARCHAR (no enum) o falla, devuelve [].
     */
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

    /**
     * Elige un estatus válido: si el deseado existe lo usa; si no (porque la
     * columna es VARCHAR y no enum, como tramites tras el fix), lo usa igual.
     */
    private function estatus(array $validos, string $deseado): string
    {
        if (empty($validos)) return $deseado; // VARCHAR: cualquier valor sirve
        return in_array($deseado, $validos, true) ? $deseado : ($validos[0] ?? 'borrador');
    }

    public function run(): void
    {
        $usuario = DB::table('users')->first();

        // Dependencias que tienen al menos un usuario asignado. Creamos datos
        // demo en cada una para que cualquier rol (que filtra por su
        // dependencia) tenga registros que probar. La revisora/admin, que ven
        // todo, los verán todos de paso.
        $depIds = DB::table('users')
            ->whereNotNull('dependencia_id')
            ->distinct()
            ->pluck('dependencia_id');

        $dependencias = DB::table('dependencias')->whereIn('id', $depIds)->get();

        // Respaldo: si por alguna razón no hay usuarios con dependencia, usar la primera.
        if ($dependencias->isEmpty()) {
            $dependencias = DB::table('dependencias')->limit(1)->get();
        }

        if (!$usuario || $dependencias->isEmpty()) {
            $this->command?->error('Faltan usuarios o dependencias. Corre primero los seeders base (AclSeeder, etc.).');
            return;
        }

        // Evita duplicados si se corre varias veces (borra TODO el demo previo).
        $this->borrarDemo();

        foreach ($dependencias as $dependencia) {
            $unidad = DB::table('unidades_administrativas')
                ->where('dependencia_id', $dependencia->id)
                ->first();

            $idsTramites     = $this->crearTramites($usuario, $dependencia, $unidad);
            $idsRegulaciones = $this->crearRegulaciones($usuario, $dependencia);
            $this->crearHijosTramite($idsTramites, $idsRegulaciones);
            $idsAcciones     = $this->crearAcciones($usuario, $dependencia, $idsTramites);
            $idsPropuestas   = $this->crearPropuestas($usuario, $dependencia);
            $this->crearAir($usuario, $idsPropuestas);
            $this->crearObservaciones($usuario, $dependencia, $idsTramites, $idsAcciones);
            $this->crearEventosCalendario($dependencia, $idsAcciones);
        }

        $this->command?->info('Demo completo creado: trámites, agenda, propuestas, regulaciones y AIR.');
        $this->command?->info('Para borrarlo: (new \\Database\\Seeders\\DemoSeeder)->borrarDemo();');
    }

    /**
     * Borra todos los registros marcados como demo (prefijo [DEMO]).
     * Respeta el orden de llaves foráneas (hijos antes que padres).
     */
    public function borrarDemo(): void
    {
        // Eventos de calendario demo (apuntan a acciones de agenda).
        DB::table('calendario_eventos')->where('titulo', 'like', self::PREFIJO . '%')->delete();

        // Observaciones demo primero (apuntan a trámites/agenda por relación polimórfica).
        DB::table('observaciones')->where('texto', 'like', self::PREFIJO . '%')->delete();

        $idsTramitesDemo = DB::table('tramites')
            ->where('nombre_oficial', 'like', self::PREFIJO . '%')->pluck('id');

        if ($idsTramitesDemo->isNotEmpty()) {
            DB::table('acciones_agenda')->whereIn('tramite_id', $idsTramitesDemo)->delete();
        }

        $idsPropuestasDemo = DB::table('propuestas_regulatorias')
            ->where('nombre', 'like', self::PREFIJO . '%')->pluck('id');
        if ($idsPropuestasDemo->isNotEmpty()) {
            $idsAir = DB::table('analisis_impacto_regulatorio')
                ->whereIn('propuesta_id', $idsPropuestasDemo)->pluck('id');
            if ($idsAir->isNotEmpty()) {
                DB::table('dictamenes_air')->whereIn('air_id', $idsAir)->delete();
            }
            DB::table('analisis_impacto_regulatorio')->whereIn('propuesta_id', $idsPropuestasDemo)->delete();
            DB::table('exenciones_air')->whereIn('propuesta_id', $idsPropuestasDemo)->delete();
        }

        DB::table('acciones_agenda')->where('descripcion', 'like', self::PREFIJO . '%')->delete();
        DB::table('tramites')->where('nombre_oficial', 'like', self::PREFIJO . '%')->delete();
        DB::table('propuestas_regulatorias')->where('nombre', 'like', self::PREFIJO . '%')->delete();
        DB::table('regulaciones')->where('nombre', 'like', self::PREFIJO . '%')->delete();
    }

    /**
     * Trámites: uno por cada estatus del flujo, con todos los campos llenos.
     */
    private function crearTramites($usuario, $dependencia, $unidad): array
    {
        $validos = $this->valoresEnum('tramites', 'estatus');

        $ejemplos = [
            ['Licencia de funcionamiento comercial', 'borrador'],
            ['Permiso de construcción menor',         'en_observacion'],
            ['Constancia de uso de suelo',            'en_correccion'],
            ['Registro de anuncio publicitario',      'en_firma'],
            ['Permiso para evento público',           'completado'],
        ];

        $ids = [];
        foreach ($ejemplos as $i => $datos) {
            [$nombre, $estatusDeseado] = $datos;
            $ids[] = DB::table('tramites')->insertGetId([
                'nombre_oficial'   => self::PREFIJO . $nombre,
                'dependencia_id'   => $dependencia->id,
                'unidad_id'        => $unidad->id ?? null,
                'servidor_publico' => 'Lic. Titular de Prueba',
                'tiene_homoclave'  => true,
                'homoclave'        => 'DEMO-' . $dependencia->id . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'objetivo'         => 'Trámite de demostración para validar el flujo completo del sistema.',
                'poblacion_objetivo' => 'Ciudadanía en general',
                'dirigido_a'       => 'ambas',
                'frecuencia'       => 'anual',
                'volumen_anual'    => 120 + $i * 30,
                'plazo_resolucion_cantidad' => 5 + $i,
                'plazo_resolucion_unidad'   => 'habiles',
                'num_areas'        => 2,
                'areas_participantes' => 'Ventanilla, Dirección',
                'visitas_requeridas'  => 1,
                'tiempo_traslado_horas' => 1,
                'tiempo_espera_horas'   => 1,
                'tiempo_atencion_horas' => 1,
                'monto_derechos'   => 250.00 + $i * 50,
                'nivel_digitalizacion' => 2,
                'estatus'          => $this->estatus($validos, $estatusDeseado),
                'created_by'       => $usuario->id,
                'created_at'       => now()->subDays(10 - $i),
                'updated_at'       => now()->subDays(10 - $i),
            ]);
        }
        return $ids;
    }

    /**
     * Regulaciones: una por cada estatus (vigente, en_revision, derogada).
     */
    private function crearRegulaciones($usuario, $dependencia): array
    {
        $validos = $this->valoresEnum('regulaciones', 'estatus');

        $ejemplos = [
            ['Reglamento de Comercio Municipal',        'vigente'],
            ['Reglamento de Construcción',              'en_revision'],
            ['Bando de Policía y Buen Gobierno (2019)', 'derogada'],
        ];

        $ids = [];
        foreach ($ejemplos as $i => $datos) {
            [$nombre, $estatusDeseado] = $datos;
            $ids[] = DB::table('regulaciones')->insertGetId([
                'nombre'            => self::PREFIJO . $nombre,
                'tipo'              => 'Reglamento',
                'dependencia_id'    => $dependencia->id,
                'fecha_publicacion' => now()->subYears(2)->toDateString(),
                'fecha_vigencia'    => now()->subYears(2)->addMonth()->toDateString(),
                'estatus'           => $this->estatus($validos, $estatusDeseado),
                'resumen'           => 'Regulación de demostración con su resumen de contenido.',
                'created_by'        => $usuario->id,
                'created_at'        => now()->subDays(30 - $i * 3),
                'updated_at'        => now()->subDays(30 - $i * 3),
            ]);
        }
        return $ids;
    }

    /**
     * Hijos del trámite: requisitos, fundamento jurídico y ficha portal.
     */
    private function crearHijosTramite(array $idsTramites, array $idsRegulaciones): void
    {
        foreach ($idsTramites as $i => $tramiteId) {
            foreach (['Identificación oficial', 'Comprobante de domicilio'] as $j => $req) {
                DB::table('requisitos')->insert([
                    'tramite_id'        => $tramiteId,
                    'orden'             => $j + 1,
                    'nombre'            => $req,
                    'original'          => true,
                    'copia'             => true,
                    'tipo_presentacion' => 'documento',
                    'dias_estimados'    => 1,
                    'horas_estimadas'   => 2,
                    'minutos_estimados' => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::table('fundamento_juridico')->insert([
                'tramite_id'        => $tramiteId,
                'regulacion_id'     => $idsRegulaciones[0] ?? null,
                'normativa_nombre'  => 'Reglamento de Comercio Municipal',
                'tipo_normativa'    => 'Reglamento',
                'articulo_fraccion' => 'Art. 12, fracción III',
                'resumen'           => 'Fundamento que da origen al trámite.',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            DB::table('ficha_portal')->insert([
                'tramite_id'       => $tramiteId,
                'nombre_ciudadano' => 'Trámite de demostración ' . ($i + 1),
                'tipo'             => 'Trámite',
                'descripcion'      => 'Descripción en lenguaje ciudadano del trámite de demostración.',
                'modalidad'        => 'Presencial',
                'canal_principal'  => 'Ventanilla única',
                'requiere_cita'    => false,
                'costo_publico'    => '$250.00',
                'forma_pago'       => 'Efectivo o tarjeta',
                'resultado'        => 'Licencia o permiso',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * Acciones de agenda SyD: una por cada estatus del enum real.
     */
    private function crearAcciones($usuario, $dependencia, array $idsTramites): array
    {
        $validos = $this->valoresEnum('acciones_agenda', 'estatus');
        $estatusList = !empty($validos) ? $validos : ['borrador'];

        $descripciones = [
            'Digitalizar la solicitud de licencia comercial',
            'Eliminar requisito de copia certificada',
            'Unificar ventanillas de atención',
            'Reducir plazo de resolución a 3 días',
            'Publicar formato en línea',
            'Integrar pago en línea',
        ];

        $idsPorEstatus = [];
        foreach ($estatusList as $i => $estatus) {
            $id = DB::table('acciones_agenda')->insertGetId([
                'tramite_id'       => $idsTramites[$i % max(count($idsTramites), 1)] ?? null,
                'tipo'             => $i % 2 === 0 ? 'simplificacion' : 'digitalizacion',
                'descripcion'      => self::PREFIJO . ($descripciones[$i] ?? "Acción de mejora {$i}"),
                'meta'             => 'Reducir el costo burocrático del trámite.',
                'fecha_inicio'     => now()->toDateString(),
                'fecha_compromiso' => now()->addMonths(2)->toDateString(),
                'responsable'      => 'Lic. Responsable de Prueba',
                'dependencia_id'   => $dependencia->id,
                'indicador'        => 'Porcentaje de reducción de tiempo',
                'estatus'          => $estatus,
                'created_by'       => $usuario->id,
                'created_at'       => now()->subDays(15 - $i * 2),
                'updated_at'       => now()->subDays(15 - $i * 2),
            ]);
            $idsPorEstatus[$estatus] = $id;
        }
        return $idsPorEstatus;
    }

    /**
     * Propuestas regulatorias: una por cada estatus del flujo.
     */
    private function crearPropuestas($usuario, $dependencia): array
    {
        $validos = $this->valoresEnum('propuestas_regulatorias', 'estatus');

        $ejemplos = [
            ['Reforma al Reglamento de Comercio',     'borrador',    'pendiente'],
            ['Nueva norma de imagen urbana',          'consulta',    'requiere_air'],
            ['Actualización de tarifas de servicios', 'determinada', 'requiere_air'],
            ['Reglamento de mercados municipales',    'dictaminada', 'requiere_air'],
            ['Lineamientos de exención por urgencia', 'publicada',   'exento'],
        ];

        $ids = [];
        foreach ($ejemplos as $i => $datos) {
            [$nombre, $estatusDeseado, $determinacion] = $datos;
            $ids[] = DB::table('propuestas_regulatorias')->insertGetId([
                'folio'             => 'PROP-DEMO-' . $dependencia->id . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'nombre'            => self::PREFIJO . $nombre,
                'tipo_regulacion'   => 'Reglamento',
                'dependencia_id'    => $dependencia->id,
                'fecha_tentativa'   => now()->addMonths(1)->toDateString(),
                'justificacion'     => 'Justificación de la propuesta regulatoria de demostración.',
                'costo_burocratico' => 15000.00 + $i * 5000,
                'poblacion_afectada' => 'Comerciantes del municipio',
                'determinacion_air' => $determinacion,
                'estatus'           => $this->estatus($validos, $estatusDeseado),
                'created_by'        => $usuario->id,
                'created_at'        => now()->subDays(20 - $i * 2),
                'updated_at'        => now()->subDays(20 - $i * 2),
            ]);
        }
        return $ids;
    }

    /**
     * AIR: uno por cada estatus, ligado a las propuestas que requieren AIR.
     */
    private function crearAir($usuario, array $idsPropuestas): void
    {
        if (empty($idsPropuestas)) return;

        $validos = $this->valoresEnum('analisis_impacto_regulatorio', 'estatus');
        $estatusList = !empty($validos) ? $validos : ['borrador'];

        foreach ($estatusList as $i => $estatus) {
            $propuestaId = $idsPropuestas[($i + 1) % count($idsPropuestas)];

            DB::table('analisis_impacto_regulatorio')->insert([
                'propuesta_id'          => $propuestaId,
                'folio'                 => 'AIR-DEMO-' . $propuestaId . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'problematica'          => 'Problemática que origina la necesidad de intervención.',
                'objetivos'             => 'Objetivos que persigue la propuesta regulatoria.',
                'alternativas'          => 'Alternativas regulatorias y no regulatorias consideradas.',
                'costos_implementacion' => 'Costos estimados de implementación de la propuesta.',
                'beneficios'            => 'Beneficios económicos y sociales esperados.',
                'impacto_estimado'      => 'Impacto estimado sobre la población objetivo.',
                'impacta_tramites'      => true,
                'ambito_aplicacion'     => 'Municipal',
                'consulta_publica'      => 'Resumen de la consulta pública realizada.',
                'estatus'               => $estatus,
                'created_by'            => $usuario->id,
                'created_at'            => now()->subDays(12 - $i),
                'updated_at'            => now()->subDays(12 - $i),
            ]);
        }
    }

    /**
     * Crea observaciones de demostración para que se vea la diferencia entre
     * "Por revisar" y "Por aprobar":
     *  - Al trámite y la acción en 'en_observacion' les pone una observación
     *    VIVA (estatus pendiente) → caen en "Por revisar".
     *  - Los registros en 'en_correccion' se quedan SIN observaciones → caen
     *    en "Por aprobar".
     */
    private function crearObservaciones($usuario, $dependencia, array $idsTramites, array $idsAcciones): void
    {
        // Autores de las observaciones. La revisora observa el trámite;
        // jurídico observa la agenda (para que jurídico tenga observaciones
        // propias en su tarjeta "Mis observaciones"). Respaldos al usuario base.
        $revisora = DB::table('users')->where('rol', 'revisora')->first();
        $juridico = DB::table('users')->where('rol', 'juridico')->first();
        $autorRevisora = $revisora->id ?? $usuario->id;
        $autorJuridico = $juridico->id ?? ($revisora->id ?? $usuario->id);

        // Trámite en observación (observado por la revisora).
        $tramiteObservado = $idsTramites[1] ?? null;
        if ($tramiteObservado) {
            DB::table('observaciones')->insert([
                'observable_type' => \App\Models\Tramite::class,
                'observable_id'   => $tramiteObservado,
                'seccion'         => 'Datos generales',
                'campo'           => 'nombre_oficial',
                'texto'           => self::PREFIJO . 'Revisar el nombre oficial del trámite; no coincide con el catálogo.',
                'realizada_por'   => $autorRevisora,
                'atendida'        => false,
                'estatus'         => 'pendiente',
                'created_at'      => now()->subDays(3),
                'updated_at'      => now()->subDays(3),
            ]);
        }

        // Acción de agenda en observación (observada por jurídico).
        $accionObservada = $idsAcciones['en_observacion'] ?? null;
        if ($accionObservada) {
            DB::table('observaciones')->insert([
                'observable_type' => \App\Models\AccionAgenda::class,
                'observable_id'   => $accionObservada,
                'seccion'         => 'Fundamento jurídico',
                'campo'           => 'descripcion',
                'texto'           => self::PREFIJO . 'Revisar el sustento normativo de la acción; falta citar el artículo aplicable.',
                'realizada_por'   => $autorJuridico,
                'atendida'        => false,
                'estatus'         => 'pendiente',
                'created_at'      => now()->subDays(2),
                'updated_at'      => now()->subDays(2),
            ]);
        }
    }

    /**
     * Crea eventos de calendario demo para que el calendario no se vea vacío.
     * Los reparte en el mes ACTUAL (para que aparezcan al abrir el calendario),
     * con distintos tipos y estados. Los liga a las acciones de agenda demo.
     */
    private function crearEventosCalendario($dependencia, array $idsAcciones): void
    {
        $accionIds = array_values($idsAcciones);
        if (empty($accionIds)) {
            return;
        }

        // Eventos repartidos en el mes actual: [día, tipo, título, estatus, avance].
        $plantillas = [
            [5,  'simplificacion', 'Entrega de diagnóstico de simplificación', 'cumplido',  100],
            [12, 'digitalizacion', 'Configurar formulario en línea',           'pendiente', 40],
            [18, 'regulatoria',    'Publicar propuesta para consulta',         'pendiente', 20],
            [22, 'simplificacion', 'Reducir requisitos del trámite',           'pendiente', 60],
            [3,  'digitalizacion', 'Integrar pago en línea (vencido)',         'vencido',   30],
        ];

        foreach ($plantillas as $i => $p) {
            [$dia, $tipo, $titulo, $estatus, $avance] = $p;

            // Fecha en el mes actual; si el día no existe en el mes, lo ajusta.
            $fecha = now()->startOfMonth()->addDays($dia - 1)->toDateString();
            $accionId = $accionIds[$i % count($accionIds)];

            DB::table('calendario_eventos')->insert([
                'tipo'           => $tipo,
                'titulo'         => self::PREFIJO . $titulo,
                'accion'         => 'Acción de mejora regulatoria',
                'meta'           => 'Reducir el costo burocrático del trámite.',
                'fecha'          => $fecha,
                'estatus'        => $estatus,
                'avance'         => $avance,
                'responsable'    => 'Lic. Responsable de Prueba',
                'dependencia_id' => $dependencia->id,
                'evidencia'      => $estatus === 'cumplido',
                'eventable_type' => \App\Models\AccionAgenda::class,
                'eventable_id'   => $accionId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
