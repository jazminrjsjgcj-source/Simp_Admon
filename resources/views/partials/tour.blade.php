{{--
    TOUR GUIADO — inyección del guion y carga de los archivos.
    ═════════════════════════════════════════════════════════

    Este partial se incluye una sola vez desde el layout. Decide, por la ruta
    actual y por el rol del usuario, si hay un tour para esta pantalla; si no lo
    hay, no imprime absolutamente nada y no carga ni un byte de más.

    ── CÓMO SE ELIGE EL TOUR ──

    La clave del guion en config/tours.php es el NOMBRE DE LA RUTA de Laravel
    ('tramites.create'). Así no hay una tabla de equivalencias que mantener: para
    añadir un tour a una pantalla basta con crear la entrada con el nombre de su
    ruta, sin tocar este archivo ni el JavaScript.

    ── POR QUÉ EL GUION VIAJA COMO JSON Y NO COMO ATRIBUTOS EN EL HTML ──

    Los textos llevan HTML (<strong>, <em>) y comillas. Metidos en atributos habría
    que escaparlos dos veces y se romperían al primer apóstrofo en español, que
    los hay a montones.

    Se utiliza Js::from() para convertir el arreglo PHP en un objeto JavaScript
    seguro, evitando problemas de interpretación de Blade con arreglos escritos
    directamente dentro de la directiva @json().
--}}

@php
    $rutaActual = request()->route()?->getName();
    $rolActual  = auth()->user()?->rol;

    /*
     * Se carga todo el archivo de configuración porque los nombres de las rutas
     * contienen puntos, por ejemplo: 'tramites.create'.
     *
     * Si se utilizara config('tours.tramites.create'), Laravel interpretaría los
     * puntos como niveles internos del arreglo y no como parte literal de la clave.
     */
    $toursConfigurados = config('tours', []);

    // Una misma pantalla puede necesitar explicaciones opuestas según quién mire:
    // en agenda.show el enlace SUBE evidencia y la revisora la APRUEBA. Por eso se
    // busca primero una variante 'ruta@rol' y solo si no existe se usa la genérica.
    $claveTour = null;
    $tour      = null;

    if ($rutaActual) {
        /*
         * Si el usuario tiene un rol, primero se busca la variante específica:
         *
         * agenda.show@revisora
         *
         * Después se busca la variante genérica:
         *
         * agenda.show
         */
        $candidatas = $rolActual
            ? [$rutaActual . '@' . $rolActual, $rutaActual]
            : [$rutaActual];

        foreach ($candidatas as $candidata) {
            if (array_key_exists($candidata, $toursConfigurados)) {
                $claveTour = $candidata;
                $tour      = $toursConfigurados[$candidata];
                break;
            }
        }
    }

    // Filtro por rol. 'roles' vacío o ausente = visible para todos.
    if ($tour) {
        $rolesTour = $tour['roles'] ?? [];

        if (
            !empty($rolesTour)
            && !in_array($rolActual, $rolesTour, true)
        ) {
            $tour      = null;
            $claveTour = null;
        }
    }

    /**
     * ¿Se le lanza solo?
     *
     * Solo la primera vez que esta persona pisa esta pantalla. Es UNA consulta con
     * índice único (user_id, tour) por carga de página, y únicamente en las
     * pantallas que tienen tour: en el resto ni se ejecuta.
     *
     * exists() y no first(): no hace falta el registro, solo saber si está.
     */
    $autoarranque = false;

    if ($tour && auth()->check()) {
        $autoarranque = !\Illuminate\Support\Facades\DB::table('tours_vistos')
            ->where('user_id', auth()->id())
            ->where('tour', $claveTour)
            ->exists();
    }
@endphp

@if($tour)
    @php
        /*
         * Se prepara primero el arreglo en PHP y después se convierte a JavaScript.
         *
         * Esto evita que Blade intente interpretar directamente un arreglo
         * multilínea dentro de @json(), que era lo que producía el ParseError.
         */
        $datosTour = [
            // La clave real puede llevar un sufijo de rol:
            // 'agenda.show@revisora'.
            'clave' => $claveTour,

            // Título mostrado en el recorrido y en el botón.
            'titulo' => $tour['titulo'] ?? 'Guía',

            // Pasos que componen el recorrido.
            'pasos' => $tour['pasos'] ?? [],

            // Indica si el recorrido debe iniciarse automáticamente.
            'autoarranque' => $autoarranque,

            // Ruta utilizada para registrar que el usuario terminó el recorrido.
            'url_completado' => route('tours.completado'),
        ];
    @endphp

    <link
        rel="stylesheet"
        href="{{ asset('css/tour.css') }}?v={{ filemtime(public_path('css/tour.css')) }}"
    >

    <script>
        /*
         * Js::from() genera una expresión JavaScript segura y correctamente
         * escapada, incluso cuando los textos contienen HTML, comillas o
         * apóstrofos.
         */
        window.PUNTA_TOUR = {{ \Illuminate\Support\Js::from($datosTour) }};
    </script>

    <script
        src="{{ asset('js/tour.js') }}?v={{ filemtime(public_path('js/tour.js')) }}"
        defer
    ></script>

    {{--
        Botón flotante para lanzar el recorrido.

        Vive aquí y no en el layout para que toda la funcionalidad esté en un
        solo archivo: si mañana se quita el tour, se borra la línea del @include
        y no queda un botón huérfano.
    --}}
    <button
        type="button"
        data-tour-iniciar
        class="tour-lanzador"
        title="{{ $tour['titulo'] ?? 'Guía' }}"
    >
        ¿Cómo funciona esto?
    </button>

    <style>
        .tour-lanzador {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 9997;
            background: var(--color-primario, #7a1c3d);
            color: #fff;
            border: 0;
            border-radius: 999px;
            padding: 11px 20px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .22);
        }

        .tour-lanzador:hover {
            filter: brightness(1.12);
        }
    </style>
@endif