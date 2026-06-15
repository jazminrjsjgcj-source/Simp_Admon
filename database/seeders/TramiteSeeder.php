<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TramiteSeeder extends Seeder
{
    public function run(): void
    {
        $enlaceId = DB::table('users')->where('rol', 'enlace')->value('id');
        $dep112   = DB::table('dependencias')->where('codigo', '112')->value('id');
        $dep102   = DB::table('dependencias')->where('codigo', '102')->value('id');
        $dep105   = DB::table('dependencias')->where('codigo', '105')->value('id');
        $dep107   = DB::table('dependencias')->where('codigo', '107')->value('id');

        $tramites = [
            [
                'nombre_oficial'           => 'Licencia de Funcionamiento para Establecimientos Comerciales',
                'homoclave'                => 'T-102-04-001',
                'dependencia_id'           => $dep102,
                'servidor_publico'         => 'Lic. Roberto Sánchez Félix',
                'tiene_homoclave'          => true,
                'objetivo'                 => 'Autorizar la apertura y operación de establecimientos comerciales en el municipio.',
                'dirigido_a'               => 'moral',
                'volumen_anual'            => 1250,
                'plazo_resolucion_cantidad'=> 10,
                'plazo_resolucion_unidad'  => 'habiles',
                'monto_derechos'           => 850.00,
                'copias_cantidad'          => 3,
                'copias_precio'            => 1.50,
                'salario_hora_w'           => 68.20,
                'nivel_digitalizacion'     => 2,
                'cbd_directo'              => 854.50,
                'cbi_indirecto'            => 5456.00,
                'cbu_unitario'             => 6310.50,
                'cbt_total'                => 7888125.00,
                'estatus'                  => 'en_revision',
                'created_by'               => $enlaceId,
                'created_at'               => now()->subDays(15),
                'updated_at'               => now()->subDays(2),
            ],
            [
                'nombre_oficial'           => 'Permiso de Uso de Suelo para Construcción',
                'homoclave'                => 'T-105-01-001',
                'dependencia_id'           => $dep105,
                'servidor_publico'         => 'Arq. María Torres Álvarez',
                'tiene_homoclave'          => true,
                'objetivo'                 => 'Verificar que el uso propuesto es compatible con la zonificación del predio.',
                'dirigido_a'               => 'ambas',
                'volumen_anual'            => 800,
                'plazo_resolucion_cantidad'=> 20,
                'plazo_resolucion_unidad'  => 'habiles',
                'monto_derechos'           => 1200.00,
                'copias_cantidad'          => 5,
                'copias_precio'            => 1.50,
                'salario_hora_w'           => 68.20,
                'nivel_digitalizacion'     => 1,
                'cbd_directo'              => 1207.50,
                'cbi_indirecto'            => 10912.00,
                'cbu_unitario'             => 12119.50,
                'cbt_total'                => 9695600.00,
                'estatus'                  => 'borrador',
                'created_by'               => $enlaceId,
                'created_at'               => now()->subDays(8),
                'updated_at'               => now()->subDays(8),
            ],
            [
                'nombre_oficial'           => 'Registro de Anuncio Publicitario en Vía Pública',
                'homoclave'                => 'T-102-04-002',
                'dependencia_id'           => $dep102,
                'servidor_publico'         => 'C. Pedro Ramírez Luna',
                'tiene_homoclave'          => true,
                'objetivo'                 => 'Regular la instalación de anuncios y publicidad exterior en el municipio.',
                'dirigido_a'               => 'moral',
                'volumen_anual'            => 450,
                'plazo_resolucion_cantidad'=> 5,
                'plazo_resolucion_unidad'  => 'habiles',
                'monto_derechos'           => 500.00,
                'copias_cantidad'          => 2,
                'copias_precio'            => 1.50,
                'salario_hora_w'           => 68.20,
                'nivel_digitalizacion'     => 3,
                'cbd_directo'              => 503.00,
                'cbi_indirecto'            => 2728.00,
                'cbu_unitario'             => 3231.00,
                'cbt_total'                => 1453950.00,
                'estatus'                  => 'observado',
                'created_by'               => $enlaceId,
                'created_at'               => now()->subDays(30),
                'updated_at'               => now()->subDays(5),
            ],
            [
                'nombre_oficial'           => 'Constancia de No Adeudo Municipal',
                'homoclave'                => 'T-102-02-001',
                'dependencia_id'           => $dep102,
                'servidor_publico'         => 'Ing. Ana Flores Medina',
                'tiene_homoclave'          => true,
                'objetivo'                 => 'Acreditar que el contribuyente no tiene adeudos con la Tesorería Municipal.',
                'dirigido_a'               => 'ambas',
                'volumen_anual'            => 3200,
                'plazo_resolucion_cantidad'=> 3,
                'plazo_resolucion_unidad'  => 'habiles',
                'monto_derechos'           => 120.00,
                'copias_cantidad'          => 1,
                'copias_precio'            => 1.50,
                'salario_hora_w'           => 68.20,
                'nivel_digitalizacion'     => 4,
                'cbd_directo'              => 121.50,
                'cbi_indirecto'            => 1636.80,
                'cbu_unitario'             => 1758.30,
                'cbt_total'                => 5626560.00,
                'estatus'                  => 'aprobado',
                'created_by'               => $enlaceId,
                'created_at'               => now()->subDays(45),
                'updated_at'               => now()->subDays(1),
            ],
            [
                'nombre_oficial'           => 'Permiso para Eventos Masivos en Espacios Públicos',
                'homoclave'                => 'T-107-02-001',
                'dependencia_id'           => $dep107,
                'servidor_publico'         => 'Lic. Jorge Herrera Castro',
                'tiene_homoclave'          => true,
                'objetivo'                 => 'Autorizar la realización de eventos con más de 50 personas en espacios públicos municipales.',
                'dirigido_a'               => 'ambas',
                'volumen_anual'            => 180,
                'plazo_resolucion_cantidad'=> 15,
                'plazo_resolucion_unidad'  => 'habiles',
                'monto_derechos'           => 2500.00,
                'copias_cantidad'          => 4,
                'copias_precio'            => 1.50,
                'salario_hora_w'           => 68.20,
                'nivel_digitalizacion'     => 2,
                'cbd_directo'              => 2506.00,
                'cbi_indirecto'            => 8184.00,
                'cbu_unitario'             => 10690.00,
                'cbt_total'                => 1924200.00,
                'estatus'                  => 'firmado',
                'created_by'               => $enlaceId,
                'created_at'               => now()->subDays(60),
                'updated_at'               => now()->subDays(10),
            ],
        ];

        foreach ($tramites as $t) {
            DB::table('tramites')->insert($t);
        }
    }
}
