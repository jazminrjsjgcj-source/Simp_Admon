<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando un formulario intenta modificar un requisito que NO pertenece al trámite
 * que se está guardando.
 *
 * ── El ataque, en una frase ──────────────────────────────────────────
 *
 * El formulario de edición manda el id de cada requisito en un campo oculto. Cambiándolo con
 * las herramientas del navegador (F12), un enlace puede escribir el id de un requisito de OTRA
 * dependencia y sobrescribirlo con lo que quiera.
 *
 * Antes esto funcionaba, porque el servicio actualizaba así:
 *
 *     Requisito::where('id', $req['id'])->update($datos);
 *
 * Sin comprobar de quién era ese requisito. Un enlace de Comercio podía cambiar el "Dictamen
 * estructural" de un trámite de Desarrollo Urbano —una revisión de seguridad de quince días— y
 * dejarlo en "Ninguno, trámite exprés", desde su propia sesión, sin permisos especiales y sin
 * dejar rastro en ningún sitio.
 *
 * Es una vulnerabilidad clásica: referencia directa a objeto sin control de propiedad.
 *
 * ── Por qué se ABORTA y no se ignora en silencio ─────────────────────
 *
 * Ignorar el id intruso también cerraría el agujero: el `update` no encontraría nada y el
 * guardado seguiría. Es más simple, y por eso era tentador.
 *
 * Pero deja al atacante probar mil veces sin que quede constancia de ninguna. Y sobre todo:
 * confunde dos cosas que no son iguales.
 *
 *   Un id que no cuadra NO ES UN DATO RARO. Es un formulario manipulado.
 *
 * El formulario de PUNTA nunca manda el id de un requisito ajeno. Si llega uno, alguien lo
 * puso ahí a mano. Tratar eso como "un dato que ignoramos" es tratar un intento de manipulación
 * como si fuera una errata.
 *
 * Abortar tiene dos consecuencias, y las dos son deseables:
 *
 *   1. El guardado entero se cancela. Si alguien manipuló UN campo del formulario, no hay
 *      razón para confiar en el resto.
 *
 *   2. Queda una línea en la bitácora con quién, cuándo y qué requisito ajeno intentó tocar.
 *      Sin eso, el atacante puede seguir probando indefinidamente y nadie lo sabrá nunca.
 */
class RequisitoAjenoException extends RuntimeException
{
    public function __construct(
        public readonly int $requisitoId,
        public readonly int $tramiteId,
    ) {
        parent::__construct(
            'El formulario intentó modificar un requisito que no pertenece a este trámite. '
            . 'El guardado se canceló por seguridad. Recargue la página y vuelva a intentarlo.'
        );
    }
}
