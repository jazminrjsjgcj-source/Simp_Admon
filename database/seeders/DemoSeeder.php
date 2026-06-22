<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DemoSeeder — datos de demostración completos (versión actualizada Jun 2026).
 *
 * Crea registros realistas del Municipio de La Paz, B.C.S. con TODOS los campos
 * del sistema llenados, incluyendo:
 *   - Campos del Paquete 1 (A-G): catálogos ATDT, tiempos, relaciones, costos
 *   - Campos del Paquete 3: acciones SIMP/DIG con explicación, niveles 0-5
 *   - Grupo 3: hitos con estado_aprobacion (evidencia y visto bueno)
 *   - #12: periodo_id en trámites y acciones
 *
 * Todos los registros llevan el prefijo [DEMO] para poder borrarlos en limpio:
 *   php artisan db:seed --class=DemoSeeder
 *   (new \Database\Seeders\DemoSeeder)->borrarDemo();
 */
class DemoSeeder extends Seeder
{
    private const PREFIJO = '[DEMO] ';

    // =========================================================
    // Catálogos oficiales ATDT (extraídos del instrumento oficial)
    // =========================================================

    private const ACCIONES_SIMP = [
        'Reducción de requisitos'
            => 'Se eliminan los comprobantes de domicilio duplicados, dejando solo uno vigente con antigüedad no mayor a 3 meses.',
        'Reducción de plazos de resolución o respuesta'
            => 'El plazo de resolución pasa de 10 a 5 días hábiles mediante digitalización del proceso de dictamen interno.',
        'Eliminación de requisitos'
            => 'Se suprime la carta de vecindad que no tiene sustento jurídico vigente en el Reglamento de Comercio.',
        'Fusión de trámites y/o modalidades'
            => 'La licencia de giro comercial y el aviso de apertura se unifican en un solo formulario de presentación.',
    ];

    private const ACCIONES_DIG = [
        'Reducción de requisitos'
            => 'El ciudadano ya no entrega copia física del RFC; el sistema lo consulta directamente al SAT mediante interoperabilidad.',
        'Eliminación de copias e impresiones'
            => 'La resolución se emite en formato digital firmado con e.firma, eliminando la impresión y entrega presencial.',
        'Mejorar experiencia de usuario'
            => 'Se rediseña el formulario en línea con asistente paso a paso, validación en tiempo real y guardado automático.',
        'Reducción de pasos en su proceso digital'
            => 'El proceso se reduce de 7 a 3 pasos eliminando validaciones manuales intermedias mediante reglas de negocio automatizadas.',
    ];

    private const GRUPOS_ATENCION = [
        'No Aplica'                 => false,
        'Mujeres'                   => true,
        'Personas mayores'          => true,
        'Personas con discapacidad' => false,
    ];

    // =========================================================
    // Punto de entrada
    // =========================================================

    public function run(): void
    {
        $usuario = DB::table('users')->first();

        $depIds = DB::table('users')
            ->whereNotNull('dependencia_id')
            ->distinct()->pluck('dependencia_id');

        $dependencias = DB::table('dependencias')->whereIn('id', $depIds)->get();
        if ($dependencias->isEmpty()) {
            $dependencias = DB::table('dependencias')->limit(1)->get();
        }

        if (!$usuario || $dependencias->isEmpty()) {
            $this->command?->error('Faltan usuarios o dependencias. Corre AclSeeder primero.');
            return;
        }

        $this->borrarDemo();

        $periodoId = DB::table('periodos')
            ->where('estatus', 'activo')
            ->where('tipo', 'agenda_syd')
            ->value('id');

        foreach ($dependencias as $dependencia) {
            $unidad = DB::table('unidades_administrativas')
                ->where('dependencia_id', $dependencia->id)->first();

            $idsTramites     = $this->crearTramites($usuario, $dependencia, $unidad, $periodoId);
            $idsRegulaciones = $this->crearRegulaciones($usuario, $dependencia);
            $this->crearHijosTramite($idsTramites, $idsRegulaciones);
            $idsAcciones     = $this->crearAcciones($usuario, $dependencia, $unidad, $idsTramites, $periodoId);
            $idsPropuestas   = $this->crearPropuestas($usuario, $dependencia);
            $this->crearAir($usuario, $idsPropuestas);
            $this->crearObservaciones($usuario, $idsTramites);
            $this->crearEventosCalendario($dependencia, $idsAcciones);
        }

        $this->command?->info('Demo creado con todos los campos llenos.');
        $this->command?->info('Para borrar: php artisan db:seed --class=DemoSeeder (borra y recrea) o (new \\Database\\Seeders\\DemoSeeder)->borrarDemo()');
    }

    // =========================================================
    // Limpieza
    // =========================================================

    public function borrarDemo(): void
    {
        DB::table('calendario_eventos')->where('titulo', 'like', self::PREFIJO . '%')->delete();
        DB::table('observaciones')->where('texto', 'like', self::PREFIJO . '%')->delete();

        $idsProp = DB::table('propuestas_regulatorias')
            ->where('nombre', 'like', self::PREFIJO . '%')->pluck('id');
        if ($idsProp->isNotEmpty()) {
            DB::table('propuesta_tramite_impacto')->whereIn('propuesta_id', $idsProp)->delete();
            $idsAir = DB::table('analisis_impacto_regulatorio')
                ->whereIn('propuesta_id', $idsProp)->pluck('id');
            if ($idsAir->isNotEmpty()) {
                DB::table('dictamenes_air')->whereIn('air_id', $idsAir)->delete();
                DB::table('analisis_impacto_regulatorio')->whereIn('id', $idsAir)->delete();
            }
            DB::table('exenciones_air')->whereIn('propuesta_id', $idsProp)->delete();
            DB::table('propuestas_regulatorias')->whereIn('id', $idsProp)->delete();
        }

        $idsTram = DB::table('tramites')
            ->where('nombre_oficial', 'like', self::PREFIJO . '%')->pluck('id');
        if ($idsTram->isNotEmpty()) {
            $idsAcc = DB::table('acciones_agenda')
                ->whereIn('tramite_id', $idsTram)->pluck('id');
            if ($idsAcc->isNotEmpty()) {
                DB::table('hitos_agenda')->whereIn('accion_agenda_id', $idsAcc)->delete();
                DB::table('acciones_agenda')->whereIn('id', $idsAcc)->delete();
            }
            DB::table('requisitos')->whereIn('tramite_id', $idsTram)->delete();
            DB::table('tramite_derechos')->whereIn('tramite_id', $idsTram)->delete();
            DB::table('proceso_atencion')->whereIn('tramite_id', $idsTram)->delete();
            DB::table('fundamento_juridico')->whereIn('tramite_id', $idsTram)->delete();
            DB::table('ficha_portal')->whereIn('tramite_id', $idsTram)->delete();
            DB::table('tramites')->whereIn('id', $idsTram)->delete();
        }

        DB::table('acciones_agenda')
            ->where('descripcion', 'like', self::PREFIJO . '%')->delete();
        DB::table('regulaciones')
            ->where('nombre', 'like', self::PREFIJO . '%')->delete();
    }

    // =========================================================
    // Trámites — todos los campos del instrumento ATDT
    // =========================================================

    private function crearTramites($usuario, $dependencia, $unidad, ?int $periodoId): array
    {
        $sectorId = DB::table('sectores_scian')->value('id');

        $datos = [
            [
                'nombre_oficial'             => self::PREFIJO . 'Licencia de Funcionamiento para Establecimientos Comerciales',
                'dependencia_id'             => $dependencia->id,
                'unidad_id'                  => $unidad->id ?? null,
                'servidor_publico'           => 'Lic. María Elena Rodríguez Castro',
                'tiene_homoclave'            => true,
                'homoclave'                  => 'LPZ-DGGD-CA-' . $dependencia->id . '1',
                'objetivo'                   => 'Obtener la autorización municipal para operar un establecimiento comercial fijo con venta de productos al público en general dentro del Municipio de La Paz, B.C.S.',
                'poblacion_objetivo'         => 'Personas físicas y morales con actividad comercial en el municipio',
                'dirigido_a'                 => 'ambas',
                'frecuencia'                 => 'Anual',
                'sector_id'                  => $sectorId,
                'volumen_anual'              => 1850,
                'plazo_resolucion_cantidad'  => 10,
                'plazo_resolucion_unidad'    => 'habiles',
                'num_areas'                  => 3,
                'areas_participantes'        => 'Ventanilla Única, Tesorería Municipal, Protección Civil',
                'visitas_requeridas'         => 2,
                'tiempo_traslado_horas'      => 1,
                'tiempo_traslado_min'        => 30,
                'tiempo_espera_horas'        => 0,
                'tiempo_espera_min'          => 45,
                'tiempo_atencion_horas'      => 0,
                'tiempo_atencion_min'        => 30,
                'monto_derechos'             => 1250.00,
                'monto_derechos_variable'    => false,
                'copias_cantidad'            => 4,
                'copias_precio'              => 1.50,
                'salario_hora_w'             => 68.20,
                'nivel_digitalizacion'       => 2,
                'tipo_relacion'              => 'Dependencia funcional',
                'relacionados_detalle'       => 'Permiso de uso de suelo, Visto bueno de Protección Civil, Constancia de zonificación',
                'tiene_relacionados'         => true,
                'acciones_simplificacion'    => json_encode(self::ACCIONES_SIMP),
                'grupos_atencion'            => json_encode(self::GRUPOS_ATENCION),
                'cbd_directo'                => 1256.00,
                'cbi_indirecto'              => 7162.50,
                'cbu_unitario'               => 8418.50,
                'cbt_total'                  => 15574225.00,
                'periodo_id'                 => $periodoId,
                'estatus'                    => 'en_observacion',
                'created_by'                 => $usuario->id,
                'created_at'                 => now()->subDays(15),
                'updated_at'                 => now()->subDays(2),
            ],
            [
                'nombre_oficial'             => self::PREFIJO . 'Permiso de Construcción de Obra Menor',
                'dependencia_id'             => $dependencia->id,
                'unidad_id'                  => $unidad->id ?? null,
                'servidor_publico'           => 'Arq. Carlos Méndez Ruiz',
                'tiene_homoclave'            => true,
                'homoclave'                  => 'LPZ-DGGD-CA-' . $dependencia->id . '2',
                'objetivo'                   => 'Autorizar la ejecución de obras de construcción menores (ampliaciones y remodelaciones hasta 60 m²) en predios urbanos del municipio.',
                'poblacion_objetivo'         => 'Propietarios de inmuebles con proyectos de obra menor',
                'dirigido_a'                 => 'ambas',
                'frecuencia'                 => 'Eventual',
                'sector_id'                  => $sectorId,
                'volumen_anual'              => 620,
                'plazo_resolucion_cantidad'  => 15,
                'plazo_resolucion_unidad'    => 'habiles',
                'num_areas'                  => 2,
                'areas_participantes'        => 'Dirección de Obras Públicas, Catastro Municipal',
                'visitas_requeridas'         => 1,
                'tiempo_traslado_horas'      => 0,
                'tiempo_traslado_min'        => 45,
                'tiempo_espera_horas'        => 1,
                'tiempo_espera_min'          => 0,
                'tiempo_atencion_horas'      => 0,
                'tiempo_atencion_min'        => 20,
                'monto_derechos'             => 850.00,
                'monto_derechos_variable'    => false,
                'copias_cantidad'            => 3,
                'copias_precio'              => 1.50,
                'salario_hora_w'             => 68.20,
                'nivel_digitalizacion'       => 1,
                'tipo_relacion'              => null,
                'relacionados_detalle'       => null,
                'tiene_relacionados'         => false,
                'acciones_simplificacion'    => json_encode([
                    'Reducción de requisitos' => 'Se elimina la copia certificada del plano arquitectónico; basta con copia simple verificada en ventanilla.',
                ]),
                'grupos_atencion'            => json_encode(['No Aplica' => false]),
                'cbd_directo'                => 854.50,
                'cbi_indirecto'              => 3410.00,
                'cbu_unitario'               => 4264.50,
                'cbt_total'                  => 2643990.00,
                'periodo_id'                 => $periodoId,
                'estatus'                    => 'borrador',
                'created_by'                 => $usuario->id,
                'created_at'                 => now()->subDays(8),
                'updated_at'                 => now()->subDays(8),
            ],
            [
                'nombre_oficial'             => self::PREFIJO . 'Constancia de No Adeudo Municipal',
                'dependencia_id'             => $dependencia->id,
                'unidad_id'                  => $unidad->id ?? null,
                'servidor_publico'           => 'C.P. Ana Torres Sánchez',
                'tiene_homoclave'            => true,
                'homoclave'                  => 'LPZ-DGGD-CA-' . $dependencia->id . '3',
                'objetivo'                   => 'Certificar que una persona física o moral no tiene adeudos pendientes con la Tesorería Municipal de La Paz, documento requerido para la realización de diversos trámites municipales.',
                'poblacion_objetivo'         => 'Contribuyentes del municipio con trámites pendientes',
                'dirigido_a'                 => 'ambas',
                'frecuencia'                 => 'Mensual',
                'sector_id'                  => $sectorId,
                'volumen_anual'              => 4200,
                'plazo_resolucion_cantidad'  => 3,
                'plazo_resolucion_unidad'    => 'habiles',
                'num_areas'                  => 1,
                'areas_participantes'        => 'Tesorería Municipal',
                'visitas_requeridas'         => 1,
                'tiempo_traslado_horas'      => 0,
                'tiempo_traslado_min'        => 30,
                'tiempo_espera_horas'        => 0,
                'tiempo_espera_min'          => 20,
                'tiempo_atencion_horas'      => 0,
                'tiempo_atencion_min'        => 10,
                'monto_derechos'             => 120.00,
                'monto_derechos_variable'    => false,
                'copias_cantidad'            => 1,
                'copias_precio'              => 1.50,
                'salario_hora_w'             => 68.20,
                'nivel_digitalizacion'       => 3,
                'tipo_relacion'              => 'Secuencia',
                'relacionados_detalle'       => 'Licencia de funcionamiento comercial, Permiso de construcción',
                'tiene_relacionados'         => true,
                'acciones_simplificacion'    => json_encode([
                    'Reducción de plazos de resolución o respuesta'         => 'Con consulta automática al padrón fiscal, la constancia puede emitirse el mismo día.',
                    'Conversión de trámites en avisos o manifestaciones'    => 'En casos sin historial fiscal se convierte en aviso automático sin revisión manual.',
                ]),
                'grupos_atencion'            => json_encode([
                    'No Aplica'        => false,
                    'Personas mayores' => true,
                ]),
                'cbd_directo'                => 121.50,
                'cbi_indirecto'              => 2047.50,
                'cbu_unitario'               => 2169.00,
                'cbt_total'                  => 9109800.00,
                'periodo_id'                 => $periodoId,
                'estatus'                    => 'completado',
                'created_by'                 => $usuario->id,
                'created_at'                 => now()->subDays(45),
                'updated_at'                 => now()->subDays(1),
            ],
        ];

        $ids = [];
        foreach ($datos as $t) {
            $ids[] = DB::table('tramites')->insertGetId($t);
        }
        return $ids;
    }

    // =========================================================
    // Hijos del trámite
    // =========================================================

    private function crearHijosTramite(array $idsTramites, array $idsRegulaciones): void
    {
        $requisitos = [
            ['Identificación oficial vigente', 'Comprobante de domicilio (no mayor a 3 meses)', 'CURP impresa'],
            ['Planos arquitectónicos firmados por DRO', 'Título de propiedad o contrato notariado', 'Memoria descriptiva de la obra'],
            ['Identificación oficial', 'RFC con homoclave vigente'],
        ];

        $montos = [[1250.00, 0, 0], [850.00, 0, 0], [120.00, 0]];

        foreach ($idsTramites as $i => $tramiteId) {
            // Requisitos
            foreach (($requisitos[$i] ?? ['Identificación oficial']) as $j => $nombre) {
                DB::table('requisitos')->insert([
                    'tramite_id'        => $tramiteId,
                    'orden'             => $j + 1,
                    'nombre'            => $nombre,
                    'original'          => true,
                    'copia'             => $j > 0,
                    'tipo_presentacion' => $j === 0 ? 'documento' : 'comprobante',
                    'dias_estimados'    => 0,
                    'horas_estimadas'   => $j === 0 ? 1 : 2,
                    'minutos_estimados' => 0,
                    'tiene_costo'       => false,
                    'costo_requisito'   => null,
                    'created_at'        => now(), 'updated_at' => now(),
                ]);
            }

            // Derecho de pago
            DB::table('tramite_derechos')->insert([
                'tramite_id' => $tramiteId,
                'concepto'   => 'Derecho de expedición',
                'monto'      => $montos[$i][0] ?? 100.00,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Procesos de atención (3 pasos)
            $pasos = [
                ['Presenta documentación en ventanilla y obtiene folio', 'Ventanilla Única'],
                ['Seguimiento de la solicitud en línea o por teléfono', 'Trámite interno'],
                ['Recoge resolución o pago y recibe el documento final', 'Ventanilla Única'],
            ];
            foreach ($pasos as $k => [$accion, $area]) {
                DB::table('proceso_atencion')->insert([
                    'tramite_id' => $tramiteId,
                    'tipo'       => 'atencion',
                    'paso'       => $k + 1,
                    'subpaso'    => 0,
                    'accion'     => $accion,
                    'area'       => $area,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // Fundamento jurídico
            DB::table('fundamento_juridico')->insert([
                'tramite_id'        => $tramiteId,
                'regulacion_id'     => $idsRegulaciones[0] ?? null,
                'normativa_nombre'  => 'Reglamento de Comercio del Municipio de La Paz',
                'tipo_normativa'    => 'Reglamento',
                'articulo_fraccion' => 'Art. 45, Fracción II',
                'resumen'           => 'Establece que toda persona física o moral que desee operar un establecimiento comercial fijo deberá obtener la licencia de funcionamiento correspondiente ante la autoridad municipal.',
                'created_at'        => now(), 'updated_at' => now(),
            ]);

            // Ficha portal
            $costos = ['$1,250.00', '$850.00', '$120.00'];
            DB::table('ficha_portal')->insert([
                'tramite_id'       => $tramiteId,
                'nombre_ciudadano' => ['Licencia de funcionamiento', 'Permiso de obra menor', 'Constancia de no adeudo'][$i] ?? 'Trámite',
                'tipo'             => 'Trámite',
                'descripcion'      => 'Descripción en lenguaje ciudadano del trámite municipal.',
                'modalidad'        => 'Presencial',
                'canal_principal'  => 'Ventanilla Única Municipal — Blvd. Forjadores km 3.5, La Paz, B.C.S.',
                'requiere_cita'    => false,
                'costo_publico'    => $costos[$i] ?? '$100.00',
                'forma_pago'       => 'Efectivo o tarjeta en cajas municipales',
                'resultado'        => ['Licencia impresa y digital con código QR', 'Permiso sellado con vigencia de obra', 'Constancia con sello y firma oficial'][$i] ?? 'Documento oficial',
                'created_at'       => now(), 'updated_at' => now(),
            ]);
        }
    }

    // =========================================================
    // Acciones Agenda SyD — Paquete 3 completo
    // =========================================================

    private function crearAcciones($usuario, $dependencia, $unidad, array $idsTramites, ?int $periodoId): array
    {
        $acciones = [
            [
                'tramite_id'              => $idsTramites[0] ?? null,
                'tipo'                    => 'simplificacion',
                'descripcion'             => self::PREFIJO . 'Simplificación de la Licencia de Funcionamiento Comercial',
                'meta'                    => 'Reducir el tiempo de resolución de 10 a 5 días hábiles y eliminar 2 requisitos sin sustento jurídico vigente.',
                'fecha_inicio'            => now()->toDateString(),
                'fecha_compromiso'        => now()->addMonths(3)->toDateString(),
                'responsable'             => 'Lic. María Elena Rodríguez Castro',
                'indicador'               => 'Porcentaje de reducción del tiempo de resolución respecto a la línea base de 10 días hábiles',
                'indicador_avance'        => 'Días hábiles promedio de resolución medidos mensualmente durante el trimestre',
                'acciones_simplificacion' => json_encode(self::ACCIONES_SIMP),
                'acciones_digitalizacion' => null,
                'nivel_actual'            => null,
                'nivel_meta'              => null,
                'estatus'                 => 'borrador',
            ],
            [
                'tramite_id'              => $idsTramites[0] ?? null,
                'tipo'                    => 'digitalizacion',
                'descripcion'             => self::PREFIJO . 'Digitalización de la Solicitud de Licencia Comercial en Línea',
                'meta'                    => 'Alcanzar Nivel 3 de digitalización (acceso electrónico transaccional) al finalizar el semestre.',
                'fecha_inicio'            => now()->subDays(30)->toDateString(),
                'fecha_compromiso'        => now()->addMonths(4)->toDateString(),
                'responsable'             => 'Ing. Jorge Herrera Castro',
                'indicador'               => 'Nivel de digitalización en la escala 0-5 del instrumento oficial ATDT',
                'indicador_avance'        => 'Nivel 2 actual — avanzando hacia Nivel 3 con implementación del formulario en línea y validación automática de RFC',
                'acciones_simplificacion' => null,
                'acciones_digitalizacion' => json_encode(self::ACCIONES_DIG),
                'nivel_actual'            => 2,
                'nivel_meta'              => 3,
                'estatus'                 => 'en_observacion',
            ],
            [
                'tramite_id'              => $idsTramites[2] ?? null,
                'tipo'                    => 'ambas',
                'descripcion'             => self::PREFIJO . 'Modernización de la Constancia de No Adeudo Municipal',
                'meta'                    => 'Simplificar el proceso presencial y elevar el nivel de digitalización de 3 a 4 con emisión digital y código QR.',
                'fecha_inicio'            => now()->subDays(90)->toDateString(),
                'fecha_compromiso'        => now()->subDays(10)->toDateString(),
                'responsable'             => 'C.P. Ana Torres Sánchez',
                'indicador'               => 'Reducción del tiempo de resolución y nivel de digitalización en escala 0-5 del instrumento ATDT',
                'indicador_avance'        => 'Tiempo reducido de 3 días a emisión el mismo día; nivel de digitalización ascendió de 3 a 4',
                'acciones_simplificacion' => json_encode([
                    'Reducción de plazos de resolución o respuesta' => 'De 3 días a emisión el mismo día mediante consulta automática al padrón fiscal municipal.',
                ]),
                'acciones_digitalizacion' => json_encode([
                    'Mejorar experiencia de usuario'       => 'Nuevo portal ciudadano con seguimiento de estatus en tiempo real y notificación por correo.',
                    'Eliminación de copias e impresiones'  => 'Constancia emitida solo en formato digital con código QR de validación en línea.',
                ]),
                'nivel_actual'            => 4,
                'nivel_meta'              => 4,
                'estatus'                 => 'completado',
            ],
        ];

        $ids = [];
        foreach ($acciones as $i => $datos) {
            $datos['dependencia_id'] = $dependencia->id;
            $datos['unidad_id']      = $unidad->id ?? null;
            $datos['periodo_id']     = $periodoId;
            $datos['created_by']     = $usuario->id;
            $datos['created_at']     = now()->subDays(30 - $i * 10);
            $datos['updated_at']     = now()->subDays(30 - $i * 10);

            $id = DB::table('acciones_agenda')->insertGetId($datos);
            $ids[] = $id;

            // Cargar el modelo para las operaciones siguientes.
            $accion = \App\Models\AccionAgenda::find($id);
            if (!$accion) continue;

            // Generar folio con el formato LPZ-AGD-SIGLAS-AÑO-NNN.
            // Se hace aquí (no en el insert raw) porque el trait GeneraFolio
            // necesita el modelo ya persistido y con su relación dependencia
            // cargada para calcular las siglas y el consecutivo correcto.
            // El save() persiste el folio antes de la siguiente iteración,
            // garantizando que el consecutivo incremente correctamente.
            $accion->load('dependencia');
            $accion->folio = $accion->generarFolio();
            $accion->saveQuietly(); // sin disparar observers de auditoría

            app(\App\Services\HitoAgendaService::class)->sembrarHitos($accion);

            // Acción completada → todos los hitos aprobados con evidencia demo
            if ($datos['estatus'] === 'completado') {
                $accion->hitos()->where('estado_aprobacion', '!=', 'aprobado')->each(function ($hito) use ($usuario) {
                    $hito->update([
                        'evidencia_archivo' => 'evidencias-hitos/demo_' . $hito->id . '.pdf',
                        'evidencia_nombre'  => 'Evidencia_' . str_replace(' ', '_', $hito->nombre) . '.pdf',
                        'estado_aprobacion' => 'aprobado',
                        'completado'        => true,
                        'completado_por'    => $usuario->id,
                        'aprobado_por'      => $usuario->id,
                        'fecha_aprobacion'  => now()->subDays(5)->toDateString(),
                        'fecha_completado'  => now()->subDays(7)->toDateString(),
                    ]);
                });
            }

            // Acción en observación → segundo hito con evidencia pendiente de visto bueno
            if ($datos['estatus'] === 'en_observacion') {
                $hito = $accion->hitos()
                    ->where('estado_aprobacion', 'sin_evidencia')
                    ->orderBy('orden')->skip(1)->first();
                if ($hito) {
                    $hito->update([
                        'evidencia_archivo' => 'evidencias-hitos/demo_pendiente_' . $hito->id . '.pdf',
                        'evidencia_nombre'  => 'Diagnóstico_digitalización.pdf',
                        'estado_aprobacion' => 'pendiente',
                        'completado_por'    => $usuario->id,
                        'fecha_completado'  => now()->subDays(2)->toDateString(),
                    ]);
                }
            }
        }
        return $ids;
    }

    // =========================================================
    // Regulaciones
    // =========================================================

    private function crearRegulaciones($usuario, $dependencia): array
    {
        $regs = [
            ['Reglamento de Comercio del Municipio de La Paz', 'vigente',      '2022-01-15'],
            ['Reglamento de Construcción y Zonificación',      'vigente',      '2020-03-10'],
            ['Bando de Policía y Buen Gobierno (2019)',        'en_revision',  '2019-01-05'],
        ];
        $ids = [];
        foreach ($regs as [$nombre, $estatus, $fecha]) {
            $ids[] = DB::table('regulaciones')->insertGetId([
                'nombre'             => self::PREFIJO . $nombre,
                'tipo'               => 'Reglamento',
                'dependencia_id'     => $dependencia->id,
                'fecha_publicacion'  => $fecha,
                'fecha_vigencia'     => now()->subYears(3)->toDateString(),
                'estatus'            => $estatus,
                'resumen'            => 'Regulación municipal vigente en el Municipio de La Paz, B.C.S.',
                'conversion_estatus' => 'pendiente',
                'created_by'         => $usuario->id,
                'created_at'         => now()->subDays(30),
                'updated_at'         => now()->subDays(30),
            ]);
        }
        return $ids;
    }

    // =========================================================
    // Propuestas regulatorias
    // =========================================================

    private function crearPropuestas($usuario, $dependencia): array
    {
        $propuestas = [
            [
                'folio'                       => 'PROP-DEMO-' . $dependencia->id . '-001',
                'nombre'                      => self::PREFIJO . 'Reforma al Reglamento de Comercio — Simplificación de Licencias',
                'tipo_regulacion'             => 'Reforma reglamentaria',
                'dependencia_id'              => $dependencia->id,
                'fecha_tentativa'             => now()->addMonths(2)->toDateString(),
                'justificacion'               => 'La tramitología actual representa una barrera para la apertura de negocios. Se propone reducir requisitos, digitalizar el proceso y reducir el plazo de resolución.',
                'genera_costos_burocraticos'  => true,
                'impacta_comercio_inversion'  => true,
                'impacta_tramites_existentes' => true,
                'determinacion_air'           => 'requiere_air',
                'estatus'                     => 'consulta',
            ],
            [
                'folio'                       => 'PROP-DEMO-' . $dependencia->id . '-002',
                'nombre'                      => self::PREFIJO . 'Nueva Norma de Imagen Urbana para Anuncios Publicitarios',
                'tipo_regulacion'             => 'Norma municipal',
                'dependencia_id'              => $dependencia->id,
                'fecha_tentativa'             => now()->addMonths(4)->toDateString(),
                'justificacion'               => 'La proliferación de anuncios sin regulación afecta la imagen urbana del centro histórico y la zona turística del Malecón de La Paz.',
                'genera_costos_burocraticos'  => false,
                'impacta_comercio_inversion'  => true,
                'impacta_tramites_existentes' => true,
                'determinacion_air'           => 'exento',
                'estatus'                     => 'borrador',
            ],
        ];

        $ids = [];
        foreach ($propuestas as $p) {
            $p['created_by'] = $usuario->id;
            $p['created_at'] = now()->subDays(20);
            $p['updated_at'] = now()->subDays(5);
            $ids[] = DB::table('propuestas_regulatorias')->insertGetId($p);
        }
        return $ids;
    }

    // =========================================================
    // AIR
    // =========================================================

    private function crearAir($usuario, array $idsPropuestas): void
    {
        if (empty($idsPropuestas)) return;

        DB::table('analisis_impacto_regulatorio')->insert([
            'propuesta_id'          => $idsPropuestas[0],
            'folio'                 => 'AIR-DEMO-' . $idsPropuestas[0] . '-001',
            'problematica'          => 'El proceso de licenciamiento comercial en La Paz presenta tiempos de hasta 10 días hábiles y requiere hasta 6 documentos, generando una carga burocrática significativa que inhibe la apertura de negocios formales.',
            'objetivos'             => 'Reducir el tiempo de resolución a 5 días hábiles, eliminar 2 requisitos sin sustento jurídico y digitalizar la solicitud inicial para permitir su presentación en línea sin necesidad de acudir a ventanilla.',
            'alternativas'          => 'Alternativa 1 (seleccionada): Reforma reglamentaria con digitalización del proceso. Alternativa 2: Circular administrativa interna sin reforma. Alternativa 3: Ventanilla digital sin modificar el reglamento. Se selecciona la Alternativa 1 por su impacto estructural y sustentabilidad a largo plazo.',
            'costos_implementacion' => 'Desarrollo del módulo digital de solicitudes: $85,000 MXN. Capacitación de 12 funcionarios de ventanilla: $12,000 MXN. Campaña de difusión ciudadana: $8,000 MXN. Total estimado: $105,000 MXN.',
            'beneficios'            => 'Reducción del CBT de $15,574 a $8,200 por trámite (ahorro de $7,374 por licencia). Ahorro ciudadano anual estimado: $13.6 millones MXN para 1,850 trámites anuales. Incremento estimado del 12% en la formalización de negocios.',
            'impacto_estimado'      => 'Impacto directo sobre 1,850 establecimientos comerciales anuales. Beneficio secundario para sus empleados, proveedores y la cadena económica municipal.',
            'impacta_tramites'      => true,
            'ambito_aplicacion'     => 'Municipal',
            'consulta_publica'      => 'Consulta pública realizada del 15 al 30 de abril 2026 mediante plataforma digital y presencial. Se recibieron 43 comentarios ciudadanos y 7 de cámaras empresariales (CANACO, COPARMEX). El 78% apoya la reforma.',
            'estatus'               => 'borrador',
            'created_by'            => $usuario->id,
            'created_at'            => now()->subDays(15),
            'updated_at'            => now()->subDays(5),
        ]);
    }

    // =========================================================
    // Observaciones y calendario
    // =========================================================

    private function crearObservaciones($usuario, array $idsTramites): void
    {
        if (empty($idsTramites)) return;

        DB::table('observaciones')->insert([
            'observable_type' => \App\Models\Tramite::class,
            'observable_id'   => $idsTramites[0],
            'seccion'         => 'Datos generales',
            'campo'           => 'volumen_anual',
            'texto'           => self::PREFIJO . 'El volumen anual debe validarse contra el registro del padrón fiscal. Se reportan 1,850 trámites pero el padrón activo refleja 1,620 establecimientos vigentes al cierre del ejercicio anterior.',
            'realizada_por'   => $usuario->id,
            'atendida'        => false,
            'estatus'         => 'pendiente',
            'created_at'      => now()->subDays(3),
            'updated_at'      => now()->subDays(3),
        ]);
    }

    private function crearEventosCalendario($dependencia, array $idsAcciones): void
    {
        if (empty($idsAcciones)) return;

        $eventos = [
            [5,  'simplificacion', 'Entrega diagnóstico inicial — Licencia Comercial',       'cumplido',  100],
            [14, 'digitalizacion', 'Configurar formulario en línea — Licencia Comercial',    'pendiente',  35],
            [21, 'simplificacion', 'Validación jurídica de requisitos a eliminar',           'pendiente',  60],
            [28, 'digitalizacion', 'Pruebas de usuario portal — Constancia No Adeudo',       'pendiente',   0],
        ];

        foreach ($eventos as $i => [$dia, $tipo, $titulo, $estatus, $avance]) {
            DB::table('calendario_eventos')->insert([
                'tipo'           => $tipo,
                'titulo'         => self::PREFIJO . $titulo,
                'accion'         => 'Acción de mejora ' . $tipo,
                'meta'           => 'Reducir costo burocrático y mejorar digitalización del trámite.',
                'fecha'          => now()->startOfMonth()->addDays($dia - 1)->toDateString(),
                'estatus'        => $estatus,
                'avance'         => $avance,
                'responsable'    => 'Lic. Responsable Demo',
                'dependencia_id' => $dependencia->id,
                'evidencia'      => $estatus === 'cumplido',
                'eventable_type' => \App\Models\AccionAgenda::class,
                'eventable_id'   => $idsAcciones[$i % count($idsAcciones)],
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
