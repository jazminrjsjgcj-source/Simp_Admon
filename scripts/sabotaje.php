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

    [
        'bug'     => 'Los encabezados vuelven a partirse en dos (SECCIÓN III / IMPUESTOS SOBRE...)',
        'archivo' => 'app/Services/RegulacionEstructuradorService.php',
        'buscar'  => '$lineas = $this->unirEncabezadosPartidos($lineas);',
        'romper'  => '// SABOTAJE: encabezados sin unir',
        'prueba'  => 'RegulacionEstructuradorTest',
        'porque'  => 'En el PDF, un encabezado viene en DOS renglones: "SECCIÓN III" y luego '
                   . '"IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS". Sin unirlos, el parser crea la sección '
                   . 'SIN NOMBRE y deja el nombre huérfano como párrafo. Y entonces el artículo 65 —que '
                   . 'dice "espectáculo" en singular, sin "públicos"— se vuelve invisible: su único '
                   . 'contexto era el nombre de esa sección.',
    ],

    [
        'bug'     => 'Los nodos dejan de heredar el contexto de su capítulo',
        'archivo' => 'app/Services/RegulacionEstructuradorService.php',
        'buscar'  => '$this->rellenarContexto($regulacion);',
        'romper'  => '// SABOTAJE: sin contexto heredado',
        'prueba'  => 'ArticuloQueRespondeLlegaTest',
        'porque'  => 'EN TODA LEY BIEN REDACTADA, UN ARTÍCULO NO REPITE EL TÍTULO DE SU CAPÍTULO. El '
                   . 'artículo 26 nunca dice "patrimonio": ya está dentro del capítulo "IMPUESTOS SOBRE '
                   . 'EL PATRIMONIO". Sin el contexto heredado, preguntar por los impuestos al patrimonio '
                   . 'no encuentra el predial. Estábamos penalizando la buena redacción jurídica.',
    ],

    [
        'bug'     => 'El buscador vuelve a ignorar el contexto de los nodos',
        'archivo' => 'app/Services/BuscadorService.php',
        'buscar'  => "to_tsvector('spanish', coalesce(n.texto, '') || ' ' || coalesce(n.contexto, '')) @@ to_tsquery('spanish', ?)",
        'romper'  => "to_tsvector('spanish', coalesce(n.texto, '')) @@ to_tsquery('spanish', ?) -- SABOTAJE",
        'prueba'  => 'ArticuloQueRespondeLlegaTest',
        'porque'  => 'El contexto existe en la base pero el buscador no lo mira. Cada artículo vuelve a '
                   . 'ser una isla sin contexto, y los que no repiten el tema de su capítulo desaparecen.',
    ],

    [
        'bug'     => 'El filtro de palabras comunes tira la palabra clave en corpus pequeños',
        'archivo' => 'app/Services/BuscadorService.php',
        'buscar'  => '$umbral = max(
            self::MINIMO_PARA_DESCARTAR,',
        'romper'  => '$umbral = min(
            self::MINIMO_PARA_DESCARTAR, // SABOTAJE',
        'prueba'  => 'BuscadorEntiendePreguntasTest',
        'porque'  => 'El umbral es un PORCENTAJE (5%). Sobre un corpus de 3 nodos, el 5% es CERO: '
                   . 'cualquier palabra que aparezca dos veces se descarta por "demasiado común". El filtro '
                   . 'acaba tirando la palabra que define la pregunta. Un porcentaje sobre un corpus '
                   . 'diminuto no significa nada.',
    ],

    [
        'bug'     => 'Se puede votar sobre las búsquedas de otra persona',
        'archivo' => 'app/Services/BusquedaLogService.php',
        'buscar'  => "->where('user_id', Auth::id())",
        'romper'  => '// SABOTAJE: sin candado de propiedad',
        'prueba'  => 'BusquedaLogSeguridadTest',
        'porque'  => 'El log_id viene del NAVEGADOR y el controlador solo lo valida como entero: comprueba '
                   . 'el TIPO, no la PROPIEDAD. Con un bucle de fetch() se pueden votar cien mil búsquedas '
                   . 'ajenas. Y esos votos son, según el docblock del propio servicio, las "training labels" '
                   . 'de un futuro modelo de ranking: un buscador entrenado con votos manipulados es un '
                   . 'buscador envenenado, y nadie sabría por qué se volvió raro.',
    ],

    [
        'bug'     => 'Un usuario puede votar mil veces el mismo resultado',
        'archivo' => 'app/Services/BusquedaLogService.php',
        'buscar'  => "\$yaVoto = DB::table('busqueda_feedback')->where(\$claves)->exists();",
        'romper'  => '$yaVoto = false; // SABOTAJE',
        'prueba'  => 'BusquedaLogSeguridadTest',
        'porque'  => 'El candado de propiedad impide votar en búsquedas ajenas. Este impide INFLAR LAS '
                   . 'PROPIAS: sin él, basta con hacer UNA búsqueda y votarla diez mil veces con un bucle. '
                   . 'Mil votos "útil" empujarían cualquier trámite al primer puesto del ranking.',
    ],

    [
        'bug'     => 'El asistente deja de ver dónde vive cada artículo dentro de la ley',
        'archivo' => 'app/Services/AsistenteRespuestaService.php',
        'buscar'  => '$bloque .= "\\n   Ubicación en la ley: {$f[\'contexto\']}";',
        'romper'  => '// SABOTAJE: el modelo no ve el contexto',
        'prueba'  => 'AsistenteRespuestaTest',
        'porque'  => 'ATENCIÓN: puede que este NO lo cacen las pruebas, y es un punto ciego CONOCIDO. El '
                   . 'buscador ENCUENTRA el artículo 26 (porque busca sobre texto + contexto), pero al modelo '
                   . 'se le pasaba solo el TEXTO: "Son objeto del Impuesto Predial, la propiedad, usufructo..." '
                   . 'Y ahí no aparece la palabra "patrimonio". El modelo tenía la respuesta delante y no podía '
                   . 'verla. Es el mismo bug del buscador, repetido un nivel más arriba: le dábamos la página '
                   . 'arrancada del libro. Se comprueba leyendo respuestas reales, no con una prueba.',
    ],

    [
        'bug'     => 'Los catálogos no se congelan al firmar',
        'archivo' => 'app/Models/Concerns/CongelaCatalogos.php',
        'buscar'  => "\$this->forceFill(['catalogos_al_firmar' => \$foto])->saveQuietly();",
        'romper'  => '// SABOTAJE: no se guarda la foto',
        'prueba'  => 'CatalogosCongeladosTest',
        'porque'  => 'Un trámite firmado debe seguir mostrando los nombres que tenía AL FIRMARSE. Si '
                   . 'alguien renombra la dependencia después, el documento firmado no puede cambiar de '
                   . 'contenido por su cuenta: sería alterar un acto jurídico ya consumado. Sin la foto, '
                   . 'los acuses impresos y la pantalla dejan de coincidir, y nadie se entera.',
    ],

    [
        'bug'     => 'El filtro por tipo del buscador se ignora',
        'archivo' => 'app/Services/BuscadorService.php',
        'buscar'  => '$incluir = fn (string $tipo) => $tipos === null || in_array($tipo, $tipos);',
        'romper'  => '$incluir = fn (string $tipo) => true; // SABOTAJE',
        'prueba'  => 'BuscadorFiltrosTest',
        'porque'  => 'El ciudadano marca "solo Requisitos" y el buscador le devuelve todo igualmente. No '
                   . 'da ningún error: simplemente el filtro que acaba de pulsar no sirve para nada, y él '
                   . 'no tiene forma de saberlo.',
    ],

    [
        'bug'     => 'Cualquiera puede crear trámites (se cae el control de permisos)',
        'archivo' => 'app/Http/Controllers/TramiteController.php',
        'buscar'  => "if (!auth()->user()->tienePermiso('tramites.crear')) {",
        'romper'  => 'if (false) { // SABOTAJE',
        'prueba'  => 'RolSujetoYJuridicoTest',
        'porque'  => 'La puerta se queda abierta. Un jurídico o un sujeto obligado —que solo deben revisar '
                   . 'y firmar— podrían crear trámites. Y OJO CON ESTE: es el caso que ya señalamos al '
                   . 'principio de la sesión. Las pruebas de rol comprueban que la REGLA existe '
                   . '(tienePermiso devuelve false), no que la PUERTA la aplique. Si sale NO LO CAZÓ, ese '
                   . 'es exactamente el agujero: se prueba la ley, no el portero.',
    ],

    [
        'bug'     => 'El filtro de palabras deja la búsqueda VACÍA',
        'archivo' => 'app/Services/BuscadorService.php',
        'buscar'  => 'return $utiles !== [] ? $utiles : $palabras;',
        'romper'  => 'return $utiles; // SABOTAJE',
        'prueba'  => 'BuscadorEntiendePreguntasTest',
        'porque'  => 'Si TODAS las palabras de la consulta son demasiado comunes, el filtro las tira todas '
                   . 'y la búsqueda se queda sin nada que buscar. Devuelve cero resultados sin dar ningún '
                   . 'error. El respaldo (devolver las originales) existe porque más vale un AND con ruido '
                   . 'que una consulta vacía.',
    ],

    [
        'bug'     => 'La respuesta destacada del buscador deja de construirse',
        'archivo' => 'app/Services/FeaturedAnswerService.php',
        'buscar'  => "if (\$intencion !== SearchIntentDetector::DEFINICION) {",
        'romper'  => 'if (true) { // SABOTAJE',
        'prueba'  => 'BuscadorFiltrosTest',
        'porque'  => 'El diccionario curado y las definiciones extraídas del articulado dejan de usarse. '
                   . 'Y el asistente de IA las SUSTITUYE en silencio: una definición que una persona curó '
                   . 'a mano queda reemplazada por texto redactado por un modelo, sin que nadie lo note. '
                   . 'La respuesta seguirá saliendo. Solo que ya no será la oficial.',
    ],

    [
        'bug'     => 'El costo de espera vuelve a ser calculable siempre (aunque falten datos)',
        'archivo' => 'app/Services/CostoBurocraticoService.php',
        'buscar'  => "'resolucion_calculable' => \$costos['resolucion_calculable'],",
        'romper'  => "'resolucion_calculable' => true, // SABOTAJE",
        'prueba'  => 'AvisosEnLaFichaTest',
        'porque'  => 'El snapshot dice que el número es de fiar cuando no lo es. La ficha pinta un $0.00 '
                   . 'indistinguible del de un trámite que se resuelve en el acto, y el CBT sale '
                   . 'subestimado sin que nada lo advierta. Un cero que parece un dato.',
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
        // ── ESTO NO ES "NO LO CAZÓ". ES "NO PUDE ROMPERLO". ──
        //
        // Y la diferencia es TODO. Un "NO LO CAZÓ" significa que tus pruebas tienen un agujero.
        // Un "NO SE PUDO SABOTEAR" significa que EL SABOTEADOR está roto: buscaba una línea que
        // ya no existe, no rompió nada, y la prueba pasó tan tranquila.
        //
        // Si los dos casos se informaran igual, el saboteador estaría MINTIENDO sobre la
        // cobertura de las pruebas. Diría "tienes un agujero" cuando lo que hay es una línea
        // renombrada.
        //
        // Una herramienta de diagnóstico que no distingue "el sistema falla" de "yo fallé" es
        // peor que no tenerla: da confianza en una lectura falsa.
        echo "      ⚠  NO PUDE SABOTEARLO. No encuentro esa línea en el archivo.\n";
        echo "         Esto NO significa que las pruebas tengan un agujero: significa que este\n";
        echo "         sabotaje está desactualizado. La línea cambió de nombre, o el arreglo no\n";
        echo "         se copió. Actualiza el sabotaje o copia el archivo.\n\n";
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

$noSabotables = count(array_filter($resultados, fn ($r) => $r[1] === 'NO SE PUDO SABOTEAR'));

if ($noSabotables > 0) {
    echo "  ⚠  {$noSabotables} sabotaje(s) NO SE PUDIERON APLICAR.\n\n";
    echo "     Eso NO es un agujero en las pruebas: es el saboteador desactualizado. Buscaba\n";
    echo "     líneas que ya no existen. Arréglalo antes de fiarte del resto del informe —\n";
    echo "     esos sabotajes no probaron NADA.\n\n";
}

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
echo "  1) EL SABOTAJE DE LOS PERMISOS (crear trámites). Si sale NO LO CAZÓ, NO es un fallo\n";
echo "     del saboteador: es EL AGUJERO. Las pruebas de rol (RolAdminTest, RolRevisoraTest)\n";
echo "     comprueban que tienePermiso() devuelve false. NO comprueban que el CONTROLADOR\n";
echo "     llame a tienePermiso().\n\n";
echo "     Prueban la LEY, no el PORTERO. Y un portero que no mira la ley deja pasar a todos.\n\n";
echo "     Si ese sabotaje no se caza, hay que escribir pruebas HTTP: pedir la página como un\n";
echo "     jurídico y comprobar que recibe un 403. Es la única forma de probar una puerta.\n\n";
echo "  2) EL IMPORT DEL AIR. Ese sabotaje SALDRÁ COMO 'NO LO CAZÓ', y es correcto.\n\n";
echo "     Sin el `use`, PHP interpreta la clase como una que no existe, y `instanceof`\n";
echo "     contra una clase inexistente devuelve false SIN LANZAR NINGÚN ERROR. La rama\n";
echo "     nunca se ejecuta, en silencio.\n\n";
echo "     Ninguna prueba puede cazarlo, porque esa rama tampoco es alcanzable hoy:\n";
echo "     resolverModelo() no acepta el tipo 'air'. El día que se retome ese módulo,\n";
echo "     habrá que escribir la prueba Y quitar este sabotaje de la lista.\n\n";
echo "     Está aquí para que no se olvide que el agujero existe.\n\n";
echo "  3) EL CONTEXTO QUE VE EL MODELO. El sabotaje que se lo quita puede salir como NO\n";
echo "     CAZADO, y es un punto ciego conocido: las pruebas del asistente usan Http::fake(),\n";
echo "     así que simulan la respuesta del modelo. No pueden saber si el modelo ENTENDIÓ el\n";
echo "     contexto. Eso solo se comprueba leyendo respuestas reales.\n\n";
echo "  4) EL CANDADO DE CONCURRENCIA. Ningún sabotaje puede cazar que se quite el\n";
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
