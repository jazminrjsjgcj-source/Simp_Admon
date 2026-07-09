<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos del formulario de trámites en dos modos:
 *
 *   - Borrador (POST sin accion=enviar): solo nombre y dependencia son
 *     obligatorios; el resto puede quedar incompleto mientras el enlace
 *     elabora el trámite.
 *
 *   - Envío a revisión (POST con accion=enviar) y Actualización (PUT):
 *     se aplican las reglas completas, incluyendo el patrón de caracteres
 *     para servidor_publico y la verificación de tipo_tramite_id.
 *
 * Al usar esta clase como type-hint en store() y update(), Laravel ejecuta
 * la validación automáticamente antes de entrar al método. El controlador
 * recibe datos ya limpios vía $request->validated() y nunca llama validate().
 */
class TramiteRequest extends FormRequest
{
    /**
     * Topes máximos para los campos numéricos del trámite.
     *
     * Los valores viven en config/punta.php ('topes_tramite') como FUENTE ÚNICA
     * DE VERDAD, compartida con la validación de frontend (window.PUNTA.topes).
     * Aquí solo se leen; no se redefinen, para no duplicar los números.
     */
    private function tope(string $clave): int
    {
        return (int) config("punta.topes_tramite.{$clave}");
    }

    /**
     * Cualquier usuario autenticado puede enviar el formulario.
     *
     * Los permisos de negocio (quién puede editar qué trámite) se revisan
     * en el controlador, donde se tiene acceso al modelo ya cargado por
     * route model binding.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Reglas de validación según el contexto de la petición.
     *
     *   PUT/PATCH  → actualización: siempre reglas completas.
     *   POST + accion=enviar → reglas completas.
     *   POST sin accion=enviar → reglas mínimas (borrador).
     */
    public function rules(): array
    {
        $esActualizacion = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $esEnvio         = $this->input('accion') === 'enviar';

        return ($esActualizacion || $esEnvio)
            ? $this->reglasCompletas()
            : $this->reglasBase();
    }

    /**
     * Calcula el tope de `plazo_resolucion_cantidad` según la unidad elegida,
     * de modo que el plazo nunca supere MAX_PLAZO_ANIOS sin importar si se
     * captura en años, meses o días.
     *
     * Ejemplo con MAX_PLAZO_ANIOS = 2: en años el tope es 2; en meses, 24;
     * en días (hábiles o naturales), 730.
     */
    private function reglaPlazoMaximo(): string
    {
        $unidad = $this->input('plazo_resolucion_unidad', 'habiles');

        $tope = match ($unidad) {
            'anios' => $this->tope('plazo_anios'),
            'meses' => $this->tope('plazo_anios') * 12,
            default => $this->tope('plazo_anios') * 365,
        };

        return 'max:' . $tope;
    }

    /**
     * Mensajes de error en español para los campos más importantes.
     *
     * Laravel genera mensajes genéricos en inglés; estos los reemplazan
     * con textos que el usuario final puede entender directamente.
     */
    public function messages(): array
    {
        return [
            'nombre_oficial.required' => 'El nombre oficial del trámite es obligatorio.',
            'dependencia_id.required' => 'Debe seleccionar la dependencia responsable.',
            'dependencia_id.exists'   => 'La dependencia seleccionada no existe en el catálogo.',
            'servidor_publico.regex'  => 'El nombre del servidor público solo puede contener letras, espacios y puntuación básica.',
            'tipo_tramite_id.exists'  => 'El tipo de trámite seleccionado no existe en el catálogo.',
            'naturaleza.required'     => 'Debe indicar si registrará un trámite o un servicio.',
            'naturaleza.in'           => 'La naturaleza debe ser "tramite" o "servicio".',
            'tipo_servicio.required_if'=> 'Seleccione el tipo de servicio.',
            'tipo_servicio.in'        => 'El tipo de servicio seleccionado no es válido.',

            // ── Obligatorios pasos 1 y 2 (mensajes claros para el usuario) ──
            'tipo_tramite_id.required_if' => 'Seleccione el tipo de trámite.',
            'unidad_id.required'          => 'Seleccione la unidad administrativa.',
            'objetivo.required'           => 'Describa el objetivo del trámite o servicio.',
            'dirigido_a.required'         => 'Indique a quién va dirigido.',
            'etapa_operacion.required_if' => 'Seleccione la etapa de operación de la persona moral.',
            'frecuencia.required'         => 'Seleccione la frecuencia.',
            'volumen_anual.required'      => 'Indique el volumen anual estimado.',
            'sector_id.required'          => 'Seleccione el sector económico.',
            'subsector_id.required'       => 'Seleccione el subsector económico.',

            // ── Paso 3 ──
            'poblacion_objetivo.required' => 'Indique la población objetivo.',
            'grupos_atencion.required'    => 'Seleccione al menos un grupo de atención prioritaria.',
            'grupos_atencion.min'         => 'Seleccione al menos un grupo de atención prioritaria.',
            'costo_monto.required_if'     => 'Ingrese el monto del costo público.',
            'costo_monto.min'             => 'El monto del costo público debe ser mayor a 0.',
            'nivel_digitalizacion.required' => 'Calcule el nivel de digitalización con el cuestionario oficial.',

            'volumen_anual.max'             => 'El volumen anual supera el máximo permitido. Verifique el dato capturado.',
            'plazo_resolucion_cantidad.max' => 'El plazo de resolución supera el máximo permitido (' . $this->tope('plazo_anios') . ' años). Verifique el dato.',
            'num_areas.max'                 => 'El número de áreas supera el máximo razonable. Verifique el dato.',
            'visitas_requeridas.max'        => 'El número de visitas supera el máximo razonable. Verifique el dato.',
            'copias_cantidad.max'           => 'El número de copias supera el máximo razonable. Verifique el dato.',
            'tiempo_traslado_horas.max'     => 'Las horas de traslado superan el máximo permitido.',
            'tiempo_espera_horas.max'       => 'Las horas de espera superan el máximo permitido.',
            'tiempo_atencion_horas.max'     => 'Las horas de atención superan el máximo permitido.',
            'costo_tipo.required' => 'Seleccione el tipo de costo público.',
        ];
    }

    // ─── Conjuntos de reglas ─────────────────────────────────────────────────

    /**
     * 26 campos que se validan igual en borrador y en envío completo.
     *
     * Es la fuente única de verdad: cualquier cambio aquí aplica a ambos
     * modos sin posibilidad de que se desincronicen. No incluye
     * tipo_tramite_id ni el regex de servidor_publico — esos se agregan
     * solo al enviar o actualizar (ver reglasCompletas).
     */
    /**
     * Bug 48: los campos de tiempo pueden llegar con ceros iniciales ("011")
     * que la regla `integer` rechaza. Los convertimos a int antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $camposTiempo = [
            'tiempo_traslado_horas', 'tiempo_traslado_min',
            'tiempo_espera_horas',   'tiempo_espera_min',
            'tiempo_atencion_horas', 'tiempo_atencion_min',
            'copias_cantidad', 'volumen_anual', 'num_areas',
            'visitas_requeridas', 'plazo_resolucion_cantidad',
        ];

        $limpios = [];
        foreach ($camposTiempo as $campo) {
            if ($this->has($campo) && $this->input($campo) !== null) {
                $limpios[$campo] = (int) $this->input($campo);
            }
        }

        if ($limpios) {
            $this->merge($limpios);
        }
    }

    private function reglasBase(): array
    {
        return [
            'nombre_oficial'            => 'required|string|max:500',
            // #42/#45/#46: naturaleza faltaba en reglasBase(). Sin esta regla,
            // Laravel's validated() nunca incluía el campo al guardar un
            // borrador, y el modelo caía al default de la BD ('tramite').
            // Un SERVICIO guardado como borrador terminaba registrado como
            // trámite, y al editarlo después el wizard restauraba la
            // naturaleza incorrecta, escondiendo los campos de servicio y
            // perdiendo la información ya capturada. naturaleza siempre se
            // elige en el paso 1 del wizard (radio con default), así que es
            // seguro exigirla también en borrador.
            'naturaleza'                => 'required|in:tramite,servicio',
            'homoclave'                 => 'nullable|string|max:50',
            'dependencia_id'            => 'required|exists:dependencias,id',
            'unidad_id'                 => 'nullable|exists:unidades_administrativas,id',
            'sector_id'                 => 'nullable|exists:sectores_scian,id',
            'subsector_id'              => 'nullable|exists:subsectores_scian,id',
            'servidor_publico'          => 'nullable|string|max:255',
            'objetivo'                  => 'nullable|string',
            'dirigido_a'                => 'nullable|in:fisica,moral,ambas',
            'frecuencia'                => 'nullable|in:Alta,Media,Baja,Eventual',
            'volumen_anual'             => 'nullable|integer|min:0|max:' . $this->tope('volumen_anual'),
            'monto_derechos'            => 'nullable|numeric|min:0',
            'plazo_resolucion_cantidad' => ['nullable', 'integer', 'min:0', $this->reglaPlazoMaximo()],
            'plazo_resolucion_unidad'   => 'nullable|in:habiles,naturales,meses,anios',
            'salario_hora_w'            => 'nullable|numeric|min:0',
            'copias_cantidad'           => 'nullable|integer|min:0|max:' . $this->tope('copias'),
            'copias_precio'             => 'nullable|numeric|min:0',
            'nivel_digitalizacion'      => 'nullable|integer|min:0|max:' . $this->tope('nivel_digital'),
            'visitas_requeridas'        => 'nullable|integer|min:0|max:' . $this->tope('visitas'),
            'num_areas'                 => 'nullable|integer|min:0|max:' . $this->tope('num_areas'),
            'areas_participantes'       => 'nullable|string|max:500',
            // #43: respaldo de servidor para la exclusividad de "No Aplica"
            // que ya se aplica en JS (partials/catalogos-tramite.blade.php).
            // Si alguien manda ambos por fuera del formulario normal (JS
            // deshabilitado, petición manual), el servidor lo rechaza.
            'grupos_atencion'           => ['nullable', 'array', function ($attribute, $value, $fail) {
                if (is_array($value) && count($value) > 1 && in_array('No Aplica', $value, true)) {
                    $fail('No puede seleccionar "No Aplica" junto con otros grupos de atención prioritaria.');
                }
            }],
            'tiempo_traslado_horas'     => 'nullable|integer|min:0|max:' . $this->tope('horas'),
            'tiempo_traslado_min'       => 'nullable|integer|min:0|max:' . $this->tope('minutos'),
            'tiempo_espera_horas'       => 'nullable|integer|min:0|max:' . $this->tope('horas'),
            'tiempo_espera_min'         => 'nullable|integer|min:0|max:' . $this->tope('minutos'),
            'tiempo_atencion_horas'     => 'nullable|integer|min:0|max:' . $this->tope('horas'),
            'tiempo_atencion_min'       => 'nullable|integer|min:0|max:' . $this->tope('minutos'),

            // Requisitos: tipo de presentación. El formulario envía checkboxes
            // (original / copia / digital) que pueden marcarse a la vez, así que
            // 'tipo' llega como arreglo. Opcional en borrador; cada elemento debe
            // ser uno de los valores permitidos. La regla '.*' valida cada casilla.
            'requisitos.*.tipo'   => 'nullable|array',
            'requisitos.*.tipo.*' => 'in:original,copia,digital',
        ];
    }

    /**
     * Reglas completas para envío a revisión y actualización.
     *
     * Extiende reglasBase() con los dos únicos campos que difieren:
     *   - tipo_tramite_id: verifica que el tipo exista en el catálogo.
     *   - naturaleza: tramite o servicio (obligatorio).
     *   - tipo_servicio: obligatorio solo cuando naturaleza=servicio,
     *     debe coincidir con un valor de config('punta.tipos_servicio').
     *   - servidor_publico: agrega el regex del config/validation_patterns.php
     *     para rechazar caracteres especiales no permitidos en el documento
     *     oficial (ej. @, #, $).
     */
    private function reglasCompletas(): array
    {
        $patronTexto    = config('validation_patterns.solo_texto.regex_php');
        $tiposServicio  = config('punta.tipos_servicio', []);

        return array_merge($this->reglasBase(), [
            // ── Paso 1: Identificación ──
            // Tipo de trámite: obligatorio solo cuando es trámite (para servicio
            // aplica tipo_servicio). required_if + nullable: exige si es trámite,
            // permite vacío si es servicio.
            'tipo_tramite_id'  => 'required_if:naturaleza,tramite|nullable|exists:tipos_tramite,id',
            'tipo_servicio'    => [
                'nullable',
                'required_if:naturaleza,servicio',
                'string',
                'max:200',
                'in:' . implode(',', $tiposServicio),
            ],
            'unidad_id'        => 'required|exists:unidades_administrativas,id',
            'servidor_publico' => 'nullable|string|max:255' . ($patronTexto ? '|regex:' . $patronTexto : ''),

            // ── Paso 2: Información general ──
            'objetivo'         => 'required|string',
            'dirigido_a'       => 'required|in:fisica,moral,ambas',
            // Etapa de operación: solo si el trámite va dirigido a persona moral
            // (o a ambas). Para persona física no aplica.
            'etapa_operacion'  => 'required_if:dirigido_a,moral,ambas',
            'frecuencia'       => 'required|in:Alta,Media,Baja,Eventual',
            'volumen_anual'    => 'required|integer|min:0|max:' . $this->tope('volumen_anual'),
            'sector_id'        => 'required|exists:sectores_scian,id',
            'subsector_id'     => 'required|exists:subsectores_scian,id',

            // ── Paso 3: Operación y costos ──
            'poblacion_objetivo' => 'required|string|max:500',
            'grupos_atencion'    => 'required|array|min:1',
            // Si el costo es "Con precio", el monto debe ser mayor a 0.
            // Solo se valida el monto cuando el costo es "Con precio". Para
            // Gratuito o "Con precio variable" se ignora (no se exige ni se
            // revisa el min), evitando el falso "debe ser mayor a 0" del 0.
            'costo_monto'        => 'exclude_unless:costo_tipo,con_costo|required|numeric|min:0.01',
            'nivel_digitalizacion' => 'required|integer|min:0|max:5',
            // ── Requisitos (paso 4) ──
            // Al enviar, cada requisito con nombre debe declarar al menos un tipo
            // de presentación (arreglo no vacío). Cada elemento debe ser válido.
            'requisitos.*.tipo'   => 'required_with:requisitos.*.nombre|array',
            'requisitos.*.tipo.*' => 'in:original,copia,digital',
            'costo_tipo' => 'required|in:gratuito,con_costo,con_costo_variable',
        ]);
    }
}