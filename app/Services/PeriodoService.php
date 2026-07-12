<?php

namespace App\Services;

use App\Models\Periodo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lógica de negocio de los periodos (agenda SyD y agenda regulatoria).
 *
 * Concentra la regla central del módulo: solo puede haber UN periodo activo por tipo.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ CAMBIÓ EN ESTE ARCHIVO Y POR QUÉ
 * ══════════════════════════════════════════════════════════════════════
 *
 * ── 1. Cerrar el periodo anterior no quedaba en bitácora ──
 *
 * cerrarOtrosActivos() hacía esto:
 *
 *     Periodo::where('estatus', 'activo')
 *         ->where('tipo', $tipo)
 *         ->update(['estatus' => 'cerrado']);
 *
 * Ese `->update()` va sobre el QUERY BUILDER, no sobre los modelos. Y un update de query
 * builder NO DISPARA LOS EVENTOS DE ELOQUENT.
 *
 * Periodo está observado por AuditObserver (AppServiceProvider, línea 61). Pero el observer
 * escucha eventos de modelo, y ahí no había ninguno. Resultado: el cierre automático del
 * periodo anterior NO APARECÍA EN LA BITÁCORA. Nunca.
 *
 * El periodo activo determina a qué agenda se imputan las acciones que se registran. Que
 * uno se cierre y otro se abra es de las cosas más importantes que pasan en el sistema, y
 * era justo la que no dejaba rastro.
 *
 * Ahora se recorren los modelos uno a uno y se guarda cada uno con ->update(), que sí
 * dispara el observer. Son cero o una filas: el coste es irrelevante y la trazabilidad no.
 *
 * ── 2. Dos administradores a la vez podían dejar dos periodos activos ──
 *
 * La comprobación estaba en código, dentro de una transacción. Pero dos transacciones
 * concurrentes no se ven entre sí hasta que confirman:
 *
 *   A: cierra los activos (no hay ninguno) → activa P1 → COMMIT
 *   B: cierra los activos (TAMPOCO ve ninguno, P1 aún no confirmó) → activa P2 → COMMIT
 *
 * Resultado: P1 y P2 activos a la vez.
 *
 * Se arregla en dos capas, y las dos hacen falta:
 *
 *   - Un `lockForUpdate()` sobre los periodos del tipo, que SERIALIZA a los dos
 *     administradores: el segundo espera a que el primero termine. Resuelve el caso normal
 *     con elegancia (el segundo ve el trabajo del primero y lo cierra).
 *
 *   - Un índice único parcial en la base (migración add_unique_periodo_activo_index), que
 *     IMPIDE el estado inválido pase lo que pase. Es el único que garantiza de verdad.
 *
 * Una comprobación en código nunca puede garantizar unicidad bajo concurrencia. Solo la base
 * puede. El código hace lo correcto; la base impide lo imposible.
 *
 * ── 3. Las cadenas mágicas ──
 *
 * 'activo', 'cerrado' y 'proximo' se escribían a mano. Un 'Activo' con mayúscula se
 * guardaría sin queja, y ese periodo no estaría activo para el sistema: no lo vería el
 * scope, no cerraría a los demás, no lo protegería el índice único. Ahora son constantes.
 */
class PeriodoService
{
    /**
     * Crea un periodo. Si nace activo, cierra los demás activos de su tipo dentro de la
     * misma transacción, para mantener la regla "1 activo por tipo".
     *
     * @param  array  $datos  Campos ya validados del formulario.
     * @param  string $tipo   Periodo::TIPO_SYD o Periodo::TIPO_REGULATORIA.
     */
    public function crear(array $datos, string $tipo): Periodo
    {
        $estatus = $this->estatusPedido($datos);

        return $this->enTransaccion(function () use ($datos, $tipo, $estatus) {
            if ($estatus === Periodo::ESTATUS_ACTIVO) {
                $this->cerrarOtrosActivos($tipo);
            }

            return Periodo::create([
                'nombre'       => $datos['nombre'],
                'tipo'         => $tipo,
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin'    => $datos['fecha_fin'],
                'estatus'      => $estatus,
                'descripcion'  => $datos['descripcion'] ?? null,
                'created_by'   => auth()->id(),
            ]);
        });
    }

    /**
     * Actualiza un periodo. Si queda activo, cierra los demás activos de su tipo (excepto él
     * mismo) dentro de la misma transacción.
     */
    public function actualizar(Periodo $periodo, array $datos): Periodo
    {
        // Si no llega estatus, se conserva el que tenía. NO se cae a 'proximo'.
        //
        // Antes esto era `$datos['estatus'] ?? 'proximo'`, y eso significaba que cualquier
        // guardado que no mandara el estatus DESACTIVABA el periodo en silencio. Un editor
        // que solo quisiera corregir una fecha podía cerrar la agenda del municipio sin
        // enterarse. Es la misma trampa que los valores por defecto de TramiteService.
        $estatus = $datos['estatus'] ?? $periodo->estatus;
        $this->validarEstatus($estatus);

        return $this->enTransaccion(function () use ($periodo, $datos, $estatus) {
            if ($estatus === Periodo::ESTATUS_ACTIVO) {
                $this->cerrarOtrosActivos($periodo->tipo, $periodo->id);
            }

            $periodo->update([
                'nombre'       => $datos['nombre'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin'    => $datos['fecha_fin'],
                'estatus'      => $estatus,
                'descripcion'  => $datos['descripcion'] ?? null,
            ]);

            return $periodo;
        });
    }

    /** Marca un periodo como activo y cierra los demás activos de su tipo. */
    public function activar(Periodo $periodo): Periodo
    {
        return $this->enTransaccion(function () use ($periodo) {
            $this->cerrarOtrosActivos($periodo->tipo, $periodo->id);
            $periodo->update(['estatus' => Periodo::ESTATUS_ACTIVO]);

            return $periodo;
        });
    }

    /* ----------------------------------------------------------------------
     | Internos
     |----------------------------------------------------------------------*/

    /**
     * Regla central del módulo: cierra todos los periodos activos de un tipo, opcionalmente
     * excluyendo uno (el que se está creando o activando).
     *
     * ── Por qué se recorren los modelos en vez de hacer un UPDATE masivo ──
     *
     * La versión anterior hacía un `->update()` sobre el query builder. Es más rápido, sí, y
     * NO DISPARA LOS EVENTOS DE ELOQUENT. Periodo está observado por AuditObserver, pero el
     * observer escucha eventos de modelo — y ahí no había ninguno.
     *
     * Resultado: el cierre automático del periodo anterior no aparecía en la bitácora.
     *
     * Aquí se recorren los modelos y se guarda cada uno, para que el observer se entere. Son
     * cero o una filas (la regla garantiza que no haya más). El coste es irrelevante; saber
     * quién cerró la agenda del municipio, no.
     *
     * No abre transacción propia: se llama siempre desde dentro de una.
     */
    private function cerrarOtrosActivos(string $tipo, ?int $exceptoId = null): void
    {
        // lockForUpdate() serializa a dos administradores que activen periodos del mismo tipo
        // a la vez: el segundo espera a que el primero confirme, y entonces sí ve su periodo
        // y lo cierra. Sin esto, los dos verían la tabla "sin activos" y los dos activarían.
        //
        // El índice único de la base es el respaldo: si aun así se colaran dos, el segundo
        // choca. El lock resuelve el caso normal; el índice impide el imposible.
        $activos = Periodo::where('tipo', $tipo)
            ->where('estatus', Periodo::ESTATUS_ACTIVO)
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->lockForUpdate()
            ->get();

        foreach ($activos as $activo) {
            // ->update() sobre el MODELO: esto sí dispara el AuditObserver.
            $activo->update(['estatus' => Periodo::ESTATUS_CERRADO]);
        }
    }

    /**
     * Envuelve la operación en una transacción y traduce el choque contra el índice único a
     * un error legible.
     *
     * Si dos administradores activan periodos del mismo tipo en el mismo instante y el lock
     * no llega a tiempo, la base rechaza al segundo. Sin esta traducción, el usuario vería un
     * error 500 con jerga de PostgreSQL.
     */
    private function enTransaccion(callable $operacion): Periodo
    {
        try {
            return DB::transaction($operacion);
        } catch (QueryException $e) {
            if ($this->esViolacionDeClaveUnica($e)) {
                throw new RuntimeException(
                    'Otro administrador activó un periodo de este tipo al mismo tiempo. '
                    . 'Recarga la página y vuelve a intentarlo.'
                );
            }

            throw $e;
        }
    }

    /**
     * El estatus pedido, o 'proximo' si no se pidió ninguno.
     *
     * Al CREAR, un estatus ausente sí puede caer a 'proximo': un periodo nuevo sin estatus es
     * razonable que nazca próximo. Al ACTUALIZAR no, y por eso ese método no usa este helper:
     * ahí un estatus ausente significa "no lo toques", no "desactívalo".
     */
    private function estatusPedido(array $datos): string
    {
        $estatus = $datos['estatus'] ?? Periodo::ESTATUS_PROXIMO;
        $this->validarEstatus($estatus);

        return $estatus;
    }

    /**
     * Rechaza cualquier estatus que no sea uno de los tres válidos.
     *
     * Hace falta porque AdminController hace `$validated['estatus'] = $request->estatus;` sin
     * ninguna regla de validación: se guarda lo que venga del formulario.
     *
     * Un 'Activo' con mayúscula, o un 'activo ' con un espacio, se guardaría sin queja. Y ese
     * periodo NO estaría activo para el sistema: scopeActivo() compara con === 'activo', el
     * índice único filtra WHERE estatus = 'activo', y el servicio cierra los demás solo si ve
     * exactamente 'activo'. El periodo existiría, se vería en la lista, diría "Activo" en la
     * pantalla... y no lo estaría. Nadie sabría por qué.
     *
     * Es más barato rechazarlo aquí que depurarlo dentro de seis meses.
     */
    private function validarEstatus(string $estatus): void
    {
        if (! array_key_exists($estatus, Periodo::ESTATUS_TODOS)) {
            $validos = implode(', ', array_keys(Periodo::ESTATUS_TODOS));

            throw new RuntimeException(
                "Estatus de periodo no válido: '{$estatus}'. Los válidos son: {$validos}."
            );
        }
    }

    /**
     * PostgreSQL usa el SQLSTATE 23505 para "unique_violation"; MySQL usa el 23000.
     * Se comprueban los dos para no atar el servicio a un motor concreto.
     */
    private function esViolacionDeClaveUnica(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23505', '23000'], true);
    }
}
