<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

use App\Models\Permiso;
use App\Models\Regulacion;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Roles del sistema. Usar estas constantes en vez de strings sueltos
     * evita typos silenciosos (un 'enalce' mal escrito no falla en compilación
     * pero rompe la verificación de permisos sin avisar).
     */
    public const ROL_ADMIN         = 'admin';
    public const ROL_ENLACE        = 'enlace';
    public const ROL_JURIDICO      = 'juridico';
    public const ROL_REVISORA      = 'revisora';
    public const ROL_SUJETO        = 'sujeto';
    public const ROL_DIGITALIZADOR = 'digitalizador';

    /** Todos los roles válidos del sistema. */
    public const ROLES_TODOS = [
        self::ROL_ADMIN,
        self::ROL_ENLACE,
        self::ROL_JURIDICO,
        self::ROL_REVISORA,
        self::ROL_SUJETO,
        self::ROL_DIGITALIZADOR,
    ];

    /** Tiempo de cache para los permisos de un usuario (segundos). */
    private const CACHE_PERMISOS_TTL = 300;

    protected $fillable = [
        'name', 'email', 'password', 'cargo', 'rol',
        'dependencia_id', 'unidad_id', 'activo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'activo' => 'boolean'];
    }

    // ========== Relaciones ==========

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function unidad()
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_id');
    }

    public function tramites()
    {
        return $this->hasMany(Tramite::class, 'created_by');
    }

    /**
     * Roles asignados al usuario vía la tabla pivote `user_role`.
     * Un usuario puede tener varios roles.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot('asignado_por')
            ->withTimestamps();
    }

    public function permisosDirectos()
    {
        return $this->belongsToMany(Permiso::class, 'user_permiso')
            ->withPivot('asignado_por')
            ->withTimestamps();
    }

    /**
     * Regulaciones que este usuario marcó como favoritas (tabla pivot
     * regulacion_favorita). Permite listar sus favoritas y consultar el estado.
     */
    public function regulacionesFavoritas()
    {
        return $this->belongsToMany(Regulacion::class, 'regulacion_favorita')
            ->withTimestamps();
    }

    /**
     * ¿Este usuario tiene marcada como favorita la regulación dada?
     * Acepta un modelo Regulacion o su id.
     */
    public function tieneFavorita($regulacion): bool
    {
        $id = $regulacion instanceof Regulacion ? $regulacion->id : (int) $regulacion;

        return $this->regulacionesFavoritas()
            ->where('regulaciones.id', $id)
            ->exists();
    }

    // ========== Helpers de rol (compatibilidad con código existente) ==========

    public function isRol(string $rol): bool
    {
        return $this->rol === $rol || $this->tieneRol($rol);
    }

    public function isAnyRol(array $roles): bool
    {
        return in_array($this->rol, $roles) || $this->tieneAlgunRol($roles);
    }

    // ========== Helpers ACL nuevos ==========

    /**
     * Verifica si el usuario tiene un rol asignado por código (admin, enlace, etc.).
     * Consulta primero la relación; si no hay roles asignados, cae al campo `users.rol`
     * como respaldo (para usuarios pre-migración ACL).
     */
    public function tieneRol(string $codigo): bool
    {
        // Primero: verificar campo legacy users.rol (siempre funciona)
        if ($this->rol === $codigo) {
            return true;
        }

        // Segundo: verificar roles ACL (tabla user_role → roles)
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('codigo', $codigo);
        }

        return $this->roles()->where('codigo', $codigo)->exists();
    }

    public function tieneAlgunRol(array $codigos): bool
    {
        foreach ($codigos as $codigo) {
            if ($this->tieneRol($codigo)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Devuelve el código de rol efectivo del usuario.
     *
     * Resuelve primero la columna legacy `users.rol`; si está vacía, toma el
     * primer rol de la relación ACL. Sirve para código que necesita un único
     * código de rol (por ejemplo, un match), de modo que funcione tanto con
     * usuarios pre-ACL (columna) como con usuarios cuyo rol vive en la pivote.
     */
    public function rolEfectivo(): ?string
    {
        if (!empty($this->rol)) {
            return $this->rol;
        }

        $rol = $this->relationLoaded('roles')
            ? $this->roles->first()
            : $this->roles()->first();

        return $rol->codigo ?? null;
    }

    /**
     * Verifica si el usuario tiene un permiso específico vía ACL.
     * Cachea el listado de permisos del usuario por 5 minutos.
     */
    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->permisosActuales(), true);
    }

    public function tieneAlgunPermiso(array $codigos): bool
    {
        $permisos = $this->permisosActuales();
        foreach ($codigos as $codigo) {
            if (in_array($codigo, $permisos, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Devuelve el array de códigos de permisos vigentes del usuario.
     * Se obtienen vía sus roles ACL. Si no tiene roles asignados,
     * devuelve un array vacío (forzando a configurar ACL).
     */
    public function permisosActuales(): array
    {
        return Cache::remember(
            $this->cacheKeyPermisos(),
            self::CACHE_PERMISOS_TTL,
            fn () => $this->roles()
                ->with('permisos:id,codigo')
                ->get()
                ->flatMap(fn ($rol) => $rol->permisos->pluck('codigo'))
                ->unique()
                ->values()
                ->all()
        );
    }

    /**
     * Invalida el cache de permisos. Debe llamarse cuando se asigna
     * o se revoca un rol/permiso al usuario.
     */
    public function olvidarPermisosCache(): void
    {
        Cache::forget($this->cacheKeyPermisos());
    }

    private function cacheKeyPermisos(): string
    {
        return "user.{$this->id}.permisos";
    }

    // ========== Helpers de autorización por dependencia ==========

    /**
     * ¿Este usuario puede editar un trámite?
     * - admin: siempre
     * - enlace: solo si es de su dependencia Y lo creó él
     * - otros: nunca
     */
    public function puedeEditarTramite(Tramite $tramite): bool
    {
        if ($this->isRol(self::ROL_ADMIN)) return true;
        if ($this->isRol(self::ROL_ENLACE)) {
            return $tramite->dependencia_id === $this->dependencia_id
                && $tramite->created_by === $this->id;
        }
        return false;
    }

    /**
     * ¿Este usuario puede eliminar un trámite?
     *
     * - admin: siempre, sin importar el estatus.
     * - enlace: solo trámites de su dependencia, creados por él, y ÚNICAMENTE
     *   mientras están en borrador. Una vez enviado a revisión (o más adelante
     *   en el flujo) ya no puede borrarlo: ese registro pertenece al proceso.
     * - otros roles: nunca.
     */
    public function puedeEliminarTramite(Tramite $tramite): bool
    {
        if ($this->isRol(self::ROL_ADMIN)) {
            return true;
        }

        if ($this->isRol(self::ROL_ENLACE)) {
            return $tramite->dependencia_id === $this->dependencia_id
                && $tramite->created_by === $this->id
                && $tramite->estatus === Tramite::ESTATUS_BORRADOR;
        }

        return false;
    }

    /**
     * ¿Puede eliminar esta acción de agenda? Misma regla que en trámites:
     * el admin siempre; el enlace solo si es de su dependencia, la creó él
     * y sigue en borrador. Una vez enviada a revisión ya no se puede borrar.
     */
    public function puedeEliminarAgenda(AccionAgenda $agenda): bool
    {
        if ($this->isRol(self::ROL_ADMIN)) {
            return true;
        }

        if ($this->isRol(self::ROL_ENLACE)) {
            return $agenda->dependencia_id === $this->dependencia_id
                && $agenda->created_by === $this->id
                && $agenda->estatus === AccionAgenda::ESTATUS_BORRADOR;
        }

        return false;
    }

    /**
     * ¿Puede eliminar esta propuesta regulatoria? Misma regla que en trámites:
     * el admin siempre; el enlace solo si es de su dependencia, la creó él y
     * sigue en borrador. Una vez en consulta/dictamen ya no se puede borrar.
     */
    public function puedeEliminarPropuesta(PropuestaRegulatoria $propuesta): bool
    {
        if ($this->isRol(self::ROL_ADMIN)) {
            return true;
        }

        if ($this->isRol(self::ROL_ENLACE)) {
            return $propuesta->dependencia_id === $this->dependencia_id
                && $propuesta->created_by === $this->id
                && $propuesta->estatus === PropuestaRegulatoria::ESTATUS_BORRADOR;
        }

        return false;
    }

    /**
     * ¿Este usuario puede editar una regulación?
     * - admin: siempre
     * - juridico: solo si es de su dependencia
     * - otros: nunca
     */
    public function puedeEditarRegulacion($regulacion): bool
    {
        if (!$this->tienePermiso('regulaciones.editar')) return false;

        // Admin puede editar cualquiera
        if ($this->isRol(self::ROL_ADMIN)) return true;

        // Los demás roles con permiso solo pueden editar las de su dependencia
        return ($regulacion->dependencia_id ?? null) === $this->dependencia_id;
    }

    /**
     * ¿Este usuario puede subir regulaciones?
     * Solo juridico y admin.
     */
    public function puedeSubirRegulacion(): bool
    {
        return $this->tienePermiso('regulaciones.crear');
    }

    /**
     * ¿El registro pertenece a la dependencia de este usuario?
     */
    public function esDeSuDependencia($registro): bool
    {
        $depId = $registro->dependencia_id ?? null;
        return $depId && $depId === $this->dependencia_id;
    }

    /**
     * ¿Este usuario "ve todo" un módulo, sin importar la dependencia?
     *
     * Son transversales el admin y quien APRUEBA el módulo (la revisora). El
     * permiso de solo observar (p. ej. el jurídico) NO da visión total: ese rol
     * trabaja sobre su propia área, como dice su descripción de rol.
     *
     * @param  string  $modulo  'tramites', 'agenda', 'agenda_regulatoria'.
     */
    public function veTodoElModulo(string $modulo): bool
    {
        return $this->isRol(self::ROL_ADMIN)
            || $this->tienePermiso("{$modulo}.aprobar");
    }

    /**
     * ¿El usuario consulta registros de varias dependencias a la vez?
     *
     * Solo admin y revisora ven trámites, regulaciones y agendas de todas
     * las dependencias. Los demás roles (enlace, sujeto, jurídico) solo ven
     * los de su propia dependencia, así que en sus listados todas las filas
     * tendrían la misma dependencia. Se usa para decidir si la columna
     * "Dependencia" aporta información o es ruido redundante para ese usuario.
     */
    public function veVariasDependencias(): bool
    {
        return $this->isAnyRol([self::ROL_ADMIN, self::ROL_REVISORA]);
    }

    /**
     * ¿Este usuario puede ver un registro de un módulo?
     *
     * Regla (C2 — evita que una dependencia ajena vea el registro copiando el
     * ID en el URL):
     *   - admin y revisora (quien aprueba) ven todo.
     *   - el resto (jurídico, enlace, sujeto) solo ve lo de su propia dependencia.
     *
     * @param  mixed   $registro  Registro a consultar (debe tener dependencia_id).
     * @param  string  $modulo    Nombre del módulo, p. ej. 'agenda_regulatoria'.
     */
    public function puedeVerRegistro($registro, string $modulo): bool
    {
        // Un borrador es trabajo en proceso, privado de quien lo creó (o del
        // admin). No lo ve nadie más, ni siquiera quien ve todo el módulo. Cubre
        // también el acceso por URL directa, no solo el filtrado de los listados.
        $esBorrador = ($registro->estatus ?? null) === 'borrador';
        if ($esBorrador && !$this->isRol(self::ROL_ADMIN)) {
            return ($registro->created_by ?? null) === $this->id;
        }

        // Un registro ya publicado (no borrador) se puede VER en modo lectura
        // desde cualquier dependencia. Así, un trámite o regulación que aparece
        // como relacionado se abre desde el detalle sin dar 403. La restricción
        // por dependencia sigue aplicando para EDITAR o gestionar (métodos
        // aparte), no para ver.
        return true;
    }

    /**
     * ¿Este usuario puede gestionar (editar, borrar, cambiar estatus) un
     * registro de un módulo? Mismo criterio que ver: admin y revisora sobre
     * todo; el resto solo sobre lo de su propia dependencia.
     *
     * @param  mixed   $registro  Registro a gestionar (debe tener dependencia_id).
     * @param  string  $modulo    Nombre del módulo, p. ej. 'agenda'.
     */
    public function puedeGestionar($registro, string $modulo): bool
    {
        return $this->veTodoElModulo($modulo)
            || $this->esDeSuDependencia($registro);
    }
}
