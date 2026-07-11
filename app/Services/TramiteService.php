<?php

namespace App\Services;

use App\Models\Requisito;
use App\Models\Tramite;
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
            ['citas' => $citas, 'manual' => $fundamentoManual] = $this->separarFundamento($datos);

            // El total de derechos alimenta monto_derechos (entra al costo).
            // Convierte los derechos en UMA a pesos antes de sumar.
            $datos['monto_derechos'] = \App\Models\TramiteDerecho::totalEnPesos($derechos);

            // Cálculo del costo burocrático a partir de los datos.
            $datos = array_merge($datos, Tramite::calcularCostoDesde($datos));

            // Estatus inicial según borrador o envío a revisión.
            $datos['estatus'] = $esEnvio
                ? Tramite::ESTATUS_EN_OBSERVACION
                : Tramite::ESTATUS_BORRADOR;

            // costo_tipo / costo_monto / costo_unidad son ayudantes del formulario
            // (validan y arman el texto portal_costo_publico); no son columnas del
            // trámite, así que se quitan antes de crear para no romper el guardado.
            unset($datos['costo_tipo'], $datos['costo_monto'], $datos['costo_unidad']);

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

    /**
     * Actualiza un trámite existente. Es el espejo de crear(): recibe los datos
     * ya extraídos del request y hace TODO el trabajo de guardado dentro de una
     * sola transacción, reutilizando los mismos helpers de sincronización. Antes
     * esta lógica vivía a mano en TramiteController::update(), sin transacción y
     * con una copia divergente de la sincronización (origen del bug C1). Al
     * unificarla aquí, alta y edición guardan exactamente igual.
     */
    public function actualizar(
        Tramite $tramite,
        array $datos,
        array $derechos = [],
        array $requisitos = [],
        array $fichaPortal = [],
        array $procesos = [],
    ): Tramite {
        return DB::transaction(function () use ($tramite, $datos, $derechos, $requisitos, $fichaPortal, $procesos) {

            // Separar el fundamento jurídico igual que en crear(): no son
            // columnas de `tramites`, van a la tabla fundamento_juridico.
            ['citas' => $citas, 'manual' => $fundamentoManual] = $this->separarFundamento($datos);

            // El total de derechos es la fuente única de monto_derechos.
            $datos['monto_derechos'] = \App\Models\TramiteDerecho::totalEnPesos($derechos);

            // Si el enlace edita un trámite que está en periodo de observación,
            // al guardar pasa a corrección para indicar que ya empezó a atender.
            if ($tramite->estatus === Tramite::ESTATUS_EN_OBSERVACION) {
                $datos['estatus'] = Tramite::ESTATUS_EN_CORRECCION;
            }

            // Recalcular el costo a partir del estado actual + los datos nuevos.
            $datos = array_merge($datos, Tramite::calcularCostoDesde(array_merge($tramite->toArray(), $datos)));

            $tramite->update($datos);

            $this->sincronizarDerechos($tramite, $derechos);
            $this->sincronizarRequisitos($tramite, $requisitos);
            $this->sincronizarFichaPortal($tramite, $fichaPortal);
            $this->sincronizarProcesos($tramite, $procesos);
            $this->sincronizarFundamento($tramite, $citas, $fundamentoManual);

            // Regenerar homoclave si está vacía y ya hay dependencia y unidad.
            if (empty($tramite->fresh()->homoclave) && $tramite->dependencia_id && $tramite->unidad_id) {
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
            'normativa_nombre'  => $datos['fundamento_normativa'] ?? null,
            'tipo_normativa'    => $datos['fundamento_tipo']      ?? null,
            'articulo_fraccion' => $datos['fundamento_articulo']  ?? null,
            'resumen'           => $datos['fundamento_resumen']   ?? null,
        ];
        $this->sincronizarFundamento($tramite, $citas, $manual);
    }

    /**
     * Separa del array de datos del trámite los campos que pertenecen al
     * fundamento jurídico (tabla fundamento_juridico, NO a `tramites`), y los
     * elimina de $datos para que Tramite::create()/update() no intente
     * asignarlos como columnas inexistentes.
     *
     * Acepta dos juegos de nombres para el mismo dato, según el formulario de
     * origen: el wizard de Trámites usa el prefijo `fundamento_*`, y el wizard
     * de Agenda usa los nombres planos (`normativa_nombre`, `tipo_normativa`,
     * etc.). Unificar aquí evita el bug que ocurría cuando un controlador
     * enviaba un nombre que el unset no contemplaba (causa de la
     * MassAssignmentException de `normativa_nombre`).
     *
     * @param  array $datos  Se modifica por referencia: se le quitan los campos de fundamento.
     * @return array{citas: array, manual: array}  Datos del fundamento extraídos.
     */
    private function separarFundamento(array &$datos): array
    {
        // Modo del fundamento de origen: 'catalogo' o 'manual' (excluyentes).
        // En 'catalogo' se ignoran los campos manuales; en 'manual' se ignoran
        // las citas. Así nunca se mezclan, aunque el formulario haya dejado
        // valores ocultos del lado que no se eligió.
        $modo = $datos['fundamento_modo'] ?? 'catalogo';

        $citas = $modo === 'manual' ? [] : ($datos['citas'] ?? []);

        $manual = $modo === 'catalogo'
            ? ['normativa_nombre' => null, 'tipo_normativa' => null, 'articulo_fraccion' => null, 'resumen' => null]
            : [
                'normativa_nombre'  => $datos['fundamento_normativa'] ?? $datos['normativa_nombre'] ?? null,
                'tipo_normativa'    => $datos['fundamento_tipo'] ?? $datos['tipo_normativa'] ?? null,
                'articulo_fraccion' => $datos['fundamento_articulo'] ?? $datos['articulo_fraccion'] ?? null,
                'resumen'           => $datos['fundamento_resumen'] ?? $datos['resumen'] ?? null,
            ];

        // Quitar TODOS los campos auxiliares que NO son columnas de `tramites`,
        // para que ninguno llegue a la asignación masiva del modelo:
        //  - fundamento_modo: bandera del modo (catálogo/manual).
        //  - requisitos: se sincronizan aparte (sincronizarRequisitos); nunca
        //    son columna de `tramites`, se quitan por defensa.
        //  - los campos de fundamento en sus dos variantes (con prefijo y planos).
        unset(
            $datos['fundamento_modo'],
            $datos['requisitos'],
            $datos['citas'],
            $datos['regulacion_id'],
            $datos['fundamento_normativa'], $datos['fundamento_tipo'],
            $datos['fundamento_articulo'], $datos['fundamento_resumen'],
            $datos['normativa_nombre'], $datos['tipo_normativa'],
            $datos['articulo_fraccion'], $datos['resumen'],
        );

        return ['citas' => $citas, 'manual' => $manual];
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
        $articulo  = trim($manual['articulo_fraccion'] ?? '');
        if ($normativa !== '' || $resumen !== '' || $articulo !== '') {
            $registros[] = [
                'regulacion_id'     => null,
                'articulo_fraccion' => $articulo ?: null,
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

    /**
     * Sincroniza los requisitos del trámite (crea, actualiza y elimina) e
     * incluye las regulaciones citadas en cada requisito. Público para que el
     * update() del controlador lo reutilice sin duplicar, igual que ya se hace
     * con sincronizarProcesos y sincronizarFundamentoPublico. Antes existía una
     * copia en el controlador que NO guardaba las citas, así que al editar se
     * perdían (bug C1); al unificar aquí, alta y edición guardan lo mismo.
     */
    public function sincronizarRequisitos(Tramite $tramite, array $enviados): void
    {
        $existentes   = $tramite->requisitos->pluck('id')->toArray();
        $actualizados = [];

        foreach ($enviados as $i => $req) {
            if (empty($req['nombre'])) {
                continue;
            }

            // Captura del costo del requisito. El formulario manda un
            // modo: 'sin' (sin costo), 'fijo' (monto conocido) o 'variable'
            // (costo de mercado no cuantificable, ej. plano arquitectónico).
            $modoCosto    = $req['costo_modo'] ?? 'sin';
            $tieneCosto   = in_array($modoCosto, ['fijo', 'variable'], true);
            $costoVariable = ($modoCosto === 'variable');
            $montoFijo    = ($modoCosto === 'fijo') ? floatval($req['costo_monto'] ?? 0) : 0;

            // Tipo de presentación: checkboxes (original / copia / digital) que
            // pueden marcarse a la vez. Llega como arreglo; lo normalizamos
            // a una lista limpia. El cast a (array) tolera el formato viejo (un
            // solo texto) por si quedara algún envío heredado. Se guarda como CSV
            // en tipo_presentacion y se derivan los booleanos original/copia para
            // que el resto del sistema siga consistente.
            $tiposPres = array_values(array_filter((array) ($req['tipo'] ?? [])));
            $tipoPresCsv = implode(',', $tiposPres);

            // El formulario envía costo_unidad (PESOS o UMA) pero
            // el servicio no lo incluía en el array de datos. La columna existe
            // en la tabla (migración add_costo_unidad_to_requisitos) y el modelo
            // ahora la tiene en $fillable, pero aquí se descartaba el valor.
            // Solo se asigna cuando el requisito tiene costo; si no lo tiene,
            // la unidad no aplica y queda null.
            $costoUnidad = $tieneCosto ? ($req['costo_unidad'] ?? 'PESOS') : null;

            $datos = [
                'orden'             => $i + 1,
                'nombre'            => $req['nombre'],
                'tipo_presentacion' => $tipoPresCsv !== '' ? $tipoPresCsv : null,
                'original'          => in_array('original', $tiposPres, true),
                'copia'             => in_array('copia', $tiposPres, true),
                'dias_estimados'    => $req['dias']    ?? 0,
                'horas_estimadas'   => $req['horas']   ?? 0,
                'minutos_estimados' => $req['minutos'] ?? 0,
                'tiene_costo'       => $tieneCosto,
                'costo_variable'    => $costoVariable,
                'costo_requisito'   => $montoFijo,
                'costo_unidad'      => $costoUnidad,
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
        // Defensa: si el formulario no envía citas para este requisito, NO se
        // tocan las existentes (evita borrarlas al editar mientras la UI de
        // citas por requisito no está cableada). Cuando esa UI exista y mande
        // un arreglo explícito, conviene cambiar esto por una bandera de
        // "el formulario gestiona citas".
        if (empty($citas)) {
            return;
        }

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