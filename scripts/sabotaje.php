<?php

/**
 * ══════════════════════════════════════════════════════════════════════════════
 *  SABOTEADOR — ¿tus pruebas sirven, o solo están en verde?
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Cómo se usa:
 *
 *     docker compose exec app php scripts/sabotaje.php
 *
 * Qué hace, para cada uno de los bugs que arreglamos:
 *
 *     1. Lo vuelve a introducir en el código (una edición quirúrgica, de una línea).
 *     2. Corre la prueba que DEBERÍA cazarlo.
 *     3. Apunta si la cazó o no.
 *     4. Deshace el sabotaje con `git checkout --`.
 *
 * ── LA REGLA QUE ESTO COMPRUEBA ───────────────────────────────────────────────
 *
 *     Una prueba que nunca ha estado en rojo no ha demostrado nada.
 *
 * Una prueba no existe para decir "esto funciona". Existe para GRITAR cuando se rompa.
 * Si nunca la has visto gritar, no sabes si sabe gritar.
 *
 * En este proyecto ya hemos visto CUATRO veces pruebas en verde que no protegían nada:
 *
 *   · test_revocar_una_firma_de_dos_no_descongela_nada pasaba mientras el método
 *     descongelarCatalogos() ni siquiera existía.
 *   · Tres pruebas de avisos pasaban mientras los avisos no estaban en el HTML.
 *   · Una prueba de costo congelaba la fórmula equivocada, en verde.
 *   · Una prueba de requisitos contaba filas en vez de comparar identidades, y no
 *     habría detectado que se borraban y recreaban en cada guardado.
 *
 * Todas eran verdes. Ninguna servía.
 *
 * ── CUIDADO ───────────────────────────────────────────────────────────────────
 *
 * Este script MODIFICA tus archivos y luego los restaura con git. Exige que el
 * repositorio esté limpio antes de empezar: si tienes cambios sin confirmar, los
 * perdería al deshacer.
 *
 * Confirma tu trabajo antes de correrlo:  git add -A && git commit -m "wip"
 */

// ── Los sabotajes ────────────────────────────────────────────────────────────
//
// Cada uno reintroduce un bug REAL que este proyecto tuvo. No son roturas
// inventadas: son exactamente los nueve fallos que llevaban meses en producción sin
// dar un solo error.

$sabotajes = [

    [
        'bug'     => 'La verificación de firma no verifica el documento',
        'archivo' => 'app/Services/FirmaDigitalService.php',
        'buscar'  => 'return $this->calcularHash($cadenaActual) === $firma->hash_acuse;',
        'romper'  => 'return true; // SABOTAJE',
        'prueba'  => 'FirmaIntegridadTest',
        'porque'  => 'Se puede editar un trámite después de firmarlo y la firma sigue dando por válida. '
                   . 'El hash SHA-256 no prueba nada: es un sello sobre un documento que se puede cambiar después.',
    ],

    [
        'bug'     => 'El costo de espera vuelve a la fórmula del salario',
        'archivo' => 'app/Services/CostoBurocraticoService.php',
        'buscar'  => "'costo'                    => \$costoOportunidad * \$dias,",
        'romper'  => "'costo'                    => \$dias * 68.20 * 8, // SABOTAJE",
        'prueba'  => 'DatosEconomicosRealesTest',
        'porque'  => 'Esperar 20 días hábiles pasaría a costar $15,276 en vez de $3.84. '
                   . 'Ese número decide si un trámite requiere un AIR.',
    ],

    [
        'bug'     => 'Los requisitos en UMA se suman como si fueran pesos',
        'archivo' => 'app/Services/CostoBurocraticoService.php',
        'buscar'  => 'return $enUma ? $monto * $valorUma : $monto;',
        'romper'  => 'return $monto; // SABOTAJE',
        'prueba'  => 'CostoBurocraticoTest',
        'porque'  => 'Un requisito de 5 UMA (unos $565) sumaría $5. El error se arrastra al CBD, '
                   . 'al CBU, al CBT, al umbral y al resultado AIR.',
    ],

    [
        'bug'     => 'El contador no avanza: dos trámites, la misma homoclave',
        'archivo' => 'app/Models/Contador.php',
        'buscar'  => '$siguiente = $valorActual + 1;',
        'romper'  => '$siguiente = $valorActual; // SABOTAJE',
        'prueba'  => 'IdentificadoresUnicosTest',
        'porque'  => 'Dos trámites distintos podrían compartir homoclave. Y la homoclave es el '
                   . 'identificador oficial impreso en el acuse firmado.',
    ],

    [
        'bug'     => 'El folio se atasca al pasar de tres dígitos',
        'archivo' => 'app/Models/Concerns/GeneraFolio.php',
        'buscar'  => "return \$prefijo . str_pad((string) \$siguiente, 3, '0', STR_PAD_LEFT);",
        'romper'  => "return \$prefijo . substr(str_pad((string) \$siguiente, 3, '0', STR_PAD_LEFT), 0, 3); // SABOTAJE",
        'prueba'  => 'IdentificadoresUnicosTest',
        'porque'  => 'El folio 1000 se recortaría a 100 y chocaría contra su índice único. '
                   . 'La agenda dejaría de funcionar por completo.',
    ],

    [
        'bug'     => 'El aviso de reestructuración no llega a nadie',
        'archivo' => 'app/Models/Regulacion.php',
        'buscar'  => "->with('tramite:id,nombre_oficial,homoclave,enlace_id,created_by')",
        'romper'  => "->with('tramite:id,nombre_oficial,homoclave') // SABOTAJE",
        'prueba'  => 'RegulacionCitadaTest',
        'porque'  => 'Sin esas dos columnas, el notificador no sabe a quién avisar y no avisa a NADIE. '
                   . 'Y el mensaje de éxito seguiría diciendo que sí notificó.',
    ],

    [
        'bug'     => 'Cerrar un periodo no queda en la bitácora',
        'archivo' => 'app/Services/PeriodoService.php',
        'buscar'  => "\$activo->update(['estatus' => Periodo::ESTATUS_CERRADO]);",
        'romper'  => "\$activo->updateQuietly(['estatus' => Periodo::ESTATUS_CERRADO]); // SABOTAJE",
        'prueba'  => 'PeriodoActivoTest',
        'porque'  => 'El periodo activo determina a qué agenda se imputan las acciones del municipio. '
                   . 'Que uno se cierre sin dejar rastro de quién lo cerró es grave.',
    ],

    [
        'bug'     => 'Revocar la última firma deja el trámite congelado para siempre',
        'archivo' => 'app/Models/Concerns/CongelaCatalogos.php',
        'buscar'  => "if (empty(\$this->catalogos_al_firmar)) {\n            return; // no había foto que tirar",
        'romper'  => "if (true) {\n            return; // SABOTAJE",
        'prueba'  => 'FirmaIntegridadTest',
        'porque'  => 'El trámite se quedaría mostrando los nombres de una firma que ya no existe, '
                   . 'y una firma nueva no podría volver a congelar.',
    ],

    [
        'bug'     => 'Las siglas vuelven a admitir acentos y espacios',
        'archivo' => 'app/Support/Siglas.php',
        'buscar'  => "return preg_replace('/[^A-Z0-9]/', '', \$limpias) ?? '';",
        'romper'  => 'return $limpias; // SABOTAJE',
        'prueba'  => 'SiglasDesdeNombreTest',
        'porque'  => 'Un identificador oficial con una tilde o un espacio dentro rompe URLs, '
                   . 'nombres de archivo y exportaciones a CSV.',
    ],

    [
        'bug'     => 'Los requisitos se borran y se recrean en cada guardado',
        'archivo' => 'app/Services/TramiteService.php',
        'buscar'  => "if (!empty(\$req['id'])) {",
        'romper'  => 'if (false) { // SABOTAJE',
        'prueba'  => 'TramiteSincronizacionTest',
        'porque'  => 'Los requisitos cambiarían de id en cada guardado. Las regulaciones citadas que '
                   . 'cuelgan de cada requisito se romperían en silencio, guardado tras guardado. '
                   . 'Una prueba que solo CONTARA filas no lo detectaría: seguirían siendo tres.',
    ],

    [
        'bug'     => 'Un articulado SIN ARTÍCULOS se da por bueno',
        'archivo' => 'app/Jobs/EstructurarRegulacionJob.php',
        'buscar'  => 'if ($articulos === 0) {',
        'romper'  => 'if (false) { // SABOTAJE',
        'prueba'  => 'EstructuracionFallidaTest',
        'porque'  => 'El estructurador vuelca cualquier línea suelta como un nodo "párrafo". Un '
                   . 'documento de texto corrido, sin un solo artículo, produce DECENAS de nodos — y el '
                   . 'sistema lo daba por bueno. El usuario ve un "articulado" que no es un articulado: '
                   . 'párrafos colgando de la nada, sin nada que poder citar. Y sin ningún aviso.',
    ],

    [
        'bug'     => 'El instanceof contra una clase sin importar (rama muerta del AIR)',
        'archivo' => 'app/Http/Controllers/FirmaController.php',
        'buscar'  => 'use App\\Models\\AnalisisImpactoRegulatorio;',
        'romper'  => '// SABOTAJE: import quitado',
        'prueba'  => 'FirmaIntegridadTest',
        'porque'  => 'ATENCIÓN — SE ESPERA QUE ESTE NO SE CACE. Sin el `use`, PHP interpreta la clase '
                   . 'como App\\Http\\Controllers\\AnalisisImpactoRegulatorio, que no existe. Y `instanceof` '
                   . 'contra una clase inexistente NO LANZA NINGÚN ERROR: devuelve false, siempre. La rama '
                   . 'nunca se ejecuta, sin excepción y sin log. Ninguna prueba puede cazarlo porque la '
                   . 'rama tampoco es alcanzable hoy (resolverModelo no acepta el tipo "air"). Es un punto '
                   . 'ciego CONOCIDO, y por eso está aquí: para que no se olvide que existe.',
    ],

    [
        'bug'     => 'La lista de pendientes de firma vuelve a cargar sin límite',
        'archivo' => 'app/Services/DashboardService.php',
        'buscar'  => 'foreach ($queryTramites->latest()->take(self::MAX_PENDIENTES)->get() as $t) {',
        'romper'  => 'foreach ($queryTramites->get() as $t) { // SABOTAJE',
        'prueba'  => 'DashboardEscalaTest',
        'porque'  => 'Con 500 trámites en firma, el dashboard cargaría 500 modelos en memoria y pintaría '
                   . '500 filas de HTML. No es un N+1 —es UNA sola consulta— y por eso es más fácil de '
                   . 'pasar por alto: el número de consultas no crece. Lo que crece es la memoria.',
    ],
];

// ── Motor ────────────────────────────────────────────────────────────────────

function salida(string $comando): array
{
    exec($comando . ' 2>&1', $lineas, $codigo);

    return [implode("\n", $lineas), $codigo];
}

echo "\n";
echo "══════════════════════════════════════════════════════════════════════\n";
echo "  SABOTEADOR — ¿las pruebas cazan los bugs que ya arreglamos?\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

// El repositorio tiene que estar limpio: al deshacer los sabotajes se hace
// `git checkout --`, que se llevaría por delante cualquier cambio sin confirmar.
[$estado] = salida('git status --porcelain');

if (trim($estado) !== '') {
    echo "✋ Tienes cambios sin confirmar. Este script restaura los archivos con git y\n";
    echo "   se los llevaría por delante.\n\n";
    echo "   Confírmalos primero:  git add -A && git commit -m \"wip\"\n\n";
    exit(1);
}

$resultados = [];

foreach ($sabotajes as $i => $s) {
    $n = $i + 1;

    echo "──────────────────────────────────────────────────────────────────────\n";
    echo "[{$n}/" . count($sabotajes) . "] {$s['bug']}\n";
    echo "      archivo: {$s['archivo']}\n";
    echo "      prueba:  {$s['prueba']}\n";

    $ruta = base_path_seguro($s['archivo']);

    if (! file_exists($ruta)) {
        echo "      ⚠  El archivo no existe. ¿No copiaste ese cambio?\n\n";
        $resultados[] = [$s['bug'], 'ARCHIVO NO ENCONTRADO', $s['prueba']];
        continue;
    }

    $original = file_get_contents($ruta);

    if (! str_contains($original, $s['buscar'])) {
        echo "      ⚠  No encuentro el código a sabotear. El archivo cambió, o no copiaste el arreglo.\n\n";
        $resultados[] = [$s['bug'], 'NO SE PUDO SABOTEAR', $s['prueba']];
        continue;
    }

    // 1. Romper.
    file_put_contents($ruta, str_replace($s['buscar'], $s['romper'], $original));

    // 2. Correr la prueba que debería cazarlo.
    echo "      corriendo…\n";
    [$textoPrueba, $codigo] = salida('php artisan test --filter=' . escapeshellarg($s['prueba']));

    // 3. Restaurar SIEMPRE, pase lo que pase.
    salida('git checkout -- ' . escapeshellarg($s['archivo']));

    // 4. Juzgar.
    //
    // El código de salida de `artisan test` es 0 si TODO pasó, distinto de 0 si algo falló.
    // Con el sabotaje puesto, lo que QUEREMOS es que falle: eso significa que la prueba lo cazó.
    if ($codigo !== 0) {
        echo "      ✅ CAZADO — la prueba se puso roja, como debía.\n\n";
        $resultados[] = [$s['bug'], 'CAZADO', $s['prueba']];
    } else {
        echo "      ❌ NO LO CAZÓ — la prueba siguió en VERDE con el bug dentro.\n";
        echo "         {$s['porque']}\n\n";
        $resultados[] = [$s['bug'], 'NO LO CAZÓ', $s['prueba']];
    }
}

// ── Informe ──────────────────────────────────────────────────────────────────

echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  RESUMEN\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

$cazados = 0;

foreach ($resultados as [$bug, $veredicto, $prueba]) {
    $marca = match ($veredicto) {
        'CAZADO'     => '✅',
        'NO LO CAZÓ' => '❌',
        default      => '⚠ ',
    };

    if ($veredicto === 'CAZADO') {
        $cazados++;
    }

    printf("  %s  %-58s %s\n", $marca, mb_substr($bug, 0, 58), $veredicto);
}

$total = count($resultados);

echo "\n";
echo "  {$cazados} de {$total} bugs cazados por las pruebas.\n\n";

if ($cazados === $total) {
    echo "  Todos los bugs que arreglamos volverían a saltar si alguien los reintroduce.\n";
    echo "  Eso es lo único que una suite de pruebas puede prometer de verdad.\n\n";
} else {
    echo "  Los que dicen NO LO CAZÓ son agujeros REALES en tu red de seguridad: ese bug\n";
    echo "  puede volver mañana —en un refactor, en un merge, en una \"optimización\"— y\n";
    echo "  ninguna prueba dirá nada.\n\n";
    echo "  Pásame cuáles fueron y escribimos la prueba que falta.\n\n";
}

echo "──────────────────────────────────────────────────────────────────────\n";
echo "  LOS PUNTOS CIEGOS QUE HAY QUE ACEPTAR\n";
echo "──────────────────────────────────────────────────────────────────────\n\n";
echo "  1) EL IMPORT DEL AIR. Ese sabotaje SALDRÁ COMO 'NO LO CAZÓ', y es correcto.\n\n";
echo "     Sin el `use`, PHP interpreta la clase como una que no existe, y `instanceof`\n";
echo "     contra una clase inexistente devuelve false SIN LANZAR NINGÚN ERROR. La rama\n";
echo "     nunca se ejecuta, en silencio.\n\n";
echo "     Ninguna prueba puede cazarlo, porque esa rama tampoco es alcanzable hoy:\n";
echo "     resolverModelo() no acepta el tipo 'air'. El día que se retome ese módulo,\n";
echo "     habrá que escribir la prueba Y quitar este sabotaje de la lista.\n\n";
echo "     Está aquí para que no se olvide que el agujero existe.\n\n";
echo "  2) EL CANDADO DE CONCURRENCIA. Ningún sabotaje puede cazar que se quite el\n";
echo "     lockForUpdate()\n";
echo "  del Contador o del PeriodoService.\n\n";
echo "  PHPUnit corre en un solo proceso, secuencial. Nunca hay dos peticiones de\n";
echo "  verdad compitiendo por la misma fila, así que la ausencia del candado no se\n";
echo "  puede detectar.\n\n";
echo "  No es un fallo de las pruebas: es un límite de lo que una prueba unitaria\n";
echo "  puede hacer. Lo que sí protege ahí son los ÍNDICES ÚNICOS de la base de datos,\n";
echo "  que no son una comprobación que alguien pueda quitar sin darse cuenta.\n\n";
echo "  La moraleja: cuando la corrección depende de la concurrencia, no se la confíes\n";
echo "  al código. Confíasela a la base. La prueba documenta la intención; el índice es\n";
echo "  quien de verdad la cumple.\n\n";

// ── Utilidades ───────────────────────────────────────────────────────────────

function base_path_seguro(string $relativa): string
{
    // El script se ejecuta desde la raíz del proyecto (docker compose exec app ...),
    // así que las rutas relativas ya funcionan. Se normaliza por si acaso.
    return rtrim(getcwd(), '/') . '/' . ltrim($relativa, '/');
}
