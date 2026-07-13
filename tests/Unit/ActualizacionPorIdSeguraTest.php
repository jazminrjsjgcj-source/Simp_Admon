<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * CENTINELA — vigila que no vuelva a aparecer el agujero del "requisito ajeno".
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ VIGILA, Y POR QUÉ NO ES UNA PRUEBA NORMAL
 * ══════════════════════════════════════════════════════════════════════
 *
 * Las demás pruebas comprueban COMPORTAMIENTO: le dan datos al sistema y miran qué hace.
 *
 * Esta comprueba el CÓDIGO FUENTE. Lo lee como texto y busca un patrón peligroso. Es una red
 * distinta, para un problema distinto.
 *
 * ── El problema que resuelve ──
 *
 * El sistema tenía UNA vulnerabilidad de "referencia directa a objeto sin control de propiedad":
 * sincronizarRequisitos() actualizaba un requisito por su id —que venía del formulario— sin
 * comprobar que fuera del trámite en curso. Un enlace podía sobrescribir el requisito de otra
 * dependencia editando un campo oculto.
 *
 * La arreglamos. Pero el arreglo es una COSTUMBRE: acordarse de poner `->where('tramite_id', ...)`.
 * Y las costumbres se olvidan. El día que alguien escriba un método nuevo que actualice algo por
 * un id del request, es probable que se olvide del candado — igual que se olvidó la primera vez.
 *
 * Esta prueba no puede impedir que se olvide. Lo que hace es DELATARLO: si aparece un
 * `where('id', ...)->update(...)` en un servicio, se pone roja y obliga a mirarlo.
 *
 * ── Por qué es un centinela y no una prohibición ──
 *
 * No todo `where('id')->update()` es peligroso. La mayoría reciben ids que el propio sistema
 * acaba de sacar de la base, ya filtrados por su dueño (reordenar hermanos, borrar lo que
 * sobra...). Esos son seguros.
 *
 * El peligroso es el que recibe un id que viene DEL USUARIO. Y desde el texto del código no
 * siempre se distingue uno de otro con total certeza.
 *
 * Por eso esta prueba mantiene una LISTA BLANCA: los sitios ya revisados, uno por uno, y
 * confirmados como seguros. Si aparece uno nuevo que no está en la lista, la prueba falla — no
 * porque sea necesariamente un bug, sino porque NADIE LO HA MIRADO TODAVÍA.
 *
 * El mensaje que da no es "esto está mal". Es "esto es nuevo, revísalo y decide".
 */
class ActualizacionPorIdSeguraTest extends TestCase
{
    /**
     * Sitios donde `where('id', ...)->update(...)` YA SE REVISÓ y es seguro.
     *
     * Cada entrada es "archivo → por qué es seguro". Para añadir uno nuevo, primero hay que
     * entender de dónde viene el id. Si viene del request, necesita un candado de propiedad ANTES
     * de entrar aquí. Si viene de la propia base ya filtrada, se documenta y se añade.
     */
    private const REVISADOS_Y_SEGUROS = [
        'app/Services/TramiteService.php' =>
            'sincronizarRequisitos: el update lleva ->where(tramite_id) y lanza RequisitoAjenoException '
            . 'si no toca ninguna fila. El delete usa ids sacados del propio trámite (array_diff sobre '
            . '$tramite->requisitos). Ambos revisados.',

        'app/Services/RegulacionNodoService.php' =>
            'reordenar y compactar orden: los ids vienen de un pluck() sobre where(regulacion_id), o '
            . 'sea que YA están filtrados por la regulación. El usuario no puede inyectar un id ajeno '
            . 'ahí. Además, el controlador ya autorizó la regulación antes de llamar al servicio.',

        'app/Services/BusquedaLogService.php' =>
            'registrarClic: el $logId VIENE DEL NAVEGADOR (POST /buscar/clic, vía fetch). El '
            . 'controlador solo lo valida como entero: comprueba el TIPO, no la PROPIEDAD. '
            . 'Se le añadió ->where(user_id, Auth::id()), así que solo se puede tocar la propia '
            . 'búsqueda. Si toca 0 filas, se registra el intento y se sigue: es una bitácora pasiva '
            . 'y no debe reventar la navegación del ciudadano. '
            . 'Y NO era un riesgo teórico: estos datos son, según el propio docblock del servicio, '
            . 'las "training labels" de un futuro modelo de ranking. Un buscador entrenado con votos '
            . 'manipulados es un buscador envenenado, y nadie sabría por qué se volvió raro.',
    ];

    /**
     * Recorre los servicios buscando `->update(` precedido de un `where('id'`. Si encuentra el
     * patrón en un archivo que no está en la lista blanca, falla.
     */
    public function test_ninguna_actualizacion_por_id_sin_revisar(): void
    {
        $raiz = dirname(__DIR__, 2); // .../tests/Unit → raíz del proyecto
        $dir  = $raiz . '/app/Services';

        $sospechosos = [];

        foreach (glob($dir . '/*.php') as $archivo) {
            $codigo = file_get_contents($archivo);

            // El patrón: un where('id' ... seguido, cerca, de ->update(
            // No es un parser: es una heurística deliberadamente amplia. Prefiere avisar de más
            // (un falso positivo se resuelve añadiéndolo a la lista blanca tras revisarlo) que
            // dejar pasar uno de menos.
            if (preg_match('/where\(\s*[\'"]id[\'"].{0,120}?->update\(/s', $codigo)) {
                $relativo = str_replace($raiz . '/', '', $archivo);

                if (! array_key_exists($relativo, self::REVISADOS_Y_SEGUROS)) {
                    $sospechosos[] = $relativo;
                }
            }
        }

        $this->assertEmpty(
            $sospechosos,
            "Hay actualizaciones por id que nadie ha revisado:\n"
            . '  - ' . implode("\n  - ", $sospechosos) . "\n\n"
            . "Cada una puede ser un agujero como el del 'requisito ajeno': si ese id viene del\n"
            . "formulario y no se comprueba de quién es, un usuario puede modificar datos de otra\n"
            . "dependencia.\n\n"
            . "Revisa cada una:\n"
            . "  · Si el id viene del REQUEST → añade ->where(dueño_id) y aborta si no toca filas.\n"
            . "  · Si el id viene de la BASE ya filtrada → es seguro; añádelo a REVISADOS_Y_SEGUROS\n"
            . "    con una frase que explique por qué.\n\n"
            . "La prueba no dice que esté mal. Dice que es nuevo y nadie lo ha mirado."
        );
    }

    /**
     * La lista blanca no puede tener entradas muertas.
     *
     * Si se refactoriza un servicio y desaparece su `where('id')->update()`, su entrada en la
     * lista blanca se queda huérfana. No es grave, pero una lista blanca con entradas que ya no
     * corresponden a nada pierde su valor: nadie sabe si siguen siendo ciertas.
     *
     * Esta prueba mantiene la lista honesta.
     */
    public function test_la_lista_blanca_no_tiene_entradas_muertas(): void
    {
        $raiz = dirname(__DIR__, 2);

        foreach (self::REVISADOS_Y_SEGUROS as $archivo => $razon) {
            $ruta = $raiz . '/' . $archivo;

            $this->assertFileExists($ruta, "La lista blanca menciona {$archivo}, que ya no existe.");

            $this->assertMatchesRegularExpression(
                '/where\(\s*[\'"]id[\'"].{0,120}?->update\(/s',
                file_get_contents($ruta),
                "{$archivo} está en la lista blanca, pero ya no tiene ningún where('id')->update(). "
                . 'Quita su entrada: una lista blanca con entradas muertas deja de ser fiable.'
            );
        }
    }
}
