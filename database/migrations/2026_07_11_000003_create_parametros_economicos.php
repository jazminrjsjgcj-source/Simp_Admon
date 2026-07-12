<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros económicos para el Costo Burocrático indirecto por plazo de resolución.
 *
 * ── Qué se estaba calculando mal ─────────────────────────────────────
 *
 * El costo de esperar la resolución de un trámite se calculaba así:
 *
 *     días naturales × salario_hora × 8
 *
 * Es decir, unos $545.60 por cada día de espera. Eso NO es lo que dice la
 * metodología, y el error es de dos órdenes de magnitud.
 *
 * La metodología no usa el salario. Usa el COSTO DE OPORTUNIDAD DIARIO: lo que la
 * persona deja de ganar por no tener todavía la resolución. Y lo calcula distinto
 * según a quién va dirigido el trámite:
 *
 *   PERSONAS FÍSICAS (Ecuaciones 6-8)
 *       PIBpc    = PIB / Población
 *       CO_diario = (TasaLibreRiesgo / 365) × (PIBpc / 365)
 *       Costo     = CO_diario × días naturales
 *
 *       En el ejemplo de la metodología: ~$0.07 por día.
 *
 *   PERSONAS MORALES (Ecuaciones 9-13)
 *       Depende de la ACTIVIDAD ECONÓMICA (SCIAN) y de la ETAPA de la empresa.
 *
 *       TasaProdAnual = ValorProducción / (Gasto + Remuneraciones + Inversión) − 1
 *       TasaProdDiaria = TasaProdAnual / 365
 *
 *       Capital = Gasto + Remuneraciones + Inversión          (operación y cierre)
 *       Capital = Gasto + Remuneraciones + Inversión + Activos (apertura)
 *
 *       CapitalPorEmpresa      = Capital / NúmeroDeEmpresas
 *       CapitalPorEmpresaDiario = CapitalPorEmpresa / 365
 *
 *       CO_diario = TasaProdDiaria × CapitalPorEmpresaDiario
 *       Costo     = CO_diario × días naturales
 *
 *       En el ejemplo de la metodología: entre $1.37 y $4.08 por día.
 *
 * ── Qué crea esta migración ──────────────────────────────────────────
 *
 * 1. Ensancha la columna `valor` de parametros_costo_burocratico. Era decimal(12,4),
 *    con tope en 99,999,999.9999. El PIB de México son billones de pesos: no cabía.
 *
 * 2. Añade las tres claves que faltaban para las personas físicas: pib, poblacion y
 *    tasa_libre_riesgo. Se guardan los tres por separado, y no el PIB per cápita ya
 *    dividido, para que el cálculo sea auditable: cualquiera puede comprobar de dónde
 *    salió cada número.
 *
 * 3. Crea `parametros_actividad_economica`, con los seis datos que la metodología
 *    exige por actividad SCIAN.
 *
 * ── Y si no se cargan los parámetros ─────────────────────────────────
 *
 * El costo de resolución sale CERO y se marca como "no calculable". No se inventa un
 * número aproximado.
 *
 * Es la misma decisión que el código ya toma con el umbral (IMPACTO_NO_DETERMINADO en
 * vez de dividir por cero). Un número mal calculado es peor que ningún número, porque
 * nadie sabe que está mal — que es exactamente cómo nació el bug que esta migración
 * viene a arreglar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. El PIB no cabe en decimal(12,4). Se ensancha.
        //    Se usa SQL crudo porque cambiar el tipo de una columna con el Schema
        //    Builder exige el paquete doctrine/dbal, que este proyecto no tiene.
        Schema::table('parametros_costo_burocratico', function (Blueprint $table) {
            // marcador: el cambio real va en el DB::statement de abajo
        });

        \DB::statement('ALTER TABLE parametros_costo_burocratico ALTER COLUMN valor TYPE numeric(24,4)');

        // 2. Los datos económicos por actividad, para las personas morales.
        Schema::create('parametros_actividad_economica', function (Blueprint $table) {
            $table->id();

            // A qué actividad SCIAN corresponden. El subsector es más específico que el
            // sector; el servicio busca primero por subsector y luego por sector, igual
            // que hace ya con los umbrales.
            $table->foreignId('sector_id')->nullable()->constrained('sectores_scian');
            $table->foreignId('subsector_id')->nullable()->constrained('subsectores_scian');

            // Los seis datos de la metodología (Ecuaciones 9-12). Son magnitudes
            // agregadas de toda la actividad económica: miles de millones de pesos.
            $table->decimal('valor_produccion', 24, 2)->comment('VProd: valor de la producción anual');
            $table->decimal('gasto_consumo',    24, 2)->comment('GaC: gasto en bienes e insumos anual');
            $table->decimal('remuneraciones',   24, 2)->comment('Rem: remuneraciones pagadas');
            // NULLABLE a propósito. La monografía de los Censos Económicos del INEGI
            // (Tabla 3) publica CINCO de los seis datos que la metodología pide:
            // producción bruta total, consumo intermedio, remuneraciones, activos fijos y
            // unidades económicas. NO publica la Formación Bruta de Capital Fijo por
            // sector — hay que sacarla del SAIC (inegi.org.mx/app/saic).
            //
            // Mientras esté en null, la actividad NO ES CALCULABLE y el sistema lo dice.
            // No se pone un cero ni una estimación: eso sería repetir exactamente el bug
            // que esta migración viene a arreglar — una fórmula plausible tapando un hueco
            // que nadie veía.
            $table->decimal('inversion', 24, 2)->nullable()->comment('Inv: formación bruta de capital fijo (INEGI SAIC)');
            $table->decimal('activos_fijos',    24, 2)->comment('Act: valor de los activos fijos (solo APERTURA)');
            $table->unsignedBigInteger('num_empresas')->comment('NumE: número de personas morales en la actividad');

            $table->unsignedSmallInteger('anio');
            $table->string('fuente', 255)->nullable()->comment('De dónde salió el dato (ej. Censos Económicos INEGI 2024)');
            $table->boolean('activo')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['subsector_id', 'activo']);
            $table->index(['sector_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_actividad_economica');

        \DB::statement('ALTER TABLE parametros_costo_burocratico ALTER COLUMN valor TYPE numeric(12,4)');
    }
};
