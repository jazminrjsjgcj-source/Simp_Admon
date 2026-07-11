<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de una propuesta regulatoria.
 *
 * Una propuesta es la puerta de entrada al Análisis de Impacto Regulatorio: al
 * enviarla a revisión recibe folio y entra en un procedimiento formal. Por eso no
 * puede tener huecos.
 *
 * Dos niveles de exigencia, como en el resto del sistema:
 *
 *   - BORRADOR: nombre y dependencia. Lo mínimo para que la propuesta exista y se
 *     reconozca en el listado (`nombre` además es NOT NULL en la base).
 *
 *   - ENVÍO (accion=enviar): se exige todo lo que sustenta la propuesta: qué tipo de
 *     norma es, por qué se regula, para cuándo, y si impacta al comercio (que es lo
 *     que determina si hace falta un AIR).
 *
 * Antes de esta clase la validación vivía en el controlador y solo pedía el nombre:
 * se podía enviar a revisión una propuesta prácticamente vacía.
 */
class PropuestaRegulatoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso se comprueba en el controlador.
        return true;
    }

    public function rules(): array
    {
        return $this->esEnvio()
            ? $this->reglasCompletas()
            : $this->reglasBorrador();
    }

    private function esEnvio(): bool
    {
        return $this->input('accion') === 'enviar';
    }

    /** Lo mínimo para guardar un borrador. */
    private function reglasBorrador(): array
    {
        return [
            // El nombre es NOT NULL en la base: sin él la propuesta no existe.
            'nombre'                     => 'required|string|max:500',
            'dependencia_id'             => 'required|exists:dependencias,id',

            'tipo_regulacion'            => 'nullable|string|max:100',
            'sector_id'                  => 'nullable|exists:sectores_scian,id',
            'subsector_id'               => 'nullable|exists:subsectores_scian,id',
            'fecha_tentativa'            => 'nullable|date',
            'justificacion'              => 'nullable|string',
            'poblacion_afectada'         => 'nullable|string|max:255',
            'impacta_comercio_inversion' => 'nullable|boolean',
            'genera_costos_burocraticos' => 'nullable|boolean',
            'impacta_tramites_existentes'=> 'nullable|boolean',
        ];
    }

    /** Todo lo que la propuesta necesita para poder enviarse a revisión. */
    private function reglasCompletas(): array
    {
        return array_merge($this->reglasBorrador(), [
            // ── Qué se propone ──
            'tipo_regulacion' => 'required|string|max:100',

            // ── Por qué se propone ──
            // La justificación es el sustento de la propuesta: sin ella no hay nada
            // que revisar.
            'justificacion'   => 'required|string|min:20',

            // ── Para cuándo ──
            'fecha_tentativa' => 'required|date',

            // ── Impacto ──
            // Esta declaración es la que decide si la propuesta requiere un Análisis
            // de Impacto Regulatorio: no puede quedar sin responder.
            'impacta_comercio_inversion' => 'required|boolean',
            'genera_costos_burocraticos' => 'required|boolean',
        ]);
    }

    /** Mensajes claros, no el genérico "El campo X es obligatorio". */
    public function messages(): array
    {
        return [
            'nombre.required'         => 'Escriba el nombre de la regulación propuesta.',
            'dependencia_id.required' => 'Seleccione la dependencia que propone la regulación.',

            'tipo_regulacion.required' => 'Indique el tipo de regulación (reglamento, acuerdo, lineamiento...).',

            'justificacion.required' => 'Explique por qué se propone esta regulación.',
            'justificacion.min'      => 'La justificación es demasiado breve: explique el problema que se busca resolver.',

            'fecha_tentativa.required' => 'Indique la fecha tentativa de publicación.',

            'impacta_comercio_inversion.required' => 'Indique si la propuesta impacta al comercio o la inversión (de esto depende si requiere un AIR).',
            'genera_costos_burocraticos.required' => 'Indique si la propuesta genera costos burocráticos.',
        ];
    }
}
