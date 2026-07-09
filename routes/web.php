<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TramiteController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AgendaRegulatoriaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RegulacionController;
use App\Http\Controllers\RegulacionNodoController;
use App\Http\Controllers\Admin\AclController;
use App\Http\Controllers\Admin\ParametroCostoController;
use App\Http\Controllers\Admin\UnidadValorController;
use App\Http\Controllers\Admin\UmbralController;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\AirController;
use App\Http\Controllers\DictamenAirController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\TipoRegulacionController;
use App\Http\Controllers\Admin\TipoTramiteController;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Support\Facades\Route;

// ─── Auth (público) ───
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// ─── Rutas autenticadas (todos los roles) ───
// La restricción de quién edita/crea está en los CONTROLLERS, no aquí.
// Aquí solo se requiere estar autenticado.
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Buscador global — accesible para todos los roles sin restricción
    Route::get('buscar', [\App\Http\Controllers\BuscadorController::class, 'index'])->name('buscar');

    // Artículo completo en JSON, para el modal de lectura del buscador.
    // {nodo} puede ser el id de una fracción o inciso — el controlador sube
    // a su artículo padre usando LegalArticleResolverService.
    Route::get('buscar/articulo/{nodo}', [\App\Http\Controllers\BuscadorController::class, 'obtenerArticulo'])->name('buscar.articulo');

    // Bitácora del buscador — Capa 1 (clic en resultado) y Capa 2 (feedback 👍/👎).
    Route::post('buscar/clic', [\App\Http\Controllers\BuscadorController::class, 'registrarClic'])->name('buscar.clic');
    Route::post('buscar/feedback', [\App\Http\Controllers\BuscadorController::class, 'registrarFeedback'])->name('buscar.feedback');
    Route::get('buscar/detalle/{tipo}/{id}', [\App\Http\Controllers\BuscadorController::class, 'obtenerDetalle'])->name('buscar.detalle');

    // Digitalización — Biblioteca del Digitalizador
    Route::get('digitalizacion/dashboard', [\App\Http\Controllers\DigitalizacionController::class, 'dashboard'])->name('digitalizacion.dashboard');
    Route::get('digitalizacion', [\App\Http\Controllers\DigitalizacionController::class, 'index'])->name('digitalizacion.index');
    Route::get('digitalizacion/{tramite}', [\App\Http\Controllers\DigitalizacionController::class, 'show'])->name('digitalizacion.show');

    // Digitalización — Iniciar y completar
    Route::post('digitalizacion/{tramite}/iniciar', [\App\Http\Controllers\DigitalizacionController::class, 'iniciarDigitalizacion'])->name('digitalizacion.iniciar');
    Route::post('digitalizacion/{tramite}/completar', [\App\Http\Controllers\DigitalizacionController::class, 'completarDigitalizacion'])->name('digitalizacion.completar');

    // Digitalización — Reingeniería CRUD
    Route::get('digitalizacion/{tramite}/reingenieria/crear', [\App\Http\Controllers\DigitalizacionController::class, 'crearReingenieria'])->name('digitalizacion.reingenieria.crear');
    Route::post('digitalizacion/{tramite}/reingenieria', [\App\Http\Controllers\DigitalizacionController::class, 'guardarReingenieria'])->name('digitalizacion.reingenieria.guardar');
    Route::get('digitalizacion/{tramite}/reingenieria/{reingenieria}/editar', [\App\Http\Controllers\DigitalizacionController::class, 'editarReingenieria'])->name('digitalizacion.reingenieria.editar');
    Route::put('digitalizacion/{tramite}/reingenieria/{reingenieria}', [\App\Http\Controllers\DigitalizacionController::class, 'actualizarReingenieria'])->name('digitalizacion.reingenieria.actualizar');
    Route::post('digitalizacion/{tramite}/reingenieria/{reingenieria}/enviar-firma', [\App\Http\Controllers\DigitalizacionController::class, 'enviarAFirma'])->name('digitalizacion.reingenieria.enviarFirma');
    Route::post('digitalizacion/{tramite}/reingenieria/nueva-version', [\App\Http\Controllers\DigitalizacionController::class, 'crearNuevaVersion'])->name('digitalizacion.reingenieria.nuevaVersion');

    // Digitalización — Diagrama
    Route::post('digitalizacion/{tramite}/diagrama/generar', [\App\Http\Controllers\DigitalizacionController::class, 'generarDiagrama'])->name('digitalizacion.diagrama.generar');
    Route::get('digitalizacion/diagrama/{diagrama}/descargar', [\App\Http\Controllers\DigitalizacionController::class, 'descargarDiagrama'])->name('digitalizacion.diagrama.descargar');

    // Flujo del proceso (levantamiento AS-IS)
    Route::post('tramites/{tramite}/flujo/iniciar', [\App\Http\Controllers\FlujoController::class, 'iniciar'])->name('flujo.iniciar');
    Route::post('tramites/{tramite}/flujo/enviar-revision', [\App\Http\Controllers\FlujoController::class, 'enviarRevision'])->name('flujo.enviarRevision');
    Route::post('tramites/{tramite}/flujo/aprobar', [\App\Http\Controllers\FlujoController::class, 'aprobar'])->name('flujo.aprobar');
    Route::post('tramites/{tramite}/flujo/observar', [\App\Http\Controllers\FlujoController::class, 'observar'])->name('flujo.observar');

    // Trámites
    Route::resource('tramites', TramiteController::class);
    Route::get('tramites/{tramite}/acuse', [TramiteController::class, 'acuse'])->name('tramites.acuse');
    Route::post('tramites/{tramite}/estatus', [TramiteController::class, 'actualizarEstatus'])->name('tramites.actualizar.estatus');

    // Agenda SyD
    Route::resource('agenda', AgendaController::class);
    Route::post('agenda/{agenda}/estatus', [AgendaController::class, 'actualizarEstatus'])->name('agenda.actualizar.estatus');
    Route::post('agenda/{agenda}/hito/{hito}/evidencia', [AgendaController::class, 'subirEvidenciaHito'])->name('agenda.hito.evidencia');
    Route::get('agenda/{agenda}/hito/{hito}/evidencia',  [AgendaController::class, 'descargarEvidenciaHito'])->name('agenda.hito.evidencia.descargar');
    Route::post('agenda/{agenda}/hito/{hito}/aprobar',   [AgendaController::class, 'aprobarHito'])->name('agenda.hito.aprobar');
    Route::post('agenda/{agenda}/hito/{hito}/rechazar',  [AgendaController::class, 'rechazarHito'])->name('agenda.hito.rechazar');

    // Exportación Excel de la Agenda SyD (instrumento oficial ATDT).
    // Disponibles solo para revisora y admin. La vista oculta los botones a otros roles.
    Route::get('agenda-exportar/simplificacion', [AgendaController::class, 'exportarSimp'])->name('agenda.exportar.simp');
    Route::get('agenda-exportar/digitalizacion', [AgendaController::class, 'exportarDig'])->name('agenda.exportar.dig');

    // Agenda Regulatoria
    Route::get('agenda-regulatoria', [AgendaRegulatoriaController::class, 'index'])->name('agenda-regulatoria.index');
    Route::get('agenda-regulatoria/propuestas/create',          [AgendaRegulatoriaController::class, 'create'])->name('propuestas.create');
    Route::post('agenda-regulatoria/propuestas',                 [AgendaRegulatoriaController::class, 'store'])->name('propuestas.store');
    Route::get('agenda-regulatoria/propuestas/{propuesta}',      [AgendaRegulatoriaController::class, 'show'])->name('propuestas.show');
    Route::get('agenda-regulatoria/propuestas/{propuesta}/edit', [AgendaRegulatoriaController::class, 'edit'])->name('propuestas.edit');
    Route::put('agenda-regulatoria/propuestas/{propuesta}',      [AgendaRegulatoriaController::class, 'update'])->name('propuestas.update');
    Route::delete('agenda-regulatoria/propuestas/{propuesta}',   [AgendaRegulatoriaController::class, 'destroy'])->name('propuestas.destroy');
    // #7: citas de impacto (trámites que la propuesta modifica)
    Route::post('agenda-regulatoria/propuestas/{propuesta}/impacto',                       [AgendaRegulatoriaController::class, 'agregarImpacto'])->name('propuestas.impacto.agregar');
    Route::delete('agenda-regulatoria/propuestas/{propuesta}/impacto/{impacto}',           [AgendaRegulatoriaController::class, 'quitarImpacto'])->name('propuestas.impacto.quitar');

    // Regulaciones (catálogo jurídico)
    Route::get('regulaciones',                          [RegulacionController::class, 'index'])->name('regulaciones.index');
    Route::get('regulaciones/crear',                    [RegulacionController::class, 'create'])->name('regulaciones.create');
    Route::get('regulaciones/descargar-zip',            [RegulacionController::class, 'descargarZip'])->name('regulaciones.descargar-zip');
    Route::post('regulaciones',                         [RegulacionController::class, 'store'])->name('regulaciones.store');
    Route::get('regulaciones/{regulacion}',             [RegulacionController::class, 'show'])->name('regulaciones.show');
    Route::get('regulaciones/{regulacion}/editar',      [RegulacionController::class, 'edit'])->name('regulaciones.edit');
    Route::put('regulaciones/{regulacion}',             [RegulacionController::class, 'update'])->name('regulaciones.update');
    Route::delete('regulaciones/{regulacion}',          [RegulacionController::class, 'destroy'])->name('regulaciones.destroy');
    Route::get('regulaciones/{regulacion}/descargar',   [RegulacionController::class, 'descargarOriginal'])->name('regulaciones.descargar');
    Route::get('regulaciones/{regulacion}/preview',    [RegulacionController::class, 'preview'])->name('regulaciones.preview');
    Route::get('regulaciones/{regulacion}/descargar-md', [RegulacionController::class, 'descargarMarkdown'])->name('regulaciones.descargar-md');
    Route::get('regulaciones/{regulacion}/descargar-pdf', [RegulacionController::class, 'descargarPdf'])->name('regulaciones.descargar-pdf');
    Route::get('regulaciones/{regulacion}/descargar-docx', [RegulacionController::class, 'descargarDocx'])->name('regulaciones.descargar-docx');
    Route::post('regulaciones/{regulacion}/reintentar', [RegulacionController::class, 'reintentar'])->name('regulaciones.reintentar');
    Route::post('regulaciones/{regulacion}/estructurar', [RegulacionController::class, 'estructurar'])->name('regulaciones.estructurar');
    Route::post('regulaciones/{regulacion}/reemplazar-archivo', [RegulacionController::class, 'reemplazarArchivo'])->name('regulaciones.reemplazar-archivo');
    Route::get('regulaciones/{regulacion}/editor/{unidad?}', [RegulacionController::class, 'editor'])->name('regulaciones.editor');

    // Editor jerárquico de regulaciones (capa de nodos). Todas exigen permiso
    // de edición sobre la regulación (jurídico de su dependencia o admin).
    Route::post('regulaciones/{regulacion}/nodos',        [RegulacionNodoController::class, 'store'])->name('regulaciones.nodos.store');
    Route::get('regulaciones/{regulacion}/nodos/sugerir-numero', [RegulacionNodoController::class, 'sugerirNumero'])->name('regulaciones.nodos.sugerir-numero');
    Route::put('regulaciones/nodos/{nodo}',               [RegulacionNodoController::class, 'update'])->name('regulaciones.nodos.update');
    Route::put('regulaciones/nodos/{nodo}/mover',         [RegulacionNodoController::class, 'mover'])->name('regulaciones.nodos.mover');
    Route::post('regulaciones/nodos/{nodo}/derogar',      [RegulacionNodoController::class, 'derogar'])->name('regulaciones.nodos.derogar');
    Route::post('regulaciones/nodos/{nodo}/restaurar',    [RegulacionNodoController::class, 'restaurar'])->name('regulaciones.nodos.restaurar');
    Route::delete('regulaciones/nodos/{nodo}',            [RegulacionNodoController::class, 'destroy'])->name('regulaciones.nodos.destroy');

    // Papelera del articulado: ver elementos enviados, restaurar o borrar definitivo.
    // {nodo} aquí es un id (el nodo está en papelera, no resuelve por route binding).
    Route::get('regulaciones/{regulacion}/papelera',          [RegulacionNodoController::class, 'papelera'])->name('regulaciones.papelera');
    Route::post('regulaciones/nodos/{nodo}/papelera/restaurar', [RegulacionNodoController::class, 'restaurarPapelera'])->name('regulaciones.nodos.papelera.restaurar');
    Route::delete('regulaciones/nodos/{nodo}/papelera',         [RegulacionNodoController::class, 'eliminarDefinitivo'])->name('regulaciones.nodos.papelera.eliminar');
    Route::post('regulaciones/{regulacion}/favorita',    [RegulacionController::class, 'toggleFavorita'])->name('regulaciones.favorita');

    // #33: Papelera de regulaciones completas (soft-deleted).
    // Las rutas usan IDs explícitos (no route model binding) porque los
    // registros tienen deleted_at y el binding normal los excluiría.
    Route::get('regulaciones-papelera',                      [RegulacionController::class, 'papeleraRegulaciones'])->name('regulaciones.papelera-regulaciones');
    Route::post('regulaciones-papelera/{id}/restaurar',      [RegulacionController::class, 'restaurar'])->name('regulaciones.restaurar');
    Route::delete('regulaciones-papelera/{id}/eliminar',     [RegulacionController::class, 'eliminarDefinitivo'])->name('regulaciones.eliminar-definitivo');

    // Calendario
    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario');
    Route::patch('calendario/{evento}/avance', [CalendarioController::class, 'actualizarAvance'])->name('calendario.avance');

    // Notificaciones (campanita)
    Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leerTodas');
    Route::get('notificaciones/{id}/abrir',  [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    Route::post('notificaciones/prueba',     [NotificacionController::class, 'enviarPrueba'])->name('notificaciones.prueba');

    // Revisión
    Route::prefix('revision')->group(function () {
        Route::post('{tipo}/{id}/observar',      [RevisionController::class, 'observar'])->name('revision.observar');
        Route::post('observaciones/{observacion}/atendida', [RevisionController::class, 'marcarAtendida'])->name('revision.atendida');
        Route::post('{tipo}/{id}/aprobar',       [RevisionController::class, 'aprobar'])->name('revision.aprobar');
    });

    // Firmas digitales
    Route::prefix('firmas')->group(function () {
        Route::get('/',                       [FirmaController::class, 'index'])->name('firmas.index');
        Route::get('{tipo}/{id}',             [FirmaController::class, 'mostrar'])->name('firmas.mostrar');
        Route::post('{tipo}/{id}/firmar',     [FirmaController::class, 'firmar'])->name('firmas.firmar');
        Route::post('{firma}/revocar',        [FirmaController::class, 'revocar'])->name('firmas.revocar');
        Route::get('{firma}/verificar',       [FirmaController::class, 'verificar'])->name('firmas.verificar');
    });

    // ─── AIR — Fase E ───────────────────────────────────────────
    Route::prefix('propuestas/{propuesta}/air')->group(function () {
        Route::get('/',                  [AirController::class, 'formulario'])->name('air.formulario');
        Route::post('/',                 [AirController::class, 'guardar'])->name('air.guardar');
        Route::post('dictaminar',        [AirController::class, 'dictaminar'])->name('air.dictaminar');
        Route::get('exencion',           [AirController::class, 'formularioExencion'])->name('air.exencion.formulario');
        Route::post('exencion',          [AirController::class, 'guardarExencion'])->name('air.exencion.guardar');
        Route::post('exencion/resolver', [AirController::class, 'resolverExencion'])->name('air.exencion.resolver');
    });

    // ─── Bandeja de dictámenes AIR (revisora) ───────────────────
    // Vista única que reúne todos los AIR y exenciones pendientes
    // de dictamen, sin entrar propuesta por propuesta.
    Route::get('dictamenes-air', [DictamenAirController::class, 'index'])->name('dictamenes-air.index');

    // Historial (bitácora) de un registro específico
    Route::get('historial/{tipo}/{id}', [HistorialController::class, 'index'])->name('historial.registro');
    Route::get('historial/{tipo}/{id}/json', [HistorialController::class, 'json'])->name('historial.json');

    // ─── Dashboard filtros inline — Fase H.2 ────────────────────
    Route::get('api/dashboard/filtrar', [DashboardController::class, 'filtrar'])->name('dashboard.filtrar');

    // ─── API unidades activas — Fase F.1 ────────────────────────
    Route::get('api/dependencias/{id}/unidades-activas', fn ($id) =>
        \App\Models\UnidadAdministrativa::where('dependencia_id', $id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo'])
    )->name('api.unidades.activas');

    // ─── Admin — solo rol admin ───
    Route::middleware('role:admin')->group(function () {
        // Usuarios
        Route::resource('admin/usuarios', AdminController::class)->except(['show'])->names('admin.usuarios');
        Route::get('admin/usuarios/{usuario}', fn($usuario) => redirect()->route('admin.usuarios.edit', $usuario))->name('admin.usuarios.show');

        // Periodos
        Route::get('admin/periodos',                    [AdminController::class, 'periodos'])->name('admin.periodos');
        Route::get('admin/periodos/crear',              [AdminController::class, 'crearPeriodo'])->name('admin.periodos.crear');
        Route::post('admin/periodos',                   [AdminController::class, 'guardarPeriodo'])->name('admin.periodos.guardar');
        Route::post('admin/periodos/{periodo}/activar', [AdminController::class, 'activarPeriodo'])->name('admin.periodos.activar');
        Route::get('admin/periodos/{periodo}/editar',   [AdminController::class, 'editarPeriodo'])->name('admin.periodos.editar');
        Route::put('admin/periodos/{periodo}',          [AdminController::class, 'actualizarPeriodo'])->name('admin.periodos.actualizar');
        Route::delete('admin/periodos/{periodo}',       [AdminController::class, 'eliminarPeriodo'])->name('admin.periodos.eliminar');

        // Catálogos — Fase C
        Route::prefix('admin/catalogos')->group(function () {
            Route::get('/',                              [CatalogoController::class, 'index'])->name('admin.catalogos.index');
            Route::get('dependencias',                   [CatalogoController::class, 'dependencias'])->name('admin.catalogos.dependencias');
            Route::get('dependencias/crear',             [CatalogoController::class, 'crearDependencia'])->name('admin.catalogos.dependencias.crear');
            Route::post('dependencias',                  [CatalogoController::class, 'guardarDependencia'])->name('admin.catalogos.dependencias.guardar');
            Route::get('dependencias/{dependencia}/editar',     [CatalogoController::class, 'editarDependencia'])->name('admin.catalogos.dependencias.editar');
            Route::put('dependencias/{dependencia}',             [CatalogoController::class, 'actualizarDependencia'])->name('admin.catalogos.dependencias.actualizar');
            Route::post('dependencias/{dependencia}/toggle',     [CatalogoController::class, 'toggleDependencia'])->name('admin.catalogos.dependencias.toggle');
            Route::get('sujetos-obligados',                      [CatalogoController::class, 'sujetosObligados'])->name('admin.catalogos.sujetos-obligados');
            Route::get('sujetos-obligados/crear',                [CatalogoController::class, 'crearSujetoObligado'])->name('admin.catalogos.sujetos-obligados.crear');
            Route::post('sujetos-obligados',                     [CatalogoController::class, 'guardarSujetoObligado'])->name('admin.catalogos.sujetos-obligados.guardar');
            Route::get('sujetos-obligados/{sujeto}/editar',      [CatalogoController::class, 'editarSujetoObligado'])->name('admin.catalogos.sujetos-obligados.editar');
            Route::put('sujetos-obligados/{sujeto}',             [CatalogoController::class, 'actualizarSujetoObligado'])->name('admin.catalogos.sujetos-obligados.actualizar');
            Route::post('sujetos-obligados/{sujeto}/toggle',     [CatalogoController::class, 'toggleSujetoObligado'])->name('admin.catalogos.sujetos-obligados.toggle');
            Route::get('unidades',                       [CatalogoController::class, 'unidades'])->name('admin.catalogos.unidades');
            Route::get('unidades/crear',                 [CatalogoController::class, 'crearUnidad'])->name('admin.catalogos.unidades.crear');
            Route::post('unidades',                      [CatalogoController::class, 'guardarUnidad'])->name('admin.catalogos.unidades.guardar');
            Route::get('unidades/{unidad}/editar',       [CatalogoController::class, 'editarUnidad'])->name('admin.catalogos.unidades.editar');
            Route::put('unidades/{unidad}',              [CatalogoController::class, 'actualizarUnidad'])->name('admin.catalogos.unidades.actualizar');
            Route::post('unidades/{unidad}/toggle',      [CatalogoController::class, 'toggleUnidad'])->name('admin.catalogos.unidades.toggle');
            Route::delete('unidades/{unidad}',           [CatalogoController::class, 'eliminarUnidad'])->name('admin.catalogos.unidades.eliminar');

            // Tipos de regulación
            Route::get('tipos-regulacion',                          [TipoRegulacionController::class, 'tiposRegulacion'])->name('admin.catalogos.tipos-regulacion');
            Route::get('tipos-regulacion/crear',                    [TipoRegulacionController::class, 'crearTipoRegulacion'])->name('admin.catalogos.tipos-regulacion.crear');
            Route::post('tipos-regulacion',                         [TipoRegulacionController::class, 'guardarTipoRegulacion'])->name('admin.catalogos.tipos-regulacion.guardar');
            Route::get('tipos-regulacion/{tipo}/editar',            [TipoRegulacionController::class, 'editarTipoRegulacion'])->name('admin.catalogos.tipos-regulacion.editar');
            Route::put('tipos-regulacion/{tipo}',                   [TipoRegulacionController::class, 'actualizarTipoRegulacion'])->name('admin.catalogos.tipos-regulacion.actualizar');
            Route::post('tipos-regulacion/{tipo}/toggle',           [TipoRegulacionController::class, 'toggleTipoRegulacion'])->name('admin.catalogos.tipos-regulacion.toggle');

            // Tipos de trámite
            Route::get('tipos-tramite',                             [TipoTramiteController::class, 'tiposTramite'])->name('admin.catalogos.tipos-tramite');
            Route::get('tipos-tramite/crear',                       [TipoTramiteController::class, 'crearTipoTramite'])->name('admin.catalogos.tipos-tramite.crear');
            Route::post('tipos-tramite',                            [TipoTramiteController::class, 'guardarTipoTramite'])->name('admin.catalogos.tipos-tramite.guardar');
            Route::get('tipos-tramite/{tipo}/editar',               [TipoTramiteController::class, 'editarTipoTramite'])->name('admin.catalogos.tipos-tramite.editar');
            Route::put('tipos-tramite/{tipo}',                      [TipoTramiteController::class, 'actualizarTipoTramite'])->name('admin.catalogos.tipos-tramite.actualizar');
            Route::post('tipos-tramite/{tipo}/toggle',              [TipoTramiteController::class, 'toggleTipoTramite'])->name('admin.catalogos.tipos-tramite.toggle');

            // Sectores SCIAN
            Route::get('sectores',                                  [CatalogoController::class, 'sectores'])->name('admin.catalogos.sectores');
            Route::get('sectores/crear',                            [CatalogoController::class, 'crearSector'])->name('admin.catalogos.sectores.crear');
            Route::post('sectores',                                 [CatalogoController::class, 'guardarSector'])->name('admin.catalogos.sectores.guardar');
            Route::get('sectores/{sector}/editar',                  [CatalogoController::class, 'editarSector'])->name('admin.catalogos.sectores.editar');
            Route::put('sectores/{sector}',                         [CatalogoController::class, 'actualizarSector'])->name('admin.catalogos.sectores.actualizar');
            Route::get('sectores/{sector}/subsectores',             [CatalogoController::class, 'subsectores'])->name('admin.catalogos.subsectores');
            Route::get('sectores/{sector}/subsectores/crear',       [CatalogoController::class, 'crearSubsector'])->name('admin.catalogos.subsectores.crear');
            Route::post('sectores/{sector}/subsectores',            [CatalogoController::class, 'guardarSubsector'])->name('admin.catalogos.subsectores.guardar');
            Route::get('sectores/{sector}/subsectores/{subsector}/editar', [CatalogoController::class, 'editarSubsector'])->name('admin.catalogos.subsectores.editar');
            Route::put('sectores/{sector}/subsectores/{subsector}', [CatalogoController::class, 'actualizarSubsector'])->name('admin.catalogos.subsectores.actualizar');
        });

        // Bitácora
        Route::get('admin/bitacora', [AdminController::class, 'bitacora'])->name('admin.bitacora');

        // Configuración del sistema
        Route::get('admin/configuracion', [AdminController::class, 'configuracion'])->name('admin.configuracion');

        // Parámetros del costo burocrático
        Route::prefix('admin/parametros')->group(function () {
            Route::get('/',              [ParametroCostoController::class, 'index'])->name('admin.parametros.index');
            Route::get('{parametro}/editar', [ParametroCostoController::class, 'edit'])->name('admin.parametros.editar');
            Route::put('{parametro}',    [ParametroCostoController::class, 'update'])->name('admin.parametros.actualizar');
        });

        // Unidades de valor
        Route::prefix('admin/unidades-valor')->group(function () {
            Route::get('/',             [UnidadValorController::class, 'index'])->name('admin.unidades-valor.index');
            Route::get('crear',         [UnidadValorController::class, 'create'])->name('admin.unidades-valor.crear');
            Route::post('/',            [UnidadValorController::class, 'store'])->name('admin.unidades-valor.guardar');
            Route::get('{unidad}/editar', [UnidadValorController::class, 'edit'])->name('admin.unidades-valor.editar');
            Route::put('{unidad}',      [UnidadValorController::class, 'update'])->name('admin.unidades-valor.actualizar');
        });

        // Umbrales
        Route::prefix('admin/umbrales')->group(function () {
            Route::get('/',              [UmbralController::class, 'index'])->name('admin.umbrales.index');
            Route::get('crear',          [UmbralController::class, 'create'])->name('admin.umbrales.crear');
            Route::post('/',             [UmbralController::class, 'store'])->name('admin.umbrales.guardar');
            Route::get('{umbral}/editar',[UmbralController::class, 'edit'])->name('admin.umbrales.editar');
            Route::put('{umbral}',       [UmbralController::class, 'update'])->name('admin.umbrales.actualizar');
            Route::delete('{umbral}',    [UmbralController::class, 'destroy'])->name('admin.umbrales.eliminar');
        });

        // ACL
        Route::prefix('admin/acl')->group(function () {
            Route::get('/',                              [AclController::class, 'index'])->name('admin.acl.index');
            Route::get('roles/{role}/editar',            [AclController::class, 'editarRol'])->name('admin.acl.editar-rol');
            Route::put('roles/{role}',                   [AclController::class, 'actualizarRol'])->name('admin.acl.actualizar-rol');
            Route::get('usuarios',                       [AclController::class, 'usuarios'])->name('admin.acl.usuarios');
            Route::get('usuarios/{usuario}/asignar',     [AclController::class, 'asignarRoles'])->name('admin.acl.asignar-roles');
            Route::put('usuarios/{usuario}/asignar',     [AclController::class, 'guardarRoles'])->name('admin.acl.guardar-roles');
            Route::get('bitacora',                       [AclController::class, 'bitacora'])->name('admin.acl.bitacora');
        });
    });

    // ─── API (cualquier autenticado) ───
    Route::prefix('api')->group(function () {
        Route::get('umbral', [AgendaRegulatoriaController::class, 'umbral']);
        Route::get('homoclave/previsualizar', function (\Illuminate\Http\Request $request) {
            $dependencia = \App\Models\Dependencia::find($request->query('dependencia_id'));
            $unidad      = \App\Models\UnidadAdministrativa::find($request->query('unidad_id'));

            if (!$dependencia || !$unidad) {
                return response()->json(['homoclave' => null, 'error' => 'Selecciona dependencia y unidad.'], 422);
            }

            // Sin siglas no se puede armar la homoclave. Antes esto reventaba con
            // 500 porque formatearHomoclave() exige strings y recibía null. Ahora
            // se responde con un mensaje claro que el frontend puede mostrar.
            if (blank($dependencia->siglas) || blank($unidad->siglas)) {
                return response()->json([
                    'homoclave' => null,
                    'error'     => 'Falta capturar las siglas de la dependencia o de la unidad en Catálogos.',
                ], 422);
            }

            $consecutivo = Tramite::siguienteConsecutivoGlobal();

            // 'S' si el wizard eligió servicio; 'T' (trámite) por defecto.
            $naturaleza = $request->query('naturaleza') === 'servicio' ? 'S' : 'T';

            return response()->json([
                'siglas_dependencia' => $dependencia->siglas,
                'siglas_unidad'      => $unidad->siglas,
                'consecutivo'        => $consecutivo,
                'homoclave'          => Tramite::formatearHomoclave($naturaleza, $dependencia->siglas, $unidad->siglas, $consecutivo),
            ]);
        })->name('api.homoclave.previsualizar');
        Route::get('dependencias/{id}/unidades', fn ($id) =>
            \App\Models\UnidadAdministrativa::where('dependencia_id', $id)->get()
        );
        // Búsqueda de trámites para el wizard de agenda (camino A: precargar existente).
        Route::get('tramites/buscar', [TramiteController::class, 'buscarJson'])->name('api.tramites.buscar');
        // Detalle completo de un trámite, para precargar en solo-lectura.
        Route::get('tramites/{tramite}/detalle', [TramiteController::class, 'detalleJson'])->name('api.tramites.detalle');
    });
});
