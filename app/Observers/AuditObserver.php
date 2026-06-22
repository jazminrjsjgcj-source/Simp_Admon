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
    /**
     * Convierte cualquier valor a una representación de texto segura para la
     * bitácora. Evita el error "Array to string conversion" cuando un campo
     * con cast 'array' (ej. acciones_simplificacion, grupos_atencion) cambia
     * y getChanges() lo devuelve como arreglo de PHP.
     *
     *   array u objeto → JSON legible (con fallback '[datos]' si falla)
     *   boolean        → 'true' / 'false' (un (string)false sería '' y confunde)
     *   null           → '-'  (consistente con el default previo del código)
     *   escalar        → su valor tal cual
     */
    private function aTexto($valor): string
    {
        if (is_array($valor) || is_object($valor)) {
            $json = json_encode($valor, JSON_UNESCAPED_UNICODE);
            return $json !== false ? $json : '[datos]';
        }
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }
        if ($valor === null) {
            return '-';
        }
        return (string) $valor;
    }

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
                    $viejo = $this->aTexto($original[$campo] ?? null);
                    $nuevoTexto = $this->aTexto($nuevo);
                    $partes[] = $campo . ': [' . $viejo . '] -> [' . $nuevoTexto . ']';
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
