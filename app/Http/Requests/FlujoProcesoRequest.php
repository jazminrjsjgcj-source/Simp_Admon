<?php

namespace App\Http\Requests;

use App\Models\Flujo\FlujoRuta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación del flujo de un proceso.
 *
 * Además de comprobar tipos y longitudes, verifica que el flujo tenga sentido como
 * red: que una decisión declare sus dos salidas, que las rutas apunten a elementos
 * que existen en el mismo envío, y que ninguna rama quede sin salida. Un flujo con
 * esos huecos se guarda sin error y produce un diagrama partido, que es peor que un
 * rechazo: parece correcto y describe un proceso imposible.
 */
class FlujoProcesoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('digitalizacion.reingenieria') ?? false;
    }

    public function rules(): array
    {
        $tiposResolutivo   = array_keys(config('punta.flujo.tipos_resolutivo', []));
        $tiposParticipante = array_keys(config('punta.flujo.tipos_participante', []));
        $destinos          = array_keys(config('punta.flujo.destinos_ruta', []));
        $accionesPago      = array_keys(config('punta.flujo.acciones_pago', []));

        return [
            // ── Datos generales ──
            'proceso_nombre'    => 'required|string|max:300',
            'resolutivo_tipo'   => 'nullable|in:' . implode(',', $tiposResolutivo),
            'resolutivo_nombre' => 'nullable|string|max:300',
            'inicia_con'        => 'nullable|string|max:500',
            'termina_con'       => 'nullable|string|max:500',

            // ── Participantes ──
            'participantes'          => 'required|array|min:1',
            'participantes.*.clave'  => 'required|string|max:40',
            'participantes.*.nombre' => 'required|string|max:200',
            'participantes.*.tipo'   => 'required|in:' . implode(',', $tiposParticipante),

            // ── Resultados finales ──
            'resultados'          => 'required|array|min:1',
            'resultados.*.clave'  => 'required|string|max:40',
            'resultados.*.nombre' => 'required|string|max:200',

            // ── Fases ──
            'fases'         => 'required|array|min:1',
            'fases.*.clave' => 'required|string|max:40',
            'fases.*.nombre'=> 'required|string|max:200',
            'fases.*.nota'  => 'nullable|string|max:2000',

            // ── Actividades ──
            'fases.*.actividades'                 => 'required|array|min:1',
            'fases.*.actividades.*.clave'         => 'required|string|max:40',
            'fases.*.actividades.*.descripcion'   => 'required|string|max:500',
            'fases.*.actividades.*.participante'  => 'required|string|max:40',
            'fases.*.actividades.*.tiene_decision'=> 'nullable|boolean',
            'fases.*.actividades.*.que_revisa'    => 'nullable|string|max:500|required_if:fases.*.actividades.*.tiene_decision,1',
            'fases.*.actividades.*.estado'        => 'nullable|string|max:60',

            // ── Rutas ──
            'fases.*.actividades.*.rutas'                     => 'nullable|array',
            'fases.*.actividades.*.rutas.*.condicion'          => 'required|in:siempre,correcto,incorrecto',
            'fases.*.actividades.*.rutas.*.destino_tipo'       => 'required|in:' . implode(',', $destinos),
            'fases.*.actividades.*.rutas.*.destino_actividad'  => 'nullable|string|max:40',
            'fases.*.actividades.*.rutas.*.resultado'          => 'nullable|string|max:40',

            // ── Pago ──
            'fases.*.actividades.*.pago.acciones'      => 'nullable|array',
            'fases.*.actividades.*.pago.acciones.*'    => 'in:' . implode(',', $accionesPago),
            'fases.*.actividades.*.pago.derecho_id'    => 'nullable|integer|exists:tramite_derechos,id',
            'fases.*.actividades.*.pago.participante'  => 'nullable|string|max:40',

            // ── Nota ──
            'fases.*.actividades.*.nota.titulo' => 'nullable|string|max:200',
            'fases.*.actividades.*.nota.texto'  => 'nullable|string|max:2000',
            'fases.*.actividades.*.nota.aplica' => 'nullable|in:actividad,fase',
        ];
    }

    public function messages(): array
    {
        return [
            'participantes.required' => 'Un proceso necesita al menos un participante.',
            'resultados.required'    => 'Indique al menos una forma en que puede terminar el proceso.',
            'fases.required'         => 'Un proceso necesita al menos una fase.',
            'fases.*.actividades.required'   => 'Cada fase necesita al menos una actividad.',
            'fases.*.actividades.*.que_revisa.required_if' => 'Si la actividad revisa algo, indique qué revisa.',
        ];
    }

    /**
     * Comprobaciones que no se pueden expresar campo a campo, porque miran el flujo
     * como conjunto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $datos = $this->all();

            $participantes = collect($datos['participantes'] ?? [])->pluck('clave')->filter()->all();
            $resultados    = collect($datos['resultados'] ?? [])->pluck('clave')->filter()->all();

            $actividades = [];
            foreach ($datos['fases'] ?? [] as $f) {
                foreach ($f['actividades'] ?? [] as $a) {
                    if (! empty($a['clave'])) {
                        $actividades[] = $a['clave'];
                    }
                }
            }

            foreach ($datos['fases'] ?? [] as $i => $f) {
                foreach ($f['actividades'] ?? [] as $j => $a) {
                    $campo   = "fases.{$i}.actividades.{$j}";
                    $nombre  = $a['descripcion'] ?? 'sin nombre';
                    $rutas   = $a['rutas'] ?? [];
                    $decide  = ! empty($a['tiene_decision']);

                    // El participante tiene que ser uno de los declarados arriba.
                    if (! empty($a['participante']) && ! in_array($a['participante'], $participantes, true)) {
                        $v->errors()->add("{$campo}.participante",
                            "La actividad «{$nombre}» está asignada a un participante que no existe.");
                    }

                    // Una actividad que revisa tiene DOS salidas. Con una sola, la mitad
                    // del proceso desaparece del diagrama sin que nada avise.
                    if ($decide) {
                        $condiciones = collect($rutas)->pluck('condicion')->all();

                        foreach (['correcto' => 'cuando sale bien', 'incorrecto' => 'cuando sale mal'] as $cond => $texto) {
                            if (! in_array($cond, $condiciones, true)) {
                                $v->errors()->add("{$campo}.rutas",
                                    "La actividad «{$nombre}» revisa algo: falta decir qué pasa {$texto}.");
                            }
                        }
                    }

                    foreach ($rutas as $k => $r) {
                        $campoRuta = "{$campo}.rutas.{$k}";

                        // Ir "a otra actividad" exige decir a cuál, y que exista.
                        if (($r['destino_tipo'] ?? '') === FlujoRuta::DESTINO_ACTIVIDAD) {
                            if (empty($r['destino_actividad'])) {
                                $v->errors()->add("{$campoRuta}.destino_actividad",
                                    "Indique a qué actividad continúa «{$nombre}».");
                            } elseif (! in_array($r['destino_actividad'], $actividades, true)) {
                                $v->errors()->add("{$campoRuta}.destino_actividad",
                                    "«{$nombre}» apunta a una actividad que no existe.");
                            }
                        }

                        // Terminar el proceso exige decir con qué resultado: si no, el
                        // diagrama no puede distinguir un final de otro.
                        if (($r['destino_tipo'] ?? '') === FlujoRuta::DESTINO_FIN) {
                            if (empty($r['resultado'])) {
                                $v->errors()->add("{$campoRuta}.resultado",
                                    "Indique con qué resultado termina el proceso en «{$nombre}».");
                            } elseif (! in_array($r['resultado'], $resultados, true)) {
                                $v->errors()->add("{$campoRuta}.resultado",
                                    "«{$nombre}» termina con un resultado que no está declarado.");
                            }
                        }
                    }
                }
            }
        });
    }
}
