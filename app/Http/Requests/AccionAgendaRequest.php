<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de una acción de la Agenda de Simplificación y Digitalización.
 *
 * Hay dos niveles de exigencia, igual que en los trámites:
 *
 *   - BORRADOR (guardar sin enviar): solo se pide la descripción. Es trabajo en
 *     proceso y tiene que poder guardarse a medias.
 *
 *   - ENVÍO A REVISIÓN (accion=enviar): se exige todo. Al enviarse, la acción recibe
 *     folio y pasa a ser un compromiso formal de la dependencia: no puede tener
 *     huecos.
 *
 * Antes de esta clase la validación vivía en el controlador y casi todo era
 * opcional: se podía enviar a revisión una acción prácticamente vacía.
 */
class AccionAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso se comprueba en el controlador (política de la agenda).
        return true;
    }

    public function rules(): array
    {
        return $this->esEnvio()
            ? $this->reglasCompletas()
            : $this->reglasBorrador();
    }

    /** ¿Se está enviando a revisión, o solo guardando el borrador? */
    private function esEnvio(): bool
    {
        return $this->input('accion') === 'enviar';
    }

    /**
     * Lo mínimo para guardar un borrador: que la acción tenga al menos una
     * descripción con la que reconocerla en el listado.
     */
    private function reglasBorrador(): array
    {
        return [
            // Mínimos con los que una acción EXISTE, aunque esté a medias: a qué
            // trámite se refiere, de qué dependencia es, y una descripción con la que
            // reconocerla en el listado. Sin ellos la acción quedaría "en el aire".
            'descripcion'      => 'required|string|min:10',
            'dependencia_id'   => 'required|exists:dependencias,id',

            // El trámite es obligatorio, SALVO cuando el wizard va por el camino de
            // registrarlo desde cero: ahí el trámite se crea en el momento y todavía
            // no tiene id que enviar.
            'tramite_id'       => 'required_unless:modo_tramite,nuevo|nullable|exists:tramites,id',

            // El alcance también es un mínimo, aunque sea un borrador: la columna
            // `tipo` de acciones_agenda es NOT NULL, así que sin él la acción no se
            // puede ni insertar. Además de él depende el tipo de folio (SIM/DIG/SYD).
            'alcance'          => 'required|in:simplificacion,digitalizacion,ambas',
            'tipo'             => 'nullable|in:simplificacion,digitalizacion,ambas',
            'unidad_id'        => 'nullable|exists:unidades_administrativas,id',
            'fecha_inicio'     => 'nullable|date',
            'fecha_compromiso' => 'nullable|date|after_or_equal:fecha_inicio',
            'nivel_actual'     => 'nullable|integer|min:0|max:5',
            'nivel_meta'       => 'nullable|integer|min:0|max:5',
        ];
    }

    /** Todo lo que una acción necesita para poder enviarse a revisión. */
    private function reglasCompletas(): array
    {
        return array_merge($this->reglasBorrador(), [
            // ── Sobre qué trámite se actúa ──
            // Una acción de agenda es una mejora SOBRE un trámite. Si el wizard va
            // por el camino de "registrar trámite nuevo", el trámite se crea en el
            // momento y este campo no aplica: por eso required_unless.
            'tramite_id' => 'required_unless:modo_tramite,nuevo|nullable|exists:tramites,id',

            // ── Qué se va a hacer ──
            'descripcion' => 'required|string|min:10',
            'meta'        => 'required|string|min:5',

            // ── Quién responde y cuándo ──
            'dependencia_id'   => 'required|exists:dependencias,id',
            'responsable'      => 'required|string|max:255',
            'fecha_inicio'     => 'required|date',
            'fecha_compromiso' => 'required|date|after_or_equal:fecha_inicio',
        ], $this->reglasDiagnosticoDelTramite());
    }

    /**
     * Diagnóstico del trámite (cómo opera HOY): visitas, áreas, tiempos y nivel de
     * digitalización. Es la línea base contra la que se medirá la mejora.
     *
     * Solo se exigen cuando el wizard va por el camino de REGISTRAR UN TRÁMITE NUEVO
     * desde la agenda (modo_tramite = nuevo). Si se elige un trámite que ya existe,
     * estos datos vienen de su ficha y el formulario los hereda en solo lectura: no
     * hay nada que capturar y exigirlos bloquearía el envío sin motivo.
     */
    private function reglasDiagnosticoDelTramite(): array
    {
        // Se exige el campo únicamente si se está creando el trámite desde aquí.
        $siEsNuevo = 'required_if:modo_tramite,nuevo|nullable';

        return [
            'tramite_visitas_requeridas'    => $siEsNuevo . '|integer|min:0',
            'tramite_num_areas'             => $siEsNuevo . '|integer|min:0',
            'tramite_tiempo_traslado_horas' => $siEsNuevo . '|integer|min:0',
            'tramite_tiempo_traslado_min'   => $siEsNuevo . '|integer|min:0|max:59',
            'tramite_tiempo_espera_horas'   => $siEsNuevo . '|integer|min:0',
            'tramite_tiempo_espera_min'     => $siEsNuevo . '|integer|min:0|max:59',
            'tramite_tiempo_atencion_horas' => $siEsNuevo . '|integer|min:0',
            'tramite_tiempo_atencion_min'   => $siEsNuevo . '|integer|min:0|max:59',
            'tramite_nivel_digitalizacion'  => $siEsNuevo . '|integer|min:0|max:5',

            // Las áreas participantes solo se piden si hay más de un área.
            'tramite_areas_participantes'   => 'nullable|string|max:500',
            'tramite_redundantes_detalle'   => 'nullable|string',
        ];
    }

    /** Mensajes en lenguaje claro, no el genérico "El campo X es obligatorio". */
    public function messages(): array
    {
        return [
            'tramite_id.required'        => 'Seleccione el trámite o servicio sobre el que se actúa.',
            'tramite_id.required_unless' => 'Seleccione el trámite o servicio sobre el que se actúa.',
            'tramite_id.exists'          => 'El trámite seleccionado no existe.',

            'alcance.required' => 'Indique el alcance: simplificación, digitalización o ambas.',
            'alcance.in'       => 'El alcance debe ser simplificación, digitalización o ambas.',

            'descripcion.required' => 'Describa la acción de mejora.',
            'descripcion.min'      => 'La descripción es demasiado breve: explique en qué consiste la acción.',

            'meta.required' => 'Indique la meta: qué se busca lograr con esta acción.',
            'meta.min'      => 'La meta es demasiado breve.',

            'dependencia_id.required' => 'Seleccione la dependencia responsable.',
            'responsable.required'    => 'Indique quién es la persona responsable de la acción.',

            'fecha_inicio.required'            => 'Indique la fecha de inicio.',
            'fecha_compromiso.required'        => 'Indique la fecha de compromiso.',
            'fecha_compromiso.after_or_equal'  => 'La fecha de compromiso no puede ser anterior a la de inicio.',

            // ── Diagnóstico del trámite (solo al registrarlo desde la agenda) ──
            'tramite_visitas_requeridas.required_if'    => 'Indique cuántas visitas requiere el trámite.',
            'tramite_num_areas.required_if'             => 'Indique cuántas áreas intervienen.',
            'tramite_tiempo_traslado_horas.required_if' => 'Indique el tiempo de traslado.',
            'tramite_tiempo_espera_horas.required_if'   => 'Indique el tiempo de espera.',
            'tramite_tiempo_atencion_horas.required_if' => 'Indique el tiempo de atención.',
            'tramite_nivel_digitalizacion.required_if'  => 'Indique el nivel de digitalización actual del trámite.',
        ];
    }
}
