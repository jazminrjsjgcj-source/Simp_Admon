<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * El tesauro: cómo llama el ciudadano a las cosas, y cómo las llama la ley.
 *
 * ══════════════════════════════════════════════════════════════════════
 * ESTE TESAURO SE SIEMBRA COMPLETO. EL CORPUS DECIDE QUÉ SE USA.
 * ══════════════════════════════════════════════════════════════════════
 *
 * La primera versión de este archivo verificaba cada término contra el texto de la Ley de Hacienda
 * y solo sembraba los que aparecían en ella. Parecía riguroso. Era un error de diseño.
 *
 * Porque el municipio va a subir MÁS regulaciones: reglamento de construcción, de alcoholes, de
 * panteones, de mercados. Y un tesauro verificado contra un solo archivo NACE CADUCADO: el día que
 * suba el reglamento de construcción, "perito responsable" no estará en el tesauro, porque no
 * estaba en la Ley de Hacienda el día que se sembró. Habría que resembrarlo a mano cada vez, y
 * nadie se acordaría.
 *
 * Ahora se siembra TODO el vocabulario que valga la pena, aunque hoy no exista en ninguna
 * regulación cargada. Y TesauroService comprueba, EN CADA BÚSQUEDA, qué sinónimos existen de
 * verdad en la base de datos. Los que no existen se ignoran solos, sin ruido y sin coste.
 *
 *     panteon → cementerio, fosa, nicho
 *
 *     Hoy, con solo la Ley de Hacienda:  los que no aparezcan se ignoran.
 *     Mañana, con el Reglamento de Panteones:  empiezan a funcionar SOLOS.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LO QUE EL CORPUS **NO** DECIDE: SI LA TRADUCCIÓN ES CORRECTA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Estos términos salieron de un diccionario de sinónimos del español (61.843 entradas) y de un
 * tesauro jurídico de la SCJN. Y NO se copiaron en bloque. Se leyeron uno a uno.
 *
 * Porque un diccionario de sinónimos generado automáticamente es una bomba:
 *
 *     comprar  →  SOBORNAR        ← sinónimo REAL del español. Y aquí, catastrófico:
 *                                   "cuánto cuesta comprar una casa" devolvería cohecho.
 *     pago     →  REGION, TERRITORIO   (un "pago" es también una comarca vitivinícola)
 *     negocio  →  CARGO
 *     obra     →  OPERA
 *     animal   →  GROSERO
 *
 * Ninguno de esos entra. Y el filtro por frecuencia NO los habría parado: "sobornar" o algo
 * parecido aparecerá en cuanto se suba un Bando de Policía.
 *
 *     EL CORPUS DECIDE QUÉ ESTÁ DISPONIBLE.  ESTE ARCHIVO DECIDE QUÉ ES CORRECTO.
 *
 * Los dos filtros hacen falta. Este es el segundo, y se hace a mano. Siempre.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL TESAURO GUARDA **PALABRAS**, NO FRASES
 * ══════════════════════════════════════════════════════════════════════
 *
 * Esta regla se aprendió rompiendo el buscador. La primera versión de este archivo decía:
 *
 *     casa  →  casa habitacion, predio urbano, bien inmueble, construccion
 *
 * Parece razonable. Y es veneno, porque PostgreSQL NO admite frases con ':*' en un tsquery: el
 * buscador PARTE cada término por los espacios. Lo que de verdad se busca es esto:
 *
 *     (casa | habitacion | predio | URBANO | BIEN | inmueble | CONSTRUCCION)
 *                                     ↑        ↑         ↑
 *                          las mitades sueltas de mis frases compuestas
 *
 * "bien" no es sinónimo de nada: es media frase. Y en una Ley de Hacienda aparece en 114 líneas
 * ("bienes muebles", "bienes de dominio público", "bienes mostrencos"...). Cada una de esas
 * mitades genéricas es un brazo del OR que engorda el resultado hasta el tope de 30 y HUNDE el
 * artículo bueno en el ranking.
 *
 * El caso real: "cuánto se paga por comprar una casa" devolvía 30 resultados y el artículo 38 —el
 * que dice "la tasa del 3%"— no estaba entre ellos. Antes devolvía cero; después, ruido. Las dos
 * cosas son fallar.
 *
 * ── Las mitades que hay que vigilar ──
 *
 *     publico, publica, bien, fiscal, via, uso, urbano, general, municipal
 *
 * Son las palabras que aparecen en TODAS partes. "publicos" sale en 113 nodos: es el ejemplo que
 * el propio BuscadorService usa para explicar por qué existe el filtro de palabras comunes.
 *
 * ── Y por qué NO se arregla con un umbral automático ──
 *
 * La tentación es pasar los sinónimos por el mismo umbral del 5% que ya filtra las palabras del
 * ciudadano. Se probó sobre los números reales, y NO sirve:
 *
 *     bien          114 líneas   ← ruido
 *     predio         60          ← útil
 *     inmueble       55          ← ÚTIL, Y ES LA QUE HACE FUNCIONAR EL CASO
 *     construccion   50          ← ruido, para "casa"
 *
 * "inmueble" y "construccion" salen casi lo mismo. Un umbral las trata igual y tiraría las dos. Y
 * el artículo 38 dice "adquisición de bienes INMUEBLES" y NO dice "casa": si se pierde "inmueble",
 * se vuelve a cero resultados.
 *
 *     LA FRECUENCIA NO SEPARA LO ÚTIL DE LO INÚTIL. Solo dice cuánto sale cada palabra.
 *
 * Es exactamente lo que ya está documentado en VocabularioCorpusService, con otras palabras. Aquí
 * vuelve a pasar. Lo que separa "inmueble" de "construccion" no es su frecuencia: es SU
 * SIGNIFICADO. Y eso lo decide una persona, en este archivo.
 *
 * ══════════════════════════════════════════════════════════════════════
 * NO SIEMBRES UN TÉRMINO QUE SEA PALABRA VACÍA
 * ══════════════════════════════════════════════════════════════════════
 *
 * SearchQueryNormalizer borra las palabras que describen la FORMA de la pregunta antes de que el
 * tesauro las vea: 'cuanto', 'cuesta', 'pago', 'pagar', 'cobro', 'cobrar', 'necesito', 'debo'...
 *
 * Una entrada de tesauro para una de esas palabras JAMÁS SE EJECUTA. Es una fila muerta que no da
 * ningún error: se lee, se cachea, y no casa con nada nunca.
 *
 * Antes de añadir una entrada, mira la lista PALABRAS_VACIAS de SearchQueryNormalizer.
 *
 * ── CÓMO SE AMPLÍA ──
 *
 *   1. Se añade el caso al banco de preguntas (database/banco_preguntas.json).
 *   2. Se busca en la regulación CÓMO LO LLAMA LA LEY.
 *   3. Se comprueba que el término ciudadano NO sea una palabra vacía.
 *   4. Se añade aquí, con origen='reportada'.
 *   5. Se corre php artisan buscador:evaluar y se ve subir el número.
 *
 * Sin el paso 3, se añade una fila muerta. Sin el paso 5, no se sabe si sirvió.
 */
class TesauroJuridicoSeeder extends Seeder
{
    public function run(): void
    {
        $entradas = [

            // ══════════════════════════════════════════════════════════════════
            // COBROS: la palabra que más confunde
            // ══════════════════════════════════════════════════════════════════
            //
            // El ciudadano dice "permiso". La ley dice DERECHO (124 veces en la Ley de Hacienda),
            // CUOTA (22) o TARIFA. Un "permiso" en la ley es casi siempre un DERECHO por uso o
            // por servicio.
            [
                'termino_ciudadano' => 'permiso',
                'terminos_ley'      => 'derecho, licencia, autorizacion, cuota',
                'nota'              => 'La ley casi nunca dice "permiso". Cobra DERECHOS por el uso de la vía '
                                     . 'pública o por servicios. Este es el caso del ambulante: el inciso que '
                                     . 'dice cuánto paga NO contiene la palabra "permiso".',
            ],
            [
                'termino_ciudadano' => 'tarifa',
                'terminos_ley'      => 'cuota, derecho, tasa',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'multa',
                'terminos_ley'      => 'sancion, infraccion, recargo',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'descuento',
                'terminos_ley'      => 'subsidio, exencion, bonificacion',
                'nota'              => 'El ciudadano pregunta por descuentos. La ley habla de subsidios, '
                                     . 'exenciones y estímulos fiscales.',
            ],
            [
                'termino_ciudadano' => 'atraso',
                'terminos_ley'      => 'recargo, mora, extemporaneo, actualizacion',
                'nota'              => 'Pagar tarde: la ley lo llama RECARGO y ACTUALIZACIÓN, no "interés".',
            ],
            [
                'termino_ciudadano' => 'interes',
                'terminos_ley'      => 'recargo, actualizacion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'reembolso',
                'terminos_ley'      => 'devolucion, compensacion',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // COMPRAVENTA DE INMUEBLES
            // ══════════════════════════════════════════════════════════════════
            //
            // El caso que destapó la necesidad del tesauro: "cuánto se paga por comprar una casa"
            // no encontraba el artículo 38 (el del 3%), porque ese artículo dice "ADQUISICIÓN de
            // BIENES INMUEBLES" y no dice ni "comprar" ni "casa".
            [
                'termino_ciudadano' => 'comprar',
                'terminos_ley'      => 'adquisicion, adquirir, enajenacion, traslacion',
                'nota'              => 'Artículo 38: "El impuesto sobre ADQUISICIÓN de bienes inmuebles será el '
                                     . 'que resulte de aplicar al valor del inmueble la tasa del 3%". La ley '
                                     . 'nunca dice "comprar". Y NO se traduce a "sobornar", que es sinónimo real '
                                     . 'del español y aquí sería catastrófico.',
            ],
            [
                'termino_ciudadano' => 'compra',
                'terminos_ley'      => 'adquisicion, enajenacion, traslacion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'vender',
                'terminos_ley'      => 'enajenacion, adquisicion, traslacion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'traspaso',
                'terminos_ley'      => 'traslacion, enajenacion, cesion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'escritura',
                'terminos_ley'      => 'fedatario, notario, escrituracion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'heredar',
                'terminos_ley'      => 'sucesion, legado, adquisicion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'herencia',
                'terminos_ley'      => 'sucesion, legado',
                'nota'              => 'La herencia también causa el impuesto sobre adquisición. La ley lo llama '
                                     . 'SUCESIÓN.',
            ],
            [
                'termino_ciudadano' => 'regalar',
                'terminos_ley'      => 'donacion, adquisicion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'esposo',
                'terminos_ley'      => 'conyuge',
                'nota'              => 'Del tesauro de la SCJN: esposos → cónyuges. Aparece en las exenciones.',
            ],
            [
                'termino_ciudadano' => 'esposa',
                'terminos_ley'      => 'conyuge',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'subasta',
                'terminos_ley'      => 'remate, adjudicacion',
                'nota'              => 'Del tesauro de la SCJN: remate público → subasta pública.',
            ],
            [
                'termino_ciudadano' => 'avaluo',
                'terminos_ley'      => 'catastral, avaluo, peritaje',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'catastro',
                'terminos_ley'      => 'catastral, avaluo, padron',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // INMUEBLES
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'casa',
                'terminos_ley'      => 'habitacion, predio, inmueble',
                'nota'              => 'PALABRAS SUELTAS, NO FRASES. Decía "casa habitacion, predio urbano, bien '
                                     . 'inmueble, construccion", y el buscador lo partía en trozos: "urbano", '
                                     . '"bien" y "construccion" entraban al OR como ruido y hundían el artículo '
                                     . '38 en el ranking. "bien" sale en 114 líneas de la ley. Quedan las tres '
                                     . 'palabras que de verdad son la casa para la ley: habitación, predio e '
                                     . 'inmueble. La palabra "casa" del ciudadano se conserva siempre aparte.',
            ],
            [
                'termino_ciudadano' => 'rancho',
                'terminos_ley'      => 'rustico, predio rustico',
                'nota'              => 'Artículo 32: "impuesto sobre la Propiedad RÚSTICA... predios rústicos... '
                                     . 'explotados por su dueño" (2.00 al millar). El ciudadano dice "rancho"; la '
                                     . 'ley no usa esa palabra nunca, dice "predio rústico". OJO GEMELOS: se '
                                     . 'traduce SOLO a "rustico", NUNCA a "ejidal". Un rancho es propiedad '
                                     . 'privada (art. 32); un ejido es otra figura jurídica (art. 33), aunque los '
                                     . 'dos digan "2 al millar". Traducir rancho→ejidal cobraría por el artículo '
                                     . 'equivocado. "rústica" sale en la ley CON tilde (9 veces); el tesauro se '
                                     . 'siembra sin acentos porque el buscador quita tildes antes de comparar.',
            ],
            [
                'termino_ciudadano' => 'trabaja',
                'terminos_ley'      => 'explotar, explotado',
                'nota'              => 'Artículo 32: la ley distingue "explotados por su dueño" (2.00 al millar) de '
                                     . '"no explotados" (2.5). El ciudadano dice "trabaja"; la ley dice "explotado". '
                                     . 'OJO: la clave es "trabaja", NO "trabajar" — el tesauro casa la palabra '
                                     . 'EXACTA que llega del normalizador (3a persona), y no conjuga. LÍMITE '
                                     . 'CONOCIDO: cada conjugación necesita su fila; comparar por raíz es otra '
                                     . 'sesión. "explotado" sale 6 veces en la ley.',
            ],
            [
                'termino_ciudadano' => 'trabajan',
                'terminos_ley'      => 'explotar, explotado',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'trabajo',
                'terminos_ley'      => 'explotar, explotado',
                'nota'              => 'El ciudadano: "lo trabajo yo mismo". "trabajo" es tambien sustantivo, pero '
                                     . 'aqui no hace dano: va en un AND con rancho/rustico.',
            ],
            [
                'termino_ciudadano' => 'explotar',
                'terminos_ley'      => 'explotar, explotado',
                'nota'              => 'Por si el ciudadano ya usa la palabra de la ley.',
            ],
            [
                'termino_ciudadano' => 'ganaderia',
                'terminos_ley'      => 'rustico, predio rustico, agropecuario',
                'nota'              => 'Otra forma en que el ciudadano nombra un predio rústico. Mismo destino que '
                                     . '"rancho": rústico, nunca ejidal.',
            ],
            [
                'termino_ciudadano' => 'terreno',
                'terminos_ley'      => 'predio, inmueble, solar, lote',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'baldio',
                'terminos_ley'      => 'baldio, edificado, solar',
                'nota'              => 'El artículo 31 castiga el suelo ocioso: 11 al millar, subiendo cada año.',
            ],
            [
                'termino_ciudadano' => 'local',
                'terminos_ley'      => 'establecimiento, giro',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'alquiler',
                'terminos_ley'      => 'arrendamiento, arrendatario, arrendador',
                'nota'              => 'La ley nunca dice "alquiler". Dice ARRENDAMIENTO.',
            ],
            [
                'termino_ciudadano' => 'renta',
                'terminos_ley'      => 'arrendamiento, arrendatario',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // BASURA Y LIMPIA
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'basura',
                'terminos_ley'      => 'residuos, recoleccion, limpia, desechos',
                'nota'              => 'El inciso e habla de "Servicio de recolección de basura", así que aquí sí '
                                     . 'coinciden. Pero otros artículos dicen "residuos sólidos".',
            ],
            [
                'termino_ciudadano' => 'reciclaje',
                'terminos_ley'      => 'residuos, aprovechamiento',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'drenaje',
                'terminos_ley'      => 'alcantarillado, saneamiento, descarga',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // COMERCIO EN LA VÍA PÚBLICA
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'puesto',
                'terminos_ley'      => 'semifijo, ambulante, tianguis',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'ambulante',
                'terminos_ley'      => 'ambulante, semifijo, vendedor',
                'nota'              => 'Se conserva "ambulante" en la lista: la ley SÍ la usa (inciso f). Lo que '
                                     . 'falta es la palabra del COBRO, que es "derecho" y no "permiso".',
            ],
            [
                'termino_ciudadano' => 'negocio',
                'terminos_ley'      => 'establecimiento, giro, comercio',
                'nota'              => 'NO se traduce a "cargo", que es sinónimo real de "negocio" en español y '
                                     . 'aquí llevaría a artículos sobre servidores públicos.',
            ],
            [
                'termino_ciudadano' => 'tienda',
                'terminos_ley'      => 'establecimiento, giro, comercio',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'mercado',
                'terminos_ley'      => 'tianguis, puesto, local',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'extension',
                'terminos_ley'      => 'ocupacion',
                'nota'              => 'Artículo 88, fracción VII TER: "Extensión en la vía pública". La ley cobra '
                                     . 'un DERECHO por ocupar la vía pública con tu negocio. Y OJO: el ciudadano '
                                     . 'suele llamarlo MULTA — pero NO se traduce multa → derecho. Una sanción y '
                                     . 'un cobro no son lo mismo, y quien pregunte "qué multa me ponen por vender '
                                     . 'sin licencia" recibiría tarifas. De esa confusión se ocupa el reformulador '
                                     . 'con IA, que entiende la pregunta; una tabla no puede.',
            ],
            [
                'termino_ciudadano' => 'ocupar',
                'terminos_ley'      => 'ocupacion, extension',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'calle',
                'terminos_ley'      => 'vialidad',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'letrero',
                'terminos_ley'      => 'anuncio, publicidad, rotulo',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'anuncio',
                'terminos_ley'      => 'anuncio, publicidad, rotulo, espectacular',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // ESPECTÁCULOS Y EVENTOS
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'evento',
                'terminos_ley'      => 'espectaculo, diversion, funcion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'concierto',
                'terminos_ley'      => 'espectaculo, diversion, funcion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'fiesta',
                'terminos_ley'      => 'espectaculo, diversion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'boleto',
                'terminos_ley'      => 'boletaje, entrada, espectaculo',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // ALCOHOL  (para el Reglamento de Alcoholes, cuando se suba)
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'alcohol',
                'terminos_ley'      => 'alcoholicas, expendio',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'bar',
                'terminos_ley'      => 'cantina, expendio, alcoholicas',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'cerveza',
                'terminos_ley'      => 'alcoholicas, expendio',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // CONSTRUCCIÓN  (para el Reglamento de Construcción, cuando se suba)
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'construir',
                'terminos_ley'      => 'construccion, edificacion, edificar',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'obra',
                'terminos_ley'      => 'construccion, edificacion, urbanizacion',
                'nota'              => 'NO se traduce a "ópera", que es sinónimo real de "obra" en español.',
            ],
            [
                'termino_ciudadano' => 'ampliacion',
                'terminos_ley'      => 'construccion, edificacion, remodelacion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'remodelar',
                'terminos_ley'      => 'remodelacion, construccion, edificacion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'demoler',
                'terminos_ley'      => 'demolicion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'dividir',
                'terminos_ley'      => 'subdivision, fraccionamiento, lotificacion',
                'nota'              => 'Partir un terreno: la ley lo llama SUBDIVISIÓN o LOTIFICACIÓN.',
            ],
            [
                'termino_ciudadano' => 'arquitecto',
                'terminos_ley'      => 'perito, responsiva',
                'nota'              => 'Vocabulario del Reglamento de Construcción. Hoy no existe en el corpus y '
                                     . 'se ignora solo. Empezará a funcionar cuando se suba el reglamento.',
            ],
            [
                'termino_ciudadano' => 'plano',
                'terminos_ley'      => 'alineamiento, lotificacion',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // PANTEONES  (para el Reglamento de Panteones, cuando se suba)
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'cementerio',
                'terminos_ley'      => 'panteon, fosa, nicho, cripta, inhumacion',
                'nota'              => 'Del tesauro de la SCJN: cementerios → panteones. La ley mexicana dice '
                                     . 'PANTEÓN.',
            ],
            [
                'termino_ciudadano' => 'entierro',
                'terminos_ley'      => 'inhumacion, panteon, fosa',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'tumba',
                'terminos_ley'      => 'fosa, nicho, cripta, panteon',
                'nota'              => null,
            ],

            // ══════════════════════════════════════════════════════════════════
            // REGISTRO CIVIL Y DOCUMENTOS
            // ══════════════════════════════════════════════════════════════════
            [
                'termino_ciudadano' => 'acta',
                'terminos_ley'      => 'acta, certificacion, constancia',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'copia',
                'terminos_ley'      => 'certificada, certificacion, constancia',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'papeles',
                'terminos_ley'      => 'constancia, certificacion',
                'nota'              => null,
            ],
            [
                'termino_ciudadano' => 'obstruir',
                'terminos_ley'      => 'obstaculo',
                'nota'              => 'El ciudadano dice el verbo ("obstruir la banqueta"); el art. 65 del '
                                     . 'Bando dice el sustantivo ("Poner obstáculos en las calles, banquetas…"). '
                                     . 'Sin esta entrada, obstruir:* nunca casa "obstáculos" (raíces distintas).',
            ],
        ];

        // ══════════════════════════════════════════════════════════════════
        // LA FILA MUERTA: se borra, no se deja "por si acaso"
        // ══════════════════════════════════════════════════════════════════
        //
        // Existía una entrada 'cobro' → 'derecho, cuota, tarifa...'. Y NO SE EJECUTABA NUNCA.
        //
        // Porque 'cobro' está en la lista PALABRAS_VACIAS de SearchQueryNormalizer: se borra de la
        // consulta ANTES de que el tesauro la vea. Cuando alguien escribe "cuánto es el cobro por
        // un puesto", al tesauro le llega ['puesto']. La palabra 'cobro' ya no existe.
        //
        // Y está BIEN que sea palabra vacía: "cobro" describe la FORMA de la pregunta, no el tema.
        // Lo que estaba mal era tener una fila de tesauro para una palabra que jamás llega.
        //
        // Se borra explícitamente porque updateOrInsert() no quita nada: si solo se quitara del
        // array, la fila seguiría viva en la base de datos de quien ya la sembró, y este seeder
        // dejaría de reflejar la realidad.
        DB::table('busqueda_tesauro')->where('termino_ciudadano', 'cobro')->delete();

        foreach ($entradas as $e) {
            DB::table('busqueda_tesauro')->updateOrInsert(
                ['termino_ciudadano' => $e['termino_ciudadano']],
                [
                    'terminos_ley' => $e['terminos_ley'],
                    'origen'       => 'inicial',
                    'activo'       => true,
                    'nota'         => $e['nota'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        $this->command?->info('Tesauro jurídico: ' . count($entradas) . ' términos cargados.');
        $this->command?->line(
            '  Se siembra COMPLETO, incluido vocabulario de regulaciones que aún no se han subido. '
            . 'TesauroService comprueba en cada búsqueda qué sinónimos existen de verdad en el '
            . 'corpus: los que no, se ignoran solos y empiezan a funcionar el día que subas la '
            . 'regulación que los usa.'
        );
    }
}
