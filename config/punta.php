<?php

/**
 * Configuración propia del sistema PUNTA.
 *
 * Valores que pueden cambiar sin tocar código. Se acceden con
 * config('punta.clave'). Por ejemplo, si el sistema se reusa en otro
 * municipio, solo se cambia el prefijo aquí.
 */
return [

    /**
     * Prefijo del municipio para las homoclaves de trámites.
     * Formato de homoclave: {prefijo}-{siglas_dep}-{siglas_unidad}-{consecutivo}
     * Ejemplo: LPZ-DGSP-VU-5
     */
    'prefijo_homoclave' => env('PUNTA_PREFIJO_HOMOCLAVE', 'LPZ'),

    /**
     * Etiquetas legibles de los estados de cada módulo.
     *
     * La clave es el valor técnico guardado en la BD (ej. 'en_observacion')
     * y el valor es lo que ve el usuario (ej. 'En observación'). Se usa
     * con el helper estatus_label() para no mostrar nombres internos.
     *
     * Si un estado no está aquí, el helper cae a una versión legible
     * automática (reemplaza guiones bajos y capitaliza).
     */
    // Etiquetas de los estados del FLUJO PRINCIPAL.
    // Solo la directiva @estatus lee de aquí, y solo se usa para trámites,
    // agenda (homologada con trámites), propuestas y regulaciones. Por eso este
    // array contiene únicamente esos estados: así no hay colisiones de nombre.
    'etiquetas_estatus' => [
        // Trámites y Agenda SyD (vocabulario homologado, comparten estados)
        'borrador'        => 'Borrador',
        'en_observacion'  => 'En observación',
        'en_correccion'   => 'En corrección',
        'en_firma'        => 'En firma',
        'completado'      => 'Completado',
        // Propuestas regulatorias
        'consulta'        => 'En consulta',
        'determinada'     => 'Determinada',
        'dictaminada'     => 'Dictaminada',
        'publicada'       => 'Publicada',
        // Regulaciones (en_revision es de regulaciones; no choca con agenda
        // porque agenda ya no usa ese estado tras la homologación)
        'vigente'         => 'Vigente',
        'en_revision'     => 'En revisión',
        'derogada'        => 'Derogada',
    ],

    // Etiquetas de los MÓDULOS SATÉLITE (AIR, dictamen, exención, eventos de
    // calendario, observaciones). Están aquí solo como referencia/catálogo;
    // estos módulos muestran su estado con sus propios textos o con métodos
    // como Observacion::estatusLegible(), NO con la directiva @estatus. Por eso
    // viven separadas: varios usan 'pendiente'/'aprobada' con el mismo texto, y
    // mantenerlas fuera del array principal evita cualquier colisión.
    'etiquetas_estatus_satelite' => [
        // AIR
        'enviado'         => 'Enviado',
        'en_dictamen'     => 'En dictamen',
        'dictaminado'     => 'Dictaminado',
        // Dictamen AIR
        'condicionado'    => 'Condicionado',
        'rechazado'       => 'Rechazado',
        // Exención AIR
        'solicitada'      => 'Solicitada',
        'aprobada'        => 'Aprobada',
        'rechazada'       => 'Rechazada',
        // Común a varios satélites (dictamen, exención, eventos, observaciones)
        'pendiente'       => 'Pendiente',
        // Eventos de calendario
        'cumplido'        => 'Cumplido',
        'vencido'         => 'Vencido',
    ],

    // Campos observables por sección del detalle del trámite (corrección #18).
    // Cuando un revisor pulsa "Agregar observación" en una sección, el modal
    // ofrece estos campos para que la observación quede ligada a uno concreto.
    // La clave es el nombre interno (se guarda en observaciones.campo); el
    // valor es la etiqueta legible que ve el revisor.
    'campos_observables_tramite' => [
        'Datos generales' => [
            'nombre_oficial'      => 'Nombre oficial del trámite',
            'homoclave'           => 'Homoclave',
            'dependencia'         => 'Dependencia',
            'unidad'              => 'Unidad administrativa',
            'servidor_publico'    => 'Servidor público',
            'dirigido_a'          => 'Dirigido a',
            'frecuencia'          => 'Frecuencia',
            'volumen_anual'       => 'Volumen anual',
            'plazo_resolucion'    => 'Plazo de resolución',
        ],
        'Costo burocrático' => [
            'monto_derechos'      => 'Pago de derechos',
            'costo_publico'       => 'Costo público',
            'copias'              => 'Copias',
            'cbd'                 => 'Costo burocrático directo',
        ],
        'Requisitos' => [
            'requisitos'          => 'Lista de requisitos',
            'tiempo_requisitos'   => 'Tiempo de los requisitos',
        ],
        'Fundamento jurídico' => [
            'fundamento_juridico' => 'Fundamento jurídico',
        ],
    ],

    // Campos observables por sección del detalle de una acción de agenda (#18).
    'campos_observables_agenda' => [
        'Datos de la acción' => [
            'tramite_vinculado' => 'Trámite vinculado',
            'tipo'              => 'Tipo de acción',
            'descripcion'       => 'Acción registrada',
            'responsable'       => 'Responsable',
        ],
        'Alcance y necesidad' => [
            'meta'             => 'Meta esperada',
            'indicador'        => 'Indicador',
            'fecha_inicio'     => 'Fecha de inicio',
            'fecha_compromiso' => 'Fecha compromiso',
        ],
        'Avance y evidencias' => [
            'avance'    => 'Avance reportado',
            'evidencia' => 'Evidencias',
        ],
    ],

    // Campos observables por sección del detalle de una propuesta regulatoria (#18).
    'campos_observables_propuesta' => [
        'Datos generales' => [
            'nombre'          => 'Nombre de la propuesta',
            'tipo_regulacion' => 'Tipo de regulación',
            'responsable'     => 'Responsable',
            'enlace'          => 'Enlace de simplificación',
        ],
        'Justificación y problemática' => [
            'justificacion'   => 'Justificación',
            'problematica'    => 'Problemática',
            'objetivo'        => 'Objetivo',
        ],
        'Beneficios, costos e impactos' => [
            'sectores_impactados' => 'Sectores impactados',
            'impacto_comercio'    => 'Impacto en comercio',
            'nuevos_costos'       => 'Nuevos costos burocráticos',
            'beneficios'          => 'Beneficios',
        ],
        'Determinación AIR' => [
            'air'    => 'Análisis de impacto regulatorio',
            'umbral' => 'Umbral de proporcionalidad',
        ],
    ],

];
