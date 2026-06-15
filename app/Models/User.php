<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

use App\Models\Permiso;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Roles del sistema. Usar estas constantes en vez de strings sueltos
     * evita typos silenciosos (un 'enalce' mal escrito no falla en compilación
     * pero rompe la verificación de permisos sin avisar).
     */
    public const ROL_ADMIN    = 'admin';
    public const ROL_ENLACE   = 'enlace';
    public const ROL_JURIDICO = 'juridico';
    public const ROL_REVISORA = 'revisora';
    public const ROL_SUJETO   = 'sujeto';

    /** Todos los roles válidos del sistema. */
    public const ROLES_TODOS = [
        self::ROL_ADMIN,
        self::ROL_ENLACE,
        self::ROL_JURIDICO,
        self::ROL_REVISORA,
        self::ROL_SUJETO,
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
     * Misma lógica que editar.
     */
    public function puedeEliminarTramite(Tramite $tramite): bool
    {
        return $this->puedeEditarTramite($tramite);
    }

    /**
     * ¿Este usuario puede editar una regulación?
     * - admin: siempre
     * - juridico: solo si es de su dependencia
     * - otros: nunca
     */
    public function puedeEditarRegulacion($regulacion): bool
    {
        if ($this->isRol(self::ROL_ADMIN)) return true;
        if ($this->isRol(self::ROL_JURIDICO)) {
            return ($regulacion->dependencia_id ?? null) === $this->dependencia_id;
        }
        return false;
    }

    /**
     * ¿Este usuario puede subir regulaciones?
     * Solo juridico y admin.
     */
    public function puedeSubirRegulacion(): bool
    {
        return $this->isAnyRol([self::ROL_JURIDICO, self::ROL_ADMIN]);
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
     * ¿Este usuario puede ver una propuesta regulatoria?
     *
     * Las propuestas son el único recurso con lectura restringida por
     * dependencia. Trámites, agenda SyD y regulaciones son catálogo
     * abierto (cualquiera los consulta).
     *
     * Regla (C2 — evita que una dependencia ajena vea la propuesta
     * copiando el ID en el URL):
     *   - admin ve todo.
     *   - Un rol transversal (revisora, jurídico) ve todo: si tiene el
     *     permiso de observar o aprobar el módulo, su trabajo abarca
     *     todas las dependencias.
     *   - El enlace solo ve propuestas de su propia dependencia.
     *
     * @param  mixed   $registro  La propuesta a consultar (debe tener dependencia_id).
     * @param  string  $modulo    Nombre del módulo, normalmente 'agenda_regulatoria'.
     * @return bool  true si puede ver el registro; false en caso contrario.
     */
    public function puedeVerRegistro($registro, string $modulo): bool
    {
        if ($this->isRol(self::ROL_ADMIN)) {
            return true;
        }

        // Rol transversal: quien observa o aprueba el módulo lo ve completo.
        if ($this->tienePermiso("{$modulo}.observar") || $this->tienePermiso("{$modulo}.aprobar")) {
            return true;
        }

        // El resto solo ve lo de su propia dependencia.
        return $this->esDeSuDependencia($registro);
    }
}
