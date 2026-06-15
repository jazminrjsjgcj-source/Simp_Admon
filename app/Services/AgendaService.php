<?php

namespace App\Services;

use App\Models\AccionAgenda;
use Illuminate\Support\Facades\DB;

/**
 * Concentra la creación de acciones de agenda SyD.
 *
 * Cubre los dos caminos del wizard de agenda:
 *   - Camino A: la acción se liga a un trámite que YA existe (o a ninguno).
 *   - Camino B: el trámite NO existe, así que se crea el trámite (vía
 *     TramiteService) y la acción ligada, todo dentro de una transacción.
 *
 * Reutiliza TramiteService para no duplicar la lógica de creación de trámites.
 * También genera el folio al enviar a revisión y crea el evento de calendario
 * cuando hay fecha compromiso, igual que hacía el controlador.
 */
class AgendaService
{
    public function __construct(
        private TramiteService $tramiteService,
        private CalendarioEventoService $calendario,
    ) {}

    /**
     * Crea una acción de agenda (camino A).
     * $tramiteId puede ser null (acción sin trámite) o el id de uno existente.
     *
     * @param array $datos    Campos de la acción (tipo, descripcion, meta, etc.).
     * @param int   $autorId  Usuario que la crea.
     * @param bool  $esEnvio  Si se envía a revisión (genera folio y cambia estatus).
     */
    public function crearAccion(array $datos, int $autorId, bool $esEnvio = false): AccionAgenda
    {
        return DB::transaction(function () use ($datos, $autorId, $esEnvio) {
            $accion = $this->insertarAccion($datos, $autorId, $esEnvio);
            $this->generarFolioSiEnvio($accion, $esEnvio);
            $this->crearEventoSiHayFecha($accion);
            return $accion;
        });
    }

    /**
     * Crea un trámite nuevo y la acción de agenda ligada a él (camino B).
     * Todo en una transacción: si falla algo, no queda ni trámite huérfano
     * ni acción a medias.
     *
     * @param array $datosTramite  Campos del trámite a crear.
     * @param array $derechos      Derechos del trámite (puede ir vacío).
     * @param array $requisitos    Requisitos del trámite (puede ir vacío).
     * @param array $fichaPortal   Ficha ciudadana del trámite (puede ir vacío).
     * @param array $datosAccion   Campos de la acción de agenda.
     * @param int   $autorId       Usuario que crea ambos.
     * @param bool  $esEnvio       Si se envía a revisión.
     */
    public function crearConTramiteNuevo(
        array $datosTramite,
        array $derechos,
        array $requisitos,
        array $fichaPortal,
        array $datosAccion,
        int $autorId,
        bool $esEnvio = false,
    ): AccionAgenda {
        return DB::transaction(function () use (
            $datosTramite, $derechos, $requisitos, $fichaPortal,
            $datosAccion, $autorId, $esEnvio
        ) {
            // 1) Crear el trámite con el servicio de la capa 1.
            $datosTramite['created_by'] = $autorId;
            $tramite = $this->tramiteService->crear(
                datos:       $datosTramite,
                derechos:    $derechos,
                requisitos:  $requisitos,
                fichaPortal: $fichaPortal,
                esEnvio:     $esEnvio,
            );

            // 2) Ligar la acción al trámite recién creado.
            $datosAccion['tramite_id'] = $tramite->id;

            // 3) Crear la acción (reusa la misma lógica que el camino A).
            $accion = $this->insertarAccion($datosAccion, $autorId, $esEnvio);
            $this->generarFolioSiEnvio($accion, $esEnvio);
            $this->crearEventoSiHayFecha($accion);

            return $accion;
        });
    }

    /* ----------------------------------------------------------------------
     | Helpers privados (lógica compartida por ambos caminos)
     |----------------------------------------------------------------------*/

    /** Inserta la acción de agenda con sus campos y estatus inicial. */
    private function insertarAccion(array $datos, int $autorId, bool $esEnvio): AccionAgenda
    {
        return AccionAgenda::create([
            'tipo'             => $datos['tipo'] ?? null,
            'descripcion'      => $datos['descripcion'] ?? null,
            'meta'             => $datos['meta'] ?? null,
            'fecha_inicio'     => $datos['fecha_inicio']     ?: null,
            'fecha_compromiso' => $datos['fecha_compromiso'] ?: null,
            'responsable'      => $datos['responsable'] ?? null,
            'dependencia_id'   => $datos['dependencia_id'] ?? null,
            'indicador'        => $datos['indicador'] ?? null,
            'tramite_id'       => $datos['tramite_id'] ?? null,
            'created_by'       => $autorId,
            'estatus'          => $esEnvio
                ? AccionAgenda::ESTATUS_EN_OBSERVACION
                : AccionAgenda::ESTATUS_BORRADOR,
        ]);
    }

    /** Al enviar a revisión, genera el folio si aún no tiene. */
    private function generarFolioSiEnvio(AccionAgenda $accion, bool $esEnvio): void
    {
        if ($esEnvio && empty($accion->folio)) {
            $accion->load('dependencia');
            $accion->folio = $accion->generarFolio();
            $accion->save();
        }
    }

    /** Si la acción tiene fecha compromiso, crea su evento de calendario. */
    private function crearEventoSiHayFecha(AccionAgenda $accion): void
    {
        if ($accion->fecha_compromiso) {
            $this->calendario->crear($accion, [
                'tipo'           => $accion->tipo,
                'titulo'         => $accion->descripcion ?: 'Acción de Agenda',
                'fecha'          => $accion->fecha_compromiso,
                'responsable'    => $accion->responsable,
                'dependencia_id' => $accion->dependencia_id,
            ]);
        }
    }
}
