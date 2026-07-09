<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Extrae los datos de la Ficha del Portal Ciudadano desde el request.
 *
 * Lo usan tanto el create/edit de trámite (TramiteController) como el wizard
 * de Agenda SyD al crear un trámite desde cero (AgendaController), para no
 * duplicar la lógica de lectura de los campos portal_*.
 */
trait ExtraeFichaPortal
{
    protected function extraerFichaPortal(Request $request): array
    {
        $camposPortal = [
            'nombre_ciudadano', 'homoclave_publica', 'tipo', 'descripcion',
            'documento_obtiene', 'casos_realizarse',
            'modalidad', 'canal_principal', 'costo_publico', 'forma_pago',
            'resultado', 'doc_resultado', 'medio_entrega', 'vigencia',
            'oficina', 'telefono', 'correo', 'enlace_cita',
            'direccion', 'url',
        ];

        $datos = [];
        foreach ($camposPortal as $campo) {
            $valor = $request->input('portal_' . $campo) ?? $request->input($campo);
            if ($valor !== null) {
                $datos[$campo] = $valor;
            }
        }

        if ($request->filled('portal_horario')) {
            $datos['horario'] = $request->input('portal_horario');
        }

        if ($request->filled('horarios_json')) {
            $decoded = json_decode($request->input('horarios_json'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datos['horarios_json'] = $decoded;
            }
        }

        $datos['requiere_cita'] = $request->boolean('requiere_cita');

        return $datos;
    }
}