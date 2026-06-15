<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder del catálogo SCIAN México 2018.
 *
 * Fuente: INEGI — Sistema de Clasificación Industrial de América del Norte
 * (https://www.inegi.org.mx/app/scian/)
 *
 * Pobla 20 sectores (códigos 11 al 81) y sus subsectores principales
 * (94 subsectores con código de 3 dígitos).
 *
 * Es idempotente: usa updateOrInsert con `codigo` como llave única.
 */
class ScianSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->sembrarSectores();
            $this->sembrarSubsectores();
        });

        $this->command?->info('SCIAN: catálogo de sectores y subsectores cargado.');
    }

    private function sembrarSectores(): void
    {
        $sectores = $this->catalogoSectores();

        foreach ($sectores as $codigo => $nombre) {
            DB::table('sectores_scian')->updateOrInsert(
                ['codigo' => $codigo],
                ['nombre' => $nombre]
            );
        }
    }

    private function sembrarSubsectores(): void
    {
        $subsectores = $this->catalogoSubsectores();

        $sectoresPorCodigo = DB::table('sectores_scian')
            ->pluck('id', 'codigo')
            ->toArray();

        foreach ($subsectores as $entry) {
            [$codigo, $codigoSector, $nombre] = $entry;

            if (!isset($sectoresPorCodigo[$codigoSector])) {
                continue;
            }

            DB::table('subsectores_scian')->updateOrInsert(
                ['codigo' => $codigo],
                [
                    'sector_id' => $sectoresPorCodigo[$codigoSector],
                    'nombre'    => $nombre,
                ]
            );
        }
    }

    /**
     * 20 sectores oficiales SCIAN México 2018.
     */
    private function catalogoSectores(): array
    {
        return [
            '11' => 'Agricultura, cría y explotación de animales, aprovechamiento forestal, pesca y caza',
            '21' => 'Minería',
            '22' => 'Generación, transmisión, distribución y comercialización de energía eléctrica, suministro de agua y de gas natural por ductos al consumidor final',
            '23' => 'Construcción',
            '31' => 'Industrias manufactureras (alimentaria, bebidas, tabaco, textiles)',
            '32' => 'Industrias manufactureras (madera, papel, química, plástico, minerales no metálicos)',
            '33' => 'Industrias manufactureras (metálicas, maquinaria, equipo, muebles)',
            '43' => 'Comercio al por mayor',
            '46' => 'Comercio al por menor',
            '48' => 'Transportes, correos y almacenamiento (terrestre, agua, aire)',
            '49' => 'Transportes, correos y almacenamiento (tubería, mensajería, paquetería)',
            '51' => 'Información en medios masivos',
            '52' => 'Servicios financieros y de seguros',
            '53' => 'Servicios inmobiliarios y de alquiler de bienes muebles e intangibles',
            '54' => 'Servicios profesionales, científicos y técnicos',
            '55' => 'Corporativos',
            '56' => 'Servicios de apoyo a los negocios y manejo de residuos y desechos, y servicios de remediación',
            '61' => 'Servicios educativos',
            '62' => 'Servicios de salud y de asistencia social',
            '71' => 'Servicios de esparcimiento culturales y deportivos, y otros servicios recreativos',
            '72' => 'Servicios de alojamiento temporal y de preparación de alimentos y bebidas',
            '81' => 'Otros servicios excepto actividades gubernamentales',
            '93' => 'Actividades legislativas, gubernamentales, de impartición de justicia y de organismos internacionales y extraterritoriales',
        ];
    }

    /**
     * Subsectores principales SCIAN. Formato: [codigo, codigo_sector, nombre]
     */
    private function catalogoSubsectores(): array
    {
        return [
            // 11 - Agricultura
            ['111', '11', 'Agricultura'],
            ['112', '11', 'Cría y explotación de animales'],
            ['113', '11', 'Aprovechamiento forestal'],
            ['114', '11', 'Pesca, caza y captura'],
            ['115', '11', 'Servicios relacionados con las actividades agropecuarias y forestales'],

            // 21 - Minería
            ['211', '21', 'Extracción de petróleo y gas'],
            ['212', '21', 'Minería de minerales metálicos y no metálicos, excepto petróleo y gas'],
            ['213', '21', 'Servicios relacionados con la minería'],

            // 22 - Energía y agua
            ['221', '22', 'Generación, transmisión, distribución y comercialización de energía eléctrica'],
            ['222', '22', 'Suministro de agua y de gas natural por ductos al consumidor final'],

            // 23 - Construcción
            ['236', '23', 'Edificación'],
            ['237', '23', 'Construcción de obras de ingeniería civil'],
            ['238', '23', 'Trabajos especializados para la construcción'],

            // 31-33 - Manufacturas
            ['311', '31', 'Industria alimentaria'],
            ['312', '31', 'Industria de las bebidas y del tabaco'],
            ['313', '31', 'Fabricación de insumos textiles y acabado de textiles'],
            ['314', '31', 'Fabricación de productos textiles, excepto prendas de vestir'],
            ['315', '31', 'Fabricación de prendas de vestir'],
            ['316', '31', 'Curtido y acabado de cuero y piel, y fabricación de productos de cuero, piel y materiales sucedáneos'],
            ['321', '32', 'Industria de la madera'],
            ['322', '32', 'Industria del papel'],
            ['323', '32', 'Impresión e industrias conexas'],
            ['324', '32', 'Fabricación de productos derivados del petróleo y del carbón'],
            ['325', '32', 'Industria química'],
            ['326', '32', 'Industria del plástico y del hule'],
            ['327', '32', 'Fabricación de productos a base de minerales no metálicos'],
            ['331', '33', 'Industrias metálicas básicas'],
            ['332', '33', 'Fabricación de productos metálicos'],
            ['333', '33', 'Fabricación de maquinaria y equipo'],
            ['334', '33', 'Fabricación de equipo de computación, comunicación, medición y de otros equipos, componentes y accesorios electrónicos'],
            ['335', '33', 'Fabricación de accesorios, aparatos eléctricos y equipo de generación de energía eléctrica'],
            ['336', '33', 'Fabricación de equipo de transporte'],
            ['337', '33', 'Fabricación de muebles, colchones y persianas'],
            ['339', '33', 'Otras industrias manufactureras'],

            // 43 - Comercio al por mayor
            ['431', '43', 'Comercio al por mayor de abarrotes, alimentos, bebidas, hielo y tabaco'],
            ['432', '43', 'Comercio al por mayor de productos textiles y calzado'],
            ['433', '43', 'Comercio al por mayor de productos farmacéuticos, de perfumería, artículos para el esparcimiento, electrodomésticos menores y aparatos de línea blanca'],
            ['434', '43', 'Comercio al por mayor de materias primas agropecuarias y forestales, para la industria, y materiales de desecho'],
            ['435', '43', 'Comercio al por mayor de maquinaria, equipo y mobiliario para actividades agropecuarias, industriales, de servicios y comerciales, y de otra maquinaria y equipo de uso general'],
            ['436', '43', 'Comercio al por mayor de camiones y de partes y refacciones nuevas para automóviles, camionetas y camiones'],
            ['437', '43', 'Intermediación de comercio al por mayor'],

            // 46 - Comercio al por menor
            ['461', '46', 'Comercio al por menor de abarrotes, alimentos, bebidas, hielo y tabaco'],
            ['462', '46', 'Comercio al por menor en tiendas de autoservicio y departamentales'],
            ['463', '46', 'Comercio al por menor de productos textiles, bisutería, accesorios de vestir y calzado'],
            ['464', '46', 'Comercio al por menor de artículos para el cuidado de la salud'],
            ['465', '46', 'Comercio al por menor de artículos de papelería, para el esparcimiento y otros artículos de uso personal'],
            ['466', '46', 'Comercio al por menor de enseres domésticos, computadoras, artículos para la decoración de interiores y artículos usados'],
            ['467', '46', 'Comercio al por menor de artículos de ferretería, tlapalería y vidrios'],
            ['468', '46', 'Comercio al por menor de vehículos de motor, refacciones, combustibles y lubricantes'],
            ['469', '46', 'Comercio al por menor exclusivamente a través de internet, y catálogos impresos, televisión y similares'],

            // 48-49 - Transportes
            ['481', '48', 'Transporte aéreo'],
            ['482', '48', 'Transporte por ferrocarril'],
            ['483', '48', 'Transporte por agua'],
            ['484', '48', 'Autotransporte de carga'],
            ['485', '48', 'Transporte terrestre de pasajeros, excepto por ferrocarril'],
            ['486', '48', 'Transporte por ductos'],
            ['487', '48', 'Transporte turístico'],
            ['488', '48', 'Servicios relacionados con el transporte'],
            ['491', '49', 'Servicios postales'],
            ['492', '49', 'Servicios de mensajería y paquetería'],
            ['493', '49', 'Servicios de almacenamiento'],

            // 51 - Información
            ['511', '51', 'Edición de publicaciones y de software, excepto a través de internet'],
            ['512', '51', 'Industria fílmica y del video, e industria del sonido'],
            ['515', '51', 'Radio y televisión'],
            ['517', '51', 'Telecomunicaciones'],
            ['518', '51', 'Procesamiento electrónico de información, hospedaje y otros servicios relacionados'],
            ['519', '51', 'Otros servicios de información'],

            // 52 - Financieros
            ['521', '52', 'Banca central'],
            ['522', '52', 'Instituciones de intermediación crediticia y financiera no bursátil'],
            ['523', '52', 'Actividades bursátiles, cambiarias y de inversión financiera'],
            ['524', '52', 'Compañías de fianzas, seguros y pensiones'],

            // 53 - Inmobiliarios
            ['531', '53', 'Servicios inmobiliarios'],
            ['532', '53', 'Servicios de alquiler de bienes muebles'],
            ['533', '53', 'Servicios de alquiler de marcas registradas, patentes y franquicias'],

            // 54 - Profesionales
            ['541', '54', 'Servicios profesionales, científicos y técnicos'],

            // 55 - Corporativos
            ['551', '55', 'Dirección de corporativos y empresas'],

            // 56 - Apoyo a negocios
            ['561', '56', 'Servicios de apoyo a los negocios'],
            ['562', '56', 'Manejo de residuos y desechos, y servicios de remediación'],

            // 61 - Educación
            ['611', '61', 'Servicios educativos'],

            // 62 - Salud
            ['621', '62', 'Servicios médicos de consulta externa y servicios relacionados'],
            ['622', '62', 'Hospitales'],
            ['623', '62', 'Residencias de asistencia social y para el cuidado de la salud'],
            ['624', '62', 'Otros servicios de asistencia social'],

            // 71 - Esparcimiento
            ['711', '71', 'Servicios artísticos, culturales y deportivos, y otros servicios relacionados'],
            ['712', '71', 'Museos, sitios históricos, zoológicos y similares'],
            ['713', '71', 'Servicios de entretenimiento en instalaciones recreativas y otros servicios recreativos'],

            // 72 - Alojamiento y alimentos
            ['721', '72', 'Servicios de alojamiento temporal'],
            ['722', '72', 'Servicios de preparación de alimentos y bebidas'],

            // 81 - Otros servicios
            ['811', '81', 'Servicios de reparación y mantenimiento'],
            ['812', '81', 'Servicios personales'],
            ['813', '81', 'Asociaciones y organizaciones'],
            ['814', '81', 'Hogares con empleados domésticos'],

            // 93 - Gobierno
            ['931', '93', 'Actividades legislativas, gubernamentales y de impartición de justicia'],
            ['932', '93', 'Organismos internacionales y extraterritoriales'],
        ];
    }
}
