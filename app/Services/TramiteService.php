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
        array $procesos = [],
        bool $esEnvio = false,
    ): Tramite {
        return DB::transaction(function () use ($datos, $derechos, $requisitos, $fichaPortal, $procesos, $esEnvio) {

            // Separar los datos del fundamento jurídico: no son columnas de
            // `tramites`, van a la tabla fundamento_juridico. Puede haber varias
            // citas del catálogo (array $citas) más una captura manual de norma.
            $citas = $datos['citas'] ?? [];
            $fundamentoManual = [
                'normativa_nombre' => $datos['fundamento_normativa'] ?? $datos['normativa_nombre'] ?? null,
                'tipo_normativa'   => $datos['fundamento_tipo'] ?? $datos['tipo_normativa'] ?? null,
                'resumen'          => $datos['fundamento_resumen'] ?? $datos['resumen'] ?? null,
            ];
            unset($datos['citas'], $datos['regulacion_id'], $datos['articulo_fraccion'],
                  $datos['fundamento_normativa'], $datos['fundamento_tipo'],
                  $datos['fundamento_resumen']);

            // El total de derechos alimenta monto_derechos (entra al costo).
            // Convierte los derechos en UMA a pesos antes de sumar.
            $datos['monto_derechos'] = \App\Models\TramiteDerecho::totalEnPesos($derechos);

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
            $this->sincronizarProcesos($tramite, $procesos);
            $this->sincronizarFundamento($tramite, $citas, $fundamentoManual);

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
                'concepto'    => $concepto,
                'monto'       => floatval($d['monto'] ?? 0),
                'unidad'      => ($d['unidad'] ?? 'pesos') === 'UMA' ? 'UMA' : 'pesos',
                'es_variable' => !empty($d['es_variable']),
                'fj_norma'    => $d['fj_norma']    ?? null,
                'fj_capitulo' => $d['fj_capitulo'] ?? null,
                'fj_articulo' => $d['fj_articulo'] ?? null,
            ]);
        }
    }

    /**
     * Guarda el fundamento jurídico del trámite (tabla fundamento_juridico).
     * Acepta VARIAS citas del catálogo (cada una con regulacion_id y
     * articulo_fraccion) más, opcionalmente, una norma capturada a mano
     * (normativa_nombre, tipo_normativa, resumen).
     * Estrategia: borra los fundamentos previos y recrea todos.
     */
    /**
     * Método público para sincronizar el fundamento jurídico desde el controlador
     * (usado en update, donde el servicio no interviene directamente).
     * Acepta un array con las claves: citas, fundamento_normativa, fundamento_tipo, fundamento_resumen.
     */
    public function sincronizarFundamentoPublico(Tramite $tramite, array $datos): void
    {
        $citas  = $datos['citas'] ?? [];
        $manual = [
            'normativa_nombre' => $datos['fundamento_normativa'] ?? null,
            'tipo_normativa'   => $datos['fundamento_tipo']      ?? null,
            'resumen'          => $datos['fundamento_resumen']   ?? null,
        ];
        $this->sincronizarFundamento($tramite, $citas, $manual);
    }

    private function sincronizarFundamento(Tramite $tramite, array $citas, array $manual): void
    {
        $registros = [];

        // Citas del catálogo (pueden ser varias).
        foreach ($citas as $cita) {
            $regId = $cita['regulacion_id'] ?? null;
            if (!$regId) {
                continue;
            }
            $registros[] = [
                'regulacion_id'     => $regId,
                'articulo_fraccion' => trim($cita['articulo_fraccion'] ?? '') ?: null,
                'normativa_nombre'  => null,
                'tipo_normativa'    => null,
                'resumen'           => null,
            ];
        }

        // Norma capturada a mano (si se llenó).
        $normativa = trim($manual['normativa_nombre'] ?? '');
        $resumen   = trim($manual['resumen'] ?? '');
        if ($normativa !== '' || $resumen !== '') {
            $registros[] = [
                'regulacion_id'     => null,
                'articulo_fraccion' => null,
                'normativa_nombre'  => $normativa ?: null,
                'tipo_normativa'    => trim($manual['tipo_normativa'] ?? '') ?: null,
                'resumen'           => $resumen ?: null,
            ];
        }

        // Si no hay nada que guardar, no se toca lo existente.
        if (empty($registros)) {
            return;
        }

        $tramite->fundamentos()->delete();
        foreach ($registros as $r) {
            $tramite->fundamentos()->create($r);
        }
    }

    private function sincronizarRequisitos(Tramite $tramite, array $enviados): void
    {
        $existentes   = $tramite->requisitos->pluck('id')->toArray();
        $actualizados = [];

        foreach ($enviados as $i => $req) {
            if (empty($req['nombre'])) {
                continue;
            }

            $datos = [
                'orden'             => $i + 1,
                'nombre'            => $req['nombre'],
                'original'          => !empty($req['original']),
                'copia'             => !empty($req['copia']),
                'dias_estimados'    => $req['dias']    ?? 0,
                'horas_estimadas'   => $req['horas']   ?? 0,
                'minutos_estimados' => $req['minutos'] ?? 0,
                'observaciones'     => $req['observaciones'] ?? null,
                'fj_norma'          => $req['fj_norma']    ?? null,
                'fj_capitulo'       => $req['fj_capitulo'] ?? null,
                'fj_articulo'       => $req['fj_articulo'] ?? null,
            ];

            if (!empty($req['id'])) {
                Requisito::where('id', $req['id'])->update($datos);
                $reqId = (int) $req['id'];
                $actualizados[] = $reqId;
            } else {
                $nuevo = $tramite->requisitos()->create($datos);
                $reqId = $nuevo->id;
                $actualizados[] = $reqId;
            }

            // Regulaciones citadas en este requisito (pueden ser varias).
            $this->sincronizarRegulacionesRequisito($reqId, $req['citas'] ?? []);
        }

        $eliminar = array_diff($existentes, $actualizados);
        if ($eliminar) {
            Requisito::whereIn('id', $eliminar)->delete();
        }
    }

    /**
     * Guarda las regulaciones citadas de un requisito (tabla intermedia
     * requisito_regulacion). Borra las previas y recrea las enviadas.
     */
    private function sincronizarRegulacionesRequisito(int $requisitoId, array $citas): void
    {
        \App\Models\RequisitoRegulacion::where('requisito_id', $requisitoId)->delete();
        foreach ($citas as $cita) {
            $regId = $cita['regulacion_id'] ?? null;
            if (!$regId) {
                continue;
            }
            \App\Models\RequisitoRegulacion::create([
                'requisito_id'      => $requisitoId,
                'regulacion_id'     => $regId,
                'articulo_fraccion' => trim($cita['articulo_fraccion'] ?? '') ?: null,
            ]);
        }
    }

    /** Guarda la ficha ciudadana (portal) si vienen datos de contenido. */
    /**
     * Sincroniza los pasos de los procesos de atención y resolución.
     * Público para que el update del controlador lo reutilice sin duplicar.
     * $procesos = ['atencion' => [ ['paso','accion','detalle','area'], ... ],
     *              'resolucion' => [ ... ]]
     * Estrategia: borra los pasos previos del trámite y recrea los enviados.
     */
    public function sincronizarProcesos(Tramite $tramite, array $procesos): void
    {
        // Si no se envió nada de procesos, no se toca lo existente.
        if (empty($procesos)) {
            return;
        }

        $tramite->procesosAtencion()->delete();

        foreach (['atencion', 'resolucion'] as $tipo) {
            $pasos = $procesos[$tipo] ?? [];
            $orden = 1;
            foreach ($pasos as $p) {
                // Omitir pasos completamente vacíos.
                if (empty($p['accion']) && empty($p['detalle']) && empty($p['area'])) {
                    continue;
                }
                $tramite->procesosAtencion()->create([
                    'tipo'    => $tipo,
                    'paso'    => $p['paso'] ?? $orden,
                    'subpaso' => $p['subpaso'] ?? 0,
                    'accion'  => $p['accion'] ?? null,
                    'detalle' => $p['detalle'] ?? null,
                    'area'    => $p['area'] ?? null,
                ]);
                $orden++;
            }
        }
    }

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
