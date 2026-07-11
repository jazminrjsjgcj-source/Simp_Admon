<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración del Access Control List (ACL).
 *
 * Crea 5 tablas:
 *   - roles: catálogo de roles del sistema
 *   - permisos: catálogo granular de acciones (modulo.accion)
 *   - role_permiso: pivote roles-permisos
 *   - user_role: pivote usuarios-roles (un usuario puede tener varios)
 *   - acl_bitacora: audita asignaciones y revocaciones
 *
 * La columna `users.rol` se CONSERVA como respaldo y caché del rol
 * principal. El middleware CheckRole sigue funcionando hasta que se
 * migre el resto a CheckPermiso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('modulo', 50);
            $table->string('accion', 50);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
            $table->index('modulo');
        });

        Schema::create('role_permiso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permiso_id']);
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('acl_bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_afectado_id')->constrained('users');
            $table->string('accion', 30);
            $table->foreignId('role_id')->nullable()->constrained('roles');
            $table->foreignId('permiso_id')->nullable()->constrained('permisos');
            $table->foreignId('ejecutado_por')->nullable()->constrained('users');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('usuario_afectado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_bitacora');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permiso');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
