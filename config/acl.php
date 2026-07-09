<?php

/**
 * Catálogo central de permisos del sistema PUNTA.
 *
 * Cada entrada es: 'codigo' => ['modulo' => '...', 'accion' => '...', 'descripcion' => '...']
 *
 * El seeder AclSeeder lee este archivo para poblar la tabla `permisos`.
 * Cuando agregues una funcionalidad nueva, agrega aquí su permiso y
 * vuelve a correr el seeder (o un comando artisan dedicado).
 *
 * Convención de códigos: modulo.accion (snake_case)
 *   ej: tramites.ver, tramites.crear, agenda_regulatoria.editar
 */
return [

    'permisos' => [
        // Trámites
        'tramites.ver'           => ['modulo' => 'tramites', 'accion' => 'ver',          'descripcion' => 'Ver listado y detalle de trámites'],
        'tramites.crear'         => ['modulo' => 'tramites', 'accion' => 'crear',        'descripcion' => 'Registrar un nuevo trámite'],
        'tramites.editar'        => ['modulo' => 'tramites', 'accion' => 'editar',       'descripcion' => 'Modificar trámites existentes'],
        'tramites.eliminar'      => ['modulo' => 'tramites', 'accion' => 'eliminar',     'descripcion' => 'Eliminar trámites propios'],
        'tramites.aprobar'       => ['modulo' => 'tramites', 'accion' => 'aprobar',      'descripcion' => 'Aprobar o regresar trámites'],
        'tramites.observar'      => ['modulo' => 'tramites', 'accion' => 'observar',     'descripcion' => 'Agregar observaciones a trámites en revisión'],

        // Agenda SyD
        'agenda.ver'          => ['modulo' => 'agenda', 'accion' => 'ver',     'descripcion' => 'Ver acciones de simplificación'],
        'agenda.crear'        => ['modulo' => 'agenda', 'accion' => 'crear',   'descripcion' => 'Registrar acciones de simplificación'],
        'agenda.editar'       => ['modulo' => 'agenda', 'accion' => 'editar',  'descripcion' => 'Modificar acciones de simplificación'],
        'agenda.eliminar'     => ['modulo' => 'agenda', 'accion' => 'eliminar','descripcion' => 'Eliminar acciones propias'],
        'agenda.aprobar'      => ['modulo' => 'agenda', 'accion' => 'aprobar',  'descripcion' => 'Aprobar acciones'],
        'agenda.observar'     => ['modulo' => 'agenda', 'accion' => 'observar', 'descripcion' => 'Agregar observaciones a acciones de agenda'],

        // Agenda Regulatoria
        'agenda_regulatoria.ver'      => ['modulo' => 'agenda_regulatoria', 'accion' => 'ver',     'descripcion' => 'Ver propuestas regulatorias'],
        'agenda_regulatoria.crear'    => ['modulo' => 'agenda_regulatoria', 'accion' => 'crear',   'descripcion' => 'Registrar propuestas regulatorias'],
        'agenda_regulatoria.editar'   => ['modulo' => 'agenda_regulatoria', 'accion' => 'editar',  'descripcion' => 'Modificar propuestas'],
        'agenda_regulatoria.eliminar' => ['modulo' => 'agenda_regulatoria', 'accion' => 'eliminar', 'descripcion' => 'Eliminar propuestas'],
        'agenda_regulatoria.aprobar'  => ['modulo' => 'agenda_regulatoria', 'accion' => 'aprobar',  'descripcion' => 'Aprobar propuestas regulatorias'],
        'agenda_regulatoria.observar' => ['modulo' => 'agenda_regulatoria', 'accion' => 'observar', 'descripcion' => 'Agregar observaciones a propuestas regulatorias'],

        // Regulaciones (catálogo jurídico)
        'regulaciones.ver'       => ['modulo' => 'regulaciones', 'accion' => 'ver',      'descripcion' => 'Consultar catálogo de regulaciones'],
        'regulaciones.crear'     => ['modulo' => 'regulaciones', 'accion' => 'crear',    'descripcion' => 'Subir nuevas regulaciones (Word/PDF)'],
        'regulaciones.editar'    => ['modulo' => 'regulaciones', 'accion' => 'editar',   'descripcion' => 'Modificar regulaciones del catálogo'],
        'regulaciones.eliminar'  => ['modulo' => 'regulaciones', 'accion' => 'eliminar', 'descripcion' => 'Eliminar regulaciones del catálogo'],

        // Calendario
        'calendario.ver'      => ['modulo' => 'calendario', 'accion' => 'ver', 'descripcion' => 'Ver el calendario de actividades'],

        // Administración
        'usuarios.ver'        => ['modulo' => 'usuarios', 'accion' => 'ver',      'descripcion' => 'Ver listado de usuarios'],
        'usuarios.crear'      => ['modulo' => 'usuarios', 'accion' => 'crear',    'descripcion' => 'Crear nuevos usuarios'],
        'usuarios.editar'     => ['modulo' => 'usuarios', 'accion' => 'editar',   'descripcion' => 'Modificar usuarios'],
        'usuarios.eliminar'   => ['modulo' => 'usuarios', 'accion' => 'eliminar', 'descripcion' => 'Eliminar (desactivar) usuarios'],

        'periodos.ver'        => ['modulo' => 'periodos', 'accion' => 'ver',      'descripcion' => 'Ver periodos de captura'],
        'periodos.crear'      => ['modulo' => 'periodos', 'accion' => 'crear',    'descripcion' => 'Crear periodos'],
        'periodos.editar'     => ['modulo' => 'periodos', 'accion' => 'editar',   'descripcion' => 'Modificar periodos'],
        'periodos.eliminar'   => ['modulo' => 'periodos', 'accion' => 'eliminar', 'descripcion' => 'Eliminar periodos no activos'],

        'bitacora.ver'        => ['modulo' => 'bitacora', 'accion' => 'ver', 'descripcion' => 'Consultar la bitácora general'],

        // ACL (gestión del propio sistema de permisos)
        'acl.gestionar'       => ['modulo' => 'acl', 'accion' => 'gestionar', 'descripcion' => 'Administrar roles, permisos y asignaciones'],

        // Catálogo SCIAN (sectores y subsectores económicos)
        'scian.ver'           => ['modulo' => 'scian', 'accion' => 'ver',     'descripcion' => 'Consultar catálogo SCIAN'],
        'scian.gestionar'     => ['modulo' => 'scian', 'accion' => 'gestionar', 'descripcion' => 'Modificar el catálogo SCIAN (admin avanzado)'],

        // Costo burocrático: parámetros y umbrales
        'parametros.gestionar' => ['modulo' => 'parametros', 'accion' => 'gestionar', 'descripcion' => 'Editar parámetros del cálculo (salario hora, copia, jornada, etc.)'],
        'umbrales.gestionar'   => ['modulo' => 'umbrales',   'accion' => 'gestionar', 'descripcion' => 'Cargar y actualizar umbrales configurados por sector/subsector'],
        'unidades_valor.gestionar' => ['modulo' => 'unidades_valor', 'accion' => 'gestionar', 'descripcion' => 'Actualizar valores de UMA, salario mínimo y UDI'],

        // Firmas (módulo futuro)
        'firmas.firmar'       => ['modulo' => 'firmas', 'accion' => 'firmar', 'descripcion' => 'Firmar digitalmente trámites y acuses'],

        // Digitalización (biblioteca, reingenierías, diagramas)
        'digitalizacion.ver'           => ['modulo' => 'digitalizacion', 'accion' => 'ver',           'descripcion' => 'Ver la Biblioteca de Digitalización'],
        'digitalizacion.reingenieria'  => ['modulo' => 'digitalizacion', 'accion' => 'reingenieria',  'descripcion' => 'Crear y editar reingenierías TO-BE'],
        'digitalizacion.diagrama'      => ['modulo' => 'digitalizacion', 'accion' => 'diagrama',      'descripcion' => 'Generar, visualizar y descargar diagramas'],
        'digitalizacion.digitalizar'   => ['modulo' => 'digitalizacion', 'accion' => 'digitalizar',   'descripcion' => 'Iniciar y gestionar la digitalización de trámites'],
        'digitalizacion.solicitar'     => ['modulo' => 'digitalizacion', 'accion' => 'solicitar',     'descripcion' => 'Solicitar reingeniería directa (sin Agenda)'],
    ],

    /**
     * Asignación inicial de permisos a cada rol del sistema.
     * Los roles marcados con `sistema = true` no se pueden eliminar
     * desde la interfaz; sí se les pueden agregar o quitar permisos.
     */
    'roles_iniciales' => [
        'admin' => [
            'nombre'      => 'Administrador',
            'descripcion' => 'Acceso total al sistema. Gestiona usuarios, periodos, bitácora y ACL.',
            'sistema'     => true,
            'permisos'    => '*', // todos
        ],
        'enlace' => [
            'nombre'      => 'Enlace de dependencia',
            'descripcion' => 'Captura trámites y acciones de agenda de su dependencia. Consulta regulaciones para citarlas como fundamento.',
            'sistema'     => true,
            'permisos'    => [
                'tramites.ver', 'tramites.crear', 'tramites.editar', 'tramites.eliminar',
                'agenda.ver', 'agenda.crear', 'agenda.editar', 'agenda.eliminar',
                'agenda_regulatoria.ver', 'agenda_regulatoria.crear', 'agenda_regulatoria.editar', 'agenda_regulatoria.eliminar',
                'regulaciones.ver',  // Bug #B23: el enlace necesita ver regulaciones para citarlas en trámites/propuestas
                'calendario.ver',
                'scian.ver',
                'firmas.firmar',
            ],
        ],
        'juridico' => [
            'nombre'      => 'Jurídico',
            'descripcion' => 'Gestiona regulaciones. Observa trámites, agenda SyD y agenda regulatoria de su área.',
            'sistema'     => true,
            'permisos'    => [
                'regulaciones.ver', 'regulaciones.crear', 'regulaciones.editar', 'regulaciones.eliminar',
                'tramites.ver', 'tramites.observar',
                'agenda.ver', 'agenda.observar',
                'agenda_regulatoria.ver', 'agenda_regulatoria.observar',
                'calendario.ver',
                'scian.ver',
                'firmas.firmar',
            ],
        ],
        'revisora' => [
            'nombre'      => 'Revisor',
            'descripcion' => 'Revisa, observa y aprueba trámites, acciones y propuestas regulatorias.',
            'sistema'     => true,
            'permisos'    => [
                'tramites.ver', 'tramites.aprobar', 'tramites.observar',
                'agenda.ver', 'agenda.aprobar', 'agenda.observar',
                'agenda_regulatoria.ver', 'agenda_regulatoria.aprobar', 'agenda_regulatoria.observar',
                'regulaciones.ver',
                'scian.ver',
                'calendario.ver',
            ],
        ],
        'sujeto' => [
            'nombre'      => 'Sujeto obligado',
            'descripcion' => 'Consulta trámites, agendas y firma documentos de su área.',
            'sistema'     => true,
            'permisos'    => [
                'tramites.ver',
                'agenda.ver',
                'agenda_regulatoria.ver',
                'regulaciones.ver',
                'calendario.ver',
                'firmas.firmar',
            ],
        ],
        'digitalizador' => [
            'nombre'      => 'Digitalizador',
            'descripcion' => 'Gestiona la digitalización de trámites y servicios: biblioteca, reingenierías, diagramas y descargas.',
            'sistema'     => true,
            'permisos'    => [
                'digitalizacion.ver',
                'digitalizacion.reingenieria',
                'digitalizacion.diagrama',
                'digitalizacion.digitalizar',
                'digitalizacion.solicitar',
                'tramites.ver',
                'tramites.editar',           // Para actualizar nivel de digitalización
                'agenda.ver',                // Consultar acciones de agenda vinculadas
                'regulaciones.ver',          // Consultar fundamentos
                'agenda_regulatoria.ver',    // Solo consulta
                'calendario.ver',
            ],
        ],
    ],
];
