<?php

use App\Models\AccionAgenda;
use App\Models\Regulacion;
use App\Models\Requisito;
use App\Models\Tramite;

/*
|--------------------------------------------------------------------------
| Lista blanca de consultas en lenguaje natural a los datos
|--------------------------------------------------------------------------
|
| Este archivo es el CONTRATO DE SEGURIDAD del módulo de preguntas a los datos
| ("¿cuántos trámites en borrador?", "regulaciones por dependencia"...).
|
| La IA NUNCA genera SQL. Solo traduce la pregunta a una "receta" (entidad,
| métrica, filtros, agrupar) y ConsultaDatosService la valida CONTRA ESTE archivo
| antes de ejecutar. Si la receta pide una entidad, columna, valor o dimensión que
| no esté declarada aquí, se rechaza. Así:
|
|   - Nunca se toca una tabla o columna no declarada (ni usuarios ni contraseñas).
|   - Nunca se escribe: solo contar / listar / agrupar.
|   - Añadir una capacidad nueva = agregar una entrada aquí, no tocar el código.
|
| Los `valores` de cada filtro salen de las constantes de los modelos y de las
| reglas de validación del proyecto, no de lo que hoy exista en la base: así se
| puede filtrar por un estado válido aunque todavía no haya registros con él.
|
*/

return [

    /*
    | 'filtros'     -> por qué se puede filtrar, y con qué valores válidos.
    | 'dimensiones' -> por qué se puede AGRUPAR: una columna directa, una relación
    |                  belongsTo ('relacion' + 'columna'), o la fecha ('tipo' => 'mes').
    | 'permiso'     -> sin él, la pregunta no se responde (se respeta el rol).
    */
    'entidades' => [

        'tramites' => [
            'label'          => 'trámites y servicios',
            'permiso'        => 'tramites.ver',
            'modelo'         => Tramite::class,
            'columna_nombre' => 'nombre_oficial',
            'columna_fecha'  => 'created_at',

            'filtros' => [
                'estatus' => [
                    'columna' => 'estatus',
                    // Tramite::ESTATUS_TODOS
                    'valores' => ['borrador', 'en_observacion', 'en_correccion', 'en_firma', 'completado'],
                ],
                'naturaleza' => [
                    'columna' => 'naturaleza',
                    'valores' => ['tramite', 'servicio'],
                ],
            ],

            'dimensiones' => [
                'estatus'     => ['tipo' => 'columna',  'columna' => 'estatus'],
                'naturaleza'  => ['tipo' => 'columna',  'columna' => 'naturaleza'],
                'dependencia' => ['tipo' => 'relacion', 'relacion' => 'dependencia', 'columna' => 'nombre'],
                'mes'         => ['tipo' => 'mes',      'columna' => 'created_at'],
            ],
        ],

        'regulaciones' => [
            'label'          => 'regulaciones',
            'permiso'        => 'regulaciones.ver',
            'modelo'         => Regulacion::class,
            'columna_nombre' => 'nombre',
            'columna_fecha'  => 'created_at',

            'filtros' => [
                // OJO: la columna `estado` de esta tabla NO es un estatus, es la
                // entidad federativa (junto con `ambito` y `municipio`). El estado
                // normativo real vive en `estatus`.
                'estatus' => [
                    'columna' => 'estatus',
                    // Regulacion::ESTATUS_TODOS
                    'valores' => ['vigente', 'en_revision', 'derogada'],
                ],
                'conversion_estatus' => [
                    'columna' => 'conversion_estatus',
                    // Regulacion::CONVERSION_* (+ 'error')
                    'valores' => ['pendiente', 'procesando', 'listo', 'error'],
                ],
                'extension' => [
                    'columna' => 'extension_original',
                    'valores' => ['pdf', 'docx', 'doc'],
                ],
                'estructurada' => [
                    'columna' => 'estructurada',
                    'valores' => ['0', '1'], // booleano
                ],
                'tipo' => [
                    'columna' => 'tipo',
                    'valores' => [], // abierto: el catálogo de tipos no es fijo
                ],
            ],

            'dimensiones' => [
                'estatus'            => ['tipo' => 'columna',  'columna' => 'estatus'],
                'conversion_estatus' => ['tipo' => 'columna',  'columna' => 'conversion_estatus'],
                'extension'          => ['tipo' => 'columna',  'columna' => 'extension_original'],
                'tipo'               => ['tipo' => 'columna',  'columna' => 'tipo'],
                'dependencia'        => ['tipo' => 'relacion', 'relacion' => 'dependencia', 'columna' => 'nombre'],
                'mes'                => ['tipo' => 'mes',      'columna' => 'created_at'],
            ],
        ],

        'requisitos' => [
            'label'          => 'requisitos',
            'permiso'        => 'tramites.ver',
            'modelo'         => Requisito::class,
            'columna_nombre' => 'nombre',
            'columna_fecha'  => 'created_at',

            'filtros' => [
                'tiene_costo' => [
                    'columna' => 'tiene_costo',
                    'valores' => ['0', '1'], // booleano
                ],
            ],

            'dimensiones' => [
                // Requisito -> tramite -> dependencia (relación anidada; el ejecutor
                // resuelve el join leyendo las llaves de Eloquent).
                'dependencia' => ['tipo' => 'relacion', 'relacion' => 'tramite.dependencia', 'columna' => 'nombre'],
                'mes'         => ['tipo' => 'mes', 'columna' => 'created_at'],
            ],
        ],

        'acciones' => [
            'label'          => 'acciones de agenda',
            'permiso'        => 'agenda_regulatoria.ver',
            'modelo'         => AccionAgenda::class,   // tabla 'acciones_agenda'
            'columna_nombre' => 'descripcion',
            'columna_fecha'  => 'created_at',

            'filtros' => [
                'estatus' => [
                    'columna' => 'estatus',
                    // AccionAgenda::ESTATUS_TODOS
                    'valores' => ['borrador', 'en_observacion', 'en_correccion', 'en_firma', 'completado'],
                ],
                'tipo' => [
                    'columna' => 'tipo',
                    // Regla de validación del request de agenda regulatoria.
                    'valores' => ['simplificacion', 'digitalizacion', 'ambas'],
                ],
            ],

            'dimensiones' => [
                'estatus'     => ['tipo' => 'columna',  'columna' => 'estatus'],
                'tipo'        => ['tipo' => 'columna',  'columna' => 'tipo'],
                'dependencia' => ['tipo' => 'relacion', 'relacion' => 'dependencia', 'columna' => 'nombre'],
                'mes'         => ['tipo' => 'mes',      'columna' => 'created_at'],
            ],
        ],

    ],

    /*
    | Métricas permitidas. La IA debe pedir una de estas; otra cosa se rechaza.
    |   conteo  -> un número            ("¿cuántos trámites en borrador?")
    |   lista   -> filas (con límite)   ("¿cuáles trámites están en borrador?")
    |   agrupar -> conteo por dimensión ("trámites por dependencia")
    */
    'metricas' => ['conteo', 'lista', 'agrupar'],

    // Tope de filas al listar, para no traer la tabla entera nunca.
    'limite_lista' => 50,
];
