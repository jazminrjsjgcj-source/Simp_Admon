<?php

namespace App\Support;

/**
 * Procesos de ejemplo para sembrar flujos.
 *
 * Son los procesos internos del propio PUNTA, descritos tal como los implementa el
 * sistema: las decisiones y los retornos de aquí corresponden a validaciones que
 * existen en el código y que están cubiertas por las pruebas.
 *
 * Sirven para probar la captura y el diagrama con procesos de verdad, que es donde
 * se ve si el modelo aguanta: el de digitalización tiene una puerta con tres
 * condiciones, el de baja de usuario tiene una salvaguarda que impide continuar, y
 * el del AIR tiene dos caminos paralelos —dictamen o exención— que terminan en
 * finales distintos.
 *
 * Formato de cada proceso:
 *
 *   participantes → clave interna, nombre visible y tipo (define su color)
 *   resultados    → las formas en que puede terminar
 *   fases         → cada una con sus actividades
 *   actividades   → quién, qué hace, si revisa algo, y a dónde sigue
 *
 * En las rutas, 'a' admite: 'siguiente', 'inicio_fase', 'inicio_proceso',
 * 'fin:<clave de resultado>' o la clave de otra actividad.
 */
class ProcesosEjemplo
{
    /** @return array<string, string> clave => nombre, para listarlos */
    public static function disponibles(): array
    {
        return [
            'digitalizacion' => 'Digitalización de un trámite',
            'alta-usuario'   => 'Alta de una persona usuaria',
            'baja-usuario'   => 'Baja de una persona usuaria',
            'air'            => 'Análisis de Impacto Regulatorio',
            'permiso'        => 'Permiso para vendedor ambulante',
        ];
    }

    /** @return array<string, mixed>|null */
    public static function obtener(string $clave): ?array
    {
        $metodo = 'proceso' . str_replace(' ', '', ucwords(str_replace('-', ' ', $clave)));

        return method_exists(static::class, $metodo) ? static::$metodo() : null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Digitalización de un trámite
    // ─────────────────────────────────────────────────────────────

    private static function procesoDigitalizacion(): array
    {
        return [
            'nombre'     => 'Digitalización de un trámite municipal',
            'resolutivo' => ['tipo' => 'registro', 'nombre' => 'Trámite publicado en línea'],
            'inicia'     => 'Una acción de agenda de digitalización se registra sobre un trámite',
            'termina'    => 'El trámite queda disponible para realizarse en línea',

            'participantes' => [
                ['clave' => 'enlace', 'nombre' => 'Enlace de la dependencia', 'tipo' => 'dependencia'],
                ['clave' => 'sujeto', 'nombre' => 'Sujeto obligado',          'tipo' => 'revisora'],
                ['clave' => 'digi',   'nombre' => 'Área de digitalización',   'tipo' => 'tecnica'],
                ['clave' => 'sis',    'nombre' => 'Sistema',                  'tipo' => 'sistema'],
            ],

            'resultados' => [
                ['clave' => 'ok',    'nombre' => 'Trámite digitalizado'],
                ['clave' => 'pausa', 'nombre' => 'Digitalización detenida por requisitos faltantes'],
            ],

            'fases' => [
                [
                    'nombre' => 'Proceso actual',
                    'nota'   => 'Sin el flujo AS-IS aprobado no se puede rediseñar nada: sería trabajar sobre un plano sin firmar.',
                    'actividades' => [
                        ['clave' => 'd1', 'quien' => 'enlace', 'hace' => 'Levanta el proceso actual paso por paso',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'd2', 'quien' => 'sujeto', 'hace' => 'Revisa el proceso levantado',
                         'revisa' => '¿El proceso refleja cómo se atiende hoy?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'inicio_fase']]],
                    ],
                ],
                [
                    'nombre' => 'Reingeniería',
                    'actividades' => [
                        ['clave' => 'd3', 'quien' => 'enlace', 'hace' => 'Redacta el proceso nuevo, simplificado',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'd4', 'quien' => 'sis', 'hace' => 'Genera el diagrama del proceso nuevo',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'd5', 'quien' => 'enlace', 'hace' => 'Firma la reingeniería',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'd6', 'quien' => 'sujeto', 'hace' => 'Firma la reingeniería',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                    ],
                ],
                [
                    'nombre' => 'Puerta de digitalización',
                    'nota'   => 'El sistema comprueba las tres condiciones antes de dejar iniciar. Si falta una, el estado no se mueve.',
                    'actividades' => [
                        ['clave' => 'd7', 'quien' => 'sis', 'hace' => 'Verifica flujo aprobado, reingeniería firmada y diagrama listo',
                         'revisa' => '¿Se cumplen las tres condiciones?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'fin:pausa']]],
                    ],
                ],
                [
                    'nombre' => 'Publicación',
                    'actividades' => [
                        ['clave' => 'd8', 'quien' => 'digi', 'hace' => 'Construye el trámite en línea',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'd9', 'quien' => 'digi', 'hace' => 'Cierra la digitalización y publica',
                         'rutas' => [['cond' => 'siempre', 'a' => 'fin:ok']]],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Alta de una persona usuaria
    // ─────────────────────────────────────────────────────────────

    private static function procesoAltaUsuario(): array
    {
        return [
            'nombre'     => 'Alta de una persona usuaria del sistema',
            'resolutivo' => ['tipo' => 'registro', 'nombre' => 'Cuenta de acceso'],
            'inicia'     => 'Una dependencia solicita acceso para una persona',
            'termina'    => 'La persona puede entrar al sistema con sus permisos',

            'participantes' => [
                ['clave' => 'dep',   'nombre' => 'Dependencia solicitante', 'tipo' => 'dependencia'],
                ['clave' => 'admin', 'nombre' => 'Administración del sistema', 'tipo' => 'juridico'],
                ['clave' => 'sis',   'nombre' => 'Sistema', 'tipo' => 'sistema'],
            ],

            'resultados' => [
                ['clave' => 'alta',     'nombre' => 'Cuenta creada y activa'],
                ['clave' => 'rechazo',  'nombre' => 'Solicitud no procedente'],
            ],

            'fases' => [
                [
                    'nombre' => 'Solicitud',
                    'actividades' => [
                        ['clave' => 'u1', 'quien' => 'dep', 'hace' => 'Solicita el acceso indicando cargo y funciones',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'u2', 'quien' => 'admin', 'hace' => 'Valora si el cargo justifica el acceso',
                         'revisa' => '¿El acceso está justificado?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'fin:rechazo']]],
                    ],
                ],
                [
                    'nombre' => 'Captura',
                    'nota'   => 'La contraseña se guarda cifrada; nunca queda en claro en la base.',
                    'actividades' => [
                        ['clave' => 'u3', 'quien' => 'admin', 'hace' => 'Captura nombre, correo, cargo y dependencia',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'u4', 'quien' => 'sis', 'hace' => 'Comprueba que el correo no esté ya registrado',
                         'revisa' => '¿El correo está libre?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'inicio_fase']]],
                    ],
                ],
                [
                    'nombre' => 'Permisos',
                    'nota'   => 'Los permisos efectivos salen del rol asignado, no de la columna de rol del usuario.',
                    'actividades' => [
                        ['clave' => 'u5', 'quien' => 'admin', 'hace' => 'Asigna el rol que corresponde al cargo',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'u6', 'quien' => 'sis', 'hace' => 'Registra el movimiento en la bitácora del ACL',
                         'rutas' => [['cond' => 'siempre', 'a' => 'fin:alta']]],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Baja de una persona usuaria
    // ─────────────────────────────────────────────────────────────

    private static function procesoBajaUsuario(): array
    {
        return [
            'nombre'     => 'Baja de una persona usuaria del sistema',
            'resolutivo' => null,
            'inicia'     => 'La persona deja el cargo o cambia de dependencia',
            'termina'    => 'La cuenta queda inactiva y su historial se conserva',

            'participantes' => [
                ['clave' => 'dep',   'nombre' => 'Dependencia', 'tipo' => 'dependencia'],
                ['clave' => 'admin', 'nombre' => 'Administración del sistema', 'tipo' => 'juridico'],
                ['clave' => 'sis',   'nombre' => 'Sistema', 'tipo' => 'sistema'],
            ],

            'resultados' => [
                ['clave' => 'baja',     'nombre' => 'Cuenta desactivada y en papelera'],
                ['clave' => 'bloqueo',  'nombre' => 'Baja no permitida'],
            ],

            'fases' => [
                [
                    'nombre' => 'Solicitud de baja',
                    'actividades' => [
                        ['clave' => 'b1', 'quien' => 'dep', 'hace' => 'Notifica que la persona deja el cargo',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'b2', 'quien' => 'admin', 'hace' => 'Localiza la cuenta y confirma la baja',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                    ],
                ],
                [
                    'nombre' => 'Salvaguardas',
                    'nota'   => 'Nadie puede darse de baja a sí mismo: el sistema quedaría sin quien administre.',
                    'actividades' => [
                        ['clave' => 'b3', 'quien' => 'sis', 'hace' => 'Comprueba que quien ejecuta no sea la propia cuenta',
                         'revisa' => '¿Es una cuenta distinta a la de quien ejecuta?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'fin:bloqueo']]],
                    ],
                ],
                [
                    'nombre' => 'Ejecución',
                    'nota'   => 'Se marca inactiva y se manda a papelera, sin borrar la fila: sus trámites, firmas y bitácora tienen que seguir apuntando a alguien.',
                    'actividades' => [
                        ['clave' => 'b4', 'quien' => 'sis', 'hace' => 'Marca la cuenta como inactiva',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'b5', 'quien' => 'sis', 'hace' => 'Envía la cuenta a la papelera conservando su historial',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'b6', 'quien' => 'sis', 'hace' => 'Registra la baja en la bitácora',
                         'rutas' => [['cond' => 'siempre', 'a' => 'fin:baja']]],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Análisis de Impacto Regulatorio
    // ─────────────────────────────────────────────────────────────

    private static function procesoAir(): array
    {
        return [
            'nombre'     => 'Análisis de Impacto Regulatorio de una propuesta',
            'resolutivo' => ['tipo' => 'dictamen', 'nombre' => 'Dictamen del AIR'],
            'inicia'     => 'Una propuesta regulatoria entra a consulta',
            'termina'    => 'La propuesta queda dictaminada o exenta',

            'participantes' => [
                ['clave' => 'enlace',   'nombre' => 'Enlace de la dependencia', 'tipo' => 'dependencia'],
                ['clave' => 'revisora', 'nombre' => 'Autoridad de mejora regulatoria', 'tipo' => 'revisora'],
                ['clave' => 'sis',      'nombre' => 'Sistema', 'tipo' => 'sistema'],
            ],

            'resultados' => [
                ['clave' => 'favorable',    'nombre' => 'Dictamen favorable'],
                ['clave' => 'no_favorable', 'nombre' => 'Dictamen no favorable'],
                ['clave' => 'exenta',       'nombre' => 'Propuesta exenta de AIR'],
            ],

            'fases' => [
                [
                    'nombre' => 'Determinación',
                    'nota'   => 'El artículo 36 de la LNETB permite eximir del análisis en ocho supuestos.',
                    'actividades' => [
                        ['clave' => 'r1', 'quien' => 'enlace', 'hace' => 'Determina si la propuesta requiere AIR',
                         'revisa' => '¿Solicita exención del artículo 36?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'r2'],
                                      ['cond' => 'incorrecto', 'a' => 'r5']]],
                        ['clave' => 'r2', 'quien' => 'enlace', 'hace' => 'Solicita la exención indicando fracción y justificación',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'r3', 'quien' => 'revisora', 'hace' => 'Resuelve la solicitud de exención',
                         'revisa' => '¿Procede la exención?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'fin:exenta'],
                                      ['cond' => 'incorrecto', 'a' => 'r5']]],
                    ],
                ],
                [
                    'nombre' => 'Elaboración del análisis',
                    'nota'   => 'Si la exención se rechaza, la propuesta vuelve al camino largo y queda marcada como que requiere AIR.',
                    'actividades' => [
                        ['clave' => 'r5', 'quien' => 'enlace', 'hace' => 'Redacta problemática, objetivos y beneficios',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'r6', 'quien' => 'enlace', 'hace' => 'Envía el análisis a dictamen',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                    ],
                ],
                [
                    'nombre' => 'Dictamen',
                    'nota'   => 'Quien redacta no dictamina: es la separación que da valor al dictamen.',
                    'actividades' => [
                        ['clave' => 'r7', 'quien' => 'revisora', 'hace' => 'Revisa el análisis presentado',
                         'revisa' => '¿El análisis sustenta la propuesta?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'fin:no_favorable']]],
                        ['clave' => 'r8', 'quien' => 'sis', 'hace' => 'Marca la propuesta como dictaminada',
                         'rutas' => [['cond' => 'siempre', 'a' => 'fin:favorable']]],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Permiso para vendedor ambulante (trámite ciudadano)
    // ─────────────────────────────────────────────────────────────

    private static function procesoPermiso(): array
    {
        return [
            'nombre'     => 'Permiso para vendedor ambulante',
            'resolutivo' => ['tipo' => 'permiso', 'nombre' => 'Permiso para vendedor ambulante'],
            'inicia'     => 'La persona solicita el permiso en línea',
            'termina'    => 'Se entrega el permiso firmado con código QR',

            'participantes' => [
                ['clave' => 'sol',  'nombre' => 'Persona solicitante',   'tipo' => 'solicitante'],
                ['clave' => 'sis',  'nombre' => 'Sistema',               'tipo' => 'sistema'],
                ['clave' => 'com',  'nombre' => 'Dirección de Comercio', 'tipo' => 'revisora'],
                ['clave' => 'pc',   'nombre' => 'Protección Civil',      'tipo' => 'tecnica'],
                ['clave' => 'tes',  'nombre' => 'Tesorería Municipal',   'tipo' => 'tesoreria'],
            ],

            'resultados' => [
                ['clave' => 'emitido', 'nombre' => 'Permiso emitido'],
                ['clave' => 'negado',  'nombre' => 'Solicitud no procedente'],
            ],

            'fases' => [
                [
                    'nombre' => 'Captura y expediente',
                    'nota'   => 'Se precargan identidad, domicilio y contacto del expediente digital.',
                    'actividades' => [
                        ['clave' => 'p1', 'quien' => 'sol', 'hace' => 'Inicia la solicitud y captura sus datos',
                         'rutas' => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'p2', 'quien' => 'sis', 'hace' => 'Valida la integridad de la solicitud',
                         'revisa' => '¿La solicitud está completa?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'inicio_fase']]],
                    ],
                ],
                [
                    'nombre' => 'Cálculo y pago',
                    'actividades' => [
                        ['clave' => 'p3', 'quien' => 'sol', 'hace' => 'Realiza el pago de los derechos',
                         'pago'   => ['calcula_monto', 'genera_referencia', 'realiza_pago'],
                         'estado' => 'Pendiente de pago',
                         'rutas'  => [['cond' => 'siempre', 'a' => 'siguiente']]],
                        ['clave' => 'p4', 'quien' => 'tes', 'hace' => 'Valida el pago recibido',
                         'revisa' => '¿El pago está completo y validado?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'siguiente'],
                                      ['cond' => 'incorrecto', 'a' => 'inicio_fase']]],
                    ],
                ],
                [
                    'nombre' => 'Revisión institucional',
                    'actividades' => [
                        ['clave' => 'p5', 'quien' => 'sis', 'hace' => 'Turna el expediente a las áreas que deben revisarlo',
                         'nota'  => ['titulo' => 'Revisión simultánea',
                                     'texto'  => 'La revisión de Protección Civil no detiene la de Comercio.'],
                         'rutas' => [['cond' => 'siempre', 'a' => 'p6'],
                                     ['cond' => 'siempre', 'a' => 'p7']]],
                        ['clave' => 'p6', 'quien' => 'pc', 'hace' => 'Revisa las medidas de seguridad',
                         'rutas' => [['cond' => 'siempre', 'a' => 'p7']]],
                        ['clave' => 'p7', 'quien' => 'com', 'hace' => 'Revisa el expediente administrativo',
                         'revisa' => '¿La solicitud es procedente?',
                         'rutas'  => [['cond' => 'correcto', 'a' => 'fin:emitido'],
                                      ['cond' => 'incorrecto', 'a' => 'fin:negado']]],
                    ],
                ],
            ],
        ];
    }
}
