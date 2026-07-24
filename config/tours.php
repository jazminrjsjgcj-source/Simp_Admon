<?php

/**
 * GUIONES DE LOS TOURS GUIADOS
 * ═════════════════════════════
 *
 * Cada entrada es un recorrido de burbujas sobre una pantalla real del sistema.
 * El tour NO llena campos ni guarda nada: señala, explica y se aparta. Quien lo
 * sigue termina sabiendo qué le pide cada paso, sin haber creado ningún registro.
 *
 * ── POR QUÉ ESTÁ EN config/ Y NO EN EL JAVASCRIPT ──
 *
 * Los textos los va a corregir alguien del Ayuntamiento después de dar una
 * capacitación y descubrir qué es lo que la gente no entiende. Esa persona no
 * tiene por qué abrir un archivo .js ni saber de comillas escapadas. Aquí edita
 * un texto entre comillas, guarda, y ya está: no hay que recompilar nada porque
 * el proyecto no usa bundler para estos scripts.
 *
 * ── ESTRUCTURA ──
 *
 *   'nombre.del.tour' => [
 *       'titulo' => Lo que se ve en el botón "¿Cómo funciona esto?"
 *       'roles'  => Qué roles lo ven. [] = todos.
 *       'pasos'  => Lista de burbujas, en orden.
 *   ]
 *
 * Y cada paso:
 *
 *   'ancla'  => Selector CSS del elemento a resaltar. Si no existe en la página,
 *               el paso SE SALTA solo (ver tour.js). Eso permite que un guion
 *               sirva para pantallas que muestran campos distintos según el rol
 *               o según lo que el usuario haya elegido antes.
 *   'titulo' => Encabezado de la burbuja. Corto.
 *   'texto'  => El cuerpo. Admite HTML simple (<strong>, <em>, <br>).
 *   'lado'   => Dónde se coloca: 'top', 'bottom', 'left', 'right'. Por defecto
 *               'bottom'. Es una preferencia, no una orden: si no cabe, la
 *               librería lo recoloca.
 *   'antes'  => (opcional) Selector de un elemento al que hay que hacer clic
 *               ANTES de mostrar la burbuja. Es lo que permite que el tour avance
 *               por los pasos del asistente sin que el usuario tenga que pulsar
 *               "Siguiente" a mano.
 *
 * ── LA REGLA DE LAS ANCLAS ──
 *
 * Usar SIEMPRE id o data-* que ya existan en la vista. Nunca clases de Tailwind
 * ni rutas del tipo `div > div:nth-child(3)`: eso se rompe en cuanto alguien
 * reordena el HTML, y se rompe EN SILENCIO, que es lo peor. Si un ancla necesaria
 * no existe, se le pone un id a la vista y se documenta aquí por qué.
 */

return [

    /**
     * ALTA DE TRÁMITE O SERVICIO — 7 pasos.
     *
     * Es el recorrido más urgente: el enlace de dependencia entra aquí sin haber
     * visto nunca la metodología ATDT, y el paso 3 (costos burocráticos) es donde
     * se atasca todo el mundo, porque pide datos que nadie tiene a la mano.
     *
     * Las anclas salen todas de resources/views/screens/tramites/create.blade.php,
     * que ya venía con ids estables (#tramiteStepper, #btnSig, #numAreas...).
     */
    'tramites.create' => [

        'titulo' => 'Cómo registrar un trámite',

        // Quien da de alta trámites. La revisora los aprueba pero no los captura,
        // así que este recorrido no le sirve: tendrá el suyo.
        'roles' => ['enlace', 'sujeto', 'admin'],

        'pasos' => [

            [
                'ancla'  => '#tramiteStepper',
                'titulo' => 'Son siete pasos, no un formulario gigante',
                'texto'  => 'Arriba siempre ve en cuál va y cuántos faltan. '
                          . '<strong>Puede salirse cuando quiera</strong>: el botón '
                          . '"Guardar borrador" conserva lo capturado y le permite '
                          . 'volver otro día.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '#pasoIdentificacion',
                'titulo' => 'Paso 1 · ¿Trámite o servicio?',
                'texto'  => 'Un <strong>trámite</strong> es una gestión que la persona '
                          . 'está obligada a hacer. Un <strong>servicio</strong> es algo '
                          . 'que el municipio ofrece y la persona decide si lo usa.<br><br>'
                          . 'Lo que elija aquí cambia el texto del resto del formulario, '
                          . 'así que conviene acertar de entrada.',
                'lado'   => 'top',
            ],

            [
                'ancla'  => '#btnSig',
                'titulo' => 'Este botón avanza',
                'texto'  => 'Cada paso valida lo suyo antes de dejarle pasar. Si algo '
                          . 'falta, se lo marca en rojo y no le hace perder lo escrito.',
                'lado'   => 'top',
            ],

            [
                'ancla'  => '[data-panel="2"]',
                'titulo' => 'Paso 2 · Para qué existe el trámite',
                'texto'  => 'Objetivo, a quién va dirigido, sector económico y plazos. '
                          . 'Escriba el objetivo <strong>como se lo explicaría a la '
                          . 'persona que hace fila</strong>, no como viene en el '
                          . 'reglamento.',
                'lado'   => 'top',
                'antes'  => '#btnSig',
            ],

            [
                'ancla'  => '[data-panel="3"]',
                'titulo' => 'Paso 3 · El costo burocrático',
                'texto'  => 'Este es el paso largo, y el que de verdad importa: mide '
                          . '<strong>cuánto le cuesta a la persona</strong> hacer el '
                          . 'trámite, no cuánto cobra el municipio.<br><br>'
                          . 'Es la metodología ATDT. Los tiempos son estimados: no '
                          . 'necesita cronómetro, necesita un número honesto.',
                'lado'   => 'top',
                'antes'  => '#btnSig',
            ],

            [
                'ancla'  => '#numAreas',
                'titulo' => 'Cuántas áreas tocan el expediente',
                'texto'  => 'Cuente todas las que firman, sellan o revisan. Si son más '
                          . 'de una, se le abrirá un campo para nombrarlas.<br><br>'
                          . 'Este número es de los que más pesan en el costo: cada área '
                          . 'de más son días de espera para la persona.',
                'lado'   => 'right',
            ],

            [
                'ancla'  => '[data-panel="4"]',
                'titulo' => 'Paso 4 · Requisitos',
                'texto'  => 'Un renglón por documento. Sea específico: '
                          . '<em>"Copia del INE vigente"</em> se entiende; '
                          . '<em>"identificación"</em> hace que la persona vuelva '
                          . 'dos veces.',
                'lado'   => 'top',
                'antes'  => '#btnSig',
            ],

            [
                'ancla'  => '[data-panel="5"]',
                'titulo' => 'Paso 5 · De dónde sale el trámite',
                'texto'  => 'La norma que <strong>crea u obliga</strong> el trámite. '
                          . 'Una sola, del catálogo o escrita a mano.<br><br>'
                          . 'Si no encuentra el fundamento, es un dato en sí mismo: '
                          . 'puede que el trámite no tenga base legal y sea candidato '
                          . 'a eliminarse.',
                'lado'   => 'top',
                'antes'  => '#btnSig',
            ],

            [
                'ancla'  => '[data-panel="6"]',
                'titulo' => 'Paso 6 · Lo que verá la ciudadanía',
                'texto'  => 'Esta ficha se publica. Es lo único de todo el formulario '
                          . 'que va a leer una persona de la calle, así que aquí se '
                          . 'escribe sin siglas y sin artículos.',
                'lado'   => 'top',
                'antes'  => '#btnSig',
            ],

            [
                'ancla'  => '#btnBorrador',
                'titulo' => 'Guardar sin terminar',
                'texto'  => 'Deja el trámite en borrador y nadie lo revisa todavía. '
                          . 'Úselo sin miedo: es preferible a perder media hora de '
                          . 'captura.',
                'lado'   => 'top',
                'antes'  => '#btnSig',
            ],

            [
                'ancla'  => '#btnGuardar',
                'titulo' => 'Y aquí termina el recorrido',
                'texto'  => 'Este botón enviaría el trámite a revisión.<br><br>'
                          . '<strong>El tutorial no lo va a pulsar</strong>: no se ha '
                          . 'creado ningún registro y no ha ensuciado nada. Cuando '
                          . 'quiera dar de alta un trámite de verdad, vuelva a esta '
                          . 'pantalla y recorra los pasos usted.',
                'lado'   => 'top',
            ],
        ],
    ],

    /**
     * ALTA DE ACCIÓN DE AGENDA SyD — 6 pasos.
     *
     * El enlace llega aquí después de registrar trámites, y la confusión típica es
     * creer que la agenda es "otro formulario del trámite". No lo es: es un
     * COMPROMISO con fecha, que la autoridad va a revisar y que hay que cumplir.
     *
     * Anclas: resources/views/screens/agenda/create.blade.php (#agendaWizard,
     * #modoTramite, #buscadorTramite, #alcanceCampo, #bloqueSimplificacion...).
     *
     * OJO con 'antes': este wizard NO tiene ids en sus botones de avance, usa
     * onclick="wzNav(1)". El selector apunta al botón del panel activo, que es
     * estable mientras nadie renombre esa función. Si algún día se le ponen ids,
     * conviene cambiarlo aquí.
     */
    'agenda.create' => [

        'titulo' => 'Cómo registrar una acción de agenda',
        'roles'  => ['enlace', 'sujeto', 'admin'],

        'pasos' => [

            [
                'ancla'  => '#agendaWizard',
                'titulo' => 'Esto no es otro formulario del trámite',
                'texto'  => 'Una acción de agenda es un <strong>compromiso con fecha</strong>: '
                          . 'qué va a simplificar o digitalizar, para cuándo y cómo se '
                          . 'medirá.<br><br>La autoridad estatal lo revisa y le da '
                          . 'seguimiento. Lo que escriba aquí sale en un documento oficial.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '[data-panel="1"]',
                'titulo' => 'Paso 1 · ¿Sobre qué trámite?',
                'texto'  => 'Puede engancharla a un trámite <strong>ya registrado</strong> '
                          . '—lo normal, y así hereda sus costos— o capturar uno nuevo '
                          . 'sobre la marcha.<br><br>Engancharla a uno existente le ahorra '
                          . 'todo el paso 2.',
                'lado'   => 'top',
            ],

            [
                'ancla'  => '[data-panel="3"]',
                'titulo' => 'Paso 3 · ¿Simplificar, digitalizar o las dos?',
                'texto'  => 'Esta elección decide <strong>en qué agenda oficial aparece</strong> '
                          . 'la acción. Son dos documentos distintos que se entregan por '
                          . 'separado.<br><br>Si marca "ambas", la acción sale en los dos, '
                          . 'que es lo correcto cuando el mismo trámite se va a simplificar '
                          . 'y a poner en línea.',
                'lado'   => 'top',
                'antes'  => '.wz-panel.activo [onclick="wzNav(1)"]',
            ],

            [
                'ancla'  => '[data-panel="4"]',
                'titulo' => 'Paso 4 · Qué va a hacer, exactamente',
                'texto'  => 'Del catálogo oficial. Y luego la meta y el indicador.<br><br>'
                          . 'La meta se escribe en <strong>resultado observable</strong>: '
                          . '<em>"trámite disponible en línea"</em>, no <em>"mejorar el '
                          . 'trámite"</em>. Si nadie puede decir si se cumplió, no es una meta.',
                'lado'   => 'top',
                'antes'  => '.wz-panel.activo [onclick="wzNav(1)"]',
            ],

            [
                'ancla'  => '[data-panel="6"]',
                'titulo' => 'Paso 6 · La fecha compromiso',
                'texto'  => 'Esta fecha es la que cuenta. Si llega el cierre del semestre y '
                          . 'la acción no está implementada, <strong>se arrastra al '
                          . 'siguiente con prioridad</strong> y hay que justificar por qué no '
                          . 'se cumplió (art. 22 de la Ley).<br><br>Ponga una fecha que pueda '
                          . 'sostener, no la más ambiciosa.',
                'lado'   => 'top',
                'antes'  => '.wz-panel.activo [onclick="wzNav(1)"]',
            ],

            [
                'ancla'  => '#accionCampo',
                'titulo' => 'Aquí termina el recorrido',
                'texto'  => 'El tutorial <strong>no ha guardado nada</strong>. Cuando registre '
                          . 'una acción de verdad, puede dejarla en borrador y volver: solo '
                          . 'cuando la envíe a revisión empieza el flujo de firmas.',
                'lado'   => 'top',
            ],
        ],
    ],

    /**
     * SEGUIMIENTO DE UNA ACCIÓN — versión del ENLACE.
     *
     * Aquí es donde se demuestra que la acción se cumplió. Es el punto más
     * incomprendido del sistema: la gente cree que firmar la acción ya es
     * cumplirla, y no lo es.
     *
     * Anclas: resources/views/partials/hitos-agenda.blade.php. Son CLASES, no ids,
     * porque ese partial no tiene ninguno. Es el guion más frágil de los seis: si
     * alguien reescribe ese partial, hay que revisarlo.
     */
    'agenda.show' => [

        'titulo' => 'Cómo demostrar que la acción se cumplió',
        'roles'  => ['enlace', 'sujeto'],

        'pasos' => [

            [
                'ancla'  => '.hitos-avance',
                'titulo' => 'Firmar no es cumplir',
                'texto'  => 'Que la acción esté firmada significa que usted se '
                          . '<strong>comprometió</strong>. El cumplimiento se demuestra '
                          . 'aquí abajo, hito por hito, con evidencia.<br><br>'
                          . 'Una acción firmada y sin hitos aprobados cuenta como '
                          . '<strong>no realizada</strong>, y se arrastra al semestre '
                          . 'siguiente.',
                'lado'   => 'top',
            ],

            [
                'ancla'  => '.hitos-barra',
                'titulo' => 'El avance sale de los hitos aprobados',
                'texto'  => 'No de los que usted marcó: de los que la revisora dio por '
                          . 'buenos. Marcar sin evidencia no mueve esta barra.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '.hito-form-evidencia',
                'titulo' => 'Suba el documento que lo pruebe',
                'texto'  => 'Un acuerdo publicado, una captura del trámite ya en línea, un '
                          . 'oficio. Lo que un tercero pueda mirar y decir "sí, esto pasó".'
                          . '<br><br>Al subirlo, el hito queda <strong>pendiente de visto '
                          . 'bueno</strong> y le llega a la revisora.',
                'lado'   => 'top',
            ],
        ],
    ],

    /**
     * SEGUIMIENTO DE UNA ACCIÓN — versión de la REVISORA.
     *
     * Misma pantalla, trabajo opuesto: no sube evidencia, la juzga. El partial de
     * tour elige esta variante por el sufijo '@revisora'.
     */
    'agenda.show@revisora' => [

        'titulo' => 'Cómo revisar evidencia de cumplimiento',
        'roles'  => ['revisora', 'admin'],

        'pasos' => [

            [
                'ancla'  => '.hitos-avance',
                'titulo' => 'Su visto bueno es lo que cuenta',
                'texto'  => 'El enlace marca y sube; usted decide. Un hito no cuenta como '
                          . 'cumplido hasta que usted lo aprueba, y ese conteo es el que '
                          . 'sale en el informe semestral.',
                'lado'   => 'top',
            ],

            [
                'ancla'  => '.hito-vistobueno',
                'titulo' => 'Aprobar o devolver',
                'texto'  => 'Si la evidencia no demuestra lo que dice la meta, devuélvala con '
                          . 'motivo. El hito vuelve al enlace con su explicación, no '
                          . 'desaparece.<br><br>Un motivo concreto ahorra tres vueltas: '
                          . '<em>"falta la fecha de publicación"</em> sirve; '
                          . '<em>"insuficiente"</em> no.',
                'lado'   => 'top',
            ],
        ],
    ],

    /**
     * BANDEJA DE FIRMAS.
     *
     * Corta y con un solo mensaje, porque la pantalla es una tabla y no da para
     * más: lo que hay que entender es qué significa firmar, no dónde hacer clic.
     */
    'firmas.index' => [

        'titulo' => 'Qué significa firmar aquí',
        'roles'  => [],   // todos los roles firman algo

        'pasos' => [

            [
                'ancla'  => '.data-table',
                'titulo' => 'Lo que espera su firma',
                'texto'  => 'Cada renglón es un documento detenido hasta que alguien lo '
                          . 'firme. Mientras esté aquí, <strong>no avanza</strong>.',
                'lado'   => 'top',
            ],

            [
                'ancla'  => '.data-table',
                'titulo' => 'La firma es suya y queda registrada',
                'texto'  => 'Se guarda con su nombre, la fecha y un folio verificable. No es '
                          . 'un "visto" informal: es el acto que da por bueno el contenido.'
                          . '<br><br>Léalo antes. Si algo no cuadra, no firme y dígalo: '
                          . 'devolver es más barato que corregir un documento ya entregado.',
                'lado'   => 'top',
            ],
        ],
    ],

    /**
     * BUSCADOR DE REGULACIONES.
     *
     * El único recorrido pensado para alguien que puede no ser funcionario. El
     * mensaje central es que NO hace falta adivinar las palabras de la ley, porque
     * es justo lo que la gente cree y lo que hace que se rinda a la segunda búsqueda.
     */
    'buscar' => [

        'titulo' => 'Cómo buscar en las regulaciones',
        'roles'  => [],

        'pasos' => [

            [
                'ancla'  => '.buscar-input',
                'titulo' => 'Pregunte como hablaría',
                'texto'  => 'No hace falta adivinar las palabras de la ley. Escriba '
                          . '<em>"cuánto cuesta el permiso para ambulantes"</em> y el '
                          . 'buscador traduce solo: sabe que donde usted dice '
                          . '<strong>permiso</strong>, la ley dice <strong>derecho</strong>, '
                          . 'licencia, autorización o cuota.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '#buscarRegInput',
                'titulo' => 'Acotar a una sola regulación',
                'texto'  => 'Si ya sabe que la respuesta está en el Bando o en la Ley de '
                          . 'Hacienda, fíltrelo aquí y se quita el ruido de las demás.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '#buscarTiposContainer',
                'titulo' => 'Filtrar por tipo de disposición',
                'texto'  => 'Artículos, fracciones, incisos. Las cifras de cobro suelen '
                          . 'estar en <strong>incisos</strong>, no en el artículo, así que '
                          . 'si busca un monto no los descarte.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '#buscarAyuda',
                'titulo' => 'Si no encuentra nada',
                'texto'  => 'Pruebe con menos palabras. El buscador exige que el artículo '
                          . 'hable de <strong>todas</strong> las que escriba, así que cada '
                          . 'palabra de más estrecha el resultado.<br><br>'
                          . 'Y recuerde: solo busca dentro de las regulaciones cargadas. Si '
                          . 'la norma no está subida, no aparece.',
                'lado'   => 'top',
            ],
        ],
    ],

    /**
     * BIBLIOTECA DE DIGITALIZACIÓN.
     *
     * El rol digitalizador es el que menos documentación tiene y el que más tarde
     * se incorporó. El recorrido explica de dónde salen los trámites que ve, que
     * es lo que nadie le cuenta: no los elige él, se los manda la agenda.
     */
    'digitalizacion.index' => [

        'titulo' => 'Cómo funciona la biblioteca',
        'roles'  => ['digitalizador', 'admin'],

        'pasos' => [

            [
                'ancla'  => 'h2',
                'titulo' => 'Estos trámites no los eligió usted',
                'texto'  => 'Llegan aquí <strong>solos</strong>: cuando una acción de agenda '
                          . 'de tipo digitalización se firma, el trámite se vincula '
                          . 'automáticamente a esta biblioteca.<br><br>'
                          . 'Es decir, cada renglón es un compromiso que alguien ya adquirió '
                          . 'ante la autoridad estatal, con una fecha detrás.',
                'lado'   => 'bottom',
            ],

            [
                'ancla'  => '.card',
                'titulo' => 'De aquí sale la evidencia',
                'texto'  => 'Cuando termine de digitalizar un trámite, lo que registre aquí '
                          . 'es lo que el enlace va a subir como evidencia de su hito. Su '
                          . 'trabajo y el suyo son el mismo expediente visto desde dos '
                          . 'lados.',
                'lado'   => 'top',
            ],
        ],
    ],
];
