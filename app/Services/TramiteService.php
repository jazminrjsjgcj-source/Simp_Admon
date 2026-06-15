<?php

namespace App\Services;

use App\Models\Tramite;
use App\Models\Requisito;
use Illuminate\Support\Facades\DB;

/**
 * Concentra la lógica de creación de un trámite.
 *
 * Antes esta lógica vivía dentro de TramiteController::store, pegada al
 * objeto $request. Al extraerla aquí, puede reutilizarse desde:
 *   - El formulario normal de trámites (TramiteController).
 *   - El wizard de agenda, camino B (crear un trámite desde cero cuando el
 *     trámite a mejorar todavía no existe en el catálogo).
 *
 * El método crear() recibe DATOS LIMPIOS (arrays), no el request, para no
 * depender del HTTP. Toda la operación va dentro de una transacción: si algo
 * falla a la mitad, no queda un trámite a medio crear.
 */
class TramiteService
{
    public function __construct(
        private CostoBurocraticoService $costoService,
    ) {}

    /**
     * Crea un trámite con toda su lógica asociada.
     *
     * @param array $datos        Campos del trámite ya validados.
     * @param array $derechos     Lista de conceptos de derechos [{concepto, monto}].
     * @param array $requisitos   Lista de requisitos del formulario.
     * @param array $fichaPortal  Datos de la ficha ciudadana (puede ir vacío).
     * @param bool  $esEnvio      Si se envía a revisión (cambia el estatus inicial).
     */
    public function crear(
        array $datos,
        array $derechos = [],
        array $requisitos = [],
        array $fichaPortal = [],
        bool $esEnvio = false,
    ): Tramite {
        return DB::transaction(function () use ($datos, $derechos, $requisitos, $fichaPortal, $esEnvio) {

            // El total de derechos alimenta monto_derechos (entra al costo).
            $datos['monto_derechos'] = collect($derechos)->sum('monto');

            // Cálculo del costo burocrático a partir de los datos.
            $datos = array_merge($datos, Tramite::calcularCostoDesde($datos));

            // Estatus inicial según borrador o envío a revisión.
            $datos['estatus'] = $esEnvio
                ? Tramite::ESTATUS_EN_OBSERVACION
                : Tramite::ESTATUS_BORRADOR;

            $tramite = Tramite::create($datos);

            $this->sincronizarDerechos($tramite, $derechos);
            $this->sincronizarRequisitos($tramite, $requisitos);
            $this->sincronizarFichaPortal($tramite, $fichaPortal);

            // Generar homoclave si hay dependencia y unidad administrativa.
            if (empty($tramite->homoclave) && $tramite->dependencia_id && $tramite->unidad_id) {
                $tramite->update(['homoclave' => $tramite->generarHomoclave()]);
            }

            // Recalcular el costo burocrático con los requisitos ya guardados.
            $this->costoService->recalcularYGuardar($tramite->fresh('requisitos'));

            return $tramite;
        });
    }

    /* ----------------------------------------------------------------------
     | Sincronización (autocontenida, recibe datos limpios)
     |----------------------------------------------------------------------*/

    /** Reemplaza los conceptos de derechos del trámite. */
    private function sincronizarDerechos(Tramite $tramite, array $derechos): void
    {
        $tramite->derechos()->delete();
        foreach ($derechos as $d) {
            // Solo conceptos con nombre.
            $concepto = trim($d['concepto'] ?? '');
            if ($concepto === '') {
                continue;
            }
            $tramite->derechos()->create([
                'concepto' => $concepto,
                'monto'    => floatval($d['monto'] ?? 0),
            ]);
        }
    }

    /** Crea/actualiza/elimina los requisitos del trámite. */
    private function sincronizarRequisitos(Tramite $tramite, array $enviados): void
    {
        $existentes   = $tramite->requisitos->pluck('id')->toArray();
        $actualizados = [];

        foreach ($enviados as $i => $req) {
            if (empty($req['nombre'])) {
                continue;
            }

            $datos = [
                'orden'           => $i + 1,
                'nombre'          => $req['nombre'],
                'original'        => !empty($req['original']),
                'copia'           => !empty($req['copia']),
                'dias_estimados'  => $req['dias']  ?? 0,
                'horas_estimadas' => $req['horas'] ?? 0,
                'observaciones'   => $req['observaciones'] ?? null,
            ];

            if (!empty($req['id'])) {
                Requisito::where('id', $req['id'])->update($datos);
                $actualizados[] = (int) $req['id'];
            } else {
                $nuevo = $tramite->requisitos()->create($datos);
                $actualizados[] = $nuevo->id;
            }
        }

        $eliminar = array_diff($existentes, $actualizados);
        if ($eliminar) {
            Requisito::whereIn('id', $eliminar)->delete();
        }
    }

    /** Guarda la ficha ciudadana (portal) si vienen datos de contenido. */
    private function sincronizarFichaPortal(Tramite $tramite, array $fichaPortal): void
    {
        // requiere_cita siempre viene (boolean); no cuenta como contenido real.
        $contenido = collect($fichaPortal)->except('requiere_cita')->filter()->isNotEmpty();
        if (!$contenido) {
            return;
        }

        $tramite->fichaPortal()->updateOrCreate(
            ['tramite_id' => $tramite->id],
            $fichaPortal
        );
    }
}
