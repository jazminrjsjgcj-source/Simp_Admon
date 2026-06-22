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
    private function reglasBase(): array
    {
        return [
            'nombre_oficial'            => 'required|string|max:500',
            'homoclave'                 => 'nullable|string|max:50',
            'dependencia_id'            => 'required|exists:dependencias,id',
            'unidad_id'                 => 'nullable|exists:unidades_administrativas,id',
            'sector_id'                 => 'nullable|exists:sectores_scian,id',
            'subsector_id'              => 'nullable|exists:subsectores_scian,id',
            'servidor_publico'          => 'nullable|string|max:255',
            'objetivo'                  => 'nullable|string',
            'dirigido_a'                => 'nullable|in:fisica,moral,ambas',
            'volumen_anual'             => 'nullable|integer|min:0',
            'monto_derechos'            => 'nullable|numeric|min:0',
            'plazo_resolucion_cantidad' => 'nullable|integer|min:0',
            'plazo_resolucion_unidad'   => 'nullable|in:habiles,naturales,meses,anios',
            'salario_hora_w'            => 'nullable|numeric|min:0',
            'copias_cantidad'           => 'nullable|integer|min:0',
            'copias_precio'             => 'nullable|numeric|min:0',
            'nivel_digitalizacion'      => 'nullable|integer|min:0|max:5',
            'visitas_requeridas'        => 'nullable|integer|min:0',
            'num_areas'                 => 'nullable|integer|min:0',
            'areas_participantes'       => 'nullable|string|max:500',
            'tiempo_traslado_horas'     => 'nullable|integer|min:0',
            'tiempo_traslado_min'       => 'nullable|integer|min:0|max:59',
            'tiempo_espera_horas'       => 'nullable|integer|min:0',
            'tiempo_espera_min'         => 'nullable|integer|min:0|max:59',
            'tiempo_atencion_horas'     => 'nullable|integer|min:0',
            'tiempo_atencion_min'       => 'nullable|integer|min:0|max:59',
        ];
    }

    /**
     * Reglas completas para envío a revisión y actualización.
     *
     * Extiende reglasBase() con los dos únicos campos que difieren:
     *   - tipo_tramite_id: verifica que el tipo exista en el catálogo.
     *   - servidor_publico: agrega el regex del config/validation_patterns.php
     *     para rechazar caracteres especiales no permitidos en el documento
     *     oficial (ej. @, #, $).
     */
    private function reglasCompletas(): array
    {
        $patronTexto = config('validation_patterns.solo_texto.regex_php');

        return array_merge($this->reglasBase(), [
            'tipo_tramite_id'  => 'nullable|exists:tipos_tramite,id',
            'servidor_publico' => 'nullable|string|max:255' . ($patronTexto ? '|regex:' . $patronTexto : ''),
        ]);
    }
}
