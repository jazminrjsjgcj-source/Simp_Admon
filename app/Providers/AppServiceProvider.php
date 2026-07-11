<?php

namespace App\Providers;

use App\Models\AccionAgenda;
use App\Models\AnalisisImpactoRegulatorio;
use App\Models\ExencionAir;
use App\Models\Periodo;
use App\Models\ProcesoAtencion;
use App\Models\PropuestaRegulatoria;
use App\Models\Regulacion;
use App\Models\Tramite;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\ProcesoAtencionObserver;
use App\Observers\TramiteCompletadoObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Red de seguridad para mass-assignment: fuera de producción, Eloquent
        // lanza una excepción si se intenta asignar un atributo que no está en
        // $fillable, en vez de descartarlo en silencio. Así, al convertir un
        // modelo de $guarded a $fillable, cualquier columna olvidada truena de
        // inmediato (con su nombre) en desarrollo, en lugar de perderse callada.
        // En producción queda desactivada para no tumbar la operación real.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Vista de paginación propia para TODA la aplicación (cualquier
        // ->links() del proyecto usa esta vista automáticamente).
        //
        // ANTES no había ninguna línea como esta, así que Laravel usaba su
        // vista de paginación de fábrica. Esa vista de fábrica escribe el
        // texto de los botones con __('pagination.previous') y
        // __('pagination.next') — le pide a Laravel que traduzca esas
        // claves. Como el proyecto no tiene ningún archivo
        // lang/{idioma}/pagination.php (ni en español ni en inglés como
        // respaldo), Laravel no encontraba ninguna traducción y devolvía
        // la clave tal cual, mostrando literalmente "pagination.previous"
        // y "pagination.next" en pantalla en vez de "Anterior" y
        // "Siguiente".
        //
        // AHORA se usa resources/views/vendor/pagination/punta.blade.php,
        // que escribe el texto en español directamente (sin depender de
        // ningún archivo de traducción) y aplica el estilo de botones
        // numerados con la página activa resaltada en guinda.
        Paginator::defaultView('vendor.pagination.punta');

        Tramite::observe(AuditObserver::class);
        AccionAgenda::observe(AuditObserver::class);
        PropuestaRegulatoria::observe(AuditObserver::class);
        Regulacion::observe(AuditObserver::class);
        AnalisisImpactoRegulatorio::observe(AuditObserver::class);
        ExencionAir::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
        Periodo::observe(AuditObserver::class);

        // Fase 6: detectar cambios en el flujo post-firma de reingeniería
        ProcesoAtencion::observe(ProcesoAtencionObserver::class);

        // Cuando un trámite se completa, activa las acciones de agenda que estaban
        // esperándolo. Ocurre cuando el trámite se registró desde la propia agenda:
        // la acción se guarda inactiva y no cuenta hasta que el trámite existe
        // formalmente (ver AccionAgenda::scopeActivas).
        Tramite::observe(TramiteCompletadoObserver::class);

        // A1: los periodos activos se calculan aquí para que el layout no
        // haga queries directas a la BD. View::composer solo ejecuta la
        // consulta cuando layouts.app se renderiza (no en responses JSON).
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $view->with('periodosActivos', Periodo::where('estatus', 'activo')
                ->orderBy('tipo')
                ->get());
        });

        // A1.a: el partial timeline recibe $tipo e $id vía @include; el
        // composer intercepta el partial, ejecuta la query centralizada en
        // BitacoraService y agrega $eventos y $total. Así la vista no hace
        // queries directas y ningún controlador necesita cambiar.
        \Illuminate\Support\Facades\View::composer('partials.timeline', function ($view) {
            $data = $view->getData();
            $tipo = $data['tipo'] ?? '';
            $id   = $data['id']   ?? 0;
            $resultado = \App\Services\BitacoraService::eventosRecientes($tipo, (int) $id);
            $view->with('eventos', $resultado['eventos']);
            $view->with('total',   $resultado['total']);
            $view->with('LIMITE',  10);
        });

        // Directiva @estatus($valor): muestra la etiqueta legible de un
        // estado técnico (ej. 'en_observacion' -> 'En observación').
        Blade::directive('estatus', function ($expression) {
            return "<?php echo config('punta.etiquetas_estatus.' . ($expression)) ?? ucfirst(str_replace('_', ' ', ($expression))); ?>";
        });

        // Directiva @dato($valor): muestra el valor, o 'Sin dato' si está
        // vacío/null. Reemplaza los guiones sueltos en tablas por un texto
        // claro y consistente.
        Blade::directive('dato', function ($expression) {
            return "<?php \$__d = ({$expression}); echo (\$__d === null || \$__d === '') ? '<span class=\"sin-dato\">Sin dato</span>' : e(\$__d); ?>";
        });

        // Directiva @plazo($cantidad, $unidad): muestra un plazo de forma
        // legible (ej. 5 + 'habiles' -> '5 días hábiles'). Si no hay
        // cantidad, muestra 'Sin dato'.
        Blade::directive('plazo', function ($expression) {
            return "<?php
                \$__args = [{$expression}];
                \$__cant = \$__args[0] ?? null;
                \$__uni  = \$__args[1] ?? null;
                if (\$__cant === null || \$__cant === '') {
                    echo '<span class=\"sin-dato\">Sin dato</span>';
                } else {
                    \$__mapa = ['habiles' => 'días hábiles', 'naturales' => 'días naturales', 'meses' => 'meses', 'anios' => 'años', 'horas' => 'horas', 'minutos' => 'minutos'];
                    \$__uniLegible = \$__mapa[\$__uni] ?? \$__uni;
                    echo e(\$__cant) . ' ' . e(\$__uniLegible);
                }
            ?>";
        });
    }
}
