<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Observers\AuditObserver;
use App\Models\Tramite;
use App\Models\AccionAgenda;
use App\Models\PropuestaRegulatoria;
use App\Models\Regulacion;
use App\Models\AnalisisImpactoRegulatorio;
use App\Models\ExencionAir;
use App\Models\User;
use App\Models\Periodo;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Tramite::observe(AuditObserver::class);
        AccionAgenda::observe(AuditObserver::class);
        PropuestaRegulatoria::observe(AuditObserver::class);
        Regulacion::observe(AuditObserver::class);
        AnalisisImpactoRegulatorio::observe(AuditObserver::class);
        ExencionAir::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
        Periodo::observe(AuditObserver::class);

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
                    \$__mapa = ['habiles' => 'días hábiles', 'naturales' => 'días naturales', 'meses' => 'meses', 'horas' => 'horas', 'minutos' => 'minutos'];
                    \$__uniLegible = \$__mapa[\$__uni] ?? \$__uni;
                    echo e(\$__cant) . ' ' . e(\$__uniLegible);
                }
            ?>";
        });
    }
}
