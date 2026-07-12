<?php

namespace App\Services;

use App\Exceptions\FirmaDuplicadaException;
use App\Models\Firma;
use App\Models\Tramite;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Servicio para gestionar firmas digitales sobre registros polimórficos
 * (trámites, acciones de agenda, propuestas regulatorias).
 *
 * Responsabilidades:
 *   1. Generar la cadena original que se va a firmar (foto de los campos clave).
 *   2. Calcular el hash de acuse (SHA-256 sobre la cadena original).
 *   3. Guardar la firma con la foto del firmante, su IP y su navegador.
 *   4. VERIFICAR que el documento firmado no ha cambiado desde que se firmó.
 *   5. Revocar una firma activa con motivo y trazabilidad.
 *
 * La estructura está preparada para integrar una autoridad certificadora externa
 * (columnas certificado_emisor, certificado_serie, metadata_firmante) cuando
 * exista la disposición correspondiente.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ CAMBIÓ EN ESTE ARCHIVO Y POR QUÉ
 * ══════════════════════════════════════════════════════════════════════
 *
 * ── 1. La verificación no verificaba nada ──
 *
 * verificarIntegridad() re-calculaba el hash de la cadena GUARDADA y lo comparaba
 * con el hash GUARDADO. Es decir: comparaba la fila `firmas` consigo misma.
 *
 * Eso detecta si alguien manipuló la tabla `firmas` a mano. NO detecta si alguien
 * editó el trámite después de firmarlo — que es justo lo que una firma existe para
 * impedir. Un trámite firmado al que se le cambiara el nombre oficial o el costo
 * seguía dando "firma válida".
 *
 * Ahora verificarIntegridad() hace las DOS comprobaciones: que la fila no se haya
 * tocado, y que el documento siga diciendo lo que decía cuando se firmó.
 *
 * ── 2. La cadena no se podía reconstruir (por eso lo anterior era imposible) ──
 *
 * Para comprobar que el documento no cambió hay que REARMAR la cadena con los
 * datos actuales y ver si da el mismo hash. Y eso antes no se podía, por dos
 * motivos que estaban enterrados en generarCadenaOriginal():
 *
 *   a) La cadena incluía 'firmado_en' => now(). Pero la columna `fecha` se
 *      llenaba con OTRA llamada a now(). Dos llamadas distintas: si caían a
 *      caballo de un segundo, la fecha guardada y la fecha hasheada no coincidían
 *      y el hash quedaba imposible de reproducir. Raro, pero real: una de cada
 *      mil firmas habría sido imposible de verificar, sin motivo aparente.
 *      Ahora se captura UN solo instante y se usa para las dos cosas.
 *
 *   b) La cadena incluía 'firmante_rfc' => $firmante->rfc, pero ese valor NO se
 *      guardaba en la fila (la columna firmante_rfc existe y estaba en $fillable,
 *      pero firmar() no la pasaba al create). Hoy da igual, porque el modelo User
 *      no tiene columna rfc y ese campo siempre vale null. Pero el día que alguien
 *      añada un RFC a los usuarios, TODAS las firmas anteriores dejarían de poder
 *      verificarse. Ahora el RFC se guarda en la fila, y la verificación lo lee de
 *      ahí — no del usuario vivo, que puede cambiar.
 *
 * La regla que se sigue en los dos casos: la cadena solo puede contener datos que
 * queden guardados en algún sitio. Si un dato entra al hash y no se persiste, el
 * hash se vuelve un número que nadie puede volver a calcular.
 *
 * ── 3. Revocar dejaba el documento congelado ──
 *
 * Al firmar, el registro CONGELA los nombres de catálogo que usó (para que un
 * documento firmado no cambie de contenido si mañana se renombra una dependencia).
 * Pero revocar() no descongelaba. Un trámite cuya única firma se revocaba quedaba
 * mostrando para siempre los nombres de una firma que ya no existía, y la firma
 * nueva no podía volver a congelar. Ahora, si no queda ninguna firma activa, se
 * descongela.
 */
class FirmaDigitalService
{
    /**
     * Firma un registro polimórfico (trámite, acción, propuesta).
     *
     * @param  Model   $firmable      Registro a firmar.
     * @param  User    $firmante      Usuario que firma.
     * @param  string  $tipo          Tipo de firma (constantes del modelo Firma).
     * @param  Request $request       Para capturar la IP y el navegador.
     * @param  ?string $observaciones Texto libre opcional.
     *
     * @throws FirmaDuplicadaException Si el registro ya tiene una firma activa
     *         de ese tipo. Puede pasar por doble clic: la comprobación previa y
     *         el índice único de la base cubren cada uno un escenario distinto.
     */
    public function firmar(
        Model $firmable,
        User $firmante,
        string $tipo,
        Request $request,
        ?string $observaciones = null
    ): Firma {
        if ($this->yaFirmadoPor($firmable, $tipo)) {
            throw FirmaDuplicadaException::paraTipo($tipo);
        }

        // UN SOLO instante para la fecha guardada y la fecha hasheada. Si se
        // llamara a now() dos veces, podrían caer en segundos distintos y el hash
        // quedaría imposible de reproducir. Este era el bug (a) del encabezado.
        $firmadoEn = now();

        // El RFC se toma del usuario UNA vez y se guarda en la fila. A partir de
        // ahí, la verificación lo lee de la fila, no del usuario (que puede
        // cambiar). Este era el bug (b) del encabezado.
        $rfcDelFirmante = $firmante->rfc ?? null;

        $cadenaOriginal = $this->armarCadena(
            tipo:           $tipo,
            firmableType:   get_class($firmable),
            firmableId:     $firmable->id,
            datosFirmable:  $this->extraerDatosClaveDelFirmable($firmable),
            firmanteId:     $firmante->id,
            firmanteRfc:    $rfcDelFirmante,
            firmadoEn:      $firmadoEn,
        );

        try {
            $firma = Firma::create([
                'firmable_type'   => get_class($firmable),
                'firmable_id'     => $firmable->id,
                'tipo'            => $tipo,
                'firmante_id'     => $firmante->id,
                'firmante_nombre' => $firmante->name,
                'firmante_cargo'  => $firmante->cargo,
                'firmante_email'  => $firmante->email,
                'firmante_rfc'    => $rfcDelFirmante,
                'fecha'           => $firmadoEn,
                'hash_acuse'      => $this->calcularHash($cadenaOriginal),
                'cadena_original' => $cadenaOriginal,
                'ip_origen'       => $request->ip(),
                'user_agent'      => substr($request->userAgent() ?? '', 0, 500),
                'observaciones'   => $observaciones,
                'estatus'         => Firma::ESTATUS_ACTIVA,
            ]);
        } catch (QueryException $e) {
            // Si dos peticiones simultáneas pasaron las dos la comprobación de
            // arriba, el índice único de la base frena a la segunda. Se traduce el
            // error técnico de PostgreSQL a un hecho del negocio.
            if ($this->esViolacionDeClaveUnica($e)) {
                throw FirmaDuplicadaException::paraTipo($tipo);
            }
            throw $e;
        }

        // Al firmarse, el documento CONGELA los nombres de catálogo que usó
        // (dependencia, unidad, sector, tipo de trámite...).
        //
        // A partir de aquí dice lo que decía: si mañana la dependencia se renombra,
        // el documento firmado no cambia. El sistema detecta la diferencia y avisa
        // —para que una persona decida si hay que rehacerlo—, pero no lo altera por
        // su cuenta. Cambiar el contenido de un acto ya firmado no es aceptable.
        //
        // Se comprueba con method_exists porque la firma es polimórfica: vale para
        // trámites, acciones de agenda y propuestas, y quizá mañana para algo que
        // todavía no use el trait CongelaCatalogos.
        if (method_exists($firmable, 'congelarCatalogos')) {
            $firmable->congelarCatalogos();
        }

        return $firma;
    }

    /**
     * ¿Sigue siendo válida esta firma?
     *
     * Comprueba DOS cosas distintas, y las dos tienen que cumplirse:
     *
     *   1. Que la fila `firmas` no se haya manipulado. Se re-calcula el hash de la
     *      cadena guardada y se compara con el hash guardado. Si alguien editó la
     *      cadena a mano en la base, esto lo detecta.
     *
     *   2. Que EL DOCUMENTO no haya cambiado desde que se firmó. Se vuelve a armar
     *      la cadena con los datos ACTUALES del trámite y se compara su hash con el
     *      que se guardó al firmar. Si alguien editó el nombre oficial o el costo
     *      después de firmar, esto lo detecta.
     *
     * La comprobación (2) es la que faltaba, y es la razón de ser de toda la firma.
     * Sin ella, el hash SHA-256 no probaba nada: era un sello sobre un documento
     * que se podía cambiar después.
     *
     * Devuelve false si la firma no se puede verificar (le falta la cadena, o el
     * documento firmado ya no existe). "No verificable" y "alterada" se tratan
     * igual: en ninguno de los dos casos se puede afirmar que la firma sea válida.
     */
    public function verificarIntegridad(Firma $firma): bool
    {
        if (empty($firma->cadena_original) || empty($firma->hash_acuse) || empty($firma->fecha)) {
            return false;
        }

        // (1) La fila no se ha tocado.
        if ($this->calcularHash($firma->cadena_original) !== $firma->hash_acuse) {
            return false;
        }

        // (2) El documento sigue diciendo lo que decía.
        $firmable = $firma->firmable;

        if (! $firmable) {
            // El trámite firmado ya no existe (se borró). No se puede afirmar nada.
            return false;
        }

        $cadenaActual = $this->armarCadena(
            tipo:          $firma->tipo,
            firmableType:  $firma->firmable_type,
            firmableId:    $firma->firmable_id,
            datosFirmable: $this->extraerDatosClaveDelFirmable($firmable),
            firmanteId:    $firma->firmante_id,
            firmanteRfc:   $firma->firmante_rfc,
            firmadoEn:     $firma->fecha,
        );

        return $this->calcularHash($cadenaActual) === $firma->hash_acuse;
    }

    /**
     * Revoca una firma activa. Una firma revocada deja de contar para los conteos
     * de firmas vigentes, pero NO se elimina: es un acto jurídico y su rastro se
     * conserva.
     *
     * Si al revocarla el registro se queda SIN ninguna firma activa, se descongelan
     * sus catálogos. Si no, el documento se quedaría mostrando para siempre los
     * nombres que tenía al firmarse una firma que ya no existe, y una firma nueva no
     * podría volver a congelar (el trait no re-fotografía si ya hay foto).
     */
    public function revocar(Firma $firma, User $revocadaPor, string $motivo): void
    {
        if ($firma->estaRevocada()) {
            return;
        }

        $firma->update([
            'estatus'           => Firma::ESTATUS_REVOCADA,
            'revocada_en'       => now(),
            'revocada_por'      => $revocadaPor->id,
            'motivo_revocacion' => $motivo,
        ]);

        $firmable = $firma->firmable;

        if ($firmable
            && method_exists($firmable, 'descongelarCatalogos')
            && $this->firmasActivas($firmable)->isEmpty()) {
            $firmable->descongelarCatalogos();
        }
    }

    /** Las firmas activas de un registro polimórfico, de la más antigua a la más nueva. */
    public function firmasActivas(Model $firmable)
    {
        return Firma::activas()
            ->where('firmable_type', get_class($firmable))
            ->where('firmable_id',   $firmable->id)
            ->with('firmante')
            ->orderBy('fecha')
            ->get();
    }

    /** ¿El registro ya tiene una firma activa de este tipo? */
    public function yaFirmadoPor(Model $firmable, string $tipo): bool
    {
        return Firma::activas()
            ->where('firmable_type', get_class($firmable))
            ->where('firmable_id',   $firmable->id)
            ->delTipo($tipo)
            ->exists();
    }

    /* ----------------------------------------------------------------------
     | La cadena original
     |----------------------------------------------------------------------*/

    /**
     * Arma la "cadena original": la representación en texto de todo lo que se está
     * firmando. Es lo que se hashea, así que cualquier cambio posterior en alguno de
     * estos datos produce un hash distinto y la firma deja de validar.
     *
     * ── La regla que gobierna este método ──
     *
     * Todos sus parámetros tienen que poder RECUPERARSE después. Si un dato entra
     * aquí pero no queda guardado en ningún sitio, el hash se convierte en un número
     * que nadie puede volver a calcular, y la firma se vuelve imposible de verificar.
     *
     * Por eso el método recibe valores sueltos y no los objetos User y Request: al
     * firmar se le pasan los datos del usuario vivo; al verificar, los mismos datos
     * leídos de la fila `firmas`. La única cosa que cambia entre una llamada y la
     * otra es $datosFirmable — que es precisamente lo que se quiere comprobar.
     *
     * @param  string          $tipo          Tipo de firma.
     * @param  string          $firmableType  Clase del registro firmado.
     * @param  int             $firmableId    Id del registro firmado.
     * @param  array           $datosFirmable Foto de los campos clave del registro.
     * @param  int             $firmanteId    Id de quien firmó.
     * @param  ?string         $firmanteRfc   RFC de quien firmó (hoy siempre null).
     * @param  CarbonInterface $firmadoEn     Instante de la firma.
     */
    private function armarCadena(
        string $tipo,
        string $firmableType,
        int $firmableId,
        array $datosFirmable,
        int $firmanteId,
        ?string $firmanteRfc,
        CarbonInterface $firmadoEn,
    ): string {
        $datos = [
            'tipo_firma'    => $tipo,
            'firmable_type' => $firmableType,
            'firmable_id'   => $firmableId,
            'firmable_data' => $datosFirmable,
            'firmante_id'   => $firmanteId,
            'firmante_rfc'  => $firmanteRfc,
            'firmado_en'    => $firmadoEn->toIso8601String(),
        ];

        // ksort deja las claves siempre en el mismo orden. Sin esto, dos cadenas con
        // los mismos datos pero escritas en distinto orden darían hashes distintos.
        ksort($datos);

        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Los campos del registro que quedan sellados por la firma. Cambiar cualquiera
     * de ellos después de firmar invalida la firma.
     *
     * Para un trámite: la homoclave, el nombre oficial, el costo unitario, el costo
     * total y el estatus. Son los datos que aparecen impresos en el acuse.
     */
    private function extraerDatosClaveDelFirmable(Model $firmable): array
    {
        if ($firmable instanceof Tramite) {
            return [
                'homoclave'      => $firmable->homoclave,
                'nombre_oficial' => $firmable->nombre_oficial,
                'cbu_unitario'   => $firmable->cbu_unitario,
                'cbt_total'      => $firmable->cbt_total,
                'estatus'        => $firmable->estatus,
            ];
        }

        // Por defecto: los campos comunes al resto de modelos firmables.
        return array_filter([
            'nombre'      => $firmable->nombre      ?? null,
            'descripcion' => $firmable->descripcion ?? null,
            'estatus'     => $firmable->estatus     ?? null,
        ]);
    }

    private function calcularHash(string $cadena): string
    {
        return hash('sha256', $cadena);
    }

    /**
     * ¿Este error de base de datos es un choque contra una clave única?
     *
     * PostgreSQL usa el SQLSTATE 23505 para "unique_violation"; MySQL usa el 23000.
     * Se comprueban los dos para no atar el servicio a un motor concreto.
     */
    private function esViolacionDeClaveUnica(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23505', '23000'], true);
    }
}
