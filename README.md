# PUNTA Proyecto Laravel

**Plataforma Unificada de Normativa, Trámites y Agendas**
H. Ayuntamiento de La Paz, B.C.S.

---

## Instalación rápida

```bash
# 1. Crear proyecto Laravel
composer create-project laravel/laravel punta
cd punta

# 2. Copiar archivos de este paquete sobre el proyecto
#    - database/migrations/ → reemplaza
#    - database/seeders/DatabaseSeeder.php → reemplaza
#    - app/Models/ → reemplaza
#    - app/Http/Controllers/ → agrega
#    - app/Http/Middleware/CheckRole.php → agrega
#    - routes/web.php → reemplaza
#    - resources/views/ → agrega

# 3. Configurar .env
#    DB_DATABASE=punta
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Registrar middleware en bootstrap/app.php
#    (ver sección "Middleware" abajo)

# 5. Crear base de datos
mysql -u root -e "CREATE DATABASE punta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Migrar y sembrar
php artisan migrate --seed

# 7. Copiar CSS del prototipo
#    Copiar toda la carpeta css/ del prototipo a public/css/

# 8. Lanzar
php artisan serve
```

---

## Middleware

En `bootstrap/app.php`, agregar:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\CheckRole::class,
    ]);
})
```

---

## Credenciales de prueba

| Rol | Email | Password |
|---|---|---|
| Enlace | enlace@lapaz.gob.mx | punta2026 |
| Sujeto Obligado | sujeto@lapaz.gob.mx | punta2026 |
| Autoridad Revisora | revisora@lapaz.gob.mx | punta2026 |
| Área Jurídica | juridico@lapaz.gob.mx | punta2026 |
| Administrador | admin@lapaz.gob.mx | punta2026 |

---

## Estructura de archivos

```
database/
  migrations/
    000001_create_core_tables.php        → dependencias, unidades, SCIAN, users, config, regulaciones
    000002_create_tramites_tables.php     → tramites, requisitos, proceso, fundamento, ficha portal
    000003_create_agenda_regulatoria.php  → agenda, propuestas, AIR, exenciones, calendario, polimórficas
  seeders/
    DatabaseSeeder.php                    → 21 dependencias, 105 unidades, 5 usuarios, config

app/
  Models/
    User.php            → Auth + roles + relaciones
    Dependencia.php     → hasMany unidades, tramites, users
    Tramite.php         → 34 columnas, relaciones completas
    + 5 model stubs     → para completar según se necesite

  Http/
    Controllers/
      DashboardController.php  → KPIs por rol + pendientes
    Middleware/
      CheckRole.php            → role:enlace,revisora,...

routes/
  web.php              → ~40 rutas organizadas por rol

resources/views/
  layouts/app.blade.php     → Sidebar + topbar + modal + toast
  screens/dashboard.blade.php → Primer screen funcional
```

---

## Orden de desarrollo recomendado

1. ✅ Migraciones + Seeders + Auth + Layout
2. → Dashboard funcional con KPIs reales
3. → Trámites CRUD (wizard → form multi-step)
4. → Agenda SyD
5. → Regulaciones
6. → Revisión + Observaciones
7. → Correcciones
8. → Firmas + Acuse (PDF con DomPDF)
9. → Agenda Regulatoria
10. → Calendario
11. → Bitácora
12. → Admin usuarios

---

## Notas técnicas

- **Base de datos:** MySQL 8+ o MariaDB 10.6+
- **PHP:** 8.2+
- **Laravel:** 11+
- **CSS:** Se reutiliza directamente del prototipo (12 archivos)
- **Tablas polimórficas:** observaciones, correcciones, firmas, documentos, bitacora usan morphs de Laravel
- **Cálculo de costo burocrático:** Basado en metodología ATDT mayo 2026 (ver MIGRACIONES.md)
