<?php

use App\Models\Tramite;
use App\Models\AccionAgenda;
use App\Models\Regulacion;
use App\Models\Observacion;

/**
 * Configuración central de flujos.
 *
 * Define, en un solo lugar, cómo se agrupan los estatus de cada módulo en las
 * categorías del dashboard de la Autoridad Revisora. El controlador y la vista
 * leen de aquí, de modo que cambiar una regla (qué estatus cuenta como
 * "completado", por ejemplo) se hace una sola vez y se refleja en todos lados.
 *
 * Los valores usan las constantes de los modelos como fuente de verdad, así no
 * hay strings de estatus escritos a mano que puedan quedar desfasados.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Observaciones "vivas"
    |--------------------------------------------------------------------------
    | Una observación está "viva" si todavía requiere acción de la revisora
    | (atender o validar). 'validada' ya no cuenta: ese ciclo terminó.
    */
    'observaciones_vivas' => Observacion::ESTATUS_VIVOS,

    /*
    |--------------------------------------------------------------------------
    | Categorías de la Autoridad Revisora
    |--------------------------------------------------------------------------
    | Por cada categoría se define, por módulo, qué estatus la componen y si
    | además debe tener (o no) observaciones vivas.
    |
    |   'estatus'    => lista de estatus de ese módulo que entran en la categoría
    |   'obs_vivas'  => true  → solo los que TIENEN observaciones vivas
    |                   false → solo los que NO tienen observaciones vivas
    |                   null  → no se filtra por observaciones
    |
    | "Pendientes" no se define aquí: es la unión de 'por_revisar' + 'por_aprobar'
    | (ver 'pendientes_incluye' abajo).
    */
    'categorias' => [

        'por_revisar' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_EN_OBSERVACION, Tramite::ESTATUS_EN_CORRECCION],
                'obs_vivas' => true,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_EN_OBSERVACION, AccionAgenda::ESTATUS_EN_CORRECCION],
                'obs_vivas' => true,
            ],
        ],

        'por_aprobar' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_EN_OBSERVACION, Tramite::ESTATUS_EN_CORRECCION],
                'obs_vivas' => false,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_EN_OBSERVACION, AccionAgenda::ESTATUS_EN_CORRECCION],
                'obs_vivas' => false,
            ],
        ],

        'completados' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_EN_FIRMA, Tramite::ESTATUS_COMPLETADO],
                'obs_vivas' => null,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_EN_FIRMA, AccionAgenda::ESTATUS_COMPLETADO],
                'obs_vivas' => null,
            ],
        ],

        // --- Categorías del Sujeto Obligado (titular de la dependencia) ---
        // Reflejan su trabajo: corregir lo observado, firmar lo aprobado, ver
        // lo que sigue su curso y lo cerrado. Siempre dentro de su dependencia
        // (el alcance por rol ya lo filtra). 'completados' se reutiliza tal cual.

        'por_corregir' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_EN_OBSERVACION, Tramite::ESTATUS_EN_CORRECCION],
                'obs_vivas' => null,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_EN_OBSERVACION, AccionAgenda::ESTATUS_EN_CORRECCION],
                'obs_vivas' => null,
            ],
        ],

        'por_firmar' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_EN_FIRMA],
                'obs_vivas' => null,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_EN_FIRMA],
                'obs_vivas' => null,
            ],
        ],

        'en_tramite' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_BORRADOR],
                'obs_vivas' => null,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_BORRADOR],
                'obs_vivas' => null,
            ],
        ],

        // Cerrados (para el sujeto): solo 'completado', sin 'en_firma'
        // (ese va en 'por_firmar'). Distinta de 'completados' de la revisora.
        'cerrados' => [
            'tramites' => [
                'estatus'   => [Tramite::ESTATUS_COMPLETADO],
                'obs_vivas' => null,
            ],
            'agenda' => [
                'estatus'   => [AccionAgenda::ESTATUS_COMPLETADO],
                'obs_vivas' => null,
            ],
        ],

        // --- Categorías del rol Jurídico (módulo de regulaciones) ---
        // Jurídico es dueño del módulo de regulaciones. Estas categorías usan
        // el módulo 'regulaciones', no trámites/agenda. Las regulaciones no
        // reciben observaciones, por eso obs_vivas es null.

        'regulaciones_por_revisar' => [
            'regulaciones' => [
                'estatus'   => [Regulacion::ESTATUS_EN_REVISION],
                'obs_vivas' => null,
            ],
        ],

        'regulaciones_vigentes' => [
            'regulaciones' => [
                'estatus'   => [Regulacion::ESTATUS_VIGENTE],
                'obs_vivas' => null,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Composición de "Pendientes"
    |--------------------------------------------------------------------------
    | La categoría "Pendientes" es la unión de estas categorías.
    */
    'pendientes_incluye' => ['por_revisar', 'por_aprobar'],

    /*
    |--------------------------------------------------------------------------
    | Panorama del Administrador
    |--------------------------------------------------------------------------
    | El admin ve todo el sistema. Para cada módulo mostramos tres cifras:
    |  - total:   todos los registros (sin filtro de estatus).
    |  - proceso: los activos que aún no se cierran.
    |  - cierre:  los terminados (completado / publicada / vigente, según el módulo).
    | Cada cifra es clicable y filtra la tabla. El filtro que llega es
    | "{modulo}_{grupo}", p.ej. "tramites_proceso".
    */
    'panorama_admin' => [
        'tramites' => [
            'etiqueta'      => 'Trámites',
            'cierre_label'  => 'Completados',
            'proceso'       => ['borrador', 'en_observacion', 'en_correccion', 'en_firma'],
            'cierre'        => ['completado'],
        ],
        'agenda' => [
            'etiqueta'      => 'Agenda SyD',
            'cierre_label'  => 'Completados',
            'proceso'       => ['borrador', 'en_observacion', 'en_correccion', 'en_firma'],
            'cierre'        => ['completado'],
        ],
        'propuestas' => [
            'etiqueta'      => 'Propuestas regulatorias',
            'cierre_label'  => 'Publicadas',
            'proceso'       => ['borrador', 'consulta', 'determinada', 'dictaminada'],
            'cierre'        => ['publicada'],
        ],
        'regulaciones' => [
            'etiqueta'      => 'Regulaciones',
            'cierre_label'  => 'Vigentes',
            'proceso'       => ['en_revision'],
            'cierre'        => ['vigente'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Etiquetas visibles
    |--------------------------------------------------------------------------
    | Una sola fuente para los textos que se muestran en las tarjetas y en el
    | título de la tabla. PHP y JavaScript leen de aquí (la vista las inyecta
    | al JS) para que no haya textos duplicados.
    */
    'etiquetas' => [
        'pendientes'  => 'Pendientes',
        'por_revisar' => 'Por revisar',
        'por_aprobar' => 'Por aprobar',
        'completados' => 'Completados',
    ],

    /*
    |--------------------------------------------------------------------------
    | Orden de las tarjetas del dashboard de la revisora
    |--------------------------------------------------------------------------
    | El orden en que aparecen las cuatro tarjetas. La primera es la que se
    | selecciona por defecto al entrar.
    */
    'tarjetas_revisora' => ['pendientes', 'por_revisar', 'por_aprobar', 'completados'],

];
