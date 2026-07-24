<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Dependencias
        $deps = [
            ['codigo'=>'101','nombre'=>'Presidencia Municipal'],
            ['codigo'=>'102','nombre'=>'Tesorería Municipal'],
            ['codigo'=>'103','nombre'=>'Secretaría del Ayuntamiento'],
            ['codigo'=>'104','nombre'=>'Sindicatura Municipal'],
            ['codigo'=>'105','nombre'=>'Dirección General de Gestión Integral de la Ciudad'],
            ['codigo'=>'106','nombre'=>'Dirección General de Desarrollo Social'],
            ['codigo'=>'107','nombre'=>'Dirección General de Seguridad Pública'],
            ['codigo'=>'108','nombre'=>'Dirección General de Obras Públicas'],
            ['codigo'=>'109','nombre'=>'Dirección General de Servicios Públicos'],
            ['codigo'=>'110','nombre'=>'Dirección General de Gobierno Digital'],
            ['codigo'=>'111','nombre'=>'Dirección General de Turismo'],
            ['codigo'=>'112','nombre'=>'Dirección General de Sustentabilidad y Manejo de Residuos'],
            ['codigo'=>'113','nombre'=>'Oficialía Mayor'],
            ['codigo'=>'114','nombre'=>'Contraloría Municipal'],
            ['codigo'=>'115','nombre'=>'Dirección de Catastro'],
            ['codigo'=>'116','nombre'=>'Dirección de Padrón y Licencias'],
            ['codigo'=>'117','nombre'=>'Dirección de Desarrollo Económico'],
            ['codigo'=>'118','nombre'=>'Dirección de Medio Ambiente'],
            ['codigo'=>'119','nombre'=>'Dirección de Protección Civil'],
            ['codigo'=>'120','nombre'=>'Coordinación de Comunicación Social'],
            ['codigo'=>'121','nombre'=>'Instituto Municipal de la Mujer'],
        ];
        foreach ($deps as $d) {
            DB::table('dependencias')->insertOrIgnore(array_merge($d, ['created_at'=>now(),'updated_at'=>now()]));
        }

        $dep110 = DB::table('dependencias')->where('codigo','110')->value('id');
        $dep113 = DB::table('dependencias')->where('codigo','113')->value('id');
        $dep102 = DB::table('dependencias')->where('codigo','102')->value('id');
        $dep114 = DB::table('dependencias')->where('codigo','114')->value('id');

        // Un usuario por cada rol para pruebas
        $usuarios = [
            ['name'=>'Carlos Méndez Ruiz',     'email'=>'enlace@lapaz.gob.mx',    'rol'=>'enlace',   'cargo'=>'Enlace de Simplificación',       'dependencia_id'=>$dep110],
            ['name'=>'Administrador PUNTA',     'email'=>'admin@lapaz.gob.mx',     'rol'=>'admin',    'cargo'=>'Administrador del Sistema',      'dependencia_id'=>$dep113],
            ['name'=>'Lic. Ana Torres Sánchez', 'email'=>'juridico@lapaz.gob.mx',  'rol'=>'juridico', 'cargo'=>'Directora Jurídica',             'dependencia_id'=>$dep110],
            ['name'=>'Mtra. Laura Vega Ruiz',   'email'=>'revisora@lapaz.gob.mx',  'rol'=>'revisora', 'cargo'=>'Revisora de Trámites',           'dependencia_id'=>$dep114],
            ['name'=>'Ing. Roberto Díaz López', 'email'=>'sujeto@lapaz.gob.mx',   'rol'=>'sujeto',   'cargo'=>'Director General (sujeto obligado)', 'dependencia_id'=>$dep102],
        ];

        foreach ($usuarios as $u) {
            DB::table('users')->insertOrIgnore(array_merge($u, [
                'password'   => Hash::make('punta2026'),
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Configuración del sistema
        DB::table('configuracion_sistema')->insertOrIgnore([
            ['clave'=>'salario_hora_w',         'valor'=>'68.20', 'created_at'=>now(),'updated_at'=>now()],
            ['clave'=>'umbral_proporcionalidad', 'valor'=>'0',    'created_at'=>now(),'updated_at'=>now()],
        ]);

      // ── Periodos: UNO ACTIVO POR TIPO ──
        //
        // Antes esto eran dos insertOrIgnore con query builder crudo, y la migración de la
        // tabla insertaba TODAVÍA OTRO periodo activo de agenda_syd. Cada instalación limpia
        // arrancaba con dos SyD activos, rompiendo la regla central del módulo.
        //
        // Se usan las constantes del modelo (Periodo::ESTATUS_ACTIVO) y no las cadenas a
        // mano: de la palabra exacta 'activo' cuelgan el scope, el servicio y el índice único
        // de la base. Un 'Activo' con mayúscula se guardaría sin queja, y ese periodo no
        // estaría activo para nadie.
        //
        // updateOrInsert por `nombre` mantiene el seeder idempotente: correrlo dos veces no
        // duplica nada.
        DB::table('periodos')->updateOrInsert(
            ['nombre' => 'Periodo SyD Enero-Junio 2026'],
            [
                'tipo'         => \App\Models\Periodo::TIPO_SYD,
                'fecha_inicio' => '2026-01-01',
                'fecha_fin'    => '2026-06-30',
                'estatus'      => \App\Models\Periodo::ESTATUS_ACTIVO,
                'descripcion'  => 'Primer semestre — Agenda de Simplificación y Desarrollo.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        DB::table('periodos')->updateOrInsert(
            ['nombre' => 'Periodo Regulatorio 2026'],
            [
                'tipo'         => \App\Models\Periodo::TIPO_REGULATORIA,
                'fecha_inicio' => '2026-01-01',
                'fecha_fin'    => '2026-12-31',
                'estatus'      => \App\Models\Periodo::ESTATUS_ACTIVO,
                'descripcion'  => 'Ejercicio anual — Agenda Regulatoria.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // SCIAN: catálogo oficial de sectores económicos
        $this->call(ScianSeeder::class);

        // Unidades Responsables: catálogo oficial del Ayuntamiento
        $this->call(UnidadResponsableSeeder::class);

        // Parámetros del cálculo de costo burocrático
        $this->call(ParametrosCostoBurocraticoSeeder::class);
        $this->call(UnidadesValorReferenciaSeeder::class);

        $this->call(TramiteSeeder::class);

        // ACL: poblar roles, permisos y migrar usuarios existentes
        $this->call(AclSeeder::class);

        // Tesauro jurídico: la traducción entre lo que dice el ciudadano y lo que
        // dice la ley (permiso → derecho, licencia, autorizacion, cuota).
        //
        // Hasta ahora este seeder NO lo llamaba nadie: solo se mencionaba dentro de
        // dos mensajes de error de `php artisan tesauro:probar`, pidiéndole a un
        // humano que lo corriera a mano. Es decir, el tesauro existía únicamente si
        // alguien se acordaba. Si no, el buscador seguía respondiendo sin errores y
        // sin encontrar nada, que es el peor modo de fallo posible.
        //
        // Es idempotente (updateOrInsert por termino_ciudadano), así que correrlo de
        // más no duplica ni rompe nada.
        $this->call(TesauroJuridicoSeeder::class);
    }
}
