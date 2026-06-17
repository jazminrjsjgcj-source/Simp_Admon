<?php

/**
 * Hitos de avance por tipo de acción de simplificación y digitalización.
 *
 * Cada tipo de acción tiene una lista ordenada de hitos. El primero SIEMPRE es
 * "Diagnóstico", que se marca como completado automáticamente al registrar la
 * acción (el diagnóstico ya quedó hecho al llenar la agenda). Los demás hitos
 * los marca el enlace conforme avanza, uno a uno y en orden.
 *
 * Cada hito tiene:
 *   'clave'  → identificador estable (no cambiar; se guarda en la BD)
 *   'nombre' → texto visible en la lista
 *   'ayuda'  → explicación del paso (tooltip)
 *
 * Las claves de tipo de acción coinciden con la nomenclatura oficial de los
 * artículos 23 (simplificación) y 24 (digitalización) de los Lineamientos.
 *
 * Si un tipo de acción no está aquí, se usa la lista 'generico'.
 */
return [

    // Hito inicial común a todas las acciones. Se marca solo al registrar.
    'diagnostico' => [
        'clave'  => 'diagnostico',
        'nombre' => 'Diagnóstico',
        'ayuda'  => 'El análisis del trámite (costos, tiempos, áreas y procesos) ya quedó registrado al capturar la acción en la agenda. Este hito se marca automáticamente.',
    ],

    // ─── Acciones de SIMPLIFICACIÓN (Art. 23 Lineamientos) ──────────────────
    'simplificacion' => [

        'reduccion_requisitos' => [
            ['clave' => 'propuesta',  'nombre' => 'Propuesta de modificación', 'ayuda' => 'Redactar la propuesta concreta de qué requisitos se reducen y cómo.'],
            ['clave' => 'validacion', 'nombre' => 'Validación jurídica',         'ayuda' => 'El área jurídica revisa que la reducción sea legalmente viable.'],
            ['clave' => 'publicacion','nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el acuerdo o instrumento que formaliza la reducción.'],
            ['clave' => 'portal',     'nombre' => 'Actualización en portal',      'ayuda' => 'Reflejar el cambio en el Portal Ciudadano Único de Trámites y Servicios.'],
        ],

        'eliminacion_requisitos' => [
            ['clave' => 'propuesta',  'nombre' => 'Propuesta de eliminación',     'ayuda' => 'Redactar qué requisitos se eliminan y la justificación.'],
            ['clave' => 'validacion', 'nombre' => 'Validación jurídica',          'ayuda' => 'El área jurídica confirma que los requisitos pueden eliminarse.'],
            ['clave' => 'publicacion','nombre' => 'Publicación en medio oficial',  'ayuda' => 'Publicar el instrumento que formaliza la eliminación.'],
            ['clave' => 'portal',     'nombre' => 'Actualización en portal',       'ayuda' => 'Reflejar el cambio en el portal ciudadano.'],
        ],

        'reduccion_plazos' => [
            ['clave' => 'propuesta',   'nombre' => 'Propuesta de nuevo plazo',    'ayuda' => 'Definir el nuevo plazo de resolución o respuesta.'],
            ['clave' => 'validacion',  'nombre' => 'Validación jurídica',         'ayuda' => 'Revisar que el nuevo plazo cumpla la normativa aplicable.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el acuerdo con el plazo reducido.'],
            ['clave' => 'verificacion','nombre' => 'Verificación operativa',       'ayuda' => 'Confirmar que el área cumple el nuevo plazo en la práctica.'],
        ],

        'eliminacion_tramite' => [
            ['clave' => 'propuesta',   'nombre' => 'Propuesta de eliminación',    'ayuda' => 'Justificar por qué el trámite o servicio puede eliminarse.'],
            ['clave' => 'consulta',    'nombre' => 'Consulta interna',            'ayuda' => 'Validar con las áreas involucradas que nadie depende del trámite.'],
            ['clave' => 'validacion',  'nombre' => 'Validación jurídica',         'ayuda' => 'El área jurídica confirma la viabilidad legal de eliminarlo.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el instrumento que elimina el trámite.'],
        ],

        'fusion_tramites' => [
            ['clave' => 'diseno',      'nombre' => 'Diseño del trámite fusionado','ayuda' => 'Diseñar cómo quedan los trámites o modalidades unificados.'],
            ['clave' => 'validacion',  'nombre' => 'Validación jurídica',         'ayuda' => 'Revisar la viabilidad legal de la fusión.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el acuerdo de fusión.'],
            ['clave' => 'portal',      'nombre' => 'Actualización en portal',      'ayuda' => 'Reflejar el trámite fusionado en el portal ciudadano.'],
        ],

        'conversion_aviso' => [
            ['clave' => 'propuesta',   'nombre' => 'Propuesta de conversión',     'ayuda' => 'Definir cómo el trámite pasa a ser un aviso o manifestación.'],
            ['clave' => 'validacion',  'nombre' => 'Validación jurídica',         'ayuda' => 'Confirmar que el trámite puede convertirse en aviso.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el instrumento que formaliza la conversión.'],
            ['clave' => 'verificacion','nombre' => 'Verificación operativa',       'ayuda' => 'Confirmar que el aviso opera correctamente.'],
        ],

        'ampliacion_vigencia' => [
            ['clave' => 'propuesta',   'nombre' => 'Propuesta de vigencia',       'ayuda' => 'Definir la nueva vigencia ampliada de la resolución.'],
            ['clave' => 'validacion',  'nombre' => 'Validación jurídica',         'ayuda' => 'Revisar que la ampliación cumpla la normativa.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el acuerdo con la vigencia ampliada.'],
        ],

        'supresion_obligaciones' => [
            ['clave' => 'propuesta',   'nombre' => 'Propuesta de supresión',      'ayuda' => 'Identificar y justificar las obligaciones a suprimir.'],
            ['clave' => 'validacion',  'nombre' => 'Validación jurídica',         'ayuda' => 'Confirmar que pueden suprimirse las obligaciones.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación en medio oficial', 'ayuda' => 'Publicar el instrumento que suprime las obligaciones.'],
        ],

        'accesibilidad_universal' => [
            ['clave' => 'diseno',       'nombre' => 'Diseño de adecuaciones',     'ayuda' => 'Diseñar las acciones afirmativas de accesibilidad universal.'],
            ['clave' => 'implementacion','nombre' => 'Implementación',            'ayuda' => 'Aplicar las adecuaciones de accesibilidad.'],
            ['clave' => 'verificacion', 'nombre' => 'Verificación',               'ayuda' => 'Confirmar que las adecuaciones funcionan para las personas usuarias.'],
        ],
    ],

    // ─── Acciones de DIGITALIZACIÓN (Art. 24 Lineamientos) ──────────────────
    'digitalizacion' => [

        'solucion_tecnologica' => [
            ['clave' => 'diseno',      'nombre' => 'Diseño de solución',         'ayuda' => 'Diseñar la solución tecnológica de punta a punta o híbrida.'],
            ['clave' => 'desarrollo',  'nombre' => 'Desarrollo',                 'ayuda' => 'Construir o configurar la solución tecnológica.'],
            ['clave' => 'pruebas',     'nombre' => 'Pruebas',                    'ayuda' => 'Probar la solución antes de ponerla en producción.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación',                'ayuda' => 'Poner la solución a disposición de las personas usuarias.'],
            ['clave' => 'verificacion','nombre' => 'Verificación operativa',      'ayuda' => 'Confirmar que la solución opera correctamente.'],
        ],

        'ventanilla_digital' => [
            ['clave' => 'coordinacion','nombre' => 'Coordinación interinstitucional', 'ayuda' => 'Coordinar con las dependencias que participan en la ventanilla.'],
            ['clave' => 'diseno',      'nombre' => 'Diseño',                     'ayuda' => 'Diseñar la ventanilla digital única.'],
            ['clave' => 'desarrollo',  'nombre' => 'Desarrollo',                 'ayuda' => 'Construir la ventanilla y sus integraciones.'],
            ['clave' => 'pruebas',     'nombre' => 'Pruebas',                    'ayuda' => 'Probar la ventanilla con todas las dependencias.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación',                'ayuda' => 'Poner la ventanilla a disposición del público.'],
        ],

        'autenticacion_digital' => [
            ['clave' => 'integracion', 'nombre' => 'Integración técnica',        'ayuda' => 'Integrar Llave MX o Firma Electrónica Avanzada al trámite.'],
            ['clave' => 'pruebas',     'nombre' => 'Pruebas',                    'ayuda' => 'Probar la autenticación con casos reales.'],
            ['clave' => 'publicacion', 'nombre' => 'Publicación',                'ayuda' => 'Habilitar la autenticación digital para las personas usuarias.'],
        ],

        'automatizacion' => [
            ['clave' => 'diseno',        'nombre' => 'Diseño del flujo automatizado', 'ayuda' => 'Diseñar qué procesos se automatizan y cómo.'],
            ['clave' => 'desarrollo',    'nombre' => 'Desarrollo',                'ayuda' => 'Construir la automatización.'],
            ['clave' => 'pruebas',       'nombre' => 'Pruebas',                   'ayuda' => 'Probar la automatización antes de aplicarla.'],
            ['clave' => 'implementacion','nombre' => 'Implementación',            'ayuda' => 'Poner en marcha la automatización.'],
        ],
    ],

    // Lista genérica para "Otra" o cuando no hay un tipo específico definido.
    'generico' => [
        ['clave' => 'diseno',        'nombre' => 'Diseño',         'ayuda' => 'Diseñar la acción de mejora a implementar.'],
        ['clave' => 'desarrollo',    'nombre' => 'Desarrollo',     'ayuda' => 'Construir o preparar lo necesario para la acción.'],
        ['clave' => 'implementacion','nombre' => 'Implementación', 'ayuda' => 'Poner en marcha la acción.'],
        ['clave' => 'verificacion',  'nombre' => 'Verificación',   'ayuda' => 'Confirmar que la acción cumplió su objetivo.'],
    ],

];
