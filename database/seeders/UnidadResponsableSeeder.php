<?php

namespace Database\Seeders;

use App\Models\UnidadResponsable;
use Illuminate\Database\Seeder;

/**
 * Catálogo oficial de Unidades Responsables del H. Ayuntamiento de La Paz, B.C.S.
 *
 * Total: 159 URs únicas.
 * Fuente: Catálogo institucional 2026.
 *
 * El código de 14 dígitos codifica:
 *   posiciones 1-2:  Poder (01-06)
 *   posiciones 3-4:  Nivel 1 jerárquico
 *   posiciones 5-6:  Dirección General
 *   posiciones 7-8:  Dirección
 *   posiciones 9-10: Subdirección
 *   posiciones 11-12: Departamento
 *   posiciones 13-14: Unidad operativa
 *
 * Idempotente: usa updateOrCreate con `codigo` como llave única.
 */
class UnidadResponsableSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogo() as $ur) {
            UnidadResponsable::updateOrCreate(
                ['codigo' => $ur['codigo']],
                array_merge($ur, ['activo' => true])
            );
        }

        $this->command?->info('Unidades Responsables: '. count($this->catalogo()) .' registros cargados.');
    }

    private function catalogo(): array
    {
        return [
            ['codigo' => '01000000000000', 'poder' => 1, 'nivel' => 'poder', 'nombre' => 'PRESIDENTE MUNICIPAL'],
            ['codigo' => '01000000000100', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN ADMINISTRATIVA'],
            ['codigo' => '01000000000300', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN DE DELEGACIONES MUNICIPALES'],
            ['codigo' => '01000000020000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SECRETARÍA TÉCNICA'],
            ['codigo' => '01000001000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'SECRETARÍA PARTICULAR'],
            ['codigo' => '01000002000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE ASUNTOS JURÍDICOS'],
            ['codigo' => '01000002010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN JURÍDICA'],
            ['codigo' => '01000003000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE ATENCIÓN CIUDADANA'],
            ['codigo' => '01000004000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE PREVENCIÓN DEL DELITO Y JUSTICIA CÍVICA'],
            ['codigo' => '01000005000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE COMUNICACIÓN SOCIAL'],
            ['codigo' => '01000006000100', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'DELEGACIÓN DE SAN ANTONIO, B.C.S.'],
            ['codigo' => '01000006000101', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL TRIUNFO, B.C.S.'],
            ['codigo' => '01000006000102', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SAN ANTONIO DE LA SIERRA, B.C.S.'],
            ['codigo' => '01000006000103', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL ROSARIO, B.C.S.'],
            ['codigo' => '01000006000104', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL VALLE PERDIDO, B.C.S.'],
            ['codigo' => '01000006000200', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'DELEGACIÓN DE TODOS SANTOS, B.C.S.'],
            ['codigo' => '01000006000201', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL PESCADERO, B.C.S.'],
            ['codigo' => '01000006000202', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL REFUGIO, B.C.S.'],
            ['codigo' => '01000006000203', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL VELADERO, B.C.S.'],
            ['codigo' => '01000006000204', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SAN ANDRÉS, B.C.S.'],
            ['codigo' => '01000006000205', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE COLONIA PLUTARCO ELÍAS CALLES, B.C.S.'],
            ['codigo' => '01000006000207', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL SALTITO DE LOS GARCÍA, B.C.S.'],
            ['codigo' => '01000006000208', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE MATANCITAS, B.C.S.'],
            ['codigo' => '01000006000209', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE TEXCALAMA, B.C.S.'],
            ['codigo' => '01000006000210', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL AGUAJE, B.C.S.'],
            ['codigo' => '01000006000211', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE LOS HORCONCITOS, B.C.S.'],
            ['codigo' => '01000006000212', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SANTA GERTRUDIS, B.C.S.'],
            ['codigo' => '01000006000300', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'DELEGACIÓN DE LOS PLANES, B.C.S.'],
            ['codigo' => '01000006000301', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL ANCÓN, B.C.S.'],
            ['codigo' => '01000006000302', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE AGUA AMARGA, B.C.S.'],
            ['codigo' => '01000006000401', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SAN PEDRO DE LA PRESA, B.C.S.'],
            ['codigo' => '01000006000402', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SAN FERMÍN, B.C.S.'],
            ['codigo' => '01000006000403', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE TORIS, B.C.S.'],
            ['codigo' => '01000006000404', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SANTA FÉ, B.C.S.'],
            ['codigo' => '01000006000405', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE LA SOLEDAD, B.C.S.'],
            ['codigo' => '01000006000406', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE CAPORAL, B.C.S.'],
            ['codigo' => '01000006000407', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL PASO DE IRITÚ, B.C.S.'],
            ['codigo' => '01000006000408', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DEL PUERTO CHALE, B.C.S.'],
            ['codigo' => '01000006000409', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SAN HILARIO, B.C.S.'],
            ['codigo' => '01000006000410', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SANTA RITA, B.C.S.'],
            ['codigo' => '01000006000501', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN DE SAN BARTOLO, B.C.S.'],
            ['codigo' => '01000006000502', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN EL CARDONAL, B.C.S.'],
            ['codigo' => '01000006000503', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN EL CORO, B.C.S.'],
            ['codigo' => '01000006000505', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN SAN BLAS, B.C.S.'],
            ['codigo' => '01000006000506', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN COLONIA ÁLVARO OBREGÓN, B.C.S.'],
            ['codigo' => '01000006000507', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN LA MATANZA, B.C.S.'],
            ['codigo' => '01000006000508', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN EJIDO MELITÓN ALBAÑEZ, B.C.S.'],
            ['codigo' => '01000006000509', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN LA TRINIDAD.'],
            ['codigo' => '01000006000600', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'DELEGACIÓN DE EL SARGENTO, B.C.S.'],
            ['codigo' => '01000006000701', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN SAN JUAN DE LA COSTA, B.C.S.'],
            ['codigo' => '01000006000702', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN LA FORTUNA, B.C.S.'],
            ['codigo' => '01000006000703', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN EJIDO ALFREDO V. BONFIL, B.C.S.'],
            ['codigo' => '01000006000704', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN SAN PEDRO, B.C.S.'],
            ['codigo' => '01000006000705', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN SAN EVARISTO, B.C.S.'],
            ['codigo' => '01000006000706', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN EL CENTENARIO, B.C.S.'],
            ['codigo' => '01000006000707', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN EL PROGRESO, B.C.S.'],
            ['codigo' => '01000006000708', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'SUBDELEGACIÓN REFORMA AGRARIA, B.C.S.'],
            ['codigo' => '01000100000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE GESTIÓN INTEGRAL DE LA CIUDAD'],
            ['codigo' => '01000100030000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN TECNICA JURIDICA'],
            ['codigo' => '01000101000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE ORDENAMIENTO DEL TERRITORIO'],
            ['codigo' => '01000101010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE PLANEACIÓN DEL DESARROLLO URBANO'],
            ['codigo' => '01000101020000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE ADMINISTRACIÓN DEL ORDENAMIENTO TERRITORIAL'],
            ['codigo' => '01000102000004', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE PRESUPUESTOS Y CONTRATACIÓN'],
            ['codigo' => '01000104000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE MOVILIDAD Y ESPECIO PÚBLICO'],
            ['codigo' => '01000200000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE SERVICIOS PÚBLICOS'],
            ['codigo' => '01000200000100', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN DE PARTICIPACIÓN CIUDADANA'],
            ['codigo' => '01000200010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN ADMINISTRATIVA'],
            ['codigo' => '01000200010001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE PERSONAL'],
            ['codigo' => '01000200010002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE COMPRAS'],
            ['codigo' => '01000200010003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ALMACÉN'],
            ['codigo' => '01000200010005', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE INSPECCIÓN'],
            ['codigo' => '01000200020001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ALUMBRADO PÚBLICO'],
            ['codigo' => '01000200020004', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE PARQUES Y JARDINES'],
            ['codigo' => '01000200020005', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE CONSTRUCCIÓN Y MANTENIMIENTO'],
            ['codigo' => '01000201000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE OPERACIONES'],
            ['codigo' => '01000201000001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE MAQUINARIA PESADA'],
            ['codigo' => '01000201000002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE TALLERES'],
            ['codigo' => '01000201000003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE BARRIDO MANUAL Y MECÁNICO'],
            ['codigo' => '01000202000001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE RECOLECCIÓN DE BASURA'],
            ['codigo' => '01000203000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE PANTEONES MUNICIPALES'],
            ['codigo' => '01000203000100', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN DE PANTEONES MUNICIPALES'],
            ['codigo' => '01000203000101', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE SERVICIOS FUNERARIOS'],
            ['codigo' => '01000203000103', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DEL PANTEÓN DE LOS SAN JUANES'],
            ['codigo' => '01000300000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE BIENESTAR Y DESARROLLO ECONÓMICO'],
            ['codigo' => '01000302000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE FOMENTO ECONÓMICO'],
            ['codigo' => '01000302000002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ADMINISTRACIÓN DE LOS MERCADOS MUNICIPALES'],
            ['codigo' => '01000302000003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ADMINISTRACIÓN DEL MERCADO PÚBLICO MUNICIPAL "FRANCISCO I. MADERO"'],
            ['codigo' => '01000302000004', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ADMINISTRACIÓN DEL MERCADO PÚBLICO MUNICIPAL " GENERAL NICOLÁS BRAVO"'],
            ['codigo' => '01000303000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE TURISMO'],
            ['codigo' => '01000304010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE PROYECTOS DE INVERSIÓN'],
            ['codigo' => '01000305000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE DESARROLLO DELEGACIONAL SUSTENTABLE'],
            ['codigo' => '01000305000001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE VINCULACIÓN Y FINANCIAMIENTO'],
            ['codigo' => '01000305000002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE PESCA, ACUACULTURA Y AGROPECUARIO'],
            ['codigo' => '01000400000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE SEGURIDAD PÚBLICA, POLICÍA PREVENTIVA Y TRÁNSITO MUNICIPAL'],
            ['codigo' => '01000400000004', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'UNIDAD DE SITIO AFIS'],
            ['codigo' => '01000400000007', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'UNIDAD MÉDICOS LEGISTAS'],
            ['codigo' => '01000400010001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'UNIDAD DE RECURSOS HUMANOS'],
            ['codigo' => '01000401000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE MOVILIDAD Y SEGURIDAD VIAL'],
            ['codigo' => '01000403000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE LA POLICÍA AUXILIAR'],
            ['codigo' => '01000405000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DEL SISTEMA MUNICIPAL DE TRANSPORTE'],
            ['codigo' => '01000500000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE CATASTRO'],
            ['codigo' => '01000500010003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE CARTOGRAFÍA'],
            ['codigo' => '01000600000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE INCLUSIÓN Y DIVERSIDAD'],
            ['codigo' => '01000600000300', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN DE COMUNICACIÓN Y DIFUSIÓN'],
            ['codigo' => '01000600000500', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN MUNICIPAL DE DERECHOS HUMANOS'],
            ['codigo' => '01000600010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE PLANEACIÓN, SEGUIMIENTO Y EVALUACIÓN DE POLÍTICAS PÚBLICAS INCLUYENTES'],
            ['codigo' => '01000601000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN MUNICIPAL DE INCLUSIÓN'],
            ['codigo' => '01000602000100', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN MUNICIPAL DE CULTURA FÍSICA'],
            ['codigo' => '01000603000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN MUNICIPAL DE LA JUVENTUD'],
            ['codigo' => '01000604000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN MUNICIPAL DE CULTURA'],
            ['codigo' => '01000604000002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE BIBLIOTECAS Y FOMENTO EDITORIAL'],
            ['codigo' => '01000700000000', 'poder' => 1, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DE GOBIERNO DIGITAL'],
            ['codigo' => '01010000000000', 'poder' => 1, 'nivel' => 'direccion_general', 'nombre' => 'SECRETARÍA GENERAL'],
            ['codigo' => '01010000000003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO ENLACE JURÍDICO Y CERTIFICACIONES DIVERSAS'],
            ['codigo' => '01010001000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN TÉCNICA DE CABILDO'],
            ['codigo' => '01010002000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE PROTECCIÓN CIVIL'],
            ['codigo' => '01010003000002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE APOYO ADMINISTRATIVO'],
            ['codigo' => '01020000000000', 'poder' => 1, 'nivel' => 'direccion_general', 'nombre' => 'TESORERÍA MUNICIPAL'],
            ['codigo' => '01020000000200', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN DE CAJA GENERAL'],
            ['codigo' => '01020001010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE RECAUDACIÓN'],
            ['codigo' => '01020001010002', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE OFICINAS RECAUDADORAS'],
            ['codigo' => '01020001020003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ZOFEMAT'],
            ['codigo' => '01020001030000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE EJECUCIÓN FISCAL'],
            ['codigo' => '01020002000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE EGRESOS'],
            ['codigo' => '01020002020000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE CONTABILIDAD'],
            ['codigo' => '01020004020000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE INSPECCIÓN'],
            ['codigo' => '01020004020001', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE NOTIFICADORES E INSPECTORES'],
            ['codigo' => '01030000000000', 'poder' => 1, 'nivel' => 'direccion_general', 'nombre' => 'OFICIALÍA MAYOR'],
            ['codigo' => '01030001000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE RECURSOS HUMANOS'],
            ['codigo' => '01030002000000', 'poder' => 1, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE ADQUISICIONES Y SERVICIOS GENERALES'],
            ['codigo' => '01030002010000', 'poder' => 1, 'nivel' => 'departamento', 'nombre' => 'SUBDIRECCIÓN DE ADQUISICIONES Y SERVICIOS GENERALES'],
            ['codigo' => '01040000000000', 'poder' => 1, 'nivel' => 'direccion_general', 'nombre' => 'CONTRALORÍA MUNICIPAL'],
            ['codigo' => '01040000000003', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE RESPONSABILIDAD Y SITUACIÓN PATRIMONIAL'],
            ['codigo' => '01040000000005', 'poder' => 1, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE LA UNIDAD DE TRANSPARENCIA'],
            ['codigo' => '02000000000000', 'poder' => 2, 'nivel' => 'poder', 'nombre' => 'SINDICATURA'],
            ['codigo' => '03010000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'PRIMERA REGIDURÍA'],
            ['codigo' => '03020000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'SEGUNDA REGIDURÍA'],
            ['codigo' => '03030000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'TERCERA REGIDURÍA'],
            ['codigo' => '03040000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'CUARTA REGIDURÍA'],
            ['codigo' => '03050000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'QUINTA REGIDURÍA'],
            ['codigo' => '03060000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'SEXTA REGIDURÍA'],
            ['codigo' => '03070000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'SÉPTIMA REGIDURÍA'],
            ['codigo' => '03080000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'OCTAVA REGIDURÍA'],
            ['codigo' => '03090000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'NOVENA REGIDURÍA'],
            ['codigo' => '03100000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'DÉCIMA REGIDURÍA'],
            ['codigo' => '03110000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'DÉCIMO PRIMERA REGIDURÍA'],
            ['codigo' => '03120000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'DÉCIMO SEGUNDA REGIDURÍA'],
            ['codigo' => '03130000000000', 'poder' => 3, 'nivel' => 'direccion_general', 'nombre' => 'DÉCIMA TERCERA REGIDURÍA'],
            ['codigo' => '04000100000000', 'poder' => 4, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DEL COMITÉ MUNICIPAL PARA EL DESARROLLO INTEGRAL DE LA FAMILIA'],
            ['codigo' => '04000100010101', 'poder' => 4, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE CENTROS ASISTENCIALES'],
            ['codigo' => '04000100010104', 'poder' => 4, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE ASISTENCIA MEDICA'],
            ['codigo' => '04000100010200', 'poder' => 4, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN ADMINISTRATIVA'],
            ['codigo' => '04000100010201', 'poder' => 4, 'nivel' => 'unidad', 'nombre' => 'DEPARTAMENTO DE RECURSOS HUMANOS'],
            ['codigo' => '04000100010400', 'poder' => 4, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN DE PROCURACIÓN DE LA DEFENSA DEL MENOR Y DE LA FAMILIA'],
            ['codigo' => '05000100000000', 'poder' => 5, 'nivel' => 'direccion', 'nombre' => 'DIRECCIÓN GENERAL DEL INSTITUTO MUNICIPAL DE PLANEACIÓN'],
            ['codigo' => '05000100000200', 'poder' => 5, 'nivel' => 'departamento', 'nombre' => 'COORDINACIÓN ADMINISTRATIVA'],
            ['codigo' => '05000102000000', 'poder' => 5, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE POLÍTICAS PUBLICAS Y GESTIÓN DE PROYECTOS'],
            ['codigo' => '05000104000000', 'poder' => 5, 'nivel' => 'subdireccion', 'nombre' => 'DIRECCIÓN DE SEGUIMIENTO Y EVALUACIÓN'],
            ['codigo' => '06000000000000', 'poder' => 6, 'nivel' => 'poder', 'nombre' => 'DIRECCIÓN GENERAL DEL INSTITUTO MUNICIPAL DE LA MUJER'],
        ];
    }
}
