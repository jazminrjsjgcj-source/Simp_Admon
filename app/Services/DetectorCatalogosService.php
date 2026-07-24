<?php

namespace App\Services;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Detecta, con IA y UNA sola vez al cargar una ley, qué artículos son "de referencia":
 * catálogos, escalas de sanción, tarifas o definiciones que OTROS artículos necesitan para
 * entenderse.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL PROBLEMA QUE RESUELVE
 * ══════════════════════════════════════════════════════════════════════
 *
 * La respuesta a "¿cuánto es la multa por obstruir la banqueta?" vive repartida en TRES
 * artículos del Bando de Policía que se remiten entre sí:
 *
 *     Artículo 65  → la CONDUCTA ("poner obstáculos en banquetas sin permiso").
 *     Artículo 105 → el CATÁLOGO (tabla: "el artículo 65 es Clase D").
 *     Artículo 104 → la ESCALA   ("Clase D = multa de 31 a 100 UMA").
 *
 * El buscador encuentra el 65 —menciona "banqueta"— pero no el 104/105, que hablan de "clases
 * de infracción". Sin ellos, el asistente rellenaba el hueco con el artículo equivocado.
 *
 * La solución es marcar el 104 y el 105 como artículos-catálogo, para que el asistente los
 * inyecte SIEMPRE que responda sobre el Bando. Este servicio hace ese marcado.
 *
 * ── Por qué con IA y no con reglas ──
 *
 * La primera versión detectaba catálogos por frases fijas ("las infracciones se clasifican").
 * Pero esa es la redacción del Bando de LA PAZ. Una ley de otro estado, del país, o general de
 * la nación lo redacta distinto. Hardcodear frases NO es replicable: cada ley nueva rompería el
 * detector.
 *
 * La IA generaliza: entiende qué ES un catálogo, sin importar cómo esté redactado. Así el mismo
 * código sirve para cualquier ley de cualquier jurisdicción.
 *
 * ── Por qué UNA vez al cargar, nunca en una búsqueda ──
 *
 * La IA no es determinista. Si detectara en cada búsqueda, el sistema parpadearía y costaría una
 * llamada por consulta. Corriendo una vez al estructurar y GUARDANDO el resultado, un proceso
 * no-determinista produce un dato determinista (la etiqueta en la base). Es la misma lógica del
 * `contexto` de los nodos: se calcula al estructurar, se guarda, las búsquedas lo leen.
 *
 * ── Las tres salvaguardas que sustituyen a la revisión humana ──
 *
 *   A. LISTA CERRADA de tipos. La IA elige de un menú fijo o dice 'ninguna'. No inventa
 *      etiquetas, así el dato es consistente (nunca "sancion"/"sanciones"/"multas" mezclados).
 *   B. El asistente SIEMPRE cita el artículo inyectado. Si la IA marca mal, el servidor público
 *      ve de qué artículo salió y lo detecta. (Solo válido porque los usuarios contrastan.)
 *   C. El marcado es corregible después con un comando, sin reprocesar la ley entera.
 *
 * ── Si la IA falla ──
 *
 * Es una MEJORA, nunca un requisito. Si la API cae o responde basura, la ley se carga igual, sin
 * catálogos marcados: el buscador funciona como antes (encuentra el art. 65, no cruza la
 * cadena). Nunca bloquea la carga.
 */
class DetectorCatalogosService
{
    /**
     * La LISTA CERRADA de tipos. La IA debe devolver uno de estos para cada artículo candidato.
     * Cambiar esta lista es una decisión deliberada de producto, no algo que la IA amplíe sola.
     */
    public const TIPO_ESCALA       = 'escala_sancion';
    public const TIPO_CATALOGO     = 'catalogo_clasificacion';
    public const TIPO_TARIFA       = 'tarifa';
    public const TIPO_DEFINICIONES = 'definiciones';

    public const TIPOS_VALIDOS = [
        self::TIPO_ESCALA,        // tabla de montos por gravedad (Bando art. 104: Clases A-D)
        self::TIPO_CATALOGO,      // tabla que asigna cada conducta a una categoría (Bando art. 105)
        self::TIPO_TARIFA,        // tabla de precios de un derecho/servicio (Hacienda art. 89)
        self::TIPO_DEFINICIONES,  // artículo que define términos de toda la ley (Bando art. 3)
    ];

    /**
     * Cuántos fallos SEGUIDOS de la IA cortan la detección. Un fallo aislado (un
     * artículo con mala suerte) no cuenta: solo una racha, que delata que la IA está
     * caída y que seguir gastaría horas en vano. Lo marcado hasta el corte queda, y
     * re-ejecutar detectar-catalogos reanuda desde la caché.
     */
    private const MAX_FALLOS_CONSECUTIVOS = 5;

    /**
     * Detecta y marca los artículos-catálogo de una regulación.
     *
     * @return int  cuántos artículos quedaron marcados
     */
    public function detectarYMarcar(Regulacion $regulacion): int
    {
        if (! $this->disponible()) {
            Log::info('Detector de catálogos: IA no configurada, se omite el marcado.', [
                'regulacion_id' => $regulacion->id,
            ]);

            return 0;
        }

        // Solo se examinan ARTÍCULOS (no fracciones ni incisos). Un catálogo es siempre un
        // artículo completo; sus filas son hijos que heredan el marcado por pertenencia.
        //
        // Se cargan los hijos (with) porque la clasificación necesita el texto completo del
        // artículo —encabezado + incisos—, no solo el encabezado. Ver textoConHijos().
        $articulos = $regulacion->nodos()
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->with('hijos:id,parent_id,texto')
            ->get(['id', 'numero', 'texto']);

        if ($articulos->isEmpty()) {
            return 0;
        }

        $marcados = 0;
        $fallosSeguidos = 0;

        // Se examina artículo por artículo. Es más lento que mandar toda la ley de golpe, pero:
        //   · una ley tiene cientos de artículos y no caben en un prompt,
        //   · un artículo mal clasificado no arrastra a los demás,
        //   · corre UNA vez al cargar, en segundo plano: la lentitud no la sufre nadie.
        foreach ($articulos as $articulo) {
            // Se clasifica sobre el texto del artículo MÁS el de sus hijos (incisos, fracciones).
            //
            // Esto es clave: un catálogo tiene su sustancia en los HIJOS, no en el encabezado. El
            // artículo 104 del Bando ("las infracciones se clasifican de la siguiente manera:")
            // solo tiene ~110 caracteres en su texto propio —las clases A, B, C, D son nodos hijos
            // aparte—. Evaluar solo el encabezado dejaba fuera justo la parte que lo delata como
            // una escala de sanción. Con los hijos, la IA ve "Clase A: 1-10 UMA... Clase D: 31-100
            // UMA" y lo reconoce sin dudar.
            $textoCompleto = $this->textoConHijos($articulo);

            [$respondio, $tipo] = $this->clasificar($textoCompleto);

            if (! $respondio) {
                // La IA no respondió para este artículo (ya con reintento). Si se acumulan
                // demasiados fallos SEGUIDOS, la IA está caída: se corta para no gastar horas
                // en vano. Lo marcado hasta aquí queda; re-ejecutar detectar-catalogos reanuda
                // desde la caché, sin repetir lo ya hecho.
                if (++$fallosSeguidos >= self::MAX_FALLOS_CONSECUTIVOS) {
                    Log::warning('Detector de catálogos: demasiados fallos seguidos de la IA; se corta la detección (queda incompleta).', [
                        'regulacion_id'   => $regulacion->id,
                        'marcados'        => $marcados,
                        'fallos_seguidos' => $fallosSeguidos,
                    ]);

                    break;
                }

                continue;
            }

            $fallosSeguidos = 0; // hubo respuesta: la racha se rompe

            if ($tipo !== null) {
                $articulo->tipo_referencia = $tipo;
                $articulo->saveQuietly(); // dato derivado; no dispara observers
                $marcados++;
            }
        }

        Log::info('Detector de catálogos: marcado completo.', [
            'regulacion_id' => $regulacion->id,
            'articulos'     => $articulos->count(),
            'marcados'      => $marcados,
        ]);

        return $marcados;
    }

    /**
     * Vuelca la tabla de clasificación LIMPIA (recuperada del PDF por el script
     * Python) en el nodo-catálogo que este detector ya marcó.
     *
     * ── Por qué hace falta ───────────────────────────────────────────────
     *
     * El nodo del catálogo (Bando art. 105) tiene, en la base, el texto que dejó
     * pdftotext: la tabla "artículo → clase" aplastada a "65 D" ilegible. El
     * detector lo marca, y la inyección lo lleva a cada respuesta, pero lleva basura.
     *
     * Aquí se le AÑADE la tabla legible ("Artículo 65 → Clase D") al final del texto.
     * Como la inyección ya arrastra este nodo, con esto el asistente recibe por fin
     * el cruce que necesita: conducta (art. 65) → clase (D) → escala (art. 104).
     *
     * ── Por qué es seguro re-ejecutarlo ──────────────────────────────────
     *
     * Al reestructurar, el nodo nace de nuevo con el texto crudo, y este paso vuelve
     * a añadir la tabla. Y si por alguna razón se llamara dos veces sin reestructurar,
     * el guard de "ya tiene la tabla" evita duplicarla.
     *
     * @param  list<array{articulo: string, clase: string}>  $pares
     * @return int  cuántos nodos-catálogo se repararon.
     */
    public function repararCatalogoConTabla(Regulacion $regulacion, array $pares): int
    {
        if ($pares === []) {
            return 0;
        }

        // El nodo que asigna cada conducta a una clase (Bando art. 105).
        $nodos = $regulacion->nodos()
            ->where('tipo_referencia', self::TIPO_CATALOGO)
            ->get();

        if ($nodos->isEmpty()) {
            return 0;
        }

        $marcaTabla = 'Clasificación recuperada de la tabla:';
        $tabla      = $this->formatearTablaCatalogo($pares);

        $reparados = 0;
        foreach ($nodos as $nodo) {
            $texto = trim((string) $nodo->texto);

            // Si ya había una tabla recuperada, se quita para volver a ponerla al día
            // (p. ej. tras mejorar el extractor). Así re-ejecutar ACTUALIZA en vez de
            // duplicar o quedarse con la versión vieja.
            $pos = mb_strpos($texto, "\n\n" . $marcaTabla);
            if ($pos !== false) {
                $texto = rtrim(mb_substr($texto, 0, $pos));
            }

            $nodo->texto = $texto . "\n\n" . $marcaTabla . "\n" . $tabla;
            $nodo->saveQuietly(); // dato derivado; no dispara observers
            $reparados++;
        }

        return $reparados;
    }

    /** Formatea los pares como líneas legibles "Artículo N → Clase X". */
    private function formatearTablaCatalogo(array $pares): string
    {
        $lineas = array_map(
            fn ($p) => "Artículo {$p['articulo']} → Clase {$p['clase']}",
            $pares
        );

        return implode("\n", $lineas);
    }

    /**
     * El texto de un artículo MÁS el de sus hijos directos (incisos, fracciones).
     *
     * Un catálogo esconde su sustancia en los hijos: el artículo 104 solo dice "las infracciones
     * se clasifican de la siguiente manera:" en su encabezado, y las clases A-D cuelgan como
     * incisos. Sin los hijos, la IA nunca vería que es una escala de sanción. Se recorta a un
     * tope para no mandar artículos gigantes a la IA.
     */
    private function textoConHijos(RegulacionNodo $articulo): string
    {
        $partes = [trim((string) $articulo->texto)];

        foreach ($articulo->hijos as $hijo) {
            $t = trim((string) $hijo->texto);
            if ($t !== '') {
                $partes[] = $t;
            }
        }

        return trim(implode("\n", array_filter($partes)));
    }

    /**
     * Pregunta a la IA qué tipo de referencia es un artículo. Devuelve uno de TIPOS_VALIDOS, o
     * null si es un artículo normal o si la IA falla.
     */
    /**
     * Clasifica un artículo. Devuelve [hubo_respuesta, tipo]:
     *   - [true,  'escala_sancion']  resuelto (IA, caché, o local): es un catálogo
     *   - [true,  null]              resuelto: NO es un catálogo (negativo confirmado)
     *   - [false, null]              la IA FALLÓ (para el corte de detectarYMarcar)
     *
     * La distinción entre [true, null] y [false, null] es clave: un negativo confirmado
     * es un resultado; un fallo es la IA sin responder. Solo los fallos cuentan para el
     * corte por racha.
     *
     * @return array{0: bool, 1: ?string}
     */
    private function clasificar(string $texto): array
    {
        // Un artículo muy corto casi nunca es un catálogo (un catálogo lista cosas). Se ahorra la
        // llamada. El umbral se aplica al texto YA CON HIJOS, así que un catálogo real —con sus
        // incisos— lo supera de sobra; lo que se salta son artículos de una sola frase. Es un
        // resultado local ("no es catálogo"), no un fallo.
        if (mb_strlen(trim($texto)) < 200) {
            return [true, null];
        }

        // Caché por contenido: si este mismo texto ya se clasificó, se reusa la respuesta sin
        // volver a molestar a la IA. Es lo que hace la re-detección rápida y determinista. La
        // AUSENCIA de fila = "nunca preguntado"; una fila con tipo NULL = "ya se miró, no es
        // catálogo". Por eso se comprueba EXISTENCIA, no el valor.
        $hash = md5($texto);

        $entrada = DB::table('clasificaciones_ia')->where('hash', $hash)->first();
        if ($entrada !== null) {
            return [true, $entrada->tipo];
        }

        [$respondio, $tipo] = $this->preguntarIa($texto);

        // Solo se cachea una respuesta REAL de la IA (un tipo válido, o "no es catálogo"). Un fallo
        // —IA caída, timeout— NO se cachea: no queremos fijar como "nada" un catálogo que solo
        // falló de conexión; la próxima corrida lo reintenta.
        if ($respondio) {
            DB::table('clasificaciones_ia')->insert([
                'hash'       => $hash,
                'tipo'       => $tipo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$respondio, $tipo];
    }

    /**
     * Le pregunta a la IA qué tipo de referencia es el artículo.
     *
     * Devuelve [respondio, tipo]:
     *   - [true,  'escala_sancion']  la IA contestó con un tipo válido
     *   - [true,  null]              la IA contestó, pero no es un catálogo
     *   - [false, null]              la IA NO contestó (caída, timeout) → no se cachea
     */
    private function preguntarIa(string $texto): array
    {
        try {
            $respuesta = Http::withToken(config('punta.asistente.api_key'))
                ->timeout(8)
                ->retry(2, 250, function ($exception) {
                    // Se reintenta SOLO un parpadeo de conexión o timeout —lo más común en
                    // una corrida larga cuando la IA va lenta—. Un 4xx/5xx NO se reintenta
                    // aquí: se maneja abajo como fallo. Y si la IA está caída del todo, el
                    // corte por fallos consecutivos (detectarYMarcar) evita insistir en vano.
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->acceptJson()
                ->post(config('punta.asistente.url'), [
                    'model'    => config('punta.asistente.modelo', 'deepseek-v4-flash'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instrucciones()],
                        ['role' => 'user',   'content' => mb_substr($texto, 0, 2000)],
                    ],
                    'thinking'        => ['type' => 'disabled'],
                    'temperature'     => 0,
                    'max_tokens'      => 20,
                    'response_format' => ['type' => 'json_object'],
                ]);

            // Solo una respuesta 2xx cuenta como "la IA contestó". Cualquier otra cosa
            // —error del servidor, 4xx— se trata como fallo y NO se cachea.
            if (! $respuesta->successful()) {
                return [false, null];
            }

            // 2xx pero SIN contenido = respuesta vacía o rota. Se trata como fallo, no
            // como "no es catálogo": no queremos cachear como negativo algo que en
            // realidad falló. Una respuesta real de la IA siempre trae content.
            $contenido = $respuesta->json('choices.0.message.content');
            if ($contenido === null) {
                return [false, null];
            }

            return [true, $this->interpretar($contenido)];

        } catch (Throwable $e) {
            Log::warning('Detector de catálogos: la IA no respondió para un artículo.', [
                'error' => $e->getMessage(),
            ]);

            return [false, null];
        }
    }

    /**
     * EL CANDADO. Valida lo que devolvió la IA antes de confiar en ello.
     *
     * Sin revisión humana, este validador es la última defensa: si la IA devuelve un tipo que no
     * está en la lista cerrada, o una frase en vez de una etiqueta, o cualquier basura, se
     * descarta y el artículo queda SIN marcar. Preferimos no marcar (el buscador sigue como hoy) a
     * marcar mal (meter ruido en las respuestas).
     */
    private function interpretar(?string $contenido): ?string
    {
        if (! $contenido) {
            return null;
        }

        $datos = json_decode($contenido, true);

        if (! is_array($datos) || ! isset($datos['tipo'])) {
            return null;
        }

        $tipo = is_string($datos['tipo']) ? trim($datos['tipo']) : '';

        // 'ninguna' es una respuesta válida de la IA (artículo normal), pero para nosotros
        // significa "no marcar" → null.
        if ($tipo === '' || $tipo === 'ninguna') {
            return null;
        }

        // EL CANDADO: solo se acepta un tipo de la lista cerrada. Cualquier otra cosa se descarta.
        if (! in_array($tipo, self::TIPOS_VALIDOS, true)) {
            Log::warning('Detector de catálogos: la IA devolvió un tipo fuera de la lista.', [
                'tipo_recibido' => $tipo,
            ]);

            return null;
        }

        return $tipo;
    }

    private function instrucciones(): string
    {
        $tipos = implode(', ', self::TIPOS_VALIDOS);

        return <<<PROMPT
            Eres un clasificador de artículos jurídicos. Recibes el texto de UN artículo de una ley y
            decides si es un "artículo de referencia": una tabla o lista que OTROS artículos necesitan
            para entenderse.

            Clasifícalo en UNO de estos tipos, o en "ninguna":

            - escala_sancion: define niveles/clases de sanción y sus montos (ej. "Clase A: multa de 1 a
              10 UMA; Clase B: ..."). Es la TABLA DE MONTOS.
            - catalogo_clasificacion: asigna conductas o artículos a categorías (ej. una tabla que dice
              "el artículo 65 es Clase D"). Es la TABLA QUE CONECTA conductas con clases.
            - tarifa: lista precios o cuotas de un derecho o servicio (ej. "copia de planos: 4 UMA").
            - definiciones: un artículo GLOSARIO que define VARIOS términos usados en toda la ley,
              como una lista (ej. "Para efectos de esta ley se entiende por: I. Arresto:...; II. Juez
              Cívico:...; III. UMA:..."). NO marques un artículo que solo define UNA cosa (ej. "El
              Centro de Detención es el inmueble donde..."): eso es "ninguna".
            - ninguna: cualquier otro artículo. La INMENSA MAYORÍA de artículos son "ninguna": los que
              describen una conducta, una obligación, un procedimiento, un derecho concreto, o
              definen UNA sola cosa.

            Reglas:
            - Ante la duda, responde "ninguna". Es mejor no marcar que marcar de más.
            - Un artículo que describe UNA conducta o UN derecho concreto NO es de referencia, aunque
              mencione una multa. Solo son de referencia las TABLAS o LISTAS que aplican a muchos
              artículos.
            - Para "definiciones", exige una LISTA de varios términos, no una definición aislada.

            Responde SOLO un JSON: {"tipo": "<uno de: $tipos, ninguna>"}
            PROMPT;
    }

    private function disponible(): bool
    {
        return (bool) config('punta.asistente.activo')
            && ! empty(config('punta.asistente.api_key'));
    }
}
