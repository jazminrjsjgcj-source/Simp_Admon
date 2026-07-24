<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquesta la respuesta a una pregunta de datos en lenguaje natural:
 *
 *   1) TraductorConsultaDatosService: pregunta -> receta (IA, o null si no aplica).
 *   2) Permiso: la entidad de la receta debe estar permitida para el rol del usuario.
 *   3) ConsultaDatosService: ejecuta la receta ya validada y devuelve el número/lista.
 *
 * Devuelve ['receta' => ..., 'resultado' => ...] para que la vista muestre TANTO
 * la interpretación ("Entendí: contar trámites en borrador") COMO el resultado.
 * Devuelve null en cuanto algo no cuadra —no es de datos, sin permiso, receta
 * inválida o error—; en ese caso el buscador sigue con su búsqueda de texto normal.
 *
 * Mantiene al controlador delgado: él solo llama a responder().
 */
class ConsultaDatosDesdeTextoService
{
    public function __construct(
        private TraductorConsultaDatosService $traductor,
        private ConsultaDatosService $ejecutor,
    ) {}

    /**
     * @return array{receta: array, resultado: array}|null
     */
    public function responder(string $pregunta, ?User $usuario): ?array
    {
        $receta = $this->traductor->traducir($pregunta);
        if ($receta === null) {
            return null;
        }

        // Permiso: si la entidad declara uno, el usuario debe tenerlo. Sin permiso
        // NO se responde con datos (cae a la búsqueda normal), igual que el resto
        // del sistema respeta lo que cada rol puede ver.
        $permiso = config("consulta_datos.entidades.{$receta['entidad']}.permiso");
        if ($permiso !== null && ($usuario === null || ! $usuario->tienePermiso($permiso))) {
            return null;
        }

        try {
            $resultado = $this->ejecutor->ejecutar($receta);
        } catch (Throwable $e) {
            // La receta pidió algo fuera de la lista blanca (la IA se equivocó).
            // Se registra y se cae a la búsqueda normal; nunca se rompe.
            Log::warning('Consulta de datos: receta rechazada por la lista blanca.', [
                'error'  => $e->getMessage(),
                'receta' => $receta,
            ]);
            return null;
        }

        return ['receta' => $receta, 'resultado' => $resultado];
    }
}
