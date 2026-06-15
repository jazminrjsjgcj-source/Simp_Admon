<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditObserver
{
    private function log(Model $model, string $tipo, ?string $detalle = null): void
    {
        try {
            $modulo = match(class_basename($model)) {
                'Tramite'                     => 'tramites',
                'AccionAgenda'                => 'agenda',
                'PropuestaRegulatoria'        => 'agenda_regulatoria',
                'Regulacion'                  => 'regulaciones',
                'AnalisisImpactoRegulatorio'  => 'air',
                'ExencionAir'                 => 'air',
                'User'                        => 'usuarios',
                'Periodo'                     => 'periodos',
                default                       => strtolower(class_basename($model)),
            };

            $accion = match($tipo) {
                'created' => 'Registro creado',
                'updated' => 'Registro actualizado',
                'deleted' => 'Registro eliminado',
                default   => $tipo,
            };

            $nombre = $model->nombre_oficial
                   ?? $model->nombre
                   ?? $model->descripcion
                   ?? 'ID #' . $model->id;

            DB::table('bitacora')->insert([
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->id,
                'usuario_id'     => Auth::id(),
                'modulo'         => $modulo,
                'tipo'           => $tipo,
                'accion'         => $accion . ': ' . Str::limit($nombre, 80),
                'detalle'        => $detalle,
                'ip_address'     => Request::ip(),
                'created_at'     => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('AuditObserver error: ' . $e->getMessage());
        }
    }

    public function created(Model $model): void
    {
        $this->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changed = $model->getChanges();
        unset($changed['updated_at'], $changed['remember_token']);

        $detalle = null;
        if (!empty($changed)) {
            $original = array_intersect_key($model->getOriginal(), $changed);
            $partes = [];
            foreach ($changed as $campo => $nuevo) {
                if ($campo === 'password') {
                    $partes[] = 'password: [modificado]';
                } else {
                    $viejo = $original[$campo] ?? '-';
                    $partes[] = $campo . ': [' . $viejo . '] -> [' . $nuevo . ']';
                }
            }
            $detalle = implode(' | ', $partes);
        }

        $this->log($model, 'updated', $detalle);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted');
    }
}
