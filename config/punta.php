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

    /*
    | Servicios externos del editor de diagramas.
    |
    | Están aquí y no incrustados en la vista porque son dependencias de red: si el
    | servidor del Ayuntamiento no tiene salida a internet, la pantalla de diagramas
    | deja de funcionar y hay que apuntar a una copia interna. Cambiarlo debe ser
    | editar una variable de entorno, no buscar una URL dentro de una plantilla.
    */
    /*
    | Catálogos del flujo de procesos (fases, participantes, actividades).
    |
    | Están en config y no escritos en el formulario porque son listas que el
    | Ayuntamiento va a querer ajustar —añadir un tipo de resolutivo, renombrar un
    | área— sin que eso sea un cambio de código. Las claves son lo que se guarda en
    | la base; el texto de la derecha es solo lo que ve el usuario, así que renombrarlo
    | no invalida los flujos ya capturados.
    */
    'flujo' => [

        // Qué produce el proceso cuando produce un documento.
        'tipos_resolutivo' => [
            'permiso'      => 'Permiso',
            'licencia'     => 'Licencia',
            'autorizacion' => 'Autorización',
            'constancia'   => 'Constancia',
            'dictamen'     => 'Dictamen',
            'resolucion'   => 'Resolución',
            'certificado'  => 'Certificado',
            'registro'     => 'Registro',
            'oficio'       => 'Oficio',
            'acta'         => 'Acta',
            'comprobante'  => 'Comprobante',
            'otro'         => 'Otro',
        ],

        // Quién puede intervenir. El color acompaña al tipo para que el diagrama
        // distinga los carriles sin que nadie elija colores a mano.
        'tipos_participante' => [
            'solicitante' => ['label' => 'Persona solicitante', 'color' => '#0ea5e9'],
            'sistema'     => ['label' => 'Sistema',             'color' => '#64748b'],
            'dependencia' => ['label' => 'Dependencia responsable', 'color' => '#750038'],
            'revisora'    => ['label' => 'Área revisora',        'color' => '#a16207'],
            'tecnica'     => ['label' => 'Área técnica',         'color' => '#0f766e'],
            'tesoreria'   => ['label' => 'Tesorería',            'color' => '#16a34a'],
            'juridico'    => ['label' => 'Jurídico',             'color' => '#7c3aed'],
            'otra'        => ['label' => 'Otra área',            'color' => '#475569'],
        ],

        // A dónde puede seguir el proceso tras una actividad.
        'destinos_ruta' => [
            'siguiente'      => 'Continúa a la siguiente actividad',
            'actividad'      => 'Va a otra actividad',
            'inicio_fase'    => 'Regresa al inicio de la fase',
            'inicio_proceso' => 'Regresa al inicio del proceso',
            'fin'            => 'Termina el proceso',
        ],

        // Qué puede pasar con el pago dentro de una actividad.
        'acciones_pago' => [
            'calcula_monto'    => 'Se calcula el monto',
            'genera_referencia'=> 'Se genera la referencia de pago',
            'realiza_pago'     => 'La persona realiza el pago',
            'valida_pago'      => 'El sistema valida el pago',
            'corrige_pago'     => 'Se corrige o completa el pago',
            'registra_pago'    => 'Se registra el pago',
        ],
    ],

    'diagramas' => [
        'editor_url'  => env('PUNTA_DIAGRAMA_EDITOR_URL', 'https://embed.diagrams.net/'),
        'mermaid_url' => env('PUNTA_MERMAID_URL', 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs'),
    ],

    /**
     * Jurisdicción de ESTA instalación.
     *
     * El buscador la usa para no mezclar derecho de otra jurisdicción: muestra
     * el federal (que aplica a todo el país) más lo estatal de este estado y lo
     * municipal de este municipio, y excluye el resto.
     *
     * Es fija de la instalación, no del usuario. Si el sistema se reusa en otro
     * municipio, se cambian estas dos líneas (o sus variables de entorno) y nada
     * más.
     */
    'jurisdiccion' => [
        'estado'    => env('PUNTA_ESTADO', 'BCS'),
        'municipio' => env('PUNTA_MUNICIPIO', 'La Paz'),
    ],

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

    /**
     * Topes máximos para los campos numéricos del trámite.
     *
     * FUENTE ÚNICA DE VERDAD: estos límites los consume tanto la validación de
     * backend (App\Http\Requests\TramiteRequest) como la validación de frontend
     * de los wizards (inyectados a window.PUNTA.topes). Cambiar un tope aquí lo
     * cambia en ambos lados, sin duplicar el número en PHP y en JS.
     *
     * Son deliberadamente estrictos para forzar revisión manual de los casos
     * extremos: un plazo de "124 años" o un volumen de "185 mil" produce un
     * costo burocrático irreal que, además, desbordaba la base de datos.
     */
    'topes_tramite' => [
        'plazo_anios'   => 2,        // plazo de resolución, en años
        'volumen_anual' => 1000000,  // trámites gestionados al año
        'num_areas'     => 50,       // áreas que intervienen
        'visitas'       => 50,       // visitas presenciales requeridas
        'copias'        => 100,      // copias solicitadas
        'horas'         => 999,      // horas de cualquier tramo de tiempo
        'minutos'       => 59,       // minutos (tope natural del reloj)
        'nivel_digital' => 5,        // escala de digitalización 0-5
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipos de servicio municipal (lista fija LNETB)
    |--------------------------------------------------------------------------
    |
    | Clasifica los servicios municipales cuando el usuario elige registrar un
    | servicio en vez de un trámite. Esta lista es fija (viene de la LNETB) y
    | no se edita desde el admin. Si en el futuro se necesita que sea editable,
    | se migra a una tabla 'tipos_servicio' con FK, igual que tipos_tramite.
    |
    | El orden del array determina el orden en el select del wizard.
    |
    */
    'tipos_servicio' => [
        'Servicio de inspección o verificación',
        'Servicio documental, constancia o certificación',
        'Servicio catastral o territorial',
        'Servicio urbano o de construcción',
        'Servicio de uso u ocupación de vía pública',
        'Servicio de limpia, recolección o residuos',
        'Servicio de seguridad pública o tránsito',
        'Servicio de protección civil',
        'Servicio de bomberos',
        'Servicio ambiental o saneamiento',
        'Servicio funerario o de panteón',
        'Servicio de rastro municipal',
        'Servicio de regulación comercial',
        'Servicio de anuncios o publicidad',
        'Servicio de atención, orientación o asesoría',
        'Programa social o beneficio',
        'Actividad institucional',
        'Otro',
    ],

    /*
    |--------------------------------------------------------------------------
    | Asistente del buscador (DeepSeek)
    |--------------------------------------------------------------------------
    |
    | Redacta una respuesta en lenguaje natural a partir de los resultados que el
    | buscador YA encontró. No busca, no consulta la base, no sabe nada de PUNTA.
    | Solo lee lo que se le pasa y lo redacta.
    |
    | ── LA REGLA QUE GOBIERNA TODO ESTO ──
    |
    |     La IA no aporta información. Aporta redacción.
    |
    | Todo lo que diga tiene que salir de las fuentes que se le entregan. Si esas
    | fuentes no contienen la respuesta, el asistente devuelve null y el usuario ve
    | la lista de resultados de siempre. NUNCA se inventa un dato.
    |
    | No es una precaución exagerada. El propio código del buscador ya lo dice, en
    | el comentario de FeaturedAnswerService: las definiciones extraídas tienen
    | confianza "media" pero "siguen siendo una fuente legal real — NO ES TEXTO
    | INVENTADO".
    |
    | Quien construyó PUNTA ya tomó esa decisión. Una IA que responda de su cabeza
    | la rompería el primer día: un ciudadano preguntaría cuánto cuesta su licencia,
    | el sistema se inventaría una cifra plausible, y esa persona se presentaría en
    | ventanilla con el dinero equivocado. Nadie lo detectaría, porque la cifra sería
    | perfectamente razonable.
    |
    */
    'asistente' => [

        // El interruptor. Con esto en false, el asistente NO HACE NINGUNA LLAMADA:
        // sale antes de tocar la red. El buscador funciona exactamente como hoy.
        //
        // Es lo primero que hay que apagar si algo va mal en producción. Sin
        // despliegue, sin migración, sin tocar código.
        'activo' => env('ASISTENTE_ACTIVO', false),

        'api_key' => env('DEEPSEEK_API_KEY'),
        'url'     => env('DEEPSEEK_URL', 'https://api.deepseek.com/chat/completions'),

        // ⚠️ OJO CON EL NOMBRE DEL MODELO ⚠️
        //
        // NO uses 'deepseek-chat'. Ese nombre —y 'deepseek-reasoner'— quedan
        // RETIRADOS E INACCESIBLES el 24 de julio de 2026 a las 15:59 UTC. Después
        // de esa fecha, cualquier petición que los use falla con un error.
        //
        // No es una obsolescencia suave: es un corte duro, sin prórroga anunciada.
        //
        // Los nombres vigentes son 'deepseek-v4-flash' (barato, rápido, suficiente
        // para redactar) y 'deepseek-v4-pro' (más caro, razona mejor). Para lo que
        // hace este asistente —resumir cuatro artículos en tres frases— el Flash
        // sobra.
        'modelo' => env('DEEPSEEK_MODELO', 'deepseek-v4-flash'),

        // Ocho segundos y ni uno más.
        //
        // Un buscador municipal no puede quedarse colgado esperando a una API
        // externa. Si DeepSeek tarda, el ciudadano prefiere ver su lista de
        // resultados AHORA que una respuesta bonita dentro de treinta segundos.
        //
        // Si se agota el plazo, se registra en el log y se devuelve null: la
        // búsqueda sigue su curso como si el asistente no existiera.
        'timeout' => (int) env('ASISTENTE_TIMEOUT', 8),

        // Cuántos resultados se le pasan como contexto.
        //
        // ── POR QUÉ SUBIÓ DE 8 A 20 ──
        //
        // Cuando el buscador usaba AND, los pocos resultados que llegaban eran precisos: si
        // sobrevivían al AND, era porque contenían todas las palabras. Ocho bastaban.
        //
        // Ahora el buscador usa OR, y trae MUCHO más ruido a propósito. Ese era el trato: el
        // buscador no tiene que acertar, solo NO PERDERSE la respuesta. Filtrar es trabajo del
        // asistente, que sabe leer.
        //
        // Pero si solo se le pasaran los 8 primeros, el bueno podría quedarse fuera. En el caso
        // real que motivó todo esto —"cuánto cuesta el permiso para ambulantes"— el artículo
        // EQUIVOCADO (sanciones por desacato) puntúa MÁS ALTO que el correcto, porque contiene
        // las dos palabras de la búsqueda. Si el corte fuera estrecho, el asistente recibiría la
        // basura y no la respuesta.
        //
        // Veinte le dan margen para encontrarla. Y como el modelo tiene instrucciones explícitas
        // de IGNORAR lo que no responda, el ruido extra no le estorba: lo descarta.
        //
        // El coste: unos 6.000 tokens por búsqueda en vez de 2.000. Con deepseek-v4-flash a
        // $0.14 el millón, son ocho diezmilésimas de dólar. Mil búsquedas cuestan menos de un
        // dólar. No es el cuello de botella.
        'max_fuentes' => (int) env('ASISTENTE_MAX_FUENTES', 20),

        // Cuánto texto de cada fuente. Un artículo largo se recorta.
        'max_caracteres_por_fuente' => (int) env('ASISTENTE_MAX_CHARS', 800),

        // Cuánto se guarda la respuesta en caché.
        //
        // Dos motivos, y los dos importan: cada llamada cuesta dinero, y la misma
        // pregunta hecha por cien ciudadanos no puede costar cien llamadas.
        //
        // La caché se invalida SOLA cuando cambian los resultados, porque la clave
        // incluye los identificadores de las fuentes: si se reestructura una
        // regulación y el buscador devuelve otros artículos, la clave cambia y se
        // vuelve a preguntar. Nadie tiene que acordarse de limpiarla.
        'cache_horas' => (int) env('ASISTENTE_CACHE_HORAS', 24),
    ],

];
