<?php

namespace App\Services;

use App\Models\AccionAgenda;
use App\Models\Tramite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        private HitoAgendaService $hitos,
        private BitacoraService $bitacora,
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

            // 4) #20 — Auditoría EXPLÍCITA del flujo combinado.
            // El AuditObserver ya registra la creación de Tramite y AccionAgenda
            // por separado, pero esos eventos no dejan ver que fueron parte de
            // un mismo flujo ni los datos relacionados (derechos, requisitos).
            // Esta entrada explícita queda en la bitácora de AMBOS modelos con
            // un resumen útil para la revisora.
            $resumen = sprintf(
                'Trámite "%s" creado desde Agenda SyD · %d derecho(s) · %d requisito(s) · monto derechos $%s',
                \Illuminate\Support\Str::limit($tramite->nombre_oficial, 60),
                count($derechos),
                count($requisitos),
                number_format((float) ($tramite->monto_derechos ?? 0), 2)
            );

            $this->bitacora->registrar(
                $tramite,
                'tramite',
                'flujo_combinado',
                'Trámite creado desde acción de Agenda SyD',
                $resumen,
                $autorId
            );
            $this->bitacora->registrar(
                $accion,
                'agenda',
                'flujo_combinado',
                'Acción creada con trámite nuevo',
                $resumen . ' · folio trámite: ' . ($tramite->homoclave ?? '—'),
                $autorId
            );

            return $accion;
        });
    }

    /* ----------------------------------------------------------------------
     | Helpers privados (lógica compartida por ambos caminos)
     |----------------------------------------------------------------------*/

    /** Inserta la acción de agenda con sus campos y estatus inicial. */
    private function insertarAccion(array $datos, int $autorId, bool $esEnvio): AccionAgenda
    {
        $accion = AccionAgenda::create([
            // La acción nace INACTIVA si su trámite todavía no está completado (pasa
            // cuando el trámite se registra desde la propia agenda: aún tiene que
            // pasar por revisión y firma). Mientras tanto no aparece en los listados
            // ajenos, ni en el calendario, ni en los indicadores: no se puede dar por
            // comprometida la mejora de algo que formalmente no existe todavía.
            // El observer de Tramite la activa sola cuando el trámite se completa.
            'activa'           => $this->tramiteEstaCompletado($datos['tramite_id'] ?? null),

            'tipo'             => $datos['tipo'] ?? null,
            'descripcion'      => $datos['descripcion'] ?? null,
            'meta'             => $datos['meta'] ?? null,
            // Se usa ?? y no ?: porque en un borrador estas claves pueden no venir
            // siquiera. El ?: exige que la clave exista y provocaba un
            // "Undefined array key" al guardar una acción sin fechas —que es
            // justamente lo que un borrador permite—. El ?: interno se conserva para
            // que una cadena vacía también quede como null.
            'fecha_inicio'     => ($datos['fecha_inicio']     ?? null) ?: null,
            'fecha_compromiso' => ($datos['fecha_compromiso'] ?? null) ?: null,
            'responsable'      => $datos['responsable'] ?? null,
            'dependencia_id'   => $datos['dependencia_id'] ?? null,
            'unidad_id'        => $datos['unidad_id'] ?? null,
            'indicador'        => $datos['indicador'] ?? null,
            'indicador_avance' => $datos['indicador_avance'] ?? null,
            'tramite_id'       => $datos['tramite_id'] ?? null,
            // Paquete 3: alcance (en la columna tipo), catálogos oficiales y niveles.
            'acciones_simplificacion' => $datos['acciones_simplificacion'] ?? [],
            'acciones_digitalizacion' => $datos['acciones_digitalizacion'] ?? [],
            'nivel_actual'            => $datos['nivel_actual'] ?? null,
            'nivel_meta'              => $datos['nivel_meta'] ?? null,
            'created_by'       => $autorId,
            'estatus'          => $esEnvio
                ? AccionAgenda::ESTATUS_EN_OBSERVACION
                : AccionAgenda::ESTATUS_BORRADOR,
        ]);

        // Sembrar los hitos de avance (incluye el Diagnóstico ya completado).
        // Es idempotente, así que no duplica si la acción ya tuviera hitos.
        $this->hitos->sembrarHitos($accion);

        return $accion;
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

    /**
     * Si la acción tiene fecha compromiso, crea su evento de calendario.
     *
     * Dos adaptaciones al esquema de calendario_eventos:
     *
     * - El título se trunca a 200 caracteres: la columna admite 500 y la
     *   descripción de una acción puede ser texto legal de miles.
     *
     * - El tipo 'ambas' que admite una acción de agenda no existe en el ENUM del
     *   calendario, así que se traduce a 'simplificacion', su categoría primaria.
     */
    private function crearEventoSiHayFecha(AccionAgenda $accion): void
    {
        if (empty($accion->fecha_compromiso)) {
            return;
        }

        // 'ambas' no es un valor válido en el ENUM de calendario_eventos.tipo.
        // El mapping queda explícito con match() para que futuras adiciones
        // de tipo en AccionAgenda obliguen a revisar este punto (falla en compile-time).
        $tipoCalendario = match ($accion->tipo) {
            'simplificacion' => 'simplificacion',
            'digitalizacion' => 'digitalizacion',
            'ambas'          => 'simplificacion', // ambas → categoría primaria SyD
            default          => 'simplificacion',
        };

        // Truncar a 200 chars (bien dentro del VARCHAR(500)) para que incluso
        // un párrafo de texto legal quepa sin desbordar la columna.
        $tituloCalendario = Str::limit($accion->descripcion ?? '', 200) ?: 'Acción de Agenda';

        $this->calendario->crear($accion, [
            'tipo'           => $tipoCalendario,
            'titulo'         => $tituloCalendario,
            'fecha'          => $accion->fecha_compromiso,
            'responsable'    => $accion->responsable,
            'dependencia_id' => $accion->dependencia_id,
        ]);
    }

    /**
     * ¿El trámite al que se ligará la acción ya está completado?
     *
     * Una acción sin trámite se considera "lista" (no hay nada a lo que esperar); es
     * un caso raro, pero puede darse en un borrador a medio capturar.
     */
    private function tramiteEstaCompletado(?int $tramiteId): bool
    {
        if (! $tramiteId) {
            return true;
        }

        $tramite = Tramite::find($tramiteId);

        return $tramite?->estatus === Tramite::ESTATUS_COMPLETADO;
    }
}
