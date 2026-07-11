<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga el catálogo de dependencias y sus unidades administrativas
 * del Ayuntamiento de La Paz.
 *
 * - Si la dependencia no existe, la crea (con código y siglas).
 * - Crea las unidades de cada dependencia (primer nivel orgánico,
 *   aplanado: Direcciones, Subdirecciones y Coordinaciones principales).
 * - Es idempotente: usa firstOrCreate, así que correrlo varias veces
 *   no duplica registros.
 */
class UnidadesAdministrativasSeeder extends Seeder
{
    public function run(): void
    {
        $tieneActivoDep = Schema::hasColumn('dependencias', 'activo');
        $tieneSiglasDep = Schema::hasColumn('dependencias', 'siglas');
        $tieneActivoUni = Schema::hasColumn('unidades_administrativas', 'activo');
        $tieneSiglasUni = Schema::hasColumn('unidades_administrativas', 'siglas');

        $catalogo = [
            [
                'dependencia' => 'Secretaría Técnica',
                'siglas'      => 'ST',
                'unidades'    => [
                ['codigo' => 'UPS', 'nombre' => 'Unidad de Planeación y Seguimiento'],
                ['codigo' => 'UAE', 'nombre' => 'Unidad de Análisis y Estadística'],
                ['codigo' => 'UER', 'nombre' => 'Unidad de Evaluación y Resultados'],
                ],
            ],
            [
                'dependencia' => 'Secretaría General Municipal',
                'siglas'      => 'SGM',
                'unidades'    => [
                ['codigo' => 'DTC', 'nombre' => 'Dirección Técnica de Cabildo'],
                ['codigo' => 'DPC', 'nombre' => 'Dirección de Protección Civil'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'CGHCB', 'nombre' => 'Comandancia General del Heroico Cuerpo de Bomberos'],
                ['codigo' => 'JMR', 'nombre' => 'Junta Municipal de Reclutamiento'],
                ['codigo' => 'DAG', 'nombre' => 'Departamento de Archivo General'],
                ['codigo' => 'DEJCD', 'nombre' => 'Departamento de Enlace Jurídico y Certificaciones Diversas'],
                ],
            ],
            [
                'dependencia' => 'Tesorería Municipal',
                'siglas'      => 'TM',
                'unidades'    => [
                ['codigo' => 'DI', 'nombre' => 'Dirección de Ingresos'],
                ['codigo' => 'DE', 'nombre' => 'Dirección de Egresos'],
                ['codigo' => 'DPP', 'nombre' => 'Dirección de Programación y Presupuesto'],
                ['codigo' => 'DC', 'nombre' => 'Dirección de Comercio'],
                ['codigo' => 'DZFMT', 'nombre' => 'Dirección de la Zona Federal Marítimo Terrestre'],
                ['codigo' => 'DPM', 'nombre' => 'Dirección de Panteones Municipales'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'CCG', 'nombre' => 'Coordinación de Caja General'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ],
            ],
            [
                'dependencia' => 'Oficialía Mayor',
                'siglas'      => 'OM',
                'unidades'    => [
                ['codigo' => 'DRH', 'nombre' => 'Dirección de Recursos Humanos'],
                ['codigo' => 'DASG', 'nombre' => 'Dirección de Adquisiciones y Servicios Generales'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ['codigo' => 'DDO', 'nombre' => 'Departamento de Desarrollo Organizacional'],
                ['codigo' => 'DAC', 'nombre' => 'Departamento de Auditorías y Certificaciones'],
                ],
            ],
            [
                'dependencia' => 'Contraloría Municipal',
                'siglas'      => 'CM',
                'unidades'    => [
                ['codigo' => 'DASG', 'nombre' => 'Dirección de Auditoría y Supervisión Gubernamental'],
                ['codigo' => 'DA', 'nombre' => 'Dirección Anticorrupción'],
                ['codigo' => 'DUT', 'nombre' => 'Dirección de la Unidad de Transparencia'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Gestión Integral de la Ciudad',
                'siglas'      => 'DGGIC',
                'unidades'    => [
                ['codigo' => 'DOT', 'nombre' => 'Dirección de Ordenamiento del Territorio'],
                ['codigo' => 'DOP', 'nombre' => 'Dirección de Obras Públicas'],
                ['codigo' => 'DMA', 'nombre' => 'Dirección de Medio Ambiente'],
                ['codigo' => 'DMEP', 'nombre' => 'Dirección de Movilidad y Espacio Público'],
                ['codigo' => 'DEA', 'nombre' => 'Dirección de Enlace Administrativo'],
                ['codigo' => 'STJ', 'nombre' => 'Subdirección Técnica Jurídica'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Servicios Públicos',
                'siglas'      => 'DGSP',
                'unidades'    => [
                ['codigo' => 'DO', 'nombre' => 'Dirección de Operaciones'],
                ['codigo' => 'DCEP', 'nombre' => 'Dirección de Conservación de Espacios Públicos'],
                ['codigo' => 'SA', 'nombre' => 'Subdirección Administrativa'],
                ['codigo' => 'DVPC', 'nombre' => 'Departamento de Vinculación y Participación Ciudadana'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Sustentabilidad y Manejo de Residuos',
                'siglas'      => 'DGSMR',
                'unidades'    => [
                ['codigo' => 'DSA', 'nombre' => 'Dirección de Saneamiento Ambiental'],
                ['codigo' => 'DA', 'nombre' => 'Dirección Administrativa'],
                ['codigo' => 'CPC', 'nombre' => 'Coordinación de Participación Ciudadana'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Bienestar y Desarrollo Económico',
                'siglas'      => 'DGBDE',
                'unidades'    => [
                ['codigo' => 'DP', 'nombre' => 'Dirección de Planeación'],
                ['codigo' => 'DFE', 'nombre' => 'Dirección de Fomento Económico'],
                ['codigo' => 'DT', 'nombre' => 'Dirección de Turismo'],
                ['codigo' => 'DPI', 'nombre' => 'Dirección de Proyectos e Inversión'],
                ['codigo' => 'DDDS', 'nombre' => 'Dirección de Desarrollo Delegacional Sustentable'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Seguridad Vial y Transporte',
                'siglas'      => 'DGSVT',
                'unidades'    => [
                ['codigo' => 'DMSV', 'nombre' => 'Dirección de Movilidad y Seguridad Vial'],
                ['codigo' => 'DT', 'nombre' => 'Dirección de Transporte'],
                ['codigo' => 'DSMT', 'nombre' => 'Dirección del Sistema Municipal de Transporte'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'CJ', 'nombre' => 'Coordinación Jurídica'],
                ['codigo' => 'UDCS', 'nombre' => 'Unidad de Difusión y Comunicación Social'],
                ['codigo' => 'UIR', 'nombre' => 'Unidad de Informática y Radiocomunicación'],
                ['codigo' => 'UAI', 'nombre' => 'Unidad de Asuntos Internos'],
                ['codigo' => 'UFIE', 'nombre' => 'Unidad de Fortalecimiento Institucional y Estadística'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Seguridad Pública y Policía Preventiva',
                'siglas'      => 'DGSPPP',
                'unidades'    => [
                ['codigo' => 'DPSSP', 'nombre' => 'Dirección de Proximidad Social y Seguridad Pública'],
                ['codigo' => 'SA', 'nombre' => 'Subdirección Administrativa'],
                ['codigo' => 'SJ', 'nombre' => 'Subdirección Jurídica'],
                ['codigo' => 'CFI', 'nombre' => 'Coordinación de Fortalecimiento Institucional'],
                ['codigo' => 'UA', 'nombre' => 'Unidad de Armamento'],
                ['codigo' => 'UPP', 'nombre' => 'Unidad de Profesionalización Policial'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Catastro',
                'siglas'      => 'DGC',
                'unidades'    => [
                ['codigo' => 'ST', 'nombre' => 'Subdirección Técnica'],
                ['codigo' => 'SAC', 'nombre' => 'Subdirección de Administración Catastral'],
                ['codigo' => 'CAA', 'nombre' => 'Coordinación de Apoyo Administrativo'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Inclusión y Diversidad',
                'siglas'      => 'DGID',
                'unidades'    => [
                ['codigo' => 'DMI', 'nombre' => 'Dirección Municipal de Inclusión'],
                ['codigo' => 'DMD', 'nombre' => 'Dirección Municipal del Deporte'],
                ['codigo' => 'DMJ', 'nombre' => 'Dirección Municipal de la Juventud'],
                ['codigo' => 'DMAIA', 'nombre' => 'Dirección Municipal de Asuntos Indígenas y Afromexicanas'],
                ['codigo' => 'DMC', 'nombre' => 'Dirección Municipal de Cultura'],
                ['codigo' => 'CAA', 'nombre' => 'Coordinación de Apoyo Administrativo'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ],
            ],
            [
                'dependencia' => 'Dirección General de Gobierno Digital',
                'siglas'      => 'DGGD',
                'unidades'    => [
                ['codigo' => 'DTID', 'nombre' => 'Dirección de Tecnologías de la Información y Digitalización'],
                ['codigo' => 'DITO', 'nombre' => 'Dirección de Infraestructura Tecnológica y Operaciones'],
                ['codigo' => 'DSA', 'nombre' => 'Dirección de Simplificación Administrativa'],
                ['codigo' => 'CC', 'nombre' => 'Coordinación de Ciberseguridad'],
                ['codigo' => 'CA', 'nombre' => 'Coordinación Administrativa'],
                ['codigo' => 'DEAJ', 'nombre' => 'Departamento de Enlace de Asuntos Jurídicos'],
                ],
            ],
            [
                'dependencia' => 'Dirección de la Policía Auxiliar',
                'siglas'      => 'DPA',
                'unidades'    => [
                ['codigo' => 'CO', 'nombre' => 'Comandancia Operativa'],
                ['codigo' => 'DEJ', 'nombre' => 'Departamento de Enlace Jurídico'],
                ['codigo' => 'DRIC', 'nombre' => 'Departamento de Relaciones Institucionales y Contratos'],
                ],
            ],
        ];

        foreach ($catalogo as $item) {
            // Crear la dependencia si no existe (busca por nombre).
            $dep = DB::table('dependencias')->where('nombre', $item['dependencia'])->first();
            if (!$dep) {
                // Busca el siguiente código numérico libre, para no chocar
                // con las dependencias que ya existían.
                // Se resuelve en PHP y no con SQL crudo, porque REGEXP y
                // CAST(... AS UNSIGNED) son sintaxis exclusiva de MySQL. Así el
                // seeder funciona igual en MySQL y en PostgreSQL.
                $maxCodigo = (int) DB::table('dependencias')
                    ->pluck('codigo')
                    ->filter(fn ($c) => ctype_digit((string) $c))
                    ->map(fn ($c) => (int) $c)
                    ->max();
                $nuevoCodigo = max($maxCodigo, 100) + 1;

                $datosDep = [
                    'codigo'     => (string) $nuevoCodigo,
                    'nombre'     => $item['dependencia'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($tieneSiglasDep) $datosDep['siglas'] = $item['siglas'];
                if ($tieneActivoDep) $datosDep['activo'] = true;

                $depId = DB::table('dependencias')->insertGetId($datosDep);
            } else {
                $depId = $dep->id;
            }

            // Crear las unidades de la dependencia (idempotente por codigo+dep).
            foreach ($item['unidades'] as $uni) {
                $existe = DB::table('unidades_administrativas')
                    ->where('dependencia_id', $depId)
                    ->where('codigo', $uni['codigo'])
                    ->exists();

                if (!$existe) {
                    $datosUni = [
                        'dependencia_id' => $depId,
                        'codigo'         => $uni['codigo'],
                        'nombre'         => $uni['nombre'],
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    if ($tieneActivoUni) $datosUni['activo'] = true;
                    if ($tieneSiglasUni) $datosUni['siglas'] = null;

                    DB::table('unidades_administrativas')->insert($datosUni);
                }
            }
        }

        $this->command->info('Catálogo de unidades administrativas cargado.');
    }
}
