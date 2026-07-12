<?php

/**
 * ══════════════════════════════════════════════════════════════════════════════
 *  ¿ESTÁ TODO EL TRABAJO APLICADO?
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Uso:
 *
 *     docker compose exec app php scripts/verificar.php
 *
 * Recorre los 30 y pico cambios de esta sesión y comprueba, uno a uno, si están en el código.
 * No prueba comportamiento —de eso ya se encargan las 240 pruebas y el saboteador—: comprueba
 * que los ARCHIVOS estén, y que dentro tengan lo que tienen que tener.
 *
 * Existe porque en una sesión larga es fácil que un archivo se quede sin copiar, o se copie en la
 * carpeta equivocada, o se pise con una versión vieja. Ya nos pasó tres veces:
 *
 *   · Los blades de los avisos no se copiaron, y tres pruebas seguían en VERDE porque afirmaban
 *     que un aviso NO aparecía... y no aparecía, claro: no existía.
 *   · El show de agenda se subió creyendo que era el de trámites (los dos se llaman igual).
 *   · Un test acabó en tests/Feature declarando el namespace Tests\Unit.
 *
 * Ninguno de los tres dio un error inmediato. Es el patrón de toda esta sesión: el sistema falla
 * en silencio.
 */

$RAIZ = rtrim(getcwd(), '/');

/**
 * Cada comprobación es: [archivo, qué buscar dentro, qué es eso].
 * Si "buscar" es null, basta con que el archivo exista.
 */
$comprobaciones = [

    'Identificadores (homoclave y folio)' => [
        ['app/Models/Contador.php', 'lockForUpdate', 'el contador que reparte números bajo bloqueo'],
        ['app/Models/Tramite.php', 'Contador::siguiente', 'la homoclave pide su número al Contador'],
        ['app/Models/Concerns/GeneraFolio.php', 'Contador::siguiente', 'el folio también'],
        ['app/Support/Siglas.php', 'preg_replace', 'las siglas sin acentos ni espacios'],
        ['app/Models/Concerns/GeneraFolio.php', 'Siglas::', 'el folio usa Siglas (adiós al substr que partía caracteres)'],
    ],

    'Fase 1 — Firmas' => [
        ['app/Services/FirmaDigitalService.php', 'armarCadena', 'la cadena se puede reconstruir'],
        ['app/Services/FirmaDigitalService.php', 'extraerDatosClaveDelFirmable($firmable)', 'la verificación relee el documento'],
        ['app/Services/FirmaDigitalService.php', 'descongelarCatalogos', 'revocar descongela'],
        ['app/Models/Concerns/CongelaCatalogos.php', 'descongelarCatalogos', 'el método existe en el trait'],
        ['app/Exceptions/FirmaDuplicadaException.php', null, 'la excepción de firma duplicada'],
        ['app/Models/Firma.php', 'HasFactory', 'Firma::factory() habilitado'],
        ['app/Http/Controllers/FirmaController.php', 'FirmaDuplicadaException', 'el controlador captura la carrera'],
        ['app/Http/Controllers/FirmaController.php', 'use App\\Models\\AnalisisImpactoRegulatorio;', 'el import que faltaba (la rama del AIR era código muerto)'],
    ],

    'Fase 2 — Requisitos' => [
        ['app/Services/TramiteService.php', 'array $requisitos,', 'actualizar() sin valores por defecto'],
        ['app/Services/TramiteService.php', "->where('tramite_id', \$tramite->id)", 'EL CANDADO: no se puede tocar un requisito ajeno'],
        ['app/Exceptions/RequisitoAjenoException.php', null, 'la excepción del formulario manipulado'],
        ['app/Http/Controllers/TramiteController.php', 'RequisitoAjenoException', 'el controlador avisa y registra el intento'],
    ],

    'Fase 3 — Costo burocrático' => [
        ['app/Services/CostoBurocraticoService.php', '$enUma ? $monto * $valorUma', 'los requisitos en UMA se convierten a pesos'],
        ['app/Services/CostoBurocraticoService.php', 'costoOportunidadPersonaFisica', 'el costo de espera según la metodología (Ec. 6-8)'],
        ['app/Services/CostoBurocraticoService.php', 'costoOportunidadPersonaMoral', 'y para personas morales (Ec. 9-13)'],
        ['app/Models/ParametroActividadEconomica.php', 'tasaProductividadDiaria', 'los datos económicos por sector SCIAN'],
        ['app/Models/ParametroCostoBurocratico.php', 'CLAVE_TASA_LIBRE_RIESGO', 'PIB, población y tasa libre de riesgo'],
        ['app/Models/TramiteCostoBurocratico.php', 'resolucion_calculable', 'el snapshot sabe si el número es de fiar'],
        ['app/Models/Tramite.php', 'costoDeEsperaCalculable', 'el trámite sabe si su costo está completo'],
        ['database/seeders/ParametrosActividadEconomicaSeeder.php', 'inversion', 'los 16 sectores del INEGI, con la Inversión total'],
    ],

    'Fase 4 — Regulaciones citadas' => [
        ['app/Models/Regulacion.php', 'enlace_id,created_by', 'el eager load carga a quién avisar (SIN ESTO NO SE AVISA A NADIE)'],
        ['app/Services/NotificadorService.php', 'sin destinatarios', 'el continue silencioso ahora deja rastro'],
    ],

    'Fase 5 — Periodos' => [
        ['app/Models/Periodo.php', 'ESTATUS_ACTIVO', 'las constantes (adiós a la cadena mágica)'],
        ['app/Services/PeriodoService.php', 'lockForUpdate', 'dos admins no pueden dejar dos activos'],
        ['app/Services/PeriodoService.php', '$activo->update(', 'cerrar el anterior SÍ dispara el AuditObserver'],
        ['app/Services/PeriodoService.php', 'validarEstatus', 'un estatus inventado se rechaza'],
    ],

    'Fase 6 — Colas y soporte' => [
        ['app/Jobs/ConvertirRegulacionJob.php', 'ShouldBeUnique', 'no se encolan cinco conversiones del mismo archivo'],
        ['app/Jobs/EstructurarRegulacionJob.php', 'TIPO_ARTICULO', 'un articulado SIN ARTÍCULOS no se da por bueno'],
        ['app/Jobs/EstructurarRegulacionJob.php', 'citacionesEnTramites', 'el aviso a los trámites citantes viajó al job'],
        ['app/Console/Commands/RescatarRegulacionesColgadas.php', null, 'el barredor de conversiones colgadas'],
        ['app/Models/Regulacion.php', 'estructuracion_error', 'el fallo del articulado llega al usuario'],
        ['app/Http/Controllers/RegulacionController.php', 'Bus::chain', 'convertir → estructurar, encadenados'],
        ['routes/console.php', 'rescatar-colgadas', 'el rescate está programado'],
        ['docker-compose.yml', 'queue:work', 'el worker existe'],
        ['docker-compose.yml', 'schedule:work', 'el scheduler existe (sin él, NADA programado corre)'],
    ],

    'Vistas — el sistema sabía y la pantalla callaba' => [
        ['resources/views/screens/tramites/show.blade.php', 'costoDeEsperaCalculable', 'aviso de costo incompleto'],
        ['resources/views/screens/tramites/show.blade.php', 'catalogosDesactualizados', 'aviso de catálogo cambiado tras firmar'],
        ['resources/views/screens/agenda/show.blade.php', 'costoDeEsperaCalculable', 'la agenda también avisa'],
        ['resources/views/screens/regulaciones/show.blade.php', 'estructuracion_error', 'el fallo del articulado se ve'],
        ['resources/views/screens/regulaciones/show.blade.php', 'CONVERSION_PROCESANDO', 'el "convirtiendo…" con auto-refresco'],
    ],

    'Pruebas' => [
        ['tests/Feature/FirmaIntegridadTest.php', null, null],
        ['tests/Feature/IdentificadoresUnicosTest.php', null, null],
        ['tests/Feature/CostoBurocraticoTest.php', 'cbd_unitario', 'ahora sí comprueba NÚMEROS'],
        ['tests/Feature/CostoResolucionTest.php', null, null],
        ['tests/Feature/DatosEconomicosRealesTest.php', null, null],
        ['tests/Feature/TramiteSincronizacionTest.php', null, null],
        ['tests/Feature/RequisitoAjenoTest.php', null, null],
        ['tests/Feature/RegulacionCitadaTest.php', null, null],
        ['tests/Feature/PeriodoActivoTest.php', null, null],
        ['tests/Feature/AvisosEnLaFichaTest.php', null, null],
        ['tests/Feature/EstructuracionFallidaTest.php', null, null],
        ['tests/Feature/RescatarRegulacionesColgadasTest.php', null, null],
        ['tests/Feature/RegulacionConversorTest.php', null, null],
        ['tests/Feature/RegulacionEstructuradorTest.php', null, null],
        ['tests/Feature/BuscadorFiltrosTest.php', null, null],
        ['tests/Feature/EntradaSinSesionTest.php', null, null],
        ['tests/Unit/SiglasDesdeNombreTest.php', null, null],
        ['tests/Unit/ActualizacionPorIdSeguraTest.php', null, 'el centinela de las actualizaciones por id'],
        ['scripts/sabotaje.php', null, 'el saboteador'],
    ],

    'Ya NO deberían existir' => [
        ['tests/Feature/ExampleTest.php', 'NO_DEBE_EXISTIR', 'la prueba de fábrica que siempre estaba roja'],
        ['tests/Unit/ExampleTest.php', 'NO_DEBE_EXISTIR', 'la que solo afirmaba que true === true'],
        ['tests/Feature/SiglasDesdeNombreTest.php', 'NO_DEBE_EXISTIR', 'la copia duplicada que rompía PHPUnit'],
    ],
];

// ── Motor ────────────────────────────────────────────────────────────────────

$ok = 0;
$faltan = [];

echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  ¿ESTÁ TODO EL TRABAJO APLICADO?\n";
echo "══════════════════════════════════════════════════════════════════════\n";

foreach ($comprobaciones as $fase => $items) {
    echo "\n── {$fase}\n";

    foreach ($items as [$archivo, $buscar, $que]) {
        $ruta   = $RAIZ . '/' . $archivo;
        $existe = file_exists($ruta);

        // Caso especial: archivos que deberían haberse BORRADO.
        if ($buscar === 'NO_DEBE_EXISTIR') {
            if ($existe) {
                printf("   ❌  %-58s SIGUE AHÍ\n", basename($archivo));
                $faltan[] = "BORRAR {$archivo} — {$que}";
            } else {
                printf("   ✅  %-58s borrado\n", basename($archivo));
                $ok++;
            }
            continue;
        }

        if (! $existe) {
            printf("   ❌  %-58s NO EXISTE\n", $archivo);
            $faltan[] = "{$archivo} — no está" . ($que ? " ({$que})" : '');
            continue;
        }

        if ($buscar === null) {
            printf("   ✅  %-58s\n", basename($archivo));
            $ok++;
            continue;
        }

        if (str_contains(file_get_contents($ruta), $buscar)) {
            printf("   ✅  %-58s %s\n", basename($archivo), $que ?? '');
            $ok++;
        } else {
            printf("   ❌  %-58s SIN «%s»\n", basename($archivo), $buscar);
            $faltan[] = "{$archivo} — le falta «{$buscar}»" . ($que ? " → {$que}" : '');
        }
    }
}

// ── Migraciones ──────────────────────────────────────────────────────────────

echo "\n── Migraciones\n";

$migraciones = [
    'contadores'              => 'el contador de identificadores',
    'unique_firma_activa'     => 'no dos firmas activas del mismo tipo',
    'parametros_economicos'   => 'PIB, población y datos SCIAN',
    'resolucion_calculable'   => 'el snapshot sabe si el número es de fiar',
    'unique_periodo_activo'   => 'un solo periodo activo por tipo',
    'estructuracion_error'    => 'el fallo del articulado',
];

$archivosMigracion = implode(' ', array_map('basename', glob($RAIZ . '/database/migrations/*.php')));

foreach ($migraciones as $clave => $que) {
    if (str_contains($archivosMigracion, $clave)) {
        printf("   ✅  %-58s %s\n", $clave, $que);
        $ok++;
    } else {
        printf("   ❌  %-58s NO ESTÁ\n", $clave);
        $faltan[] = "migración '{$clave}' — {$que}";
    }
}

// ── Configuración ────────────────────────────────────────────────────────────

echo "\n── Configuración\n";

// Se lee el .env directamente y NO con config().
//
// config() solo existe si Laravel está arrancado, y este script se ejecuta con `php` a secas
// —fuera del framework— precisamente para que funcione aunque la aplicación esté rota. Un
// verificador que necesita que todo funcione para poder decirte qué no funciona no sirve de nada.
$cola = 'no encontrado';

if (file_exists($RAIZ . '/.env')) {
    foreach (file($RAIZ . '/.env') as $linea) {
        if (preg_match('/^\s*QUEUE_CONNECTION\s*=\s*(\S+)/', $linea, $m)) {
            $cola = trim($m[1], "\"'");
        }
    }
}

if ($cola === 'database') {
    printf("   ✅  %-58s %s\n", 'QUEUE_CONNECTION', 'database (las conversiones van en segundo plano)');
    $ok++;
} else {
    printf("   ❌  %-58s es «%s», debería ser «database»\n", 'QUEUE_CONNECTION', $cola);
    $faltan[] = "QUEUE_CONNECTION está en '{$cola}'. Con 'sync', la conversión vuelve a bloquear "
              . 'la petición del usuario y el worker no sirve de nada.';
}

// ── Resumen ──────────────────────────────────────────────────────────────────

$total = $ok + count($faltan);

echo "\n══════════════════════════════════════════════════════════════════════\n";
printf("  %d de %d comprobaciones correctas.\n", $ok, $total);
echo "══════════════════════════════════════════════════════════════════════\n\n";

if ($faltan === []) {
    echo "  Todo el trabajo de la sesión está aplicado.\n\n";
    echo "  Siguiente paso, y es el que de verdad importa:\n";
    echo "      docker compose exec app php artisan test\n";
    echo "      docker compose exec app php scripts/sabotaje.php\n\n";
    echo "  Las pruebas dicen que nada está roto. El saboteador dice que las pruebas\n";
    echo "  saben avisar cuando algo SE ROMPA. No es lo mismo.\n\n";
    exit(0);
}

echo "  FALTA POR APLICAR:\n\n";
foreach ($faltan as $f) {
    echo "    · {$f}\n";
}
echo "\n";
exit(1);
