<?php

namespace App\Services;

use App\Models\Flujo\FlujoActividad;
use App\Models\Flujo\FlujoFase;
use App\Models\Flujo\FlujoParticipante;
use App\Models\Flujo\FlujoResultado;
use App\Models\Flujo\FlujoRuta;
use App\Models\Reingenieria;
use Illuminate\Support\Facades\DB;

/**
 * Guarda el flujo completo de un proceso tal como llega del formulario.
 *
 * Se guarda ENTERO en cada envío, no por partes. Un flujo es una red: las rutas
 * apuntan a actividades y las actividades a participantes, así que guardar la mitad
 * dejaría rutas apuntando a cosas que ya no existen. Todo ocurre dentro de una
 * transacción: o queda el flujo nuevo completo, o queda el anterior intacto.
 *
 * El formulario identifica cada elemento con una CLAVE TEMPORAL (fase_1, act_3) en
 * lugar de un id de base de datos, porque al capturar se crean actividades que
 * todavía no existen y otras ya tienen que poder apuntarles. Aquí se traduce esa
 * clave al id real una vez creado el registro.
 */
class GuardarFlujoProcesoService
{
    /** Clave temporal del formulario → id real, por tipo de elemento. */
    private array $mapa = ['participante' => [], 'fase' => [], 'actividad' => [], 'resultado' => []];

    /**
     * @param  array<string, mixed>  $datos  ya validado por FlujoProcesoRequest
     */
    public function guardar(Reingenieria $reingenieria, array $datos): void
    {
        DB::transaction(function () use ($reingenieria, $datos) {
            $this->mapa = ['participante' => [], 'fase' => [], 'actividad' => [], 'resultado' => []];

            $reingenieria->update([
                'proceso_nombre'    => $datos['proceso_nombre'] ?? null,
                'resolutivo_tipo'   => $datos['resolutivo_tipo'] ?? null,
                'resolutivo_nombre' => $datos['resolutivo_nombre'] ?? null,
                'inicia_con'        => $datos['inicia_con'] ?? null,
                'termina_con'       => $datos['termina_con'] ?? null,
            ]);

            // Se borra lo anterior antes de escribir. Las claves foráneas están en
            // cascada, así que al borrar las fases se llevan sus actividades y rutas.
            $reingenieria->fases()->delete();
            $reingenieria->participantes()->delete();
            $reingenieria->resultados()->delete();

            $this->crearParticipantes($reingenieria, $datos['participantes'] ?? []);
            $this->crearResultados($reingenieria, $datos['resultados'] ?? []);
            $this->crearFasesYActividades($reingenieria, $datos['fases'] ?? []);

            // Las rutas van al final: una ruta puede apuntar a una actividad de una
            // fase posterior, que hasta ahora no existía.
            $this->crearRutas($datos['fases'] ?? []);
        });
    }

    private function crearParticipantes(Reingenieria $reingenieria, array $participantes): void
    {
        foreach (array_values($participantes) as $i => $p) {
            if (empty($p['nombre'])) {
                continue;
            }

            $registro = FlujoParticipante::create([
                'reingenieria_id' => $reingenieria->id,
                'nombre'          => $p['nombre'],
                'tipo'            => $p['tipo'] ?? 'otra',
                'orden'           => $i + 1,
            ]);

            $this->mapa['participante'][$p['clave'] ?? "p{$i}"] = $registro->id;
        }
    }

    private function crearResultados(Reingenieria $reingenieria, array $resultados): void
    {
        foreach (array_values($resultados) as $i => $r) {
            if (empty($r['nombre'])) {
                continue;
            }

            $registro = FlujoResultado::create([
                'reingenieria_id' => $reingenieria->id,
                'nombre'          => $r['nombre'],
                'orden'           => $i + 1,
            ]);

            $this->mapa['resultado'][$r['clave'] ?? "r{$i}"] = $registro->id;
        }
    }

    private function crearFasesYActividades(Reingenieria $reingenieria, array $fases): void
    {
        foreach (array_values($fases) as $i => $f) {
            if (empty($f['nombre'])) {
                continue;
            }

            $fase = FlujoFase::create([
                'reingenieria_id' => $reingenieria->id,
                'nombre'          => $f['nombre'],
                'nota'            => $f['nota'] ?? null,
                'orden'           => $i + 1,
            ]);

            $this->mapa['fase'][$f['clave'] ?? "f{$i}"] = $fase->id;

            foreach (array_values($f['actividades'] ?? []) as $j => $a) {
                if (empty($a['descripcion'])) {
                    continue;
                }

                $actividad = FlujoActividad::create([
                    'fase_id'         => $fase->id,
                    'participante_id' => $this->mapa['participante'][$a['participante'] ?? ''] ?? null,
                    'descripcion'     => $a['descripcion'],
                    'tiene_decision'  => (bool) ($a['tiene_decision'] ?? false),
                    'que_revisa'      => $a['que_revisa'] ?? null,
                    'detalle'         => $this->detalleDe($a),
                    'orden'           => $j + 1,
                ]);

                $this->mapa['actividad'][$a['clave'] ?? "a{$i}_{$j}"] = $actividad->id;
            }
        }
    }

    /**
     * Segunda pasada: ahora que todas las actividades existen, se crean las rutas.
     */
    private function crearRutas(array $fases): void
    {
        foreach ($fases as $f) {
            foreach ($f['actividades'] ?? [] as $a) {
                $id = $this->mapa['actividad'][$a['clave'] ?? ''] ?? null;

                if (! $id) {
                    continue;
                }

                foreach ($a['rutas'] ?? [] as $r) {
                    if (empty($r['destino_tipo'])) {
                        continue;
                    }

                    FlujoRuta::create([
                        'actividad_id'         => $id,
                        'condicion'            => $r['condicion'] ?? FlujoRuta::CONDICION_SIEMPRE,
                        'destino_tipo'         => $r['destino_tipo'],
                        'destino_actividad_id' => $this->mapa['actividad'][$r['destino_actividad'] ?? ''] ?? null,
                        'resultado_id'         => $this->mapa['resultado'][$r['resultado'] ?? ''] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Lo que va al JSON `detalle`: pago, nota y cambio de estado.
     *
     * Se omite lo vacío para no llenar la base de estructuras con todos los campos
     * en null, que además harían que `tienePago()` diera verdadero sin haber pago.
     *
     * @return array<string, mixed>|null
     */
    private function detalleDe(array $a): ?array
    {
        $detalle = [];

        if (! empty($a['pago']['acciones'])) {
            $detalle['pago'] = [
                'acciones'        => array_values($a['pago']['acciones']),
                // El importe no se copia: se referencia el concepto del catálogo del
                // trámite, para que el diagrama y el catálogo no puedan discrepar.
                'derecho_id'      => $a['pago']['derecho_id'] ?? null,
                'participante_id' => $this->mapa['participante'][$a['pago']['participante'] ?? ''] ?? null,
            ];
        }

        if (! empty($a['nota']['texto'])) {
            $detalle['nota'] = [
                'titulo' => $a['nota']['titulo'] ?? 'Nota',
                'texto'  => $a['nota']['texto'],
                'aplica' => $a['nota']['aplica'] ?? 'actividad',
            ];
        }

        if (! empty($a['estado'])) {
            $detalle['estado'] = $a['estado'];
        }

        return $detalle ?: null;
    }
}
